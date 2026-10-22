#!/bin/sh
# Offline test of bin/check-images.sh (fake curl, no network) and of the guards in cloudflare/landing-static.mjs:
#   sh deploy/mydevil/test/check-images.sh
HERE="$(cd "$(dirname "$0")" && pwd)"
SCRIPT="$HERE/../bin/check-images.sh"
EXPORT="$HERE/../cloudflare/landing-static.mjs"
fails=0
tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT

# Fake curl: the last argument is the URL; MODE picks what the "host" answers.
cat > "$tmp/curl" <<'EOS'
#!/bin/sh
for last; do :; done
url="$last"
case "$url" in
  */cdn-cgi/image/*onerror=redirect/*)
    case "$MODE" in
      noredirect) printf 'HTTP/2 404\r\ncontent-type: text/plain\r\n\r\n' ;;
      *) printf 'HTTP/2 302\r\nLocation: %s\r\n\r\n' "${url#*onerror=redirect/}" ;;
    esac ;;
  */cdn-cgi/image/*)
    case "$MODE" in
      off) printf 'HTTP/2 404\r\ncontent-type: text/html\r\n\r\n' ;;
      original) printf 'HTTP/2 200\r\ncontent-type: image/jpeg\r\n\r\n' ;;
      nocfresized) printf 'HTTP/2 200\r\nContent-Type: image/webp\r\n\r\n' ;;
      *) printf 'HTTP/2 200\r\nContent-Type: image/avif\r\nCF-Resized: internal=ok\r\n\r\n' ;;
    esac ;;
  https://nopage.example/) printf '<html><p>no images</p></html>' ;;
  https://rel.example/) printf '<img src="/landing/hero.png">' ;;
  https://*/) printf '<img srcset="/cdn-cgi/image/width=640/https://storage.ulams.app/coffee/cover.jpg 640w"><img src="https://storage.ulams.app/coffee/other.png">' ;;
esac
EOS
chmod +x "$tmp/curl"

run() { # <mode> <args...> -> sets out, code
  mode="$1"; shift
  out="$(MODE="$mode" CURL_BIN="$tmp/curl" sh "$SCRIPT" "$@" 2>&1)"; code=$?
}
expect() { # <label> <exit> <mode> <args...>
  label="$1"; want="$2"; shift 2
  run "$@"
  if [ "$code" -eq "$want" ]; then echo "ok    $label -> $code"; else echo "FAIL  $label -> $code, wanted $want"; echo "$out"; fails=$((fails + 1)); fi
}
contains() { # <label> <text>
  case "$out" in *"$2"*) echo "ok    $1" ;; *) echo "FAIL  $1: '$2' missing"; echo "$out"; fails=$((fails + 1)) ;; esac
}

expect "all good, source discovered" 0 good coffee.ulams.app
contains "discovers the absolute source, not the cdn-cgi one" "source https://storage.ulams.app/coffee/cover.jpg"
expect "explicit source and url host" 0 good https://coffee.ulams.app/learn/1 https://storage.ulams.app/coffee/x.jpg
expect "relative source on the host" 0 good rel.example
contains "relative source becomes absolute" "source https://rel.example/landing/hero.png"
expect "transformations off" 1 off coffee.ulams.app
contains "off explains error 9524" "9524"
expect "original served untouched" 1 original coffee.ulams.app
expect "no cf-resized header" 1 nocfresized coffee.ulams.app
expect "no onerror redirect" 1 noredirect coffee.ulams.app
expect "no image on the page" 1 good nopage.example
expect "no arguments" 2 good

# landing-static.mjs refuses a build whose image mode is not cloudflare or noop (usage and host checks come first)
if command -v node >/dev/null 2>&1; then
  admin='https://{slug}-admin.ulams.app'
  jsrun() { # <label> <want> <image service> <admin url> <message>
    want="$2"
    o="$(env -u ULAMS_IMAGE_SERVICE -u ULAMS_ADMIN_URL ${3:+ULAMS_IMAGE_SERVICE="$3"} ${4:+ULAMS_ADMIN_URL="$4"} node "$EXPORT" "$tmp/never-created" 2>&1)"; c=$?
    if [ "$c" -ne 0 ] && [ "$want" = refuse ]; then
      case "$o" in *"$5"*) echo "ok    $1" ;; *) echo "FAIL  $1: wrong message: $o"; fails=$((fails + 1)) ;; esac
    else echo "FAIL  $1 (exit $c)"; fails=$((fails + 1)); fi
  }
  jsrun "export refuses an unset image mode" refuse "" "$admin" ULAMS_IMAGE_SERVICE
  jsrun "export refuses astro mode" refuse astro "$admin" ULAMS_IMAGE_SERVICE
  jsrun "export refuses an unset admin URL" refuse cloudflare "" ULAMS_ADMIN_URL
  jsrun "export refuses an admin URL without {slug}" refuse cloudflare "http://admin.localhost" ULAMS_ADMIN_URL
else
  echo "skip  landing-static.mjs guards (node not found)"
fi

if [ "$fails" -eq 0 ]; then echo "all passed"; else echo "$fails failed"; exit 1; fi
