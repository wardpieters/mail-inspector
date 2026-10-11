#!/bin/sh
set -eu
if [ -z "${APP_KEY:-}" ] || [ -z "${DB_PASSWORD:-}" ] || [ -z "${REDIS_PASSWORD:-}" ]; then
  echo "Application and database secrets are required. Run php scripts/setup-env.php before starting Compose." >&2
  exit 1
fi
if [ "${APP_ENV:-production}" = production ]; then php artisan config:cache --no-interaction >/dev/null; fi
exec "$@"
