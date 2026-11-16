#!/usr/bin/env bash
# Start script for the Moodle Railway template:
# 1. waits for Postgres, 2. installs Moodle on an empty database (admin password from
# MOODLE_ADMIN_PASSWORD) or runs the upgrade after a version bump, 3. starts cron and Apache.
set -euo pipefail

export PORT="${PORT:-8080}"
APP=/var/www/html
DATA="${MOODLE_DATAROOT:-/var/www/moodledata}"
PHP="php -d memory_limit=1024M"
as_www() { runuser -u www-data -- "$@"; }
log() { echo "[moodle-railway] $*"; }

: "${MOODLE_URL:?MOODLE_URL must be set, for example https://YOUR-DOMAIN}"
if [ -z "${MOODLE_DATABASE_URL:-}" ] && [ -z "${DATABASE_URL:-}" ]; then
  echo "MOODLE_DATABASE_URL must be set" >&2; exit 1
fi

# The data volume is mounted as root: hand it to www-data.
mkdir -p "$DATA" /var/cache/moodle
chown www-data:www-data "$DATA" /var/cache/moodle
find "$DATA" -mindepth 1 -maxdepth 1 ! -name lost+found ! -user www-data -exec chown -R www-data:www-data {} + 2>/dev/null || true

# While installing or upgrading, a placeholder answers on PORT (health check passes, the
# web installer stays unreachable).
php -S "0.0.0.0:${PORT}" /opt/moodle-railway/installing.php >/dev/null 2>&1 &
PLACEHOLDER=$!
trap 'kill $PLACEHOLDER 2>/dev/null || true' EXIT

for i in $(seq 1 60); do
  if STATE=$($PHP /opt/moodle-railway/dbstate.php state 2>/dev/null); then break; fi
  log "waiting for the database ($i)"; sleep 5
done
[ -n "${STATE:-}" ] || { echo "database unreachable" >&2; exit 1; }

if [ "$STATE" = "partial" ]; then
  # A first install was stopped before it finished (for example by a redeploy). It holds no
  # user data yet, so drop its tables and install again.
  log "found an unfinished first install: starting it again"
  $PHP /opt/moodle-railway/dbstate.php reset
  STATE=empty
fi

if [ "$STATE" = "empty" ]; then
  : "${MOODLE_ADMIN_PASSWORD:?MOODLE_ADMIN_PASSWORD must be set for the first install}"
  log "empty database: installing Moodle (a few minutes)"
  ARGS=(--agree-license --lang="${MOODLE_LANG:-en}"
        --adminuser="${MOODLE_ADMIN_USER:-admin}" --adminpass="$MOODLE_ADMIN_PASSWORD"
        --fullname="${MOODLE_SITE_NAME:-Moodle}" --shortname="${MOODLE_SITE_SHORTNAME:-Moodle}")
  [ -n "${MOODLE_ADMIN_EMAIL:-}" ] && ARGS+=(--adminemail="$MOODLE_ADMIN_EMAIL")
  as_www $PHP "$APP/admin/cli/install_database.php" "${ARGS[@]}"
  $PHP /opt/moodle-railway/dbstate.php mark
  log "install finished"
else
  log "existing site: running upgrade check"
  # Only one Moodle container runs at a time (the data volume attaches to one), so an upgrade
  # lock found now was left by a container that stopped mid-upgrade.
  $PHP /opt/moodle-railway/dbstate.php unlock
  $PHP /opt/moodle-railway/dbstate.php mark
  if ! as_www $PHP "$APP/admin/cli/upgrade.php" --non-interactive; then
    echo "upgrade failed: see the log above" >&2; exit 1
  fi
  # Leave maintenance mode in case an earlier upgrade was interrupted.
  as_www $PHP "$APP/admin/cli/maintenance.php" --disable >/dev/null || true
fi

as_www $PHP "$APP/admin/cli/purge_caches.php" || true

kill "$PLACEHOLDER" 2>/dev/null || true
wait "$PLACEHOLDER" 2>/dev/null || true
trap - EXIT

# Moodle cron, once a minute, as www-data in the background.
(
  while true; do
    as_www php "$APP/admin/cli/cron.php" --keep-alive=59 >/dev/null || true
    sleep 1
  done
) &

log "starting Apache on port $PORT"
exec moodle-docker-php-entrypoint apache2-foreground
