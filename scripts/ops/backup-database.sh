#!/usr/bin/env bash
# Foresight Radar — database backup (OPS-02, OPS-03, OPS-04, OPS-05; production operations validation 2026-11-13).
#
# One script for the deploy (before migrations) and for the daily cron job on the VPS:
#   bash scripts/ops/backup-database.sh <label>      # label: previous release sha (deploy) or "scheduled"
#   bash scripts/ops/backup-database.sh --check      # freshness check only: exit 1 if no valid dump is recent
#
# Run from the project directory (it reads DB_DATABASE, DB_USERNAME, DB_PASSWORD from .env and prints none).
# Settings (environment variables, all optional):
#   BACKUP_DIR            where dumps go                       (default ~/foresight-backups)
#   RETENTION_DAYS        delete valid dumps older than this   (default 14)
#   KEEP_MIN              but always keep at least this many    (default 3)
#   MAX_AGE_HOURS         --check fails above this age         (default 26)
#   OFFSITE_REMOTE        rclone destination, e.g. "foresight-offsite:backups" (empty = no off-server copy)
#   BACKUP_PING_URL       URL called after a successful backup (dead man's switch, e.g. healthchecks.io); empty = none
#
# Safety: dumps are owner-only (dir 700, files 600). A dump is written as .partial and renamed only after
# mysqldump finished ("-- Dump completed" last line), so a failed dump never counts as a backup and never
# causes a valid one to be deleted. Retention runs only after a successful dump.

set -euo pipefail
umask 077

# Optional owner-only settings file on the server, shared by the deploy and the cron job
# (e.g. OFFSITE_REMOTE, BACKUP_PING_URL). Variables already set in the environment win.
SETTINGS_FILE="${BACKUP_SETTINGS_FILE:-$HOME/.config/foresight-backup.env}"
if [ -f "$SETTINGS_FILE" ]; then
  while IFS='=' read -r key value; do
    case "$key" in
      BACKUP_DIR|RETENTION_DAYS|KEEP_MIN|MAX_AGE_HOURS|OFFSITE_REMOTE|BACKUP_PING_URL)
        if [ -z "${!key:-}" ]; then
          value="${value%\"}"; value="${value#\"}"
          export "$key=$value"
        fi
        ;;
    esac
  done < <(grep -E '^[A-Z_]+=' "$SETTINGS_FILE")
fi

BACKUP_DIR="${BACKUP_DIR:-$HOME/foresight-backups}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
KEEP_MIN="${KEEP_MIN:-3}"
MAX_AGE_HOURS="${MAX_AGE_HOURS:-26}"
OFFSITE_REMOTE="${OFFSITE_REMOTE:-}"
BACKUP_PING_URL="${BACKUP_PING_URL:-}"
MYSQL_CONTAINER="${MYSQL_CONTAINER:-laravel_mysql}"
LABEL="${1:?usage: backup-database.sh <label>|--check}"

log() { printf '%s %s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "$*"; }
fail() { log "BACKUP FAILED: $*"; command -v logger >/dev/null && logger -t foresight-backup "FAILED: $*"; exit 1; }
valid_dumps() { ls -1t "$BACKUP_DIR"/db-*.sql.gz 2>/dev/null || true; }

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"

# --- freshness check -------------------------------------------------------------------------
if [ "$LABEL" = "--check" ]; then
  NEWEST="$(valid_dumps | head -n 1)"
  [ -n "$NEWEST" ] || fail "no backup in $BACKUP_DIR"
  AGE_H=$(( ( $(date +%s) - $(stat -c %Y "$NEWEST") ) / 3600 ))
  if [ "$AGE_H" -gt "$MAX_AGE_HOURS" ]; then fail "newest backup $(basename "$NEWEST") is ${AGE_H} h old (max ${MAX_AGE_HOURS} h)"; fi
  gunzip -c "$NEWEST" | tail -n 1 | grep -q "Dump completed" || fail "newest backup $(basename "$NEWEST") is incomplete"
  log "OK newest backup $(basename "$NEWEST") is ${AGE_H} h old"
  exit 0
fi

case "$LABEL" in *[!A-Za-z0-9._-]*|"") fail "label may only contain letters, digits, dot, dash and underscore";; esac
[ -f .env ] || fail "no .env in $(pwd): run from the project directory"

