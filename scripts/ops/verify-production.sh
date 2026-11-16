#!/usr/bin/env bash
# Foresight Radar — read-only production verification (production operations validation, 2026-11-12).
#
# Run on the VPS from the project directory:   bash scripts/ops/verify-production.sh
# or:                                           bash verify-production.sh /path/to/project
#
# It changes nothing: no writes to the database, files, containers or queues.
# It prints no secrets (passwords, keys, tokens, .env values) and no business records: only versions,
# statuses, sizes, counts, table names and yes/no answers. Review the output before sharing it.

set -u
PROJECT_DIR="${1:-$PWD}"
BACKUP_DIR="${BACKUP_DIR:-$HOME/foresight-backups}"
cd "$PROJECT_DIR" || { echo "Project directory not found: $PROJECT_DIR"; exit 1; }
[ -f .env ] || { echo "No .env in $PROJECT_DIR: run this from the project directory."; exit 1; }

section() { printf '\n=== %s ===\n' "$1"; }
env_value() { grep -E "^$1=" .env | tail -n 1 | cut -d= -f2- | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'$/\1/"; }
is_set() { [ -n "$(env_value "$1")" ] && echo "set" || echo "empty"; }
DB_NAME="$(env_value DB_DATABASE)"
DB_USER="$(env_value DB_USERNAME)"
DB_PASS="$(env_value DB_PASSWORD)"
mysql_q() { docker exec -e MYSQL_PWD="$DB_PASS" laravel_mysql mysql --protocol=TCP -h 127.0.0.1 -u"$DB_USER" -N -B "$DB_NAME" -e "$1" 2>&1 | grep -v "Using a password"; }

section "1. Time and host resources"
date -u '+%Y-%m-%d %H:%M:%S UTC'
uptime
df -h / "$PROJECT_DIR" "$BACKUP_DIR" 2>/dev/null | awk 'NR==1 || !seen[$0]++'
free -m

section "2. Release on disk"
echo "branch: $(git rev-parse --abbrev-ref HEAD)"
echo "HEAD:   $(git rev-parse --short HEAD) $(git log -1 --format='%ci %s')"
echo "origin/main (last fetch): $(git rev-parse --short origin/main 2>/dev/null)"
echo "uncommitted files: $(git status --porcelain | wc -l)"
git status --porcelain | awk '{print "  " $0}' | head -n 20
echo "last moves of HEAD:"
git reflog -n 5 --date=iso --format='  %h %gd %gs' 2>/dev/null
echo "release log (~/foresight-releases.log, written by post-deploy-check.sh; last 5):"
tail -n 5 "$HOME/foresight-releases.log" 2>/dev/null | awk '{print "  " $0}' || echo "  none yet"

section "3. Containers"
docker ps -a --filter name=laravel_ --format '{{.Names}}\t{{.Image}}\t{{.Status}}'
for c in laravel_app laravel_queue laravel_scheduler laravel_nginx laravel_mysql; do
  docker inspect -f '{{.Name}} restarts={{.RestartCount}} started={{.State.StartedAt}} health={{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$c" 2>/dev/null
done
docker stats --no-stream --format '{{.Name}}\tCPU {{.CPUPerc}}\tMEM {{.MemUsage}}' $(docker ps --filter name=laravel_ -q) 2>/dev/null

section "4. Mounted config: host file vs what the container sees"
echo "nginx default.conf  host: $(sha256sum docker/nginx/default.conf | cut -c1-16)  container: $(docker exec laravel_nginx sha256sum /etc/nginx/conf.d/default.conf 2>/dev/null | cut -c1-16)"
echo "  Permissions-Policy lines  host: $(grep -c Permissions-Policy docker/nginx/default.conf)  container: $(docker exec laravel_nginx grep -c Permissions-Policy /etc/nginx/conf.d/default.conf 2>/dev/null)"
echo "  nginx -t: $(docker exec laravel_nginx nginx -t 2>&1 | tail -n 1)"
echo "php local.ini       host: $(sha256sum docker/php/local.ini | cut -c1-16)  app container: $(docker exec laravel_app sha256sum /usr/local/etc/php/conf.d/local.ini 2>/dev/null | cut -c1-16)"
echo "  expose_php in PHP-FPM container: $(docker exec laravel_app php -r 'echo ini_get("expose_php") ? "On" : "Off";' 2>/dev/null)"
echo "  opcache.validate_timestamps: $(docker exec laravel_app php -r 'echo ini_get("opcache.validate_timestamps");' 2>/dev/null)"

section "5. Laravel configuration (values that are not secret)"
docker exec laravel_app php artisan about --only=environment 2>/dev/null | sed -n '1,20p'
# HOME=/tmp: psysh (tinker) wants a writable config directory, /var/www/.config is not.
docker exec -e HOME=/tmp -e XDG_CONFIG_HOME=/tmp laravel_app php artisan tinker --execute '
$c = fn ($k) => json_encode(config($k));
echo "app.env=".$c("app.env")." app.debug=".$c("app.debug")." app.url=".$c("app.url").PHP_EOL;
echo "logging.default=".$c("logging.default")." stack.channels=".$c("logging.channels.stack.channels")." daily.days=".$c("logging.channels.daily.days").PHP_EOL;
echo "queue.default=".$c("queue.default")." cache.default=".$c("cache.default")." session.driver=".$c("session.driver").PHP_EOL;
echo "session.secure=".$c("session.secure")." session.http_only=".$c("session.http_only")." session.same_site=".$c("session.same_site")." session.lifetime=".$c("session.lifetime").PHP_EOL;
echo "mail.default=".$c("mail.default")." mail.from.set=".json_encode(filled(config("mail.from.address")))." ops.alert_email.set=".json_encode(filled(config("ops.alert_email"))).PHP_EOL;
' 2>&1 | grep -v "^$"
echo ".env keys (set/empty only): APP_DEBUG=$(env_value APP_DEBUG) LOG_CHANNEL=$(env_value LOG_CHANNEL) LOG_STACK=$(is_set LOG_STACK) OPS_ALERT_EMAIL=$(is_set OPS_ALERT_EMAIL) MAIL_MAILER=$(env_value MAIL_MAILER) MAIL_HOST=$(is_set MAIL_HOST)"

section "6. Database"
echo "connect as app user: $(mysql_q 'SELECT 1' | head -n 1)"
echo "server version: $(mysql_q 'SELECT VERSION()')"
echo "grants of the app user (user name masked):"
mysql_q 'SHOW GRANTS FOR CURRENT_USER()' | sed -E "s/'[^']+'@'([^']+)'/'<app-user>'@'\1'/g" | awk '{print "  " $0}'
echo "tables: $(mysql_q "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'")"
echo "engines:"
mysql_q "SELECT engine, COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' GROUP BY engine" | awk '{print "  " $0}'
echo "size MB: $(mysql_q "SELECT ROUND(SUM(data_length + index_length) / 1048576, 1) FROM information_schema.tables WHERE table_schema = DATABASE()")"
echo "migrations: ran=$(mysql_q 'SELECT COUNT(*) FROM migrations') last=$(mysql_q 'SELECT migration FROM migrations ORDER BY id DESC LIMIT 1')"
echo "pending migrations:"
docker exec laravel_app php artisan migrate:status --pending 2>&1 | tail -n 5 | awk '{print "  " $0}'
echo "row counts (counts only):"
for t in tenants users scenario_planning_projects project_driving_forces force_assessments critical_uncertainties scenarios scenario_quadrants scenario_conditions scenario_narrative_revisions scenario_implications strategic_responses early_warning_indicators indicator_observations indicator_threshold_events project_decisions project_participants signals sources scenario_citations driving_forces tenant_invitations model_has_roles jobs failed_jobs; do
  printf '  %-32s %s\n' "$t" "$(mysql_q "SELECT COUNT(*) FROM \`$t\`" 2>/dev/null | head -n 1)"
done

section "7. Queue, scheduler, failed jobs"
echo "jobs waiting: $(mysql_q 'SELECT COUNT(*) FROM jobs')  oldest available_at: $(mysql_q 'SELECT FROM_UNIXTIME(MIN(available_at)) FROM jobs')"
echo "failed_jobs total: $(mysql_q 'SELECT COUNT(*) FROM failed_jobs')  last 24h: $(mysql_q 'SELECT COUNT(*) FROM failed_jobs WHERE failed_at >= NOW() - INTERVAL 1 DAY')"
echo "queue worker command: $(docker inspect -f '{{join .Config.Cmd " "}}' laravel_queue 2>/dev/null)"
echo "scheduler command:    $(docker inspect -f '{{join .Config.Cmd " "}}' laravel_scheduler 2>/dev/null)"
echo "schedule:"
docker exec laravel_app php artisan schedule:list 2>&1 | grep -E 'artisan' | awk '{print "  " $0}'
echo "scheduler runs of ops:failed-jobs in the last 6 h: $(docker logs --since 6h laravel_scheduler 2>&1 | grep -c 'ops:failed-jobs')"
docker logs --since 6h laravel_scheduler 2>&1 | grep 'ops:failed-jobs' | tail -n 3 | cut -c1-160 | awk '{print "  " $0}'
echo "queue worker log, last 6 h: DONE=$(docker logs --since 6h laravel_queue 2>&1 | grep -c ' DONE') FAIL=$(docker logs --since 6h laravel_queue 2>&1 | grep -c ' FAIL')"

section "8. Logs"
docker exec laravel_app sh -c 'ls -la --time-style=+%Y-%m-%d storage/logs | tail -n 20' 2>/dev/null | awk '{print "  " $5, $6, $7}'
echo "ERROR lines per daily log file (newest 3, counts only):"
docker exec laravel_app sh -c 'for f in $(ls -1t storage/logs/laravel-*.log 2>/dev/null | head -n 3); do echo "  $(basename $f): $(grep -c "\.ERROR:" $f)"; done' 2>/dev/null
echo "ERROR messages in the newest daily file (message text only, first 80 chars, grouped):"
docker exec laravel_app sh -c 'f=$(ls -1t storage/logs/laravel-*.log 2>/dev/null | head -n 1); [ -n "$f" ] && grep -oE "\.ERROR: [^{]{0,80}" "$f" | sed -E "s/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+/<email>/g" | sort | uniq -c | sort -rn | head -n 10' 2>/dev/null | awk '{print "  " $0}'

section "9. Backups in $BACKUP_DIR"
ls -la --time-style='+%Y-%m-%d %H:%M' "$BACKUP_DIR" 2>&1 | awk '{print "  " $1, $5, $6, $7, $8}'
LATEST="$(ls -1t "$BACKUP_DIR"/db-*.sql.gz 2>/dev/null | head -n 1)"
if [ -n "$LATEST" ]; then
  echo "latest: $(basename "$LATEST")"
  echo "  size bytes: $(stat -c %s "$LATEST")  mode: $(stat -c %a "$LATEST")  modified: $(stat -c %y "$LATEST" | cut -c1-19)"
  echo "  sha256: $(sha256sum "$LATEST" | cut -d' ' -f1)"
  echo "  gzip -t: $(gzip -t "$LATEST" 2>&1 && echo ok)"
  echo "  last line: $(gunzip -c "$LATEST" | tail -n 1 | cut -c1-60)"
  echo "  options in header: $(gunzip -c "$LATEST" | head -n 30 | grep -E '^-- (Host|Server version|MySQL dump)' | tr '\n' ' ' | cut -c1-160)"
  echo "  CREATE TABLE: $(gunzip -c "$LATEST" | grep -c '^CREATE TABLE')  INSERT statements: $(gunzip -c "$LATEST" | grep -c '^INSERT INTO')"
  echo "  tables in dump:"
  gunzip -c "$LATEST" | grep '^CREATE TABLE' | sed -E 's/CREATE TABLE `([^`]+)`.*/\1/' | tr '\n' ' ' | fold -w 150 | awk '{print "    " $0}'
  echo "  engines in dump: $(gunzip -c "$LATEST" | grep -oE 'ENGINE=[A-Za-z]+' | sort | uniq -c | tr '\n' ' ')"
  echo "  checksum sidecar: $([ -f "$LATEST.sha256" ] && ( [ "$(sha256sum "$LATEST" | cut -d' ' -f1)" = "$(cut -d' ' -f1 "$LATEST.sha256")" ] && echo matches || echo DIFFERS ) || echo 'none (dump older than the sidecar)')"
  echo "  backup --check (age, gzip, completion, checksum): $(bash scripts/ops/backup-database.sh --check 2>&1 | tail -n 1)"
else
  echo "no backup file found"
fi
echo "off-server copy configured? cron entries mentioning backups:"
crontab -l 2>/dev/null | grep -i -E 'backup|rclone|s3|rsync' | awk '{print "  " $0}' || true
command -v rclone >/dev/null && echo "  rclone installed" || echo "  rclone not installed"
command -v aws >/dev/null && echo "  aws cli installed" || echo "  aws cli not installed"


section "10. Verdicts (PASS / WARN / FAIL / SKIP)"
# Production readiness 2026-11-13: each check ends in exactly one verdict. SKIP is never counted as PASS.
# The script exits 1 when any check FAILs. Nothing below prints a secret: passwords are only compared.
N_PASS=0; N_WARN=0; N_FAIL=0; N_SKIP=0
verdict() { # verdict PASS|WARN|FAIL|SKIP "check" "detail"
  case "$1" in PASS) N_PASS=$((N_PASS+1));; WARN) N_WARN=$((N_WARN+1));; FAIL) N_FAIL=$((N_FAIL+1));; *) N_SKIP=$((N_SKIP+1));; esac
  printf '  [%-4s] %-44s %s\n' "$1" "$2" "${3:-}"
}
cfg() { docker exec -e HOME=/tmp -e XDG_CONFIG_HOME=/tmp laravel_app php artisan tinker --execute "echo json_encode(config('$1'));" 2>/dev/null | tail -n 1; }

