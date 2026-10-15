#!/usr/bin/env bash
# Wrapper around docker compose for this project: loads .env, derives the WordPress image tag.
# Shell environment variables (for example WP_VERSION=6.6) override values in .env.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"
[ -f .env ] || { echo "ERROR: .env is missing. Run: make init-env" >&2; exit 1; }
env_value() { grep -E "^$1=" .env | head -1 | cut -d= -f2-; }
WP_VERSION="${WP_VERSION:-$(env_value WP_VERSION)}"
WP_VERSION="${WP_VERSION:-latest}"
if [ "$WP_VERSION" = "latest" ]; then export WP_IMAGE_TAG="php8.3-apache"; else export WP_IMAGE_TAG="${WP_VERSION}-php8.3-apache"; fi
export WP_VERSION
export COMPOSE_PROJECT_NAME="${JOOWT_PROJECT:-jooservices-wp-themes}"
exec docker compose -f docker/compose.yaml --env-file .env "$@"
