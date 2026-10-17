#!/usr/bin/env bash
# Seeds the demo content (shared PHP CLI container, in the site folder). Usage: seed.sh <theme> [--reset]
set -euo pipefail
cd "${JOO_SITE_DIR:?}"
THEME="${1:?theme slug}"
shift || true
DIR="${JOO_ROOT:?}/artifacts/demo/${DEMO_SET:-${THEME}}"
[ -f "${DIR}/content.xml" ] || { echo "ERROR: ${DIR}/content.xml missing (run the generator on the host first)" >&2; exit 1; }
RESET=0
[ "${1:-}" = "--reset" ] && RESET=1
SEED_DIR="${DIR}" SEED_RESET="${RESET}" wp eval-file "$JOO_ROOT/docker/scripts/seed.php"
