#!/bin/sh
# Starts the commerce service: `web` (nginx + php-fpm, migrates first) or `worker` (outbox delivery). Anything else runs as a command.
set -eu
cd /var/www/sylius

need() {
  eval "v=\${$1:-}"
  [ -n "$v" ] || { echo "ulams-sylius: $1 is not set" >&2; exit 64; }
}

prepare() {
  need APP_SECRET
  need DATABASE_URL
  need JWT_PASSPHRASE
  # keys live on a volume (config/jwt, config/encryption); generated once, never baked into the image
  [ -f config/jwt/private.pem ] || php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction
  [ -f config/encryption/payment.key ] || php bin/console sylius:payment:generate-key --no-interaction
  php bin/console cache:warmup --no-interaction
}

case "${1:-web}" in
  web)
    prepare
    php bin/console doctrine:database:create --if-not-exists --no-interaction
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
    # the one admin API user the LMS signs in with (credentials from the environment only)
    if [ -n "${ULAMS_ADMIN_API_EMAIL:-}" ] && [ -n "${ULAMS_ADMIN_API_PASSWORD:-}" ]; then
      php bin/console ulams:admin-api-user:ensure --no-interaction
    fi
    php-fpm --daemonize
    exec nginx -g 'daemon off;'
    ;;
  worker)
    prepare
    exec php bin/console ulams:outbox:deliver --watch --sleep="${ULAMS_OUTBOX_POLL_SECONDS:-3}"
    ;;
  *)
    exec "$@"
    ;;
esac
