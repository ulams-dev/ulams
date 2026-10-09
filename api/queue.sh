#!/bin/bash
# Long-lived queue workers for every tenant domain, see workers.sh. The platform queue itself
# is served by Horizon (single domain mode).
exec "$(cd "$(dirname "$0")" && pwd)/workers.sh" queue
