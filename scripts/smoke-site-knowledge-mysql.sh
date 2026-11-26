#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"

command -v docker >/dev/null 2>&1 || { echo '[fail] Docker is required for the disposable MySQL smoke.' >&2; exit 1; }
php -r 'exit(extension_loaded("mysqli") ? 0 : 1);' || { echo '[fail] Native PHP mysqli is required.' >&2; exit 1; }

TEMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/npcink-reconciliation-mysql.XXXXXX")"
CONTAINER_ID=""
cleanup() {
	local status=$?
	if [ -n "${CONTAINER_ID}" ] && ! docker rm -fv "${CONTAINER_ID}" >/dev/null; then
		status=1
	fi
	rm -rf "${TEMP_DIR}" || status=1
	exit "${status}"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

# The database is created for this run. There is no existing site or DB input.
DB_PASSWORD="$(php -r 'echo bin2hex(random_bytes(16));')"
CONTAINER_ID="$(docker run --detach --publish 127.0.0.1::3306 \
	--env MYSQL_RANDOM_ROOT_PASSWORD=yes --env MYSQL_DATABASE=reconciliation \
	--env MYSQL_USER=fixture --env MYSQL_PASSWORD="${DB_PASSWORD}" mysql:8.4)"
DB_ADDRESS="$(docker port "${CONTAINER_ID}" 3306/tcp)"
case "${DB_ADDRESS}" in
	127.0.0.1:[0-9]*) ;;
	*) echo '[fail] The disposable MySQL container has no loopback port.' >&2; exit 1 ;;
esac

cd "${TEMP_DIR}"
export WP_CLI_CONFIG_PATH=/dev/null
wp_fixture() {
	php "${ROOT_DIR}/scripts/run-wp-cli.php" --path="${TEMP_DIR}" --skip-packages "$@"
}

wp_fixture core download --version=7.0.4 --skip-content

for attempt in $(seq 1 45); do
	if docker exec --env MYSQL_PWD="${DB_PASSWORD}" "${CONTAINER_ID}" \
		mysql --protocol=TCP --host=127.0.0.1 --user=fixture --database=reconciliation --execute='SELECT 1' >/dev/null 2>&1; then
		break
	fi
	if [ "${attempt}" -eq 45 ]; then
		echo '[fail] The disposable MySQL database did not become ready.' >&2
		exit 1
	fi
	sleep 1
done

wp_fixture config create --dbname=reconciliation --dbuser=fixture --dbpass="${DB_PASSWORD}" \
	--dbhost="${DB_ADDRESS}" --skip-salts --extra-php <<'PHP'
define( 'NPCINK_RECONCILIATION_SMOKE', true );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'DISABLE_WP_CRON', true );
PHP
wp_fixture core install --url=http://npcink-reconciliation.test --title='Reconciliation fixture' \
	--admin_user=fixture --admin_password=fixture-only --admin_email=fixture@example.test --skip-email
mkdir -p "${TEMP_DIR}/wp-content/plugins"
ln -s "${ROOT_DIR}" "${TEMP_DIR}/wp-content/plugins/npcink-cloud-addon"
wp_fixture plugin activate npcink-cloud-addon
wp_fixture eval 'require WP_PLUGIN_DIR . "/npcink-cloud-addon/tests/integration/site-knowledge-mysql.php";'