# Release
# A git command that fails (wrong directory, "dubious ownership") is a SKIP, never a PASS.
if GIT_STATUS="$(git status --porcelain 2>/dev/null)" && GIT_HEAD="$(git rev-parse HEAD 2>/dev/null)"; then
  [ -z "$GIT_STATUS" ] && verdict PASS "working copy clean" || verdict WARN "working copy clean" "$(echo "$GIT_STATUS" | wc -l) changed file(s)"
  GIT_MAIN="$(git rev-parse origin/main 2>/dev/null || true)"
  if [ -n "$GIT_MAIN" ] && [ "$GIT_HEAD" = "$GIT_MAIN" ]; then verdict PASS "HEAD = origin/main (last fetch)" "${GIT_HEAD:0:7}"; else verdict WARN "HEAD = origin/main (last fetch)" "HEAD ${GIT_HEAD:0:7}, origin/main ${GIT_MAIN:0:7}"; fi
else
  verdict SKIP "working copy clean" "git failed in $PROJECT_DIR (run as the deploy user)"
  verdict SKIP "HEAD = origin/main (last fetch)" "git failed"
fi
if [ -f "$HOME/foresight-releases.log" ]; then
  LAST_CHECK="$(tail -n 1 "$HOME/foresight-releases.log")"
  case "$LAST_CHECK" in *check=ok*) verdict PASS "last post-deploy check" "$LAST_CHECK";; *) verdict FAIL "last post-deploy check" "$LAST_CHECK";; esac
