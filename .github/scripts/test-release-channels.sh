#!/bin/sh
# Tests of release-channels.sh decide: which tags get stable and lts.
#   sh .github/scripts/test-release-channels.sh
set -eu
HERE="$(cd "$(dirname "$0")" && pwd)"
fails=0

check() {
  name="$1" tags="$2" lts="$3" tag="$4" expected="$5"
  actual="$(TAGS="$tags" LTS_LINES="$lts" sh "$HERE/release-channels.sh" decide "$tag" | tr '\n' ' ' | sed 's/ $//')"
  if [ "$actual" = "$expected" ]; then
    echo "ok   $name"
  else
    echo "FAIL $name: expected '$expected', got '$actual'"
    fails=$((fails + 1))
  fi
}

check "first release is stable" "" "" v0.3.0 "stable"
check "newest release is stable" "v0.3.0
v0.4.0" "" v0.4.1 "stable"
check "a patch of an older line is not stable" "v0.3.0
v0.4.0" "" v0.3.1 ""
check "numeric, not lexical, order" "v0.9.0" "" v0.10.0 "stable"
check "pre-release gets nothing" "v0.3.0" "" v0.4.0-rc.1 ""
check "pre-release tags do not hide the newest release" "v0.5.0-rc.1" "" v0.4.0 "stable"
check "lts line patch" "v1.0.0
v1.1.0" "1.0" v1.0.1 "lts"
check "newest release on an lts line gets both" "v0.9.0" "1.0" v1.0.0 "stable lts"
check "older patch of an lts line gets nothing" "v1.0.0
v1.0.2
v1.1.0" "1.0" v1.0.1 ""
check "line not declared lts" "v1.0.0" "1.0" v1.1.0 "stable"
check "not a release tag" "v1.0.0" "" main ""

if [ "$fails" -gt 0 ]; then
  echo "$fails failed"
  exit 1
fi
echo "all passed"
