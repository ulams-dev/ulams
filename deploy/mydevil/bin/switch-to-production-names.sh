#!/bin/sh
# Switches the platform and the tenants of an install from the -staging host names to the production ones, in place:
# the platform .env, the host columns of the tenants table, the tenants' env files and registrations (sync-env), and the
# H5P and PDF Node sites. The staging hosts keep working until the Cloudflare redirects replace them: the old env files
# and registrations stay, and the old host is still a platform host.
#
#   sh switch-to-production-names.sh [zone]      (run add-production-hosts.sh first)
#
# A copy of the .env is kept as .env.before-production-names.
DIR="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=lib.sh
. "$DIR/lib.sh"
set -eu

ZONE="${1:-ulams.app}"
cd "$ULAMS_API"
[ -f .env.before-production-names ] || cp -p .env .env.before-production-names

set_env() { # <KEY> <value>: replace the line or append it
  if grep -q "^$1=" .env; then
    awk -v k="$1" -v v="$2" 'BEGIN { FS = OFS = "=" } $1 == k { print k "=" v; next } { print }' .env > .env.new
  else
    { cat .env; printf '%s=%s\n' "$1" "$2"; } > .env.new
  fi
  cat .env.new > .env && rm -f .env.new
}

set_env APP_URL "https://api.$ZONE"
set_env FRONTEND_URL "https://$ZONE"
set_env ADMIN_URL "https://admin.$ZONE"
set_env TENANCY_PLATFORM_HOSTS "api.$ZONE,staging-api.$ZONE"
set_env TENANCY_API_HOST "{slug}-api.$ZONE"
set_env TENANCY_FRONT_HOST "{slug}.$ZONE"
set_env TENANCY_ADMIN_HOST "{slug}-admin.$ZONE"
set_env TENANCY_CONTENT_HOST "{slug}-content.$ZONE"
# R2 buckets cannot be renamed; the storage Worker maps the short names, so no URL carries "staging"
set_env AWS_URL "https://storage.$ZONE/ulams"
set_env TENANCY_STORAGE_PUBLIC_URL "https://storage.$ZONE"
set_env TENANCY_BUCKET_PUBLIC_URL "https://storage.$ZONE/{slug}"
set_env H5P_SERVICE_URL "https://h5p.$ZONE"
set_env PDF_SERVICE_URL "https://pdf.$ZONE"
say "platform .env switched (backup: .env.before-production-names)"

"$ULAMS_PHP" artisan optimize:clear --no-interaction >/dev/null 2>&1 || true
"$ULAMS_PHP" artisan tinker --no-interaction --execute='
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Support\TenantNaming;
Tenant::query()->orderBy("slug")->get()->each(function (Tenant $t) {
    $t->update([
        "api_host" => TenantNaming::pattern("api_host", $t->slug),
        "front_host" => TenantNaming::pattern("front_host", $t->slug),
        "admin_host" => TenantNaming::pattern("admin_host", $t->slug),
    ]);
    echo $t->slug . " " . $t->api_host . " " . $t->front_host . " " . $t->admin_host . PHP_EOL;
});
'
"$ULAMS_PHP" artisan ulams:tenant:sync-env --no-interaction
post_deploy

# the H5P and PDF sites read their settings once at start
devil www restart "h5p.$ZONE" || true
devil www restart "pdf.$ZONE" || true
say "done: tenants re-registered under the production names"
