#!/usr/bin/env bash
#
# Run Composer for this repository: the host's Composer if it is installed
# (with the PHP extensions PHPCS needs), otherwise the official Docker image.
#
# Usage: tools/composer.sh <composer arguments>, e.g. tools/composer.sh lint

set -euo pipefail

cd "$(dirname "$0")/.."

if command -v composer >/dev/null 2>&1 && php -m | grep -qi '^xmlwriter$'; then
	exec composer "$@"
fi

exec docker run --rm --interactive \
	--volume "$PWD":/app --workdir /app \
	--user "$(id -u):$(id -g)" \
	--env COMPOSER_HOME=/tmp/composer \
	composer:2 "$@"
