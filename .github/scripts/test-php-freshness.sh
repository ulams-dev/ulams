#!/bin/sh
# Tests for php-freshness.sh without network or GitHub: sh .github/scripts/test-php-freshness.sh
HERE="$(cd "$(dirname "$0")" && pwd)"
S="$HERE/php-freshness.sh"
fails=0
tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT

check() { # name expected actual
  if [ "$2" = "$3" ]; then echo "ok    $1"; else echo "FAIL  $1: wanted '$2', got '$3'"; fails=$((fails + 1)); fi
}

check "version-of" 8.4.27 "$(printf 'PHP 8.4.27 (cli) (built: Oct 21 2026 16:02:00) (NTS)\nCopyright (c) The PHP Group\n' | sh "$S" version-of)"
check "version-of rc" 8.5.0 "$(printf 'PHP 8.5.0RC1 (cli)\n' | sh "$S" version-of)"
check "version-of garbage" "" "$(printf 'nothing\n' | sh "$S" version-of)"

cmp() { sh "$S" compare "$1" "$2" "$3" "$tmp/report.md" 2>/dev/null; }
check "all current" behind=false "$(cmp 8.4.27 8.4.27 8.4.27)"
check "official behind" behind=true "$(cmp 8.4.27 8.4.26 8.4.27)"
check "ours behind" behind=true "$(cmp 8.4.27 8.4.27 8.4.26)"
check "ahead of latest" behind=false "$(cmp 8.4.27 8.4.28 8.4.28)"
check "numeric compare (8.4.9 < 8.4.27)" behind=true "$(cmp 8.4.27 8.4.27 8.4.9)"
check "numeric compare (8.4.100 > 8.4.27)" behind=false "$(cmp 8.4.27 8.4.100 8.4.100)"
check "ours unavailable is skipped" behind=false "$(cmp 8.4.27 8.4.27 unavailable)"
check "ours empty is skipped" behind=false "$(cmp 8.4.27 8.4.27 '')"
check "official unavailable alone" behind=false "$(cmp 8.4.27 unavailable 8.4.27)"
check "no latest, no verdict" behind=false "$(cmp '' 8.4.26 8.4.26)"
cmp 8.4.27 8.4.26 unavailable >/dev/null
grep -q 'behind 8.4.27' "$tmp/report.md" && grep -q 'not available | skipped' "$tmp/report.md"
check "report content" 0 $?

# sync-issue with a fake gh that records its calls
mkdir -p "$tmp/bin"
cat > "$tmp/bin/gh" <<'EOS'
#!/bin/sh
echo "$*" >> "$GH_LOG"
case "$1 $2" in
  "issue list") echo "$FAKE_OPEN_ISSUE" ;;
  "issue view") cat "$FAKE_BODY" ;;
esac
exit 0
EOS
chmod +x "$tmp/bin/gh"
export GH_LOG="$tmp/gh.log"
run() { : > "$GH_LOG"; PATH="$tmp/bin:$PATH" sh "$S" sync-issue "$@" >/dev/null; }

cmp 8.4.27 8.4.26 8.4.26 >/dev/null
FAKE_OPEN_ISSUE="" FAKE_BODY=/dev/null run behind "$tmp/report.md"
grep -q '^issue create ' "$GH_LOG"; check "behind, no issue: creates one" 0 $?
FAKE_OPEN_ISSUE="" FAKE_BODY=/dev/null run current "$tmp/report.md"
grep -q 'issue create\|issue close' "$GH_LOG"; check "current, no issue: does nothing" 1 $?
FAKE_OPEN_ISSUE=42 FAKE_BODY="$tmp/report.md" run behind "$tmp/report.md"
grep -q 'issue create\|issue edit\|issue comment' "$GH_LOG"; check "behind, unchanged: no new noise" 1 $?
echo "<!-- php-freshness: latest=8.4.26 official=8.4.25 ours=8.4.25 -->" > "$tmp/old.md"
FAKE_OPEN_ISSUE=42 FAKE_BODY="$tmp/old.md" run behind "$tmp/report.md"
grep -q '^issue edit 42' "$GH_LOG"; check "behind, changed: edits" 0 $?
FAKE_OPEN_ISSUE=42 FAKE_BODY="$tmp/old.md" run current "$tmp/report.md"
grep -q '^issue close 42' "$GH_LOG"; check "current, open issue: closes it" 0 $?
grep -q -- '--label security' "$GH_LOG" || grep -q '^issue list --label security' "$GH_LOG"; check "issue lookup uses the label" 0 $?

if [ "$fails" -eq 0 ]; then echo "all passed"; else echo "$fails failed"; exit 1; fi