else
  verdict SKIP "last post-deploy check" "no ~/foresight-releases.log yet"
fi

# Containers and runtime config
for c in laravel_app laravel_nginx laravel_mysql laravel_queue laravel_scheduler; do
  [ "$(docker inspect -f '{{.State.Running}}' "$c" 2>/dev/null)" = "true" ] && verdict PASS "container $c running" || verdict FAIL "container $c running"
done
[ "$(sha256sum docker/nginx/default.conf | cut -c1-64)" = "$(docker exec laravel_nginx sha256sum /etc/nginx/conf.d/default.conf 2>/dev/null | cut -c1-64)" ] && verdict PASS "nginx loads the repository config" || verdict FAIL "nginx loads the repository config"
docker exec laravel_nginx nginx -t >/dev/null 2>&1 && verdict PASS "nginx -t" || verdict FAIL "nginx -t"
[ "$(sha256sum docker/php/local.ini | cut -c1-64)" = "$(docker exec laravel_app sha256sum /usr/local/etc/php/conf.d/local.ini 2>/dev/null | cut -c1-64)" ] && verdict PASS "PHP loads the repository local.ini" || verdict FAIL "PHP loads the repository local.ini"

# Application configuration (values compared, not printed when secret)
[ "$(cfg app.debug)" = "false" ] && verdict PASS "APP_DEBUG off" || verdict FAIL "APP_DEBUG off" "app.debug=$(cfg app.debug)"
[ "$(cfg app.env)" = '"production"' ] && verdict PASS "APP_ENV production" || verdict WARN "APP_ENV production" "app.env=$(cfg app.env)"
[ "$(cfg session.secure)" = "true" ] && verdict PASS "secure session cookie" || verdict FAIL "secure session cookie" "session.secure=$(cfg session.secure)"
ENV_MODE="$(stat -c %a .env)"
case "$ENV_MODE" in *[1-7]) verdict WARN ".env not readable by others" "mode $ENV_MODE (suggest 640 or 600)";; *) verdict PASS ".env not readable by others" "mode $ENV_MODE";; esac
HEALTH="$(curl -sk -o /dev/null -w '%{http_code}' --max-time 15 --resolve "$(env_value APP_URL | sed -E 's#^https?://##; s#/.*$##'):443:127.0.0.1" "https://$(env_value APP_URL | sed -E 's#^https?://##; s#/.*$##')/up" 2>/dev/null)"
[ "$HEALTH" = "200" ] && verdict PASS "GET /up through nginx" "200" || verdict FAIL "GET /up through nginx" "$HEALTH"

