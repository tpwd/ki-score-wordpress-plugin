#!/usr/bin/env bash
# PHP 7.4 is the plugin's minimum: lint every plugin file and run the unit tests on it.
set -euo pipefail
cd "$(dirname "$0")/.."
docker run --rm -v "$PWD":/app -w /app php:7.4-cli sh -c '
	for file in kiscore.php uninstall.php includes/*.php; do php -l "$file" || exit 1; done
	vendor/bin/phpunit --testsuite unit
'
