#!/bin/sh
# Smoke test for Cloudflare Image Transformations on one host (ADR 0193, IMG-6a). Run it against a host BEFORE
# building its front (or the landing) with ULAMS_IMAGE_SERVICE=cloudflare, and again after. Changes nothing.
#
#   sh check-images.sh <host-or-url> [source-image-url]
#
# <host> serves the pages (a demo front such as coffee.ulams.app, or ulams.app for the landing). Without a source
# image, the first .jpg/.jpeg/.png/.webp of the host's home page is used (an absolute URL, or a path of that host).
# Checks, each printed as `ok` or `FAIL` (exit 1 when any fails):
#   1. https://<host>/cdn-cgi/image/<options>/<source> answers 200 with content-type image/avif or image/webp
#      and a cf-resized header (Transformations on, source accepted, no Worker interception, error 9524).
#   2. The same with a source that does not exist and onerror=redirect answers 3xx to that source (the fallback
#      the builder relies on when the monthly free quota is used up; without it the browser would get an error).
#   3. Only with ULAMS_EXPECT_WAF=1 (IMG-6b): the optional WAF rule (cloudflare/image-waf-rule.mjs) is active, so a
#      width the builder never emits (width=777) is blocked with 403. Without the rule that request would create a
#      unique transformation, so it is never sent by default.
# OPTIONS is one of the option strings @ulams/sdk emits (the `card` preset, front/sdk/src/images.ts), so the smoke also
# passes with the WAF rule on; test/check-images.sh checks that it stays in `allowedCloudflareOptions()`.
# CURL_BIN overrides the curl binary (tests use a fake).

CURL="${CURL_BIN:-curl}"
OPTIONS="width=240,quality=78,format=auto,fit=scale-down,onerror=redirect"
ACCEPT="image/avif,image/webp,image/*,*/*;q=0.8"
fails=0

pass() { echo "  ok       $1"; }
fail() { echo "  FAIL     $1"; fails=$((fails + 1)); }

# Prints the response headers (lowercased, CR removed) of a GET without following redirects.
headers() {
  "$CURL" -sS -m 30 -o /dev/null -D - -H "accept: $ACCEPT" "$1" 2>/dev/null | tr -d '\r' | tr '[:upper:]' '[:lower:]'
}
status_of() { printf '%s\n' "$1" | sed -n '1s/^[^ ]* \([0-9][0-9]*\).*/\1/p'; }
header_of() { printf '%s\n' "$1" | sed -n "s/^$2: *//p" | head -n 1; }

if [ "$#" -lt 1 ] || [ "$#" -gt 2 ]; then
  echo "usage: sh check-images.sh <host-or-url> [source-image-url]" >&2
  exit 2
fi

host="${1#https://}"; host="${host#http://}"; host="${host%%/*}"
base="https://$host"
source="${2:-}"

if [ -z "$source" ]; then
  page="$("$CURL" -sS -m 30 "$base/" 2>/dev/null || true)"
  source="$(printf '%s' "$page" | grep -oE 'https://[A-Za-z0-9._~:/%+@-]+\.(jpe?g|png|webp)' | head -n 1)"
  if [ -z "$source" ]; then
    rel="$(printf '%s' "$page" | grep -oE '(src|href)="/[^"?]+\.(jpe?g|png|webp)"' | grep -v '/cdn-cgi/' | head -n 1 | sed 's/^[a-z]*="//; s/"$//')"
    [ -n "$rel" ] && source="$base$rel"
  fi
  if [ -z "$source" ]; then
    echo "  FAIL     no image found on $base/; pass a source image URL as the second argument"
    exit 1
  fi
fi
echo "host $host, source $source"

# 1. a real transformation
out="$(headers "$base/cdn-cgi/image/$OPTIONS/$source")"
code="$(status_of "$out")"
type="$(header_of "$out" content-type)"
resized="$(header_of "$out" cf-resized)"
if [ "$code" != 200 ]; then
  fail "transformation: HTTP ${code:-none} (wanted 200). Transformations off for the zone, source origin not allowed, or the source URL is intercepted by a Worker (error 9524); a 3xx means onerror=redirect fell back to the original"
else
  case "$type" in
    image/avif*|image/webp*) pass "transformation: 200 $type" ;;
    *) fail "transformation: content-type '${type:-none}' (wanted image/avif or image/webp: the original was served untouched)" ;;
  esac
  if [ -n "$resized" ]; then pass "transformation: cf-resized present"; else fail "transformation: no cf-resized header"; fi
fi

# 2. the fallback: a missing source with onerror=redirect goes back to the source URL
missing="${source%/*}/ulams-smoke-missing-$$.png"
out="$(headers "$base/cdn-cgi/image/$OPTIONS/$missing")"
code="$(status_of "$out")"
location="$(header_of "$out" location)"
case "$code" in
  301|302|303|307|308)
    case "$location" in
      *ulams-smoke-missing-*) pass "onerror=redirect: $code to the source" ;;
      *) fail "onerror=redirect: $code but Location is '${location:-empty}', not the source" ;;
    esac ;;
  *) fail "onerror=redirect: HTTP ${code:-none} (wanted a redirect to the source)" ;;
esac

# 3. the WAF rule blocks an option string the builder never emits (opt-in: without the rule this costs a transformation)
if [ "${ULAMS_EXPECT_WAF:-}" = 1 ]; then
  out="$(headers "$base/cdn-cgi/image/width=777,quality=78,format=auto,fit=scale-down,onerror=redirect/$source")"
  code="$(status_of "$out")"
  if [ "$code" = 403 ]; then pass "WAF rule: width=777 blocked with 403"; else fail "WAF rule: HTTP ${code:-none} for width=777 (wanted 403: the rule is missing or not enabled)"; fi
fi

if [ "$fails" -eq 0 ]; then echo "all passed"; else echo "$fails failed"; exit 1; fi
