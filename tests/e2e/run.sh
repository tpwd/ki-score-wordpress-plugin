#!/usr/bin/env bash
# Starts the local WordPress stack, installs the plugin and runs the e2e suite.
# The stack keeps running for the next run; remove it with:
#   docker compose -f tests/e2e/docker-compose.yml down -v
set -euo pipefail
cd "$(dirname "$0")"

docker compose up -d --wait db collector apache fpm nginx

wp() { docker compose run --rm -T cli wp "$@"; }

for _ in $(seq 1 60); do
	curl -fs -o /dev/null "http://localhost:${KISCORE_E2E_APACHE_PORT:-8091}/wp-admin/install.php" && break
	sleep 1
done

if ! wp core is-installed 2>/dev/null; then
	wp core install --url="http://localhost:${KISCORE_E2E_APACHE_PORT:-8091}" --title="KIscore E2E" \
		--admin_user=e2e-admin --admin_password=e2e-admin-password \
		--admin_email=e2e@example.test --skip-email
fi
wp rewrite structure '/%postname%/'
wp plugin activate kiscore

cd ../..
vendor/bin/phpunit --testsuite e2e "$@"
