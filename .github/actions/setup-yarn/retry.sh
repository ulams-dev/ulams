#!/usr/bin/env bash
# Usage: retry.sh <label> <command...>
# Runs the command up to RETRY_ATTEMPTS times (default 4), sleeping RETRY_BASE_SLEEP
# seconds (default 10) times the attempt number between tries.
set -u
label="$1"
shift
attempts="${RETRY_ATTEMPTS:-4}"
base="${RETRY_BASE_SLEEP:-10}"
for ((i = 1; i <= attempts; i++)); do
  if "$@"; then
    exit 0
  fi
  if ((i < attempts)); then
    wait=$((base * i))
    echo "::warning::${label} failed (attempt ${i}/${attempts}); retrying in ${wait}s (registry.npmjs.org may be unavailable)"
    sleep "$wait"
  fi
done
echo "::error::${label} failed after ${attempts} attempts; registry.npmjs.org is likely down, re-run the job later"
exit 1
