#!/bin/sh
# Turns a Trivy JSON report of one published image into ONE issue labelled `security`: opened when there
# are fixable CRITICAL or HIGH findings, edited when the set changes, closed when the image is clean.
# The nightly run never fails because of findings; the issue is the report (same pattern as php-freshness).
#
#   security-issues.sh report IMAGE REPORT.json OUT.md   write the issue body, print count=<n> (CRITICAL+HIGH)
#   security-issues.sh sync IMAGE COUNT OUT.md           open, update or close the issue (needs gh and GH_REPO)
#
# IMAGE is the short name (api, h5p, ...). Deadlines are the patch policy in SECURITY.md.
set -eu

ISSUE_LABEL="${ISSUE_LABEL:-security}"
title_of() { echo "Security: fixable vulnerabilities in ghcr.io/ulams-dev/$1:latest"; }

cmd_report() {
  image="${1:?image}"; json="${2:?trivy json}"; out="${3:?output file}"
  rows="$(jq -r '[.Results[]?.Vulnerabilities[]? | select(.Severity == "CRITICAL" or .Severity == "HIGH")
      | {id: .VulnerabilityID, sev: .Severity, pkg: .PkgName, installed: .InstalledVersion, fixed: .FixedVersion}]
      | unique_by(.id + .pkg + .installed)
      | sort_by(.sev, .pkg, .id)[]
      | "\(.sev)|\(.id)|\(.pkg)|\(.installed)|\(.fixed)"' "$json")"
  critical="$(printf '%s\n' "$rows" | grep -c '^CRITICAL|' || true)"
  high="$(printf '%s\n' "$rows" | grep -c '^HIGH|' || true)"
  count=$((critical + high))
  # First line: a fingerprint of the findings, so the issue is only edited when they change.
  fingerprint="$(printf '%s\n' "$rows" | cut -d'|' -f2,3,4 | cksum | cut -d' ' -f1)"
  {
    echo "<!-- trivy-findings:$fingerprint -->"
    echo "The nightly Trivy scan of \`ghcr.io/ulams-dev/$image:latest\` found **$critical critical** and **$high high** vulnerabilities that have a fix available."
    echo
    echo "Patch policy (SECURITY.md): critical within 2 days, high within 14 days of this issue being opened. Rebuild the image"
    echo "(\`Publish images\` workflow pulls fresh base layers) or bump the dependency; if a finding is accepted, add it to"
    echo "\`.trivyignore.yaml\` with a reason and an expiry."
    echo
    echo "| Severity | ID | Package | Installed | Fixed in |"
    echo "|---|---|---|---|---|"
    printf '%s\n' "$rows" | head -n 60 | while IFS='|' read -r sev id pkg installed fixed; do
      if [ -n "$id" ]; then echo "| $sev | $id | $pkg | $installed | $fixed |"; fi
    done
    if [ "$count" -gt 60 ]; then
      echo
      echo "…and $((count - 60)) more; run \`.github/scripts/trivy.sh image ghcr.io/ulams-dev/$image:latest\` for the full list."
    fi
    echo
    echo "Opened and closed by \`.github/workflows/nightly-security.yml\`. Docs: Operators > Hardening."
  } > "$out"
  echo "count=$count"
}

cmd_sync() {
  image="${1:?image}"; count="${2:?count}"; report="${3:?report file}"
  title="$(title_of "$image")"
  gh label create "$ISSUE_LABEL" --color B60205 --description "Security updates" >/dev/null 2>&1 || true
  number="$(gh issue list --label "$ISSUE_LABEL" --state open --search "\"$title\" in:title" \
    --json number,title --jq "[.[] | select(.title == \"$title\")][0].number // empty")"
  if [ "$count" -gt 0 ]; then
    if [ -z "$number" ]; then
      gh issue create --title "$title" --label "$ISSUE_LABEL" --body-file "$report"
    else
      old_marker="$(gh issue view "$number" --json body --jq .body | head -n 1)"
      if [ "$(head -n 1 "$report")" != "$old_marker" ]; then
        gh issue edit "$number" --body-file "$report"
        gh issue comment "$number" --body "The findings changed, see the table above."
      else
        echo "issue #$number is already up to date"
      fi
    fi
  elif [ -n "$number" ]; then
    gh issue close "$number" --comment "No fixable critical or high vulnerabilities in the latest image any more. Closing."
  else
    echo "$image is clean, no open issue"
  fi
}

mode="${1:-}"; [ "$#" -gt 0 ] && shift
case "$mode" in
  report) cmd_report "$@" ;;
  sync) cmd_sync "$@" ;;
  *) echo "usage: security-issues.sh report IMAGE JSON OUT | sync IMAGE COUNT REPORT" >&2; exit 2 ;;
esac
