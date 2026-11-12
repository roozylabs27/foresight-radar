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
docker exec laravel_app php artisan tinker --execute '
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
echo "ERROR lines today (count only): $(docker exec laravel_app sh -c "cat storage/logs/laravel-$(date -u +%Y-%m-%d).log storage/logs/laravel.log 2>/dev/null | grep -c '\.ERROR:'")"

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
else
  echo "no backup file found"
fi
echo "off-server copy configured? cron entries mentioning backups:"
crontab -l 2>/dev/null | grep -i -E 'backup|rclone|s3|rsync' | awk '{print "  " $0}' || true
command -v rclone >/dev/null && echo "  rclone installed" || echo "  rclone not installed"
command -v aws >/dev/null && echo "  aws cli installed" || echo "  aws cli not installed"

echo
echo "Done. Nothing was changed."
