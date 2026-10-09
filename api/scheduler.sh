#!/bin/bash
# Long-lived scheduler per domain (platform unless MULTI_DOMAINS, plus every tenant), see
# workers.sh and `ulams:tenant:schedule-loop`.
exec "$(cd "$(dirname "$0")" && pwd)/workers.sh" scheduler
