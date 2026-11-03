#!/bin/sh
# Daily 404 check of the public site. Usage: link-check.sh check REPORT | sync-issue broken|ok REPORT
#
# Two checks, both against production:
#   1. every link on the landing page (ulams.app and *.ulams.app) must answer 2xx/3xx;
#   2. every demo academy must list at least one course, and each listed course page must answer 200.
# A failure is retried once after a pause, so a Cloudflare blip does not open an issue. Findings go to the report
# file (first line is a marker used to skip no-op issue edits); the script exits 0 either way.
LANDING="${LANDING_URL:-https://ulams.app/}"
ACADEMIES="${ACADEMIES:-coffee oncall nightsky gravity poland microdosing stablecoins}"
ACADEMY_DOMAIN="${ACADEMY_DOMAIN:-ulams.app}"
RETRY_PAUSE="${RETRY_PAUSE:-10}"
ISSUE_TITLE="Public pages return 404"
ISSUE_LABEL="monitoring"

status() { curl -s -o /dev/null -L --max-time 20 -w '%{http_code}' "$1" 2>/dev/null || echo 000; }

# Status that survives one retry.
stable_status() {
  code="$(status "$1")"
  case "$code" in 2*) echo "$code"; return ;; esac
  sleep "$RETRY_PAUSE"
  status "$1"
}

# Links worth checking: absolute http(s) links to ulams.app hosts, root-relative links; no anchors,
# no Cloudflare's e-mail obfuscation endpoint.
landing_links() {
  grep -oE 'href="[^"#]+"' | sed 's/^href="//;s/"$//' | grep -E '^(/|https://([a-z0-9-]+\.)?ulams\.app)' \
    | grep -v '^//' | grep -v '/cdn-cgi/' | sort -u
}

cmd_check() {
  report="${1:?report file}"; found="$(mktemp)"
  base="$(printf '%s' "$LANDING" | sed -E 's#^(https?://[^/]+).*#\1#')"
  page="$(curl -s --max-time 30 --retry 2 "$LANDING" || true)"
  if [ -z "$page" ]; then echo "- $LANDING did not return a page" >> "$found"; fi
  for link in $(printf '%s' "$page" | landing_links); do
    case "$link" in /*) url="$base$link" ;; *) url="$link" ;; esac
    code="$(stable_status "$url")"
    case "$code" in 2*|3*) ;; *) echo "- landing link \`$url\` answers $code" >> "$found" ;; esac
  done
  for slug in $ACADEMIES; do
    api="https://$slug-api.$ACADEMY_DOMAIN/api/courses"
    ids="$(curl -s --max-time 30 --retry 2 "$api" | jq -r '.data[]?.id' 2>/dev/null || true)"
    if [ -z "$ids" ]; then
      echo "- academy \`$slug\` lists no courses ($api), so its demo links cannot resolve" >> "$found"
      continue
    fi
    for id in $ids; do
      url="https://$slug.$ACADEMY_DOMAIN/courses/$id"
      code="$(stable_status "$url")"
      [ "$code" = 200 ] || echo "- course page \`$url\` answers $code" >> "$found"
    done
  done
  if [ -s "$found" ]; then
    { echo "<!-- link-check: $(cksum < "$found" | cut -d' ' -f1) -->"; echo "Checked $(date -u +%Y-%m-%dT%H:%MZ). Pages that must not 404 but do:"; echo; cat "$found"; } > "$report"
    echo "broken=true"
  else
    echo "All checked pages answer." > "$report"
    echo "broken=false"
  fi
  rm -f "$found"
}

cmd_sync_issue() {
  state="${1:?broken|ok}"; report="${2:?report file}"
  gh label create "$ISSUE_LABEL" --color FBCA04 --description "Automated monitoring" >/dev/null 2>&1 || true
  number="$(gh issue list --label "$ISSUE_LABEL" --state open --search "\"$ISSUE_TITLE\" in:title" \
    --json number,title --jq "[.[] | select(.title == \"$ISSUE_TITLE\")][0].number // empty")"
  if [ "$state" = broken ]; then
    if [ -z "$number" ]; then
      gh issue create --title "$ISSUE_TITLE" --label "$ISSUE_LABEL" --body-file "$report"
    else
      old_marker="$(gh issue view "$number" --json body --jq .body | head -n 1)"
      if [ "$(head -n 1 "$report")" != "$old_marker" ]; then
        gh issue edit "$number" --body-file "$report"
        gh issue comment "$number" --body "The list of failing pages changed, see the description."
      else
        echo "issue #$number is already up to date"
      fi
    fi
  elif [ -n "$number" ]; then
    gh issue close "$number" --comment "Every checked page answers again. Closing."
  else
    echo "ok, no open issue"
  fi
}

mode="${1:-}"; [ "$#" -gt 0 ] && shift
case "$mode" in
  check) cmd_check "$@" ;;
  landing-links) landing_links ;;
  sync-issue) cmd_sync_issue "$@" ;;
  *) echo "usage: link-check.sh check REPORT | landing-links | sync-issue broken|ok REPORT" >&2; exit 2 ;;
esac