# Database security (compose defaults are root/root and secret; see docker-compose.yml)
[ "$(mysql_q 'SELECT 1' | head -n 1)" = "1" ] && verdict PASS "app connects with its .env account" || verdict FAIL "app connects with its .env account"
[ "$DB_USER" != "root" ] && verdict PASS "app does not use MySQL root" || verdict FAIL "app does not use MySQL root"
[ "$DB_PASS" != "secret" ] && [ -n "$DB_PASS" ] && verdict PASS "app password is not the compose default" || verdict FAIL "app password is not the compose default"
if docker exec -e MYSQL_PWD=root laravel_mysql mysql --protocol=TCP -h 127.0.0.1 -uroot -N -B -e 'SELECT 1' >/dev/null 2>&1    || docker exec -e MYSQL_PWD=root laravel_mysql mysql -uroot -N -B -e 'SELECT 1' >/dev/null 2>&1; then
  verdict FAIL "root does not accept the compose default" "rotate the root password (runbook)"
else
  verdict PASS "root does not accept the compose default"
fi
GLOBAL_GRANTS="$(mysql_q 'SHOW GRANTS FOR CURRENT_USER()' | grep -c ' ON \*\.\* ' | tr -d ' ')"
GLOBAL_WIDE="$(mysql_q 'SHOW GRANTS FOR CURRENT_USER()' | grep ' ON \*\.\* ' | grep -c -E 'ALL PRIVILEGES|SUPER|GRANT OPTION|FILE|PROCESS|SHUTDOWN|CREATE USER' | tr -d ' ')"
[ "${GLOBAL_WIDE:-0}" = "0" ] && verdict PASS "app user has no global admin privileges" "global grant lines: ${GLOBAL_GRANTS:-0} (USAGE is normal)" || verdict FAIL "app user has no global admin privileges" "$GLOBAL_WIDE wide global grant(s)"
MYSQL_BIND="$(docker port laravel_mysql 3306 2>/dev/null | tr '\n' ' ')"
case "$MYSQL_BIND" in "") verdict PASS "MySQL not published on the host";; *0.0.0.0*|*'[::]'*) verdict FAIL "MySQL bound to localhost only" "$MYSQL_BIND";; *) verdict PASS "MySQL bound to localhost only" "$MYSQL_BIND";; esac

