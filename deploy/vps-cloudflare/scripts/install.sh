#!/usr/bin/env bash
# Installs (or re-runs the install of) ulams on one Ubuntu 24.04 / Debian 12 server behind Cloudflare.
# Idempotent: every step checks before it changes anything, secrets that are already set in .env are
# never regenerated (APP_KEY and the Passport keys protect the tenants' data), and a re-run pulls the
# pinned images, restarts what changed and runs `ulams:upgrade`.
#
#   sudo scripts/install.sh --domain ulams.app --admin-email you@example.com
#   scripts/install.sh --local          # trial on a laptop: plain HTTP on 127.0.0.1, MinIO instead of R2
#
# Options:
#   --domain D           the Cloudflare domain (default: ULAMS_DOMAIN of .env, or ulams.app)
#   --style flat|nested  tenant host layout (default flat; see ADR 0091)
#   --mode tunnel|origin_ca
#   --admin-email E      e-mail of the first platform administrator
#   --version TAG        image tag (a sha-<short> or release tag), or `latest` to resolve the newest
#                        main build to its sha tag and pin that
#   --dir DIR            install directory (default /opt/ulams; with --local: this directory)
#   --ssh-port N         SSH port the firewall keeps open (default 22)
#   --no-swap            do not create a 2 GB swap file
#   --no-start           prepare everything, start nothing
#   --local              no OS changes, no Cloudflare; project "ulams-local" on http://*.ulams.localhost:8480
set -euo pipefail

src="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

domain="" style="" mode="" admin_email="" version="" dir="" ssh_port=22 swap=1 start=1 local_mode=0
while (($#)); do
  case "$1" in
    --domain) domain="${2:?}"; shift ;;
    --style) style="${2:?}"; shift ;;
    --mode) mode="${2:?}"; shift ;;
    --admin-email) admin_email="${2:?}"; shift ;;
    --version) version="${2:?}"; shift ;;
    --dir) dir="${2:?}"; shift ;;
    --ssh-port) ssh_port="${2:?}"; shift ;;
    --no-swap) swap=0 ;;
    --no-start) start=0 ;;
    --local) local_mode=1 ;;
    -h | --help) sed -n '2,24p' "${BASH_SOURCE[0]}"; exit 0 ;;
    *) printf 'unknown argument %s (see --help)\n' "$1" >&2; exit 2 ;;
  esac
  shift
done

if ((local_mode)); then
  export ULAMS_LOCAL=1 ULAMS_PROJECT="${ULAMS_PROJECT:-ulams-local}"
  dir="${dir:-$src}"
else
  dir="${dir:-/opt/ulams}"
fi
export ULAMS_DEPLOY_DIR="$dir" ULAMS_ENV_FILE="$dir/.env"
# shellcheck source=lib.sh
. "$src/scripts/lib.sh"

as_root() { if [[ $EUID -eq 0 ]]; then "$@"; else sudo "$@"; fi; }

