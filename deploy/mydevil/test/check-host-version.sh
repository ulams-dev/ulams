#!/bin/sh
# Offline test of the PHP version check in bin/check-host.sh (no MyDevil host needed):
#   sh deploy/mydevil/test/check-host-version.sh
HERE="$(cd "$(dirname "$0")" && pwd)"
SCRIPT="$HERE/../bin/check-host.sh"
fails=0

expect() { # <exit code> <version>
  out="$(sh "$SCRIPT" --check-version "$2" 2>&1)"; code=$?
  if [ "$code" -eq "$1" ]; then echo "ok    $2 -> $code"; else echo "FAIL  $2 -> $code, wanted $1"; echo "$out"; fails=$((fails + 1)); fi
}

expect 1 8.4.26
expect 0 8.4.27
expect 0 8.4.30
expect 0 8.4.27-dev
expect 1 8.4.9
expect 0 8.4.100
expect 1 8.3.35
expect 0 8.3.36
expect 1 8.5.11
expect 0 8.5.12
expect 1 8.2.34
expect 0 8.2.35
expect 1 8.1.99
expect 1 7.4.33
expect 0 8.6.0
expect 1 ""
expect 1 "not a version"

# the failure message must point at the issue and the advisory
out="$(sh "$SCRIPT" --check-version 8.4.26)"
case "$out" in *'#327'*'2026-10-21-5'*) echo "ok    message" ;; *) echo "FAIL  message"; fails=$((fails + 1)) ;; esac

# whole script with a fake php binary: survey runs on, exit code follows the version
tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT
for v in 8.4.26 8.4.27; do
  cat > "$tmp/php-$v" <<EOS
#!/bin/sh
case "\$1" in -v) echo "PHP $v (cli)" ;; -m) echo mbstring ;; -r) case "\$2" in 'echo PHP_VERSION;') printf '%s' $v ;; esac ;; esac
EOS
  chmod +x "$tmp/php-$v"
  out="$(PATH="$tmp:$PATH" sh "$SCRIPT" "php-$v" 2>&1)"; code=$?
  want=0; [ "$v" = 8.4.26 ] && want=1
  case "$out" in *"PHP_VERSION=$v"*) ;; *) echo "FAIL  full run $v: PHP_VERSION not printed"; fails=$((fails + 1)) ;; esac
  if [ "$code" -eq "$want" ]; then echo "ok    full run $v -> $code"; else echo "FAIL  full run $v -> $code, wanted $want"; fails=$((fails + 1)); fi
done

if [ "$fails" -eq 0 ]; then echo "all passed"; else echo "$fails failed"; exit 1; fi
