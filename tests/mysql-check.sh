#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
export APP_UID="$(id -u)" APP_GID="$(id -g)"
compose=(docker compose -p "${CONNECTOR_TEST_PROJECT:-editora-connector-baseline}" -f .docker/mysql-tests.yml)
if [[ -n "$("${compose[@]}" ps --all --quiet)" ]]; then
  echo 'The dedicated MySQL test stack must be absent before this check starts.' >&2
  exit 1
fi
trap '"${compose[@]}" down --volumes' EXIT
"${compose[@]}" up -d --wait mysql
"${compose[@]}" run --rm -T tester
