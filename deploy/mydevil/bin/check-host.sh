#!/bin/sh
# Run once over SSH on the MyDevil account, before install.sh. Prints what the account really offers,
# so the "to confirm" items in README.md and the docs page get answered. Changes nothing.
#
#   sh check-host.sh [php-binary]      (default php84)
PHP="${1:-php84}"

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
  for ext in pdo_pgsql pgsql redis intl gd imagick zip bcmath opcache sodium mbstring dom xml curl fileinfo exif pcntl posix apcu; do
    if "$PHP" -m | grep -qi "^$ext\$"; then echo "  ok       $ext"; else echo "  MISSING  $ext"; fi
  done
  "$PHP" -r 'echo "memory_limit=", ini_get("memory_limit"), " max_execution_time(cli)=", ini_get("max_execution_time"), " disable_functions=", ini_get("disable_functions"), "\n";'
  "$PHP" -r 'foreach (["proc_open","exec","shell_exec","popen","pcntl_signal"] as $f) echo "  function $f: ", function_exists($f) ? "yes" : "no", "\n";'
else
  echo "$PHP not found; try: ls /usr/local/bin | grep '^php'"
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