# --- credentials from .env (never printed) ----------------------------------------------------
env_value() { grep -E "^$1=" .env | tail -n 1 | cut -d= -f2- | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'$/\1/"; }
DB_NAME="$(env_value DB_DATABASE)"
DB_USER="$(env_value DB_USERNAME)"
DB_PASS="$(env_value DB_PASSWORD)"
[ -n "$DB_NAME" ] && [ -n "$DB_USER" ] || fail "DB_DATABASE or DB_USERNAME missing in .env"

# --- dump --------------------------------------------------------------------------------------
chmod 600 "$BACKUP_DIR"/db-*.sql.gz 2>/dev/null || true
rm -f "$BACKUP_DIR"/*.partial
FILE="$BACKUP_DIR/db-$(date -u +%Y%m%d-%H%M%S)-$LABEL.sql.gz"
START=$(date +%s)
# MYSQL_PWD keeps the password off the command line; TCP matches a 'user'@'%' account.
if ! docker exec -e MYSQL_PWD="$DB_PASS" "$MYSQL_CONTAINER" \
     mysqldump --protocol=TCP -h 127.0.0.1 -u"$DB_USER" --single-transaction --no-tablespaces --triggers "$DB_NAME" \
     | gzip > "$FILE.partial"; then
  fail "mysqldump or gzip exited with an error (partial file left as $(basename "$FILE").partial)"
fi
gunzip -c "$FILE.partial" | tail -n 1 | grep -q "Dump completed" || fail "dump did not complete"
mv "$FILE.partial" "$FILE"
chmod 600 "$FILE"
SHA="$(sha256sum "$FILE" | cut -d' ' -f1)"
log "Database backup: $FILE ($(stat -c %s "$FILE") bytes, sha256 ${SHA:0:16}, $(( $(date +%s) - START )) s)"

# --- off-server copy (OPS-03) ------------------------------------------------------------------
if [ -n "$OFFSITE_REMOTE" ]; then
  command -v rclone >/dev/null || fail "OFFSITE_REMOTE is set but rclone is not installed"
  rclone copyto "$FILE" "$OFFSITE_REMOTE/$(basename "$FILE")" || fail "off-server copy to $OFFSITE_REMOTE failed"
  # Verify the remote copy byte for byte (--download also works for encrypted `crypt` remotes).
  rclone check --one-way --download "$BACKUP_DIR" "$OFFSITE_REMOTE" --include "$(basename "$FILE")" >/dev/null 2>&1 \
    || fail "off-server copy of $(basename "$FILE") does not match"
  log "Off-server copy verified: $OFFSITE_REMOTE/$(basename "$FILE")"
else
  log "Off-server copy: not configured (OFFSITE_REMOTE empty)"
fi

# --- retention: only after a successful dump; never below KEEP_MIN valid dumps ------------------
KEPT=0
while IFS= read -r f; do
  KEPT=$((KEPT + 1))
  if [ "$KEPT" -gt "$KEEP_MIN" ] && [ -n "$(find "$f" -mtime +"$RETENTION_DAYS" 2>/dev/null)" ]; then
    rm -f "$f"
    log "Retention: removed $(basename "$f") (older than $RETENTION_DAYS days)"
  fi
done < <(valid_dumps)

# --- dead man's switch (OPS-11) ------------------------------------------------------------------
if [ -n "$BACKUP_PING_URL" ]; then
  curl -fsS -m 10 --retry 3 "$BACKUP_PING_URL" >/dev/null || log "warning: ping to BACKUP_PING_URL failed"
fi
