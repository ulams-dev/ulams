#!/bin/sh
# Logic of .github/workflows/php-freshness.yml, kept in a script so it can be tested without GitHub
# (.github/scripts/test-php-freshness.sh). POSIX sh; needs gh (and GH_TOKEN) only for `sync-issue`.
#
#   php-freshness.sh version-of            read `php -v` output on stdin, print X.Y.Z
#   php-freshness.sh compare LATEST OFFICIAL OURS [REPORT_FILE]
#                                          OURS may be "" or "unavailable" (not published / not pullable)
#                                          writes the markdown report, prints behind=true|false
#   php-freshness.sh sync-issue behind|current REPORT_FILE
#                                          opens or updates ONE issue labelled `security` when behind,
#                                          closes it when current. Never fails because PHP is behind.
set -eu

ISSUE_TITLE="PHP is behind the latest release"
ISSUE_LABEL="security"

# true when $1 >= $2 (X.Y.Z)
version_ge() {
  [ "$(printf '%s\n%s\n' "$1" "$2" | sort -t. -k1,1n -k2,2n -k3,3n | head -n 1)" = "$2" ]
}

known() { case "${1:-}" in ''|unavailable) return 1 ;; *) return 0 ;; esac; }

cmd_version_of() {
  sed -n 's/^PHP \([0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*\).*/\1/p' | head -n 1
}

cmd_compare() {
  latest="${1:-}"; official="${2:-}"; ours="${3:-}"; report="${4:-/dev/stdout}"
  if ! known "$latest"; then
    echo "cannot read the latest release from php.net; nothing to compare" >&2
    echo "behind=false"
    return 0
  fi
  behind=false
  {
    echo "<!-- php-freshness: latest=$latest official=${official:-unavailable} ours=${ours:-unavailable} -->"
    echo "Latest PHP 8.4 release on php.net: **$latest**."
    echo
    echo "| Image | PHP version | Status |"
    echo "|---|---|---|"
  } > "$report"
  row() { # name version
    if ! known "$2"; then
      echo "| $1 | not available | skipped |" >> "$report"
    elif version_ge "$2" "$latest"; then
      echo "| $1 | $2 | current |" >> "$report"
    else
      echo "| $1 | $2 | **behind $latest** |" >> "$report"
      behind=true
    fi
  }
  row "\`php:8.4-fpm-alpine\` (official, our base)" "$official"
  row "\`ghcr.io/ulams-dev/php:8.4\` (our published image)" "$ours"
  {
    echo
    echo "What to do: when the official image is behind, wait for it (Docker publishes it hours or days after php.net);"
    echo "the image build already refuses a PHP below \`MIN_PHP_VERSION\` in \`api/docker/php/install.sh\`, so raise that"
    echo "default for a security release. When only our image is behind, run the \`Publish images\` workflow (it pulls the"
    echo "base) and check the new image with \`docker run --rm ghcr.io/ulams-dev/php:8.4 php -v\`."
    echo "MyDevil hosts are checked separately with \`deploy/mydevil/bin/check-host.sh\`."
    echo
    echo "Opened and closed by \`.github/workflows/php-freshness.yml\` (daily). Docs: Operators > Security."
  } >> "$report"
  echo "behind=$behind"
}

cmd_sync_issue() {
  state="${1:?behind|current}"; report="${2:?report file}"
  gh label create "$ISSUE_LABEL" --color B60205 --description "Security updates" >/dev/null 2>&1 || true
  number="$(gh issue list --label "$ISSUE_LABEL" --state open --search "\"$ISSUE_TITLE\" in:title" \
    --json number,title --jq "[.[] | select(.title == \"$ISSUE_TITLE\")][0].number // empty")"
  if [ "$state" = behind ]; then
    if [ -z "$number" ]; then
      gh issue create --title "$ISSUE_TITLE" --label "$ISSUE_LABEL" --body-file "$report"
    else
      current_marker="$(head -n 1 "$report")"
      old_marker="$(gh issue view "$number" --json body --jq .body | head -n 1)"
      if [ "$current_marker" != "$old_marker" ]; then
        gh issue edit "$number" --body-file "$report"
        gh issue comment "$number" --body "The versions changed, see the table above."
      else
        echo "issue #$number is already up to date"
      fi
    fi
  elif [ -n "$number" ]; then
    gh issue close "$number" --comment "Both images are on the latest PHP 8.4 release again. Closing."
  else
    echo "current, no open issue"
  fi
}

mode="${1:-}"; [ "$#" -gt 0 ] && shift
case "$mode" in
  version-of) cmd_version_of ;;
  compare) cmd_compare "$@" ;;
  sync-issue) cmd_sync_issue "$@" ;;
  *) echo "usage: php-freshness.sh version-of | compare LATEST OFFICIAL OURS [REPORT] | sync-issue behind|current REPORT" >&2; exit 2 ;;
esac
