#!/usr/bin/env bash
# Origin mode only (compose.origin.yml): lets port 443 accept connections from Cloudflare's address
# ranges and drops everyone else, in ufw and in the DOCKER-USER chain (Docker publishes ports around
# ufw, so ufw rules alone do not protect a published container port). Run as root; safe to re-run, e.g.
# from cron or systemd/ulams-firewall.service when Cloudflare changes its ranges
# (https://www.cloudflare.com/ips/). Not needed with the tunnel: nothing listens publicly.
set -euo pipefail

[[ $EUID -eq 0 ]] || { echo "run as root" >&2; exit 1; }
command -v iptables >/dev/null || { echo "iptables is required" >&2; exit 1; }

fetch() { curl -fsS --max-time 20 "https://www.cloudflare.com/ips-v$1" 2>/dev/null || true; }
v4="$(fetch 4)"
v6="$(fetch 6)"
# Fallback: the ranges published on 2026-10-09
[[ -n "$v4" ]] || v4="173.245.48.0/20 103.21.244.0/22 103.22.200.0/22 103.31.4.0/22 141.101.64.0/18 108.162.192.0/18 190.93.240.0/20 188.114.96.0/20 197.234.240.0/22 198.41.128.0/17 162.158.0.0/15 104.16.0.0/13 104.24.0.0/14 172.64.0.0/13 131.0.72.0/22"
[[ -n "$v6" ]] || v6="2400:cb00::/32 2606:4700::/32 2803:f800::/32 2405:b500::/32 2405:8100::/32 2a06:98c0::/29 2c0f:f248::/32"

apply() { # TOOL RANGES
  local tool="$1" ranges="$2" r
  "$tool" -N ULAMS-CF 2>/dev/null || true
  "$tool" -F ULAMS-CF
  for r in $ranges; do "$tool" -A ULAMS-CF -s "$r" -j RETURN; done
  "$tool" -A ULAMS-CF -j DROP
  "$tool" -N DOCKER-USER 2>/dev/null || true
  # published port 443 (the original destination port, before Docker's DNAT)
  "$tool" -C DOCKER-USER -p tcp -m conntrack --ctorigdstport 443 --ctdir ORIGINAL -j ULAMS-CF 2>/dev/null \
    || "$tool" -I DOCKER-USER -p tcp -m conntrack --ctorigdstport 443 --ctdir ORIGINAL -j ULAMS-CF
}
apply iptables "$v4"
command -v ip6tables >/dev/null && [[ -n "$v6" ]] && apply ip6tables "$v6" || true

if command -v ufw >/dev/null; then
  for r in $v4 $v6; do ufw allow from "$r" to any port 443 proto tcp >/dev/null; done
fi
echo "443/tcp now accepts Cloudflare ranges only ($(wc -w <<<"$v4") IPv4, $(wc -w <<<"$v6") IPv6)."
