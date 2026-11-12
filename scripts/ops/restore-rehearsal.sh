#!/usr/bin/env bash
# Foresight Radar — database restore rehearsal into the ISOLATED recovery stack (docker-compose.recovery.yml).
#
#   bash scripts/ops/restore-rehearsal.sh <backup.sql.gz> [expected-sha256]
#
# It never touches production or the local dev database: it refuses to run unless the target
# container `recovery_mysql` belongs to the Compose project `foresight-recovery`.
# It prints timings, counts and table names only; no records.

set -euo pipefail
export MSYS_NO_PATHCONV=1   # Git Bash on Windows: keep container paths as they are

BACKUP="${1:?usage: restore-rehearsal.sh <backup.sql.gz> [expected-sha256]}"
EXPECTED_SHA="${2:-}"
DB=foresight_radar
DB_USER=recovery
DB_PASS=recovery-local-only        # docker/recovery/recovery.env (local throw-away value)
ROOT_PASS=recovery-root-local-only  # docker-compose.recovery.yml (local throw-away value)
COMPOSE=(docker compose -p foresight-recovery -f docker-compose.recovery.yml)
now() { date +%s.%N; }
secs() { awk -v a="$1" -v b="$2" 'BEGIN { printf "%.1f", b - a }'; }
q() { docker exec -e MYSQL_PWD="$DB_PASS" recovery_mysql mysql -u"$DB_USER" -N -B "$DB" -e "$1"; }

echo "=== 0. Target is the isolated recovery stack ==="
PROJECT="$(docker inspect -f '{{ index .Config.Labels "com.docker.compose.project" }}' recovery_mysql 2>/dev/null || true)"
if [ "$PROJECT" != "foresight-recovery" ]; then
  echo "Refusing: recovery_mysql is not running in the foresight-recovery project (found: '${PROJECT:-none}')."
  echo "Start it with: ${COMPOSE[*]} up -d"
  exit 1
fi
NETWORKS="$(docker inspect -f '{{ range $k, $v := .NetworkSettings.Networks }}{{ $k }} {{ end }}' recovery_mysql)"
echo "recovery_mysql project=$PROJECT networks=$NETWORKS"
case "$NETWORKS" in *laravel*) echo "Refusing: recovery_mysql is attached to the application network."; exit 1;; esac

echo "=== 1. Backup integrity ==="
SHA="$(sha256sum "$BACKUP" | cut -d' ' -f1)"
echo "file: $(basename "$BACKUP")  bytes: $(wc -c < "$BACKUP")  sha256: $SHA"
if [ -n "$EXPECTED_SHA" ] && [ "$SHA" != "$EXPECTED_SHA" ]; then echo "Checksum differs from the expected one: stop."; exit 1; fi
gzip -t "$BACKUP" && echo "gzip -t: ok"
LAST="$(gunzip -c "$BACKUP" | tail -n 1)"
echo "last line: $LAST"
case "$LAST" in *"Dump completed"*) ;; *) echo "Not a completed dump: stop."; exit 1;; esac
DUMP_TABLES="$(gunzip -c "$BACKUP" | grep -c '^CREATE TABLE')"
echo "tables in dump: $DUMP_TABLES"

echo "=== 2. Wait for the recovery database ==="
# Over TCP with the password: on first start the image runs a temporary socket-only server
# without a root password, which answers a plain ping before the real server is up.
for _ in $(seq 1 90); do
  docker exec -e MYSQL_PWD="$ROOT_PASS" recovery_mysql mysql --protocol=TCP -h 127.0.0.1 -uroot -e 'SELECT 1' >/dev/null 2>&1 && break
  sleep 2
done
docker exec -e MYSQL_PWD="$ROOT_PASS" recovery_mysql mysql -uroot -N -B -e "SELECT CONCAT('server ', VERSION())"

echo "=== 3. Empty recovery database (recovery stack only) ==="
docker exec -e MYSQL_PWD="$ROOT_PASS" recovery_mysql mysql -uroot -e \
  "DROP DATABASE IF EXISTS $DB; CREATE DATABASE $DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON $DB.* TO '$DB_USER'@'%';"

