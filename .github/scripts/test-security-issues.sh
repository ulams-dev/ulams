#!/bin/sh
# Tests for security-issues.sh without network or GitHub (gh is a stub): sh .github/scripts/test-security-issues.sh
HERE="$(cd "$(dirname "$0")" && pwd)"
S="$HERE/security-issues.sh"
fails=0
tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT

check() { # name expected actual
  if [ "$2" = "$3" ]; then echo "ok    $1"; else echo "FAIL  $1: wanted '$2', got '$3'"; fails=$((fails + 1)); fi
}

cat > "$tmp/found.json" <<'JSON'
{"Results":[{"Target":"alpine","Vulnerabilities":[
 {"VulnerabilityID":"CVE-1","Severity":"CRITICAL","PkgName":"zlib","InstalledVersion":"1.0","FixedVersion":"1.1"},
 {"VulnerabilityID":"CVE-2","Severity":"HIGH","PkgName":"curl","InstalledVersion":"8.0","FixedVersion":"8.1"},
 {"VulnerabilityID":"CVE-2","Severity":"HIGH","PkgName":"curl","InstalledVersion":"8.0","FixedVersion":"8.1"},
 {"VulnerabilityID":"CVE-3","Severity":"MEDIUM","PkgName":"musl","InstalledVersion":"1","FixedVersion":"2"}]},
 {"Target":"node","Vulnerabilities":[
 {"VulnerabilityID":"CVE-4","Severity":"HIGH","PkgName":"ws","InstalledVersion":"1","FixedVersion":"2"}]}]}
JSON
echo '{"Results":[{"Target":"alpine"}]}' > "$tmp/clean.json"
echo '{}' > "$tmp/empty.json"

check "counts critical and high, drops medium and duplicates" count=3 "$(sh "$S" report api "$tmp/found.json" "$tmp/found.md")"
check "clean report" count=0 "$(sh "$S" report api "$tmp/clean.json" "$tmp/clean.md")"
check "report without results" count=0 "$(sh "$S" report api "$tmp/empty.json" "$tmp/empty.md")"
grep -q '^| CRITICAL | CVE-1 | zlib | 1.0 | 1.1 |' "$tmp/found.md"; check "table row" 0 $?
grep -q 'CVE-3' "$tmp/found.md"; check "medium is not listed" 1 $?
check "first line is the fingerprint" true "$(head -n 1 "$tmp/found.md" | grep -q '^<!-- trivy-findings:[0-9]* -->$' && echo true)"
sh "$S" report api "$tmp/found.json" "$tmp/again.md" >/dev/null
check "fingerprint is stable" "$(head -n 1 "$tmp/found.md")" "$(head -n 1 "$tmp/again.md")"
sh "$S" report api "$tmp/clean.json" "$tmp/clean.md" >/dev/null
check "fingerprint changes with the findings" false "$([ "$(head -n 1 "$tmp/found.md")" = "$(head -n 1 "$tmp/clean.md")" ] && echo true || echo false)"

# gh stub: records calls; STUB_NUMBER is the open issue (empty for none), STUB_BODY its first line
mkdir -p "$tmp/bin"
cat > "$tmp/bin/gh" <<'STUB'
#!/bin/sh
echo "$*" >> "$STUB_LOG"
case "$1 $2" in
  "issue list") echo "$STUB_NUMBER" ;;
  "issue view") echo "$STUB_BODY" ;;
esac
STUB
chmod +x "$tmp/bin/gh"
run() { # number body count report -> calls made
  : > "$tmp/log"
  STUB_LOG="$tmp/log" STUB_NUMBER="$1" STUB_BODY="$2" PATH="$tmp/bin:$PATH" sh "$S" sync api "$3" "$4" >/dev/null
  grep -c "^issue \(create\|edit\|close\|comment\)" "$tmp/log" | tr -d ' '
}
check "findings, no issue: opens one" 1 "$(run '' '' 3 "$tmp/found.md")"
grep -q '^issue create --title Security: fixable vulnerabilities in ghcr.io/ulams-dev/api:latest --label security' "$tmp/log"; check "issue title and label" 0 $?
check "same findings: nothing to do" 0 "$(run 7 "$(head -n 1 "$tmp/found.md")" 3 "$tmp/found.md")"
check "changed findings: edit and comment" 2 "$(run 7 '<!-- trivy-findings:1 -->' 3 "$tmp/found.md")"
check "clean with open issue: closes it" 1 "$(run 7 x 0 "$tmp/clean.md")"
check "clean, no issue: nothing" 0 "$(run '' '' 0 "$tmp/clean.md")"

[ "$fails" -eq 0 ] || { echo "$fails failure(s)"; exit 1; }
echo "all security-issues checks passed"