# --- 1. the operating system ------------------------------------------------------------------
os_setup() {
  [[ -r /etc/os-release ]] || ulams_die "cannot read /etc/os-release"
  # shellcheck disable=SC1091
  . /etc/os-release
  case "${ID:-}:${VERSION_ID:-}" in
    ubuntu:24.04 | debian:12) ;;
    *) ulams_warn "tested on Ubuntu 24.04 and Debian 12, this is ${PRETTY_NAME:-unknown}" ;;
  esac
  [[ $EUID -eq 0 ]] || command -v sudo >/dev/null || ulams_die "run as root or install sudo"

  ulams_log "packages: ca-certificates curl jq openssl ufw fail2ban unattended-upgrades"
  as_root apt-get update -qq
  as_root env DEBIAN_FRONTEND=noninteractive apt-get install -y -qq ca-certificates curl gnupg jq openssl ufw fail2ban unattended-upgrades >/dev/null

  if ! command -v docker >/dev/null || ! docker compose version >/dev/null 2>&1; then
    ulams_log "Docker Engine and the Compose plugin (docker.com apt repository)"
    as_root install -m 0755 -d /etc/apt/keyrings
    as_root curl -fsSL "https://download.docker.com/linux/$ID/gpg" -o /etc/apt/keyrings/docker.asc
    as_root chmod a+r /etc/apt/keyrings/docker.asc
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/$ID ${VERSION_CODENAME} stable" \
      | as_root tee /etc/apt/sources.list.d/docker.list >/dev/null
    as_root apt-get update -qq
    as_root env DEBIAN_FRONTEND=noninteractive apt-get install -y -qq docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin >/dev/null
  fi
  as_root systemctl enable --now docker >/dev/null 2>&1 || true

  if ! id ulams >/dev/null 2>&1; then
    ulams_log "system user 'ulams' (member of docker; no password, no login shell)"
    as_root useradd --system --create-home --home-dir /home/ulams --shell /usr/sbin/nologin --groups docker ulams
  fi

  ulams_log "unattended security upgrades"
  printf 'APT::Periodic::Update-Package-Lists "1";\nAPT::Periodic::Unattended-Upgrade "1";\nAPT::Periodic::AutocleanInterval "7";\n' \
    | as_root tee /etc/apt/apt.conf.d/20auto-upgrades >/dev/null

  if ((swap)) && [[ -z "$(swapon --show --noheadings 2>/dev/null)" ]]; then
    ulams_log "2 GB swap file (the host has none)"
    as_root fallocate -l 2G /swapfile && as_root chmod 600 /swapfile && as_root mkswap /swapfile >/dev/null && as_root swapon /swapfile
    grep -q '^/swapfile ' /etc/fstab || echo '/swapfile none swap sw 0 0' | as_root tee -a /etc/fstab >/dev/null
    echo 'vm.swappiness=10' | as_root tee /etc/sysctl.d/90-ulams.conf >/dev/null && as_root sysctl -q -p /etc/sysctl.d/90-ulams.conf || true
  fi

  as_root systemctl enable --now fail2ban >/dev/null 2>&1 || true
}

# the firewall depends on the origin mode, so it runs after .env is known
firewall_setup() {
  local m="$1"
  ulams_log "firewall (ufw): deny incoming, allow SSH on $ssh_port"
  as_root ufw default deny incoming >/dev/null
  as_root ufw default allow outgoing >/dev/null
  as_root ufw limit "$ssh_port/tcp" >/dev/null
  if [[ "$m" == origin_ca ]]; then
    ulams_log "origin_ca: 443 only from Cloudflare's address ranges (ufw and the DOCKER-USER chain)"
    as_root "$dir/scripts/cloudflare-firewall.sh"
  else
    echo "    tunnel mode: no port is open besides SSH; cloudflared only makes outgoing connections"
  fi
  as_root ufw --force enable >/dev/null
}

# --- 2. files and .env ----------------------------------------------------------------------
sync_files() {
  [[ "$src" == "$dir" ]] && return 0
  ulams_log "copying the deployment files to $dir (keeping .env, certs and the tunnel credentials)"
  as_root install -d -m 0750 -o root -g root "$dir"
  tar -C "$src" --exclude=./.env --exclude=./certs --exclude=./cloudflared/credentials.json --exclude=./backups -cf - . | as_root tar -C "$dir" -xf -
}

gen_if_unset() { # KEY COMMAND...
  local key="$1"
  shift
  local cur
  cur="$(ulams_env_get "$ULAMS_ENV_FILE" "$key")"
  if ulams_is_placeholder "$cur"; then
    ulams_env_set "$ULAMS_ENV_FILE" "$key" "$("$@")"
    generated+=("$key")
  fi
}