# Migrations, queue, scheduler
if PENDING_OUT="$(docker exec laravel_app php artisan migrate:status 2>/dev/null)"; then
  echo "$PENDING_OUT" | grep -q "Pending" && verdict FAIL "no pending migrations" "$(echo "$PENDING_OUT" | grep -c Pending) pending" || verdict PASS "no pending migrations"
else
  verdict FAIL "no pending migrations" "migrate:status failed"
fi
OLDEST_WAIT="$(mysql_q 'SELECT COALESCE(TIMESTAMPDIFF(MINUTE, FROM_UNIXTIME(MIN(available_at)), NOW()), 0) FROM jobs' | head -n 1)"
[ "${OLDEST_WAIT:-0}" -le 15 ] 2>/dev/null && verdict PASS "queue keeps up" "oldest waiting job ${OLDEST_WAIT:-0} min" || verdict WARN "queue keeps up" "oldest waiting job ${OLDEST_WAIT} min"
FAILED_24H="$(mysql_q 'SELECT COUNT(*) FROM failed_jobs WHERE failed_at >= NOW() - INTERVAL 1 DAY' | head -n 1)"
[ "${FAILED_24H:-0}" = "0" ] && verdict PASS "no failed jobs in 24 h" || verdict WARN "no failed jobs in 24 h" "$FAILED_24H failed (php artisan queue:failed)"
SCHED_RUNS="$(docker logs --since 2h laravel_scheduler 2>&1 | grep -c 'ops:failed-jobs')"
[ "${SCHED_RUNS:-0}" -ge 1 ] && verdict PASS "scheduler ran ops:failed-jobs (2 h)" "$SCHED_RUNS run(s)" || verdict FAIL "scheduler ran ops:failed-jobs (2 h)" "0 runs"