echo "=== 4. Restore (same command shape as the production runbook: application user, MYSQL_PWD) ==="
START="$(now)"
gunzip -c "$BACKUP" | docker exec -i -e MYSQL_PWD="$DB_PASS" recovery_mysql mysql -u"$DB_USER" "$DB"
END="$(now)"
echo "restore exit: 0  duration: $(secs "$START" "$END") s"

echo "=== 5. Schema and data checks ==="
TABLES="$(q "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'")"
echo "tables restored: $TABLES (dump: $DUMP_TABLES) $([ "$TABLES" = "$DUMP_TABLES" ] && echo MATCH || echo MISMATCH)"
echo "engines: $(q "SELECT GROUP_CONCAT(CONCAT(engine, '=', n)) FROM (SELECT engine, COUNT(*) n FROM information_schema.tables WHERE table_schema = DATABASE() GROUP BY engine) e")"
echo "migrations ran: $(q 'SELECT COUNT(*) FROM migrations')  last: $(q 'SELECT migration FROM migrations ORDER BY id DESC LIMIT 1')"
echo "row counts:"
for t in tenants users scenario_planning_projects project_driving_forces force_assessments critical_uncertainties scenarios scenario_quadrants scenario_conditions scenario_narrative_revisions scenario_implications strategic_responses early_warning_indicators indicator_observations indicator_threshold_events project_decisions project_participants signals sources scenario_citations driving_forces tenant_invitations model_has_roles jobs failed_jobs; do
  printf '  %-32s %s\n' "$t" "$(q "SELECT COUNT(*) FROM \`$t\`")"
done
echo "foreign keys checked for orphans:"
ORPHANS=0; FKS=0
while IFS=$'\t' read -r tbl col rtbl rcol; do
  [ -z "$tbl" ] && continue
  FKS=$((FKS + 1))
  n="$(q "SELECT COUNT(*) FROM \`$tbl\` c LEFT JOIN \`$rtbl\` p ON c.\`$col\` = p.\`$rcol\` WHERE c.\`$col\` IS NOT NULL AND p.\`$rcol\` IS NULL")"
  if [ "$n" != "0" ]; then echo "  ORPHANS $tbl.$col -> $rtbl.$rcol: $n"; ORPHANS=$((ORPHANS + n)); fi
done < <(q "SELECT table_name, column_name, referenced_table_name, referenced_column_name FROM information_schema.key_column_usage WHERE table_schema = DATABASE() AND referenced_table_name IS NOT NULL")
echo "  foreign keys: $FKS  orphan rows: $ORPHANS"
echo "tenant ownership (rows whose tenant does not exist):"
for t in $(q "SELECT table_name FROM information_schema.columns WHERE table_schema = DATABASE() AND column_name = 'tenant_id' ORDER BY table_name"); do
  n="$(q "SELECT COUNT(*) FROM \`$t\` x LEFT JOIN tenants tn ON tn.id = x.tenant_id WHERE x.tenant_id IS NOT NULL AND tn.id IS NULL")"
  [ "$n" != "0" ] && echo "  $t: $n"
done
echo "  checked"

echo "=== 6. The application reads the restored database ==="
docker exec -w /var/www recovery_app php artisan migrate:status --pending 2>&1 | tail -n 2
docker exec -w /var/www recovery_app php artisan about --only=environment 2>&1 | grep -E 'Environment|Debug|URL' | head -n 4
docker exec -w /var/www -e HOME=/tmp -e XDG_CONFIG_HOME=/tmp recovery_app php artisan tinker --execute '
echo "db=".DB::connection()->getDatabaseName()." host=".config("database.connections.mysql.host").PHP_EOL;
echo "projects via Eloquent (all tenants)=".App\Models\ScenarioPlanningProject::withoutGlobalScopes()->count().PHP_EOL;
echo "mail=".config("mail.default")." queue=".config("queue.default")." log=".config("logging.default").PHP_EOL;
' 2>&1 | grep -v '^$'

echo
echo "Restore rehearsal finished. Measured restore duration: $(secs "$START" "$END") s."
