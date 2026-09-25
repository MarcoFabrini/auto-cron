#!/bin/sh
set -e

chown -R www-data:www-data /var/storage/uploads /var/backups /app/config/jwt

# Dietro un reverse proxy locale (nginx/Caddy/Traefik sullo stesso network Docker,
# il caso comune self-host) l'IP del peer TCP è il proxy stesso: fidarsene per
# leggere X-Forwarded-For è sicuro. Se il deploy è dietro un proxy esterno con IP
# noti (es. Cloudflare), override esplicito in .env — vedi SECURITY.md.
if [ -z "${TRUSTED_PROXIES:-}" ]; then
    TRUSTED_PROXIES="REMOTE_ADDR"; export TRUSTED_PROXIES
fi

if [ -n "${DOMAIN:-}" ] && [ -z "${CORS_ALLOW_ORIGIN:-}" ]; then
    CORS_ALLOW_ORIGIN="^https://$(printf '%s' "$DOMAIN" | sed 's/\./\\./g')\$"; export CORS_ALLOW_ORIGIN
fi
if [ -n "${DOMAIN:-}" ] && [ -z "${FRONTEND_URL:-}" ]; then
    FRONTEND_URL="https://${DOMAIN}"; export FRONTEND_URL
fi
if [ -n "${DOMAIN:-}" ] && [ -z "${DEFAULT_URI:-}" ]; then
    DEFAULT_URI="https://${DOMAIN}"; export DEFAULT_URI
fi

su-exec www-data php bin/console lexik:jwt:generate-keypair --skip-if-exists 2>/dev/null || true

if [ "${AUTO_MIGRATE:-false}" = "true" ]; then
    su-exec www-data php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
fi

su-exec www-data php bin/console cache:warmup --no-debug 2>/dev/null || true

exec su-exec www-data "$@"
