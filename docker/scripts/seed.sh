#!/usr/bin/env bash
# Seeds the demo content inside the wordpress container (as www-data). Usage: seed.sh <theme> [--reset]
set -euo pipefail
cd /var/www/html
THEME="${1:?theme slug}"
shift || true
DIR="/workspace/artifacts/demo/${DEMO_SET:-${THEME}}"
[ -f "${DIR}/content.xml" ] || { echo "ERROR: ${DIR}/content.xml missing (run the generator on the host first)" >&2; exit 1; }
RESET=0
[ "${1:-}" = "--reset" ] && RESET=1
SEED_DIR="${DIR}" SEED_RESET="${RESET}" wp eval-file /scripts/seed.php
