#!/usr/bin/env bash
# Shared helpers of the deployment scripts. Sourced, never run. No secret is ever printed here.
# shellcheck shell=bash

ULAMS_DEPLOY_DIR="${ULAMS_DEPLOY_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
ULAMS_ENV_FILE="${ULAMS_ENV_FILE:-$ULAMS_DEPLOY_DIR/.env}"
ULAMS_PROJECT="${ULAMS_PROJECT:-ulams}"
# LOCAL=1 (install.sh --local): plain HTTP on 127.0.0.1, MinIO instead of R2, nothing on the host changed
ULAMS_LOCAL="${ULAMS_LOCAL:-0}"

ulams_log() { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
ulams_warn() { printf '\033[1;33mwarning:\033[0m %s\n' "$*" >&2; }
ulams_die() { printf '\033[1;31merror:\033[0m %s\n' "$*" >&2; exit 1; }

# ulams_env_get FILE KEY: the value without surrounding single or double quotes ("" if absent)
ulams_env_get() {
  local line
  line="$(grep -E "^$2=" "$1" 2>/dev/null | tail -1 || true)"
  line="${line#*=}"
  line="${line%\'}"; line="${line#\'}"
  line="${line%\"}"; line="${line#\"}"
  printf '%s' "$line"
}

# ulams_env_set FILE KEY VALUE: replaces the line or appends it; the value is single-quoted
ulams_env_set() {
  local file="$1" key="$2" value="$3" tmp
  [[ "$value" != *"'"* ]] || ulams_die "value of $key contains a single quote"
  tmp="$(mktemp)"
  if grep -qE "^$key=" "$file"; then
    K="$key" V="$value" awk 'BEGIN{FS="="} $1==ENVIRON["K"] {print ENVIRON["K"] "=\047" ENVIRON["V"] "\047"; next} {print}' "$file" >"$tmp"
  else
    cat "$file" >"$tmp"
    printf "%s='%s'\n" "$key" "$value" >>"$tmp"
  fi
  cat "$tmp" >"$file"
  rm -f "$tmp"
}

# A value that still needs to be filled in
ulams_is_placeholder() {
  case "$1" in
    "" | GENERATE | CHANGE | CHANGE* | *.CHANGE.* | *"CHANGE.r2."*) return 0 ;;
  esac
  return 1
}

ulams_hex() { openssl rand -hex "$1"; }

# ulams_write_derived FILE DOMAIN STYLE MODE SCHEME PORT_SUFFIX
# Rewrites the block between "# BEGIN derived" and "# END derived" with the host patterns, the regular
# expressions of the Caddyfile and the placeholders it uses, for the flat or the nested layout.
ulams_write_derived() {
  local file="$1" domain="$2" style="$3" mode="$4" scheme="$5" port="$6"
  local d="${domain//./[.]}" front admin api content platform_content re_content_origin
  local api_of front_of admin_of sep_admin sep_api sep_content fcgi=on site="http://"
  case "$style" in
    flat)
      front="{slug}.$domain"; admin="{slug}-admin.$domain"; api="{slug}-api.$domain"; content="{slug}-content.$domain"
      platform_content="platform-content.$domain"
      re_content_origin="^(null|https?://[^/:]+-content[.]$d(:[0-9]+)?)\$"
      api_of="{re.slug.1}-api.$domain"; front_of="{re.slug.1}.$domain"; admin_of="{re.slug.1}-admin.$domain"
      sep_admin='-admin'; sep_api='-api'; sep_content='-content'
      ;;
    nested)
      front="{slug}.$domain"; admin="{slug}.admin.$domain"; api="{slug}.api.$domain"; content="{slug}.content.$domain"
      platform_content="platform.content.$domain"
      re_content_origin="^(null|https?://[^/:]+[.]content[.]$d(:[0-9]+)?)\$"
      api_of="{re.slug.1}.api.$domain"; front_of="{re.slug.1}.$domain"; admin_of="{re.slug.1}.admin.$domain"
      sep_admin='[.]admin'; sep_api='[.]api'; sep_content='[.]content'
      ;;
    *) ulams_die "ULAMS_HOST_STYLE must be flat or nested, not '$style'" ;;
  esac
  [[ "$mode" == origin_ca ]] && site="https://"
  [[ "$scheme" == http ]] && fcgi=off

  local block tmp
  block="$(cat <<BLOCK
