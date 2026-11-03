#!/bin/sh
# Run once over SSH on the MyDevil account, before install.sh. Prints what the account really offers,
# so the "to confirm" items in README.md and the docs page get answered. Changes nothing.
#
#   sh check-host.sh [php-binary]      (default php84)
#   sh check-host.sh --check-version X.Y.Z   only judge a PHP version string (no host needed; used by the tests)
#
# The survey runs to the end, then exits 1 when the PHP version is below the fixed release of its branch
# (php.net advisory 2026-10-21-5, issue #327).

# Fixed releases of php.net advisory 2026-10-21-5 (two critical unauthenticated RCE bugs, CVE-2026-109618 and
# CVE-2026-109619). Branches older than 8.2 get no fix from the PHP project.
# Prints the minimum for the branch of $1 (like 8.4.31), "none" when the branch is newer than we know, or
# "unsupported" for 8.1 and older.
min_php_for() {
  case "$1" in
    8.2.*) echo 8.2.35 ;;
    8.3.*) echo 8.3.36 ;;
    8.4.*) echo 8.4.27 ;;
    8.5.*) echo 8.5.12 ;;
    [0-7].*|8.[01].*) echo unsupported ;;
    *) echo none ;;
  esac
}

# true when version $1 >= $2, both X.Y.Z (a suffix such as -dev or RC1 is ignored)
version_ge() {
  a="$(printf '%s' "$1" | sed 's/[^0-9.].*//')"; b="$(printf '%s' "$2" | sed 's/[^0-9.].*//')"
  for i in 1 2 3; do
    x="$(printf '%s' "$a" | cut -d. -f"$i")"; y="$(printf '%s' "$b" | cut -d. -f"$i")"
    x="${x:-0}"; y="${y:-0}"
    [ "$x" -gt "$y" ] && return 0
    [ "$x" -lt "$y" ] && return 1
  done
  return 0
}

# Prints the verdict for version $1; returns 1 when it is vulnerable or not a version.
check_php_version() {
  v="$1"
  case "$v" in [0-9]*.[0-9]*.[0-9]*) ;; *) echo "  FAIL     cannot read the PHP version (got '$v')"; return 1 ;; esac
  min="$(min_php_for "$v")"
  case "$min" in
    unsupported)
      echo "  FAIL     PHP $v is no longer maintained and gets no fix for the critical RCE bugs of php.net advisory 2026-10-21-5."
      echo "           Use php84 (fixed in 8.4.27). Issue #327, https://www.php.net/archive/2026.php#2026-10-21-5"
      return 1 ;;
    none) echo "  ok       PHP $v (a branch newer than the advisory lists; check https://www.php.net/releases/ yourself)"; return 0 ;;
  esac
  if version_ge "$v" "$min"; then
    echo "  ok       PHP $v >= $min (advisory 2026-10-21-5 fixed)"
    return 0
  fi
  echo "  FAIL     PHP $v is below $min: it still has the two critical unauthenticated RCE bugs (CVE-2026-109618, CVE-2026-109619)."
  echo "           Do not expose this host. Ask MyDevil to update the PHP build and wait for $min or higher."
  echo "           Issue #327, https://www.php.net/archive/2026.php#2026-10-21-5"
  return 1
}

if [ "$1" = "--check-version" ]; then
  check_php_version "$2"
  exit $?
fi

PHP="${1:-php84}"
PHP_STATUS=0

section() { printf '\n== %s\n' "$*"; }
have() { command -v "$1" >/dev/null 2>&1; }

section "System"
uname -a
echo "user: $(id -un)  home: $HOME"
have devil && devil info 2>&1 | head -30 || echo "devil: not found (not a MyDevil shell?)"
echo "limits:"; ulimit -a 2>&1 | head -20
echo "binexec: run 'devil binexec list' or check the panel (Additional services); needed for your own binaries"

section "PHP ($PHP)"
if have "$PHP"; then
  "$PHP" -v | head -1
  PHP_VERSION="$("$PHP" -r 'echo PHP_VERSION;' 2>/dev/null)"
  echo "PHP_VERSION=$PHP_VERSION"
  check_php_version "$PHP_VERSION" || PHP_STATUS=1
  for ext in pdo_pgsql pgsql redis intl gd imagick zip bcmath opcache sodium mbstring dom xml curl fileinfo exif pcntl posix apcu; do
    if "$PHP" -m | grep -qi "^$ext\$"; then echo "  ok       $ext"; else echo "  MISSING  $ext"; fi
  done
  "$PHP" -r 'echo "memory_limit=", ini_get("memory_limit"), " max_execution_time(cli)=", ini_get("max_execution_time"), " disable_functions=", ini_get("disable_functions"), "\n";'
  "$PHP" -r 'foreach (["proc_open","exec","shell_exec","popen","pcntl_signal"] as $f) echo "  function $f: ", function_exists($f) ? "yes" : "no", "\n";'
else
  echo "$PHP not found; try: ls /usr/local/bin | grep '^php'"
  PHP_STATUS=1
fi

section "Tools"
for tool in composer git rsync curl fetch openssl screen tmux flock ffmpeg ffprobe node22 node24 npm22 redis-server valkey-server memcached psql pg_dump gzip tar; do
  if have "$tool"; then echo "  ok       $tool  ($(command -v "$tool"))"; else echo "  missing  $tool"; fi
done
have node22 && node22 -v
have ffmpeg && ffmpeg -version | head -1

section "Network (outbound)"
for url in https://api.anthropic.com https://registry.npmjs.org https://www.cloudflarestatus.com; do
  if have curl; then code="$(curl -s -o /dev/null -m 10 -w '%{http_code}' "$url" 2>&1)"; else code="$(fetch -q -T 10 -o /dev/null "$url" 2>&1 && echo ok)"; fi
  echo "  $url -> $code"
done
echo "  R2: run 'curl -sI https://<account id>.r2.cloudflarestorage.com' with your account id"

section "PostgreSQL"
echo "Your databases: devil pgsql list"
have devil && devil pgsql list 2>&1 | head -20
echo "Server version: psql -h pgsqlN.mydevil.net -U <user> <db> -c 'select version()'"
echo "Extensions you may enable per database: devil pgsql extensions <db> <name> (wiki list: pg_trgm, unaccent, pgcrypto, uuid-ossp, vector, ...)"

section "Ports and processes"
have devil && devil port list 2>&1 | head
echo "Processes of this account: ps -U $(id -un) | wc -l"
ps -U "$(id -un)" 2>/dev/null | wc -l

section "Result"
if [ "$PHP_STATUS" -ne 0 ]; then
  echo "FAIL: the PHP check above did not pass (issue #327). Fix it before this host serves requests."
  exit 1
fi
echo "PHP check passed."
