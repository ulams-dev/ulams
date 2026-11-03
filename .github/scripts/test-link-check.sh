#!/bin/sh
# Tests for link-check.sh with fake curl and gh: sh .github/scripts/test-link-check.sh
HERE="$(cd "$(dirname "$0")" && pwd)"
S="$HERE/link-check.sh"
fails=0
tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT
check() { if [ "$2" = "$3" ]; then echo "ok    $1"; else echo "FAIL  $1: wanted '$2', got '$3'"; fails=$((fails + 1)); fi; }

check "landing links" "$(printf '/blog/\nhttps://coffee.ulams.app/learn/1\n')" \
  "$(printf '<a href="/blog/">x</a><a href="https://coffee.ulams.app/learn/1">y</a><a href="/cdn-cgi/l/email-protection">z</a><a href="https://example.com/">e</a><a href="#top">t</a><a href="/blog/">dup</a>' | sh "$S" landing-links)"

mkdir -p "$tmp/bin"
cat > "$tmp/bin/curl" <<'EOS'
#!/bin/sh
# Fake: api URLs return JSON, page URLs return a status from $FAKE_404 (space separated substrings that 404).
for last; do :; done
case "$last" in
  https://ulams.app/) echo '<a href="https://good.ulams.app/learn/1">a</a><a href="https://bad.ulams.app/learn/1">b</a>' ;;
  https://good-api.ulams.app/*) echo '{"data":[{"id":1}]}' ;;
  https://bad-api.ulams.app/*) echo '{"data":[]}' ;;
  *) for p in $FAKE_404; do case "$last" in *"$p"*) printf 404; exit 0 ;; esac; done; printf 200 ;;
esac
EOS
chmod +x "$tmp/bin/curl"
run() { PATH="$tmp/bin:$PATH" RETRY_PAUSE=0 ACADEMIES="$1" FAKE_404="$2" sh "$S" check "$tmp/report.md"; }

check "all fine" broken=false "$(run good '')"
check "landing link 404" broken=true "$(run good bad.ulams.app)"
grep -q 'bad.ulams.app/learn/1. answers 404' "$tmp/report.md"; check "report names the link" 0 $?
check "empty academy" broken=true "$(run bad '')"
grep -q 'academy `bad` lists no courses' "$tmp/report.md"; check "report names the academy" 0 $?
check "course page 404" broken=true "$(run good good.ulams.app/courses/1)"

cat > "$tmp/bin/gh" <<'EOS'
#!/bin/sh
echo "$*" >> "$GH_LOG"
case "$1 $2" in "issue list") echo "$FAKE_OPEN_ISSUE" ;; "issue view") cat "$FAKE_BODY" ;; esac
exit 0
EOS
chmod +x "$tmp/bin/gh"
printf '<!-- link-check: 1 -->\nbody\n' > "$tmp/r1.md"
sync() { : > "$tmp/gh.log"; PATH="$tmp/bin:$PATH" GH_LOG="$tmp/gh.log" FAKE_OPEN_ISSUE="$2" FAKE_BODY="$tmp/r1.md" sh "$S" sync-issue "$1" "$tmp/r1.md" >/dev/null; grep -c "$3" "$tmp/gh.log"; }
check "broken opens an issue" 1 "$(sync broken '' 'issue create')"
check "broken, same marker: no edit" 0 "$(sync broken 7 'issue edit')"
check "ok closes the issue" 1 "$(sync ok 7 'issue close')"
check "ok without issue does nothing" 0 "$(sync ok '' 'issue close')"
[ "$fails" -eq 0 ] || { echo "$fails failed"; exit 1; }