ULAMS_SCHEME=$scheme
ULAMS_PORT_SUFFIX=$port
ULAMS_FCGI_HTTPS=$fcgi
ULAMS_SITE_ADDRESS='$site'
ULAMS_FRONT_PATTERN='$front'
ULAMS_ADMIN_PATTERN='$admin'
ULAMS_API_PATTERN='$api'
ULAMS_CONTENT_PATTERN='$content'
ULAMS_PLATFORM_CONTENT_HOST='$platform_content'
TENANCY_EMAIL_DOMAIN='{slug}.$domain'
ULAMS_RE_FRONT='^([a-z][a-z0-9]*)[.]$d\$'
ULAMS_RE_ADMIN='^([a-z][a-z0-9]*)${sep_admin}[.]$d\$'
ULAMS_RE_API='^([a-z][a-z0-9]*)${sep_api}[.]$d\$'
ULAMS_RE_CONTENT='^([a-z][a-z0-9]*)${sep_content}[.]$d\$'
ULAMS_RE_CONTENT_ORIGIN='$re_content_origin'
ULAMS_API_HOST_OF_SLUG='$api_of'
ULAMS_FRONT_HOST_OF_SLUG='$front_of'
ULAMS_ADMIN_HOST_OF_SLUG='$admin_of'
BLOCK
)"
  tmp="$(mktemp)"
  BLOCK="$block" awk '
    /^# BEGIN derived/ {print; print ENVIRON["BLOCK"]; skip=1; next}
    /^# END derived/ {skip=0}
    !skip {print}
  ' "$file" >"$tmp" || ulams_die "could not rewrite $file"
  cat "$tmp" >"$file"
  rm -f "$tmp"
}

# ulams_compose ARGS...: docker compose for this install (project, env file, mode files)
ulams_compose() {
  local files=(-f "$ULAMS_DEPLOY_DIR/compose.yml")
  local mode
  mode="$(ulams_env_get "$ULAMS_ENV_FILE" ULAMS_ORIGIN_MODE)"
  if [[ "$ULAMS_LOCAL" == 1 ]]; then
    files+=(-f "$ULAMS_DEPLOY_DIR/compose.local.yml")
  elif [[ "$mode" == origin_ca ]]; then
    files+=(-f "$ULAMS_DEPLOY_DIR/compose.origin.yml")
  fi
  docker compose -p "$ULAMS_PROJECT" --env-file "$ULAMS_ENV_FILE" "${files[@]}" "$@"
}

# ulams_artisan ARGS...: php artisan inside the api container (no TTY, works from cron and systemd)
ulams_artisan() { ulams_compose exec -T api php artisan "$@"; }

# Waits until the api answers its health check
ulams_wait_healthy() {
  local service="$1" tries="${2:-90}" i status cid
  for ((i = 0; i < tries; i++)); do
    cid="$(ulams_compose ps -q "$service" 2>/dev/null || true)"
    if [[ -n "$cid" ]]; then
      status="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$cid" 2>/dev/null || true)"
      [[ "$status" == healthy || "$status" == running ]] && return 0
    fi
    sleep 5
  done
  return 1
}

ulams_require_env() {
  [[ -f "$ULAMS_ENV_FILE" ]] || ulams_die "$ULAMS_ENV_FILE not found: run scripts/install.sh first"
}

# ulams_image_revision IMAGE: the git revision a published :latest image was built from
ulams_image_revision() {
  docker buildx imagetools inspect "ghcr.io/ulams-dev/$1:latest" --format '{{json .Image}}' 2>/dev/null \
    | jq -r 'to_entries[0].value.config.Labels["org.opencontainers.image.revision"] // empty'
}