env_setup() {
  generated=()
  if [[ ! -f "$ULAMS_ENV_FILE" ]]; then
    ulams_log "creating .env from .env.example"
    (umask 077 && cp "$dir/.env.example" "$ULAMS_ENV_FILE")
    first_run=1
  else
    first_run=0
  fi
  chmod 600 "$ULAMS_ENV_FILE"

  if ((local_mode)); then
    domain="${domain:-ulams.localhost}"
    style="${style:-flat}" mode=tunnel
    ulams_env_set "$ULAMS_ENV_FILE" ULAMS_DOMAIN "$domain"
    ulams_env_set "$ULAMS_ENV_FILE" ULAMS_HOST_STYLE "$style"
    ulams_env_set "$ULAMS_ENV_FILE" ULAMS_ORIGIN_MODE tunnel
    ulams_env_set "$ULAMS_ENV_FILE" COMPOSE_PROFILES ""
    ulams_env_set "$ULAMS_ENV_FILE" ULAMS_COOKIE_SECURE false
    local port="${LOCAL_HTTP_PORT:-8480}" s3port="${LOCAL_S3_PORT:-9190}"
    ulams_write_derived "$ULAMS_ENV_FILE" "$domain" "$style" tunnel http ":$port"
    ulams_env_set "$ULAMS_ENV_FILE" S3_ENDPOINT "http://minio:9000"
    ulams_env_set "$ULAMS_ENV_FILE" S3_REGION us-east-1
    ulams_env_set "$ULAMS_ENV_FILE" S3_PLATFORM_PUBLIC_URL "http://localhost:$s3port/$(ulams_env_get "$ULAMS_ENV_FILE" S3_PLATFORM_BUCKET)"
    ulams_env_set "$ULAMS_ENV_FILE" TENANCY_BUCKET_PUBLIC_URL ""
    ulams_env_set "$ULAMS_ENV_FILE" TENANCY_S3_PUBLIC_POLICY true
    ulams_env_set "$ULAMS_ENV_FILE" ULAMS_STORAGE_ORIGINS "http://localhost:$s3port"
    ulams_env_set "$ULAMS_ENV_FILE" CLOUDFLARE_TUNNEL_ID local
    ulams_env_set "$ULAMS_ENV_FILE" CLOUDFLARE_ACCOUNT_ID local
    ulams_env_set "$ULAMS_ENV_FILE" MAIL_HOST ""
    ulams_env_set "$ULAMS_ENV_FILE" ULAMS_LOCAL_MODE 1
    ulams_env_set "$ULAMS_ENV_FILE" ULAMS_LOCAL_PROJECT "$ULAMS_PROJECT"
    ulams_env_set "$ULAMS_ENV_FILE" LOCAL_HTTP_PORT "$port"
    ulams_env_set "$ULAMS_ENV_FILE" LOCAL_S3_PORT "$s3port"
    gen_if_unset S3_KEY ulams_hex 8
    gen_if_unset S3_SECRET ulams_hex 16
    gen_if_unset MAIL_USERNAME echo local
    gen_if_unset MAIL_PASSWORD echo local
    [[ -n "$admin_email" ]] || admin_email="admin@$domain"
  else
    domain="${domain:-$(ulams_env_get "$ULAMS_ENV_FILE" ULAMS_DOMAIN)}"
    style="${style:-$(ulams_env_get "$ULAMS_ENV_FILE" ULAMS_HOST_STYLE)}"
    mode="${mode:-$(ulams_env_get "$ULAMS_ENV_FILE" ULAMS_ORIGIN_MODE)}"
    : "${domain:=ulams.app}" "${style:=flat}" "${mode:=tunnel}"
    ulams_env_set "$ULAMS_ENV_FILE" ULAMS_DOMAIN "$domain"
    ulams_env_set "$ULAMS_ENV_FILE" ULAMS_HOST_STYLE "$style"
    ulams_env_set "$ULAMS_ENV_FILE" ULAMS_ORIGIN_MODE "$mode"
    ulams_env_set "$ULAMS_ENV_FILE" COMPOSE_PROFILES "$([[ $mode == tunnel ]] && echo tunnel || true)"
    ulams_write_derived "$ULAMS_ENV_FILE" "$domain" "$style" "$mode" https ""
    # the host defaults of the R2 block follow the domain, once, while they still hold the example value
    retarget S3_PLATFORM_PUBLIC_URL "https://files.$domain"
    retarget TENANCY_BUCKET_PUBLIC_URL "https://{slug}-files.$domain"
    retarget ULAMS_STORAGE_ORIGINS "https://files.$domain"
    retarget MAIL_FROM_ADDRESS "no-reply@$domain"
  fi

  [[ -n "$admin_email" ]] && ulams_env_set "$ULAMS_ENV_FILE" PLATFORM_ADMIN_EMAIL "$admin_email"

  gen_if_unset APP_KEY bash -c 'echo "base64:$(openssl rand -base64 32)"'
  if ulams_is_placeholder "$(ulams_env_get "$ULAMS_ENV_FILE" JWT_PRIVATE_KEY_BASE64)" || ulams_is_placeholder "$(ulams_env_get "$ULAMS_ENV_FILE" JWT_PUBLIC_KEY_BASE64)"; then
    local keys
    keys="$(mktemp -d)"
    openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:4096 -out "$keys/private.key" 2>/dev/null
    openssl rsa -in "$keys/private.key" -pubout -out "$keys/public.key" 2>/dev/null
    ulams_env_set "$ULAMS_ENV_FILE" JWT_PRIVATE_KEY_BASE64 "$(base64 <"$keys/private.key" | tr -d '\n')"
    ulams_env_set "$ULAMS_ENV_FILE" JWT_PUBLIC_KEY_BASE64 "$(base64 <"$keys/public.key" | tr -d '\n')"
    rm -rf "$keys"
    generated+=(JWT_PRIVATE_KEY_BASE64 JWT_PUBLIC_KEY_BASE64)
  fi
  gen_if_unset POSTGRES_PASSWORD ulams_hex 24
  gen_if_unset VALKEY_PASSWORD ulams_hex 24
  gen_if_unset H5P_INTERNAL_TOKEN ulams_hex 32
  gen_if_unset PDF_INTERNAL_TOKEN ulams_hex 32
  gen_if_unset PLATFORM_ADMIN_PASSWORD ulams_hex 12
  gen_if_unset TENANT_INITIAL_PASSWORD ulams_hex 12
  gen_if_unset BACKUP_PASSPHRASE ulams_hex 24

  if ((first_run)); then admin_password_shown=1; else admin_password_shown=0; fi
  if ((local_mode)); then :; else
    local missing=() key
    for key in S3_ENDPOINT S3_KEY S3_SECRET PLATFORM_ADMIN_EMAIL; do
      ulams_is_placeholder "$(ulams_env_get "$ULAMS_ENV_FILE" "$key")" && missing+=("$key")
    done
    [[ "$(ulams_env_get "$ULAMS_ENV_FILE" PLATFORM_ADMIN_EMAIL)" == admin@example.com ]] && missing+=("PLATFORM_ADMIN_EMAIL (pass --admin-email)")
    ((${#missing[@]} == 0)) || ulams_die "fill in $ULAMS_ENV_FILE first (R2 endpoint and access key: guide, \"R2\"): ${missing[*]}"
    [[ "$(ulams_env_get "$ULAMS_ENV_FILE" MAIL_HOST)" != smtp.example.com ]] || ulams_warn "MAIL_HOST is still the example value: the platform cannot send e-mail (password resets, invitations) until you set the SMTP variables"
  fi
  ((${#generated[@]} == 0)) || echo "    generated (kept in $ULAMS_ENV_FILE, mode 600): ${generated[*]}"
}

retarget() { # KEY NEWVALUE: replaces the value only while it is the shipped ulams.app example
  local cur
  cur="$(ulams_env_get "$ULAMS_ENV_FILE" "$1")"
  [[ "$domain" != ulams.app && "$cur" == *ulams.app* ]] && ulams_env_set "$ULAMS_ENV_FILE" "$1" "$2"
  return 0
}

# --- 3. the image tag ------------------------------------------------------------------------
resolve_version() { ulams_resolve_version "${version:-}"; }

# --- 4. Cloudflare ---------------------------------------------------------------------------
cloudflare_check() {
  ((local_mode)) && return 0
  [[ "$mode" == tunnel ]] || {
    [[ -f "$dir/certs/origin.pem" && -f "$dir/certs/origin.key" && -f "$dir/certs/authenticated_origin_pull_ca.pem" ]] \
      || ulams_die "origin_ca mode needs $dir/certs/{origin.pem,origin.key,authenticated_origin_pull_ca.pem} (guide, \"Origin CA\")"
    return 0
  }
  if [[ ! -f "$dir/cloudflared/credentials.json" ]]; then
    if [[ -n "${CLOUDFLARE_API_TOKEN:-}" ]]; then
      ulams_log "creating the tunnel (CLOUDFLARE_API_TOKEN is set)"
      "$dir/scripts/cloudflare-setup.sh" tunnel
    else
      ulams_die "no tunnel yet. Run scripts/cloudflare-setup.sh (needs CLOUDFLARE_API_TOKEN) or create the tunnel in the dashboard, put its credentials in $dir/cloudflared/credentials.json and its id in CLOUDFLARE_TUNNEL_ID, then run this script again. Everything before this point is done."
    fi
  fi
  ulams_is_placeholder "$(ulams_env_get "$ULAMS_ENV_FILE" CLOUDFLARE_TUNNEL_ID)" && ulams_die "CLOUDFLARE_TUNNEL_ID is not set in $ULAMS_ENV_FILE"
  return 0
}

# --- 5. start --------------------------------------------------------------------------------
start_stack() {
  local rerun=0
  [[ -f "$dir/.installed" ]] && rerun=1
  ulams_log "pulling the images"
  ulams_compose pull -q
  ((start)) || { echo "    --no-start: stopping here"; return 0; }
  ulams_log "starting the stack (first start runs migrations; allow a few minutes)"
  ulams_compose up -d --remove-orphans
  ulams_wait_healthy postgres 60 || ulams_die "postgres did not become healthy: docker compose logs postgres"
  ulams_wait_healthy api 120 || ulams_die "the api did not become healthy: ulams_compose logs api"
  # init.sh has migrated the platform, synced the tenants and seeded permissions and the first admin
  ulams_log "H5P service configuration"
  ulams_artisan ulams:h5p:export-config
  if ((rerun)); then
    ulams_log "ulams:upgrade (re-run)"
    ulams_artisan ulams:upgrade
  fi
  touch "$dir/.installed"
}

# retry CMD...: the api reports healthy before init.sh has finished seeding, so wait for the real answers
retry() {
  local i
  for ((i = 0; i < 60; i++)); do "$@" && return 0; sleep 5; done
  return 1
}

http_code() { # HOST PATH: status code through Caddy, empty if no answer
  ulams_compose exec -T caddy wget -q -S -O /dev/null --header "Host: $1" "http://127.0.0.1:80$2" 2>&1 \
    | sed -n 's/.*HTTP\/[0-9.]* \([0-9]*\).*/\1/p' | tail -1
}

check_http() { [[ "$(http_code "$1" "$2")" =~ ^[23][0-9][0-9]$ ]]; }

admin_exists() {
  local email
  email="$(ulams_env_get "$ULAMS_ENV_FILE" PLATFORM_ADMIN_EMAIL)"
  [[ "$(ulams_compose exec -T postgres psql -U "$(ulams_env_get "$ULAMS_ENV_FILE" POSTGRES_USER)" -d "$(ulams_env_get "$ULAMS_ENV_FILE" POSTGRES_DB)" -Atc "select count(*) from users where email = '$email'" 2>/dev/null | tr -d '[:space:]')" == 1 ]]
}

verify() {
  ((start)) || return 0
  local ok=1 pair
  ulams_log "checks (through Caddy, with the production Host headers; the first start seeds for a minute or two)"
  for pair in "api.$domain:/api/health" "api.$domain:/h5p/health" "$domain:/healthz" "admin.$domain:/"; do
    if retry check_http "${pair%%:*}" "${pair#*:}"; then printf '    ok    %s%s\n' "${pair%%:*}" "${pair#*:}"; else printf '    FAIL  %s%s  (%s)\n' "${pair%%:*}" "${pair#*:}" "$(http_code "${pair%%:*}" "${pair#*:}")"; ok=0; fi
  done
  if retry admin_exists; then
    echo "    ok    platform administrator $(ulams_env_get "$ULAMS_ENV_FILE" PLATFORM_ADMIN_EMAIL) exists"
  else
    echo "    FAIL  platform administrator not found (docker compose logs api | grep -i seed)"; ok=0
  fi
  ((ok)) || ulams_die "some checks failed"
}

timers() {
  ((local_mode)) && return 0
  [[ -d /run/systemd/system ]] || { ulams_warn "no systemd: use cron for scripts/backup.sh (systemd/ulams-backup.cron)"; return 0; }
  ulams_log "systemd timer: daily backup"
  local unit
  for unit in ulams-backup.service ulams-backup.timer; do
    sed "s#@DIR@#$dir#g" "$dir/systemd/$unit" | as_root tee "/etc/systemd/system/$unit" >/dev/null
  done
  as_root systemctl daemon-reload
  as_root systemctl enable --now ulams-backup.timer >/dev/null
  echo "    next runs: $(systemctl list-timers ulams-backup.timer --no-legend 2>/dev/null | awk '{print $1, $2}' || true)"
}

ownership() {
  ((local_mode)) && return 0
  as_root chown -R ulams:ulams "$dir" 2>/dev/null || true
  as_root chmod 600 "$ULAMS_ENV_FILE"
  [[ -f "$dir/cloudflared/credentials.json" ]] && as_root chmod 600 "$dir/cloudflared/credentials.json"
  return 0
}

# --- run -------------------------------------------------------------------------------------
if ((local_mode)); then
  command -v docker >/dev/null || ulams_die "docker is required"
else
  os_setup
  sync_files
fi
env_setup
((local_mode)) || firewall_setup "$mode"
resolve_version
cloudflare_check
((local_mode)) || ownership
start_stack
verify
timers

printf '\n'
ulams_log "ulams is installed"
if ((local_mode)); then
  echo "    api    http://api.$domain:$(ulams_env_get "$ULAMS_ENV_FILE" LOCAL_HTTP_PORT)/api/health"
  echo "    web    http://$domain:$(ulams_env_get "$ULAMS_ENV_FILE" LOCAL_HTTP_PORT)/"
  echo "    admin  http://admin.$domain:$(ulams_env_get "$ULAMS_ENV_FILE" LOCAL_HTTP_PORT)/"
else
  echo "    platform   https://$domain   api: https://api.$domain   admin: https://admin.$domain"
  echo "    first tenant: scripts/create-tenant.sh <slug> [--name 'Display name']"
fi
if [[ "${admin_password_shown:-0}" == 1 ]]; then
  echo "    platform administrator: $(ulams_env_get "$ULAMS_ENV_FILE" PLATFORM_ADMIN_EMAIL) (generated password: PLATFORM_ADMIN_PASSWORD in $ULAMS_ENV_FILE; change it after the first sign-in)"
fi
echo "    keep a copy of $ULAMS_ENV_FILE in a password manager: APP_KEY decrypts every tenant's secrets."