# Alerting
ALERT_SET="$(cfg ops.alert_email | grep -c '@' | tr -d ' ')"
[ "$ALERT_SET" = "1" ] && verdict PASS "OPS_ALERT_EMAIL set" || verdict FAIL "OPS_ALERT_EMAIL set"
MAILER="$(cfg mail.default)"
case "$MAILER" in '"log"'|'"array"'|'null'|'') verdict FAIL "mailer can deliver" "mail.default=$MAILER";; *) verdict PASS "mailer can deliver" "mail.default=$MAILER";; esac
verdict SKIP "alert actually delivered" "run: php artisan ops:alert --test, then confirm the inbox"

# Backups
BACKUP_CHECK="$(bash scripts/ops/backup-database.sh --check 2>&1 | tail -n 1)"
case "$BACKUP_CHECK" in *" OK "*) verdict PASS "backup fresh and intact" "$BACKUP_CHECK";; *) verdict FAIL "backup fresh and intact" "$BACKUP_CHECK";; esac
crontab -l 2>/dev/null | grep -q 'backup-database.sh scheduled' && verdict PASS "daily backup cron installed" || verdict FAIL "daily backup cron installed"
crontab -l 2>/dev/null | grep -q 'backup-database.sh --check' && verdict PASS "backup freshness cron installed" || verdict WARN "backup freshness cron installed"
SETTINGS_FILE="${BACKUP_SETTINGS_FILE:-$HOME/.config/foresight-backup.env}"
grep -qE '^OFFSITE_REMOTE=.+' "$SETTINGS_FILE" 2>/dev/null && verdict PASS "off-server copy configured" || verdict WARN "off-server copy configured" "OFFSITE_REMOTE empty: backups share the VPS failure domain"
[ "$(stat -c %a "$BACKUP_DIR" 2>/dev/null)" = "700" ] && verdict PASS "backup directory owner-only" || verdict FAIL "backup directory owner-only" "mode $(stat -c %a "$BACKUP_DIR" 2>/dev/null)"

# Host resources
DISK_USED="$(df -P / | awk 'NR==2 {gsub("%",""); print $5}')"
[ "${DISK_USED:-100}" -lt 85 ] && verdict PASS "root disk below 85%" "${DISK_USED}% used" || verdict WARN "root disk below 85%" "${DISK_USED}% used"
MEM_AVAIL="$(free -m | awk '/^Mem:/ {print $7}')"
[ "${MEM_AVAIL:-0}" -ge 256 ] && verdict PASS "available memory >= 256 MB" "${MEM_AVAIL} MB" || verdict WARN "available memory >= 256 MB" "${MEM_AVAIL} MB"

echo
echo "RESULT: $N_PASS PASS, $N_WARN WARN, $N_FAIL FAIL, $N_SKIP SKIP"

echo
echo "Done. Nothing was changed (backup --check only creates its lock file in $BACKUP_DIR)."
[ "$N_FAIL" -eq 0 ]