# ulams_resolve_version [TAG|latest]: pins ULAMS_VERSION in .env. `latest` (or no tag and no value in
# .env) takes the sha-<short> tag of a main build for which all five images exist: CI publishes the
# images in separate jobs, so the newest build of one image can be ahead of another. Candidates are the
# revisions the :latest images were built from, the first one that exists everywhere wins.
ulams_resolve_version() {
  local wanted="${1:-}" cur img rev candidate ok
  cur="$(ulams_env_get "$ULAMS_ENV_FILE" ULAMS_VERSION)"
  [[ -n "$wanted" ]] || wanted="$cur"
  if [[ "$wanted" == latest || -z "$wanted" ]]; then
    ulams_log "resolving the newest published build to a pinned sha tag"
    local candidates=()
    for img in api web h5p pdf admin; do
      rev="$(ulams_image_revision "$img")"
      [[ -n "$rev" ]] && candidates+=("sha-${rev:0:7}")
    done
    wanted=""
    for candidate in $(printf '%s\n' "${candidates[@]}" | awk '!seen[$0]++'); do
      ok=1
      for img in api h5p pdf web admin; do
        docker manifest inspect "ghcr.io/ulams-dev/$img:$candidate" >/dev/null 2>&1 || { ok=0; break; }
      done
      if ((ok)); then wanted="$candidate"; break; fi
    done
    if [[ -z "$wanted" ]]; then
      # CI may be mid-publish: keep a tag that is already pinned and complete
      [[ -n "$cur" && "$cur" != latest ]] || ulams_die "no build exists for all five images yet; pass an explicit sha-<short> or release tag"
      ulams_warn "no newer build exists for all five images yet (CI may still be publishing); staying on $cur"
      wanted="$cur"
    fi
  else
    for img in api h5p pdf web admin; do
      docker manifest inspect "ghcr.io/ulams-dev/$img:$wanted" >/dev/null 2>&1 \
        || ulams_die "ghcr.io/ulams-dev/$img:$wanted does not exist (every image of one release carries the same tag)"
    done
  fi
  if [[ "$cur" != "$wanted" ]]; then
    [[ -z "$cur" || "$cur" == latest ]] || ulams_env_set "$ULAMS_ENV_FILE" ULAMS_VERSION_PREVIOUS "$cur"
    ulams_env_set "$ULAMS_ENV_FILE" ULAMS_VERSION "$wanted"
  fi
  echo "    images: ghcr.io/ulams-dev/{api,h5p,pdf,web,admin}:$wanted"
}

# ulams_rclone ARGS...: rclone (S3 remote "s3:") against the R2 / S3 endpoint of .env, on the compose network;
# ULAMS_RCLONE_DIR is mounted at /data when set
ulams_rclone() {
  local endpoint provider key secret mounts=()
  [[ -z "${ULAMS_RCLONE_DIR:-}" ]] || mounts=(-v "$ULAMS_RCLONE_DIR:/data")
  endpoint="$(ulams_env_get "$ULAMS_ENV_FILE" S3_ENDPOINT)"
  key="$(ulams_env_get "$ULAMS_ENV_FILE" BACKUP_S3_KEY)"
  secret="$(ulams_env_get "$ULAMS_ENV_FILE" BACKUP_S3_SECRET)"
  [[ -n "$key" ]] || key="$(ulams_env_get "$ULAMS_ENV_FILE" S3_KEY)"
  [[ -n "$secret" ]] || secret="$(ulams_env_get "$ULAMS_ENV_FILE" S3_SECRET)"
  provider=Other
  [[ "$endpoint" == *r2.cloudflarestorage.com* ]] && provider=Cloudflare
  docker run --rm --network "${ULAMS_PROJECT}_default" \
    -e RCLONE_CONFIG_S3_TYPE=s3 -e RCLONE_CONFIG_S3_PROVIDER="$provider" \
    -e RCLONE_CONFIG_S3_ENDPOINT="$endpoint" -e RCLONE_CONFIG_S3_ACCESS_KEY_ID="$key" \
    -e RCLONE_CONFIG_S3_SECRET_ACCESS_KEY="$secret" -e RCLONE_CONFIG_S3_REGION=auto \
    -e RCLONE_CONFIG_S3_NO_CHECK_BUCKET=true -e RCLONE_CONFIG_S3_ACL= \
    ${mounts[@]+"${mounts[@]}"} \
    rclone/rclone:1.75.2 "$@"
}

# A local trial (install.sh --local) records itself in .env, so every script finds its project
if [[ "$ULAMS_LOCAL" != 1 && -f "$ULAMS_ENV_FILE" && "$(ulams_env_get "$ULAMS_ENV_FILE" ULAMS_LOCAL_MODE)" == 1 ]]; then
  ULAMS_LOCAL=1
  ULAMS_PROJECT="$(ulams_env_get "$ULAMS_ENV_FILE" ULAMS_LOCAL_PROJECT)"
fi
