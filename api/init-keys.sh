#!/bin/bash
# Creates the application and Passport keys that are missing, and nothing else.
#
#   ./init-keys.sh [artisan options, e.g. --domain=<host>]
#
# APP_KEY encrypts the tenant secrets in the platform database (ADR 0007): regenerating an
# existing one makes them unreadable. It is generated only when no key is configured (APP_KEY in
# the environment or in the env file). Missing Passport keys are recreated on their own; losing
# oauth-private.key never touches APP_KEY.
#
# Environment: ENV_FILE (default .env) and PASSPORT_PRIVATE_KEY_FILE (default
# storage/oauth-private.key) let the multi-domain script point at a domain's own files.

env_file="${ENV_FILE:-.env}"
private_key="${PASSPORT_PRIVATE_KEY_FILE:-storage/oauth-private.key}"

# the process APP_KEY belongs to the platform; a domain is judged by its own env file only
configured_key=""
[ -z "${ENV_FILE:-}" ] && configured_key="${APP_KEY:-}"
if [ -z "$configured_key" ] && [ -f "$env_file" ]; then
    configured_key=$(grep -E '^APP_KEY=' "$env_file" | tail -1 | cut -d= -f2- | tr -d "\"' \r")
fi

if [ -n "$configured_key" ]; then
    echo "APP_KEY is set, keeping it."
else
    echo "APP_KEY is empty. Generating it."
    php artisan key:generate --force --no-interaction "$@" || exit 1
fi

if [ -f "$private_key" ]; then
    echo "$private_key exists."
else
    echo "$private_key does not exist. Generating passport keys and the personal access client (APP_KEY is not touched)"
    php artisan passport:keys --force --no-interaction "$@" || exit 1
    php artisan passport:client --personal --no-interaction "$@" || exit 1
fi
