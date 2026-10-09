#!/bin/bash
# Long-lived broadcast queue workers for every tenant domain, see workers.sh.
exec "$(cd "$(dirname "$0")" && pwd)/workers.sh" broadcast
