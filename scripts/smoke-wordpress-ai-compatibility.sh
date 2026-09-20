#!/usr/bin/env bash
set -euo pipefail

# Runs a bounded, no-credential WordPress AI compatibility lane in Playground.
# Stable lanes block; upstream develop is deliberately warning-only.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
MATRIX_PATH="${ROOT_DIR}/tests/fixtures/wp-ai-compatibility-matrix.json"
LANE="${1:-${WP_AI_COMPAT_LANE:-}}"
PLAYGROUND_CLI_VERSION="${NPCINK_PLAYGROUND_CLI_VERSION:-3.1.43}"
PLAYGROUND_PORT="${NPCINK_COMPAT_PLAYGROUND_PORT:-9427}"
TEMP_DIR=""
SERVER_PID=""

fail() { echo "[fail] $*" >&2; exit 1; }
warn() { echo "[warning] $*" >&2; }

cleanup() {
	local status=$?
	if [ -n "${SERVER_PID}" ] && kill -0 "${SERVER_PID}" 2>/dev/null; then
		kill "${SERVER_PID}" 2>/dev/null || true
		wait "${SERVER_PID}" 2>/dev/null || true
	fi
	if [ "${NPCINK_COMPAT_KEEP_ARTIFACTS:-0}" = '1' ]; then
		echo "[debug] keeping compatibility artifacts at ${TEMP_DIR}" >&2
	elif [ -n "${TEMP_DIR}" ] && [ -d "${TEMP_DIR}" ]; then
		rm -rf "${TEMP_DIR}"
	fi
	exit "${status}"
}
trap cleanup EXIT INT TERM

[ -n "${LANE}" ] || fail 'Pass a lane id, for example stable-primary.'
[ -f "${MATRIX_PATH}" ] || fail 'Compatibility matrix fixture is missing.'
command -v node >/dev/null 2>&1 || fail 'Node.js is required.'
command -v npx >/dev/null 2>&1 || fail 'npx is required.'
command -v curl >/dev/null 2>&1 || fail 'curl is required.'
command -v unzip >/dev/null 2>&1 || fail 'unzip is required.'

MATRIX_VALUES="$(node - "${MATRIX_PATH}" "${LANE}" <<'NODE'
const fs = require('fs');
const [matrixPath, laneId] = process.argv.slice(2);
const matrix = JSON.parse(fs.readFileSync(matrixPath, 'utf8'));
const lane = matrix.lanes.find((candidate) => candidate.lane_id === laneId);
if (!lane || !lane.wordpress || !Array.isArray(lane.wordpress_ai_versions) || lane.wordpress_ai_versions.length !== 1
	|| !Array.isArray(lane.wordpress_ai_urls) || lane.wordpress_ai_urls.length !== 1 || !Array.isArray(lane.php_versions)
	|| lane.php_versions.length < 1 || !['blocking', 'non_blocking_warning'].includes(lane.gate)) {
	throw new Error(`Invalid or unknown compatibility lane: ${laneId}`);
}
process.stdout.write([
	lane.wordpress,
	lane.wordpress_ai_versions[0],
	lane.wordpress_ai_urls[0],
	lane.php_versions.join(','),
	lane.gate,
].join('\t'));
NODE
)"
IFS=$'\t' read -r WP_VERSION AI_VERSION AI_URL SUPPORTED_PHP_VERSIONS GATE <<<"${MATRIX_VALUES}"
REQUESTED_WP_VERSION="${NPCINK_COMPAT_WORDPRESS_VERSION:-}"
REQUESTED_AI_VERSION="${NPCINK_COMPAT_AI_VERSION:-}"
REQUESTED_PHP_VERSION="${NPCINK_COMPAT_PHP_VERSION:-}"
if [ -n "${REQUESTED_WP_VERSION}" ] && [ "${REQUESTED_WP_VERSION}" != "${WP_VERSION}" ]; then
	fail "Requested WordPress ${REQUESTED_WP_VERSION} does not match lane ${LANE} (${WP_VERSION})."
fi
if [ -n "${REQUESTED_AI_VERSION}" ] && [ "${REQUESTED_AI_VERSION}" != "${AI_VERSION}" ]; then
	fail "Requested WordPress AI ${REQUESTED_AI_VERSION} does not match lane ${LANE} (${AI_VERSION})."
fi
PHP_VERSION="${REQUESTED_PHP_VERSION:-${SUPPORTED_PHP_VERSIONS%%,*}}"
case ",${SUPPORTED_PHP_VERSIONS}," in
	*,"${PHP_VERSION}",*) : ;;
	*) fail "PHP ${PHP_VERSION} is not declared for lane ${LANE} (${SUPPORTED_PHP_VERSIONS})." ;;
esac

case "${PLAYGROUND_PORT}" in
	*[!0-9]* | '') fail 'NPCINK_COMPAT_PLAYGROUND_PORT must be a TCP port number.' ;;
esac
if [ "${PLAYGROUND_PORT}" -lt 1024 ] || [ "${PLAYGROUND_PORT}" -gt 65535 ]; then
	fail 'NPCINK_COMPAT_PLAYGROUND_PORT must be between 1024 and 65535.'
fi
if command -v lsof >/dev/null 2>&1 && lsof -nP -iTCP:"${PLAYGROUND_PORT}" -sTCP:LISTEN >/dev/null 2>&1; then
	fail "Port ${PLAYGROUND_PORT} is already in use; choose NPCINK_COMPAT_PLAYGROUND_PORT=<free-port>."
fi

TEMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/npcink-cloud-addon-compatibility.XXXXXX")"
BLUEPRINT_PATH="${TEMP_DIR}/blueprint.json"
SERVER_LOG="${TEMP_DIR}/server.log"
RESULT_JSON="${TEMP_DIR}/compatibility.json"
WP_URL="${NPCINK_COMPAT_WORDPRESS_URL:-https://wordpress.org/wordpress-${WP_VERSION}.zip}"
PLAYGROUND_CACHE_ROOT="${HOME}/.wordpress-playground"
PLAYGROUND_WP_CACHE_KEY="$(node -e 'process.stdout.write("custom-" + require("crypto").createHash("sha1").update(process.argv[1]).digest("hex").slice(0, 8))' "${WP_URL}")"
PLAYGROUND_WP_CACHE_FILE="${PLAYGROUND_CACHE_ROOT}/${PLAYGROUND_WP_CACHE_KEY}.zip"
mkdir -p "${PLAYGROUND_CACHE_ROOT}"
if [ ! -f "${PLAYGROUND_WP_CACHE_FILE}" ] || ! unzip -tqq "${PLAYGROUND_WP_CACHE_FILE}" >/dev/null 2>&1; then
	echo "[prepare] Downloading WordPress ${WP_VERSION} into the persistent Playground cache."
	curl --location --fail --silent --show-error --retry 4 --retry-all-errors --connect-timeout 15 --max-time 180 "${WP_URL}" --output "${PLAYGROUND_WP_CACHE_FILE}.partial"
	unzip -tqq "${PLAYGROUND_WP_CACHE_FILE}.partial" >/dev/null 2>&1 || fail "WordPress ${WP_VERSION} archive failed ZIP verification."
	mv "${PLAYGROUND_WP_CACHE_FILE}.partial" "${PLAYGROUND_WP_CACHE_FILE}"
fi

AI_CACHE_KEY="ai-${AI_VERSION//[^A-Za-z0-9._-]/-}"
AI_CACHE_FILE="${PLAYGROUND_CACHE_ROOT}/${AI_CACHE_KEY}.zip"
AI_ZIP="${TEMP_DIR}/ai.zip"
AI_UNPACKED="${TEMP_DIR}/ai-unpacked"
if [ ! -f "${AI_CACHE_FILE}" ] || ! unzip -tqq "${AI_CACHE_FILE}" >/dev/null 2>&1; then
	curl --location --fail --silent --show-error --retry 4 --retry-all-errors --connect-timeout 15 --max-time 180 -H 'Accept: application/octet-stream' "${AI_URL}" --output "${AI_CACHE_FILE}.partial" || {
		if [ "${GATE}" = 'non_blocking_warning' ]; then
			warn "Could not download WordPress AI ${AI_VERSION}; upstream lane remains warning-only."
			exit 0
		fi
		fail "Could not download blocking WordPress AI ${AI_VERSION}."
	}
	unzip -tqq "${AI_CACHE_FILE}.partial" >/dev/null 2>&1 || fail "WordPress AI ${AI_VERSION} archive failed ZIP verification."
	mv "${AI_CACHE_FILE}.partial" "${AI_CACHE_FILE}"
fi
cp "${AI_CACHE_FILE}" "${AI_ZIP}"
unzip -q "${AI_ZIP}" -d "${AI_UNPACKED}" || {
	if [ "${GATE}" = 'non_blocking_warning' ]; then
		warn "WordPress AI ${AI_VERSION} archive is not installable; upstream lane remains warning-only."
		exit 0
	fi
	fail "WordPress AI ${AI_VERSION} archive is not installable."
}
AI_MAIN_FILE="$(find "${AI_UNPACKED}" -mindepth 1 -maxdepth 3 -type f -name ai.php -print -quit)"
if [ -z "${AI_MAIN_FILE}" ]; then
	if [ "${GATE}" = 'non_blocking_warning' ]; then
		warn "WordPress AI ${AI_VERSION} archive has no ai.php; upstream lane remains warning-only."
		exit 0
	fi
	fail "WordPress AI ${AI_VERSION} archive has no ai.php."
fi
AI_ROOT="$(dirname "${AI_MAIN_FILE}")"

node - "${BLUEPRINT_PATH}" "${WP_VERSION}" "${PHP_VERSION}" <<'NODE'
const fs = require('fs');
const [outputPath, wp, php] = process.argv.slice(2);
const blueprint = {
	$schema: 'https://playground.wordpress.net/blueprint-schema.json',
	preferredVersions: { wp, php },
	steps: [
		{ step: 'activatePlugin', pluginPath: '/wordpress/wp-content/plugins/npcink-cloud-addon/npcink-cloud-addon.php' },
		{ step: 'activatePlugin', pluginPath: '/wordpress/wp-content/plugins/ai/ai.php' },
	],
};
fs.writeFileSync(outputPath, JSON.stringify(blueprint, null, 2));
NODE

echo "== WordPress AI compatibility lane ${LANE} (WP ${WP_VERSION}; AI ${AI_VERSION}; PHP ${PHP_VERSION}) =="
npx --yes "@wp-playground/cli@${PLAYGROUND_CLI_VERSION}" server \
	--port="${PLAYGROUND_PORT}" \
	--site-url="http://127.0.0.1:${PLAYGROUND_PORT}" \
	--wp="${WP_URL}" \
	--php="${PHP_VERSION}" \
	--mount-before-install="${AI_ROOT}:/wordpress/wp-content/plugins/ai" \
	--mount-before-install="${ROOT_DIR}:/wordpress/wp-content/plugins/npcink-cloud-addon" \
	--mount-before-install="${ROOT_DIR}/tests/playground/mu-plugins:/wordpress/wp-content/mu-plugins" \
	--blueprint="${BLUEPRINT_PATH}" \
	--verbosity=normal >"${SERVER_LOG}" 2>&1 &
SERVER_PID=$!

for _ in $(seq 1 120); do
	if curl --location --fail --silent --show-error "http://127.0.0.1:${PLAYGROUND_PORT}/wp-json/npcink-cloud-addon-playground/v1/compatibility" >"${RESULT_JSON}" 2>/dev/null; then
		break
	fi
	if ! kill -0 "${SERVER_PID}" 2>/dev/null; then
		wait "${SERVER_PID}" 2>/dev/null || true
		SERVER_PID=""
		break
	fi
	sleep 1
done

if [ ! -s "${RESULT_JSON}" ]; then
	if [ "${GATE}" = 'non_blocking_warning' ]; then
		warn "Lane ${LANE} did not reach its compatibility route; upstream lane remains warning-only."
		cat "${SERVER_LOG}" >&2 || true
		exit 0
	fi
	cat "${SERVER_LOG}" >&2 || true
	fail "Blocking lane ${LANE} did not reach its compatibility route."
fi

if ! node - "${RESULT_JSON}" "${LANE}" "${WP_VERSION}" "${AI_VERSION}" "${PHP_VERSION}" "${GATE}" <<'NODE'
const fs = require('fs');
const [resultPath, lane, expectedWp, expectedAi, expectedPhp, gate] = process.argv.slice(2);
const result = JSON.parse(fs.readFileSync(resultPath, 'utf8'));
const errors = [];
if (result.wordpress_version !== expectedWp) errors.push(`WordPress ${result.wordpress_version} != ${expectedWp}`);
if (result.php_version !== expectedPhp) errors.push(`PHP ${result.php_version} != ${expectedPhp}`);
if (expectedAi !== 'develop' && result.wordpress_ai_version !== expectedAi) errors.push(`WordPress AI ${result.wordpress_ai_version} != ${expectedAi}`);
if (!result.wordpress_ai_active) errors.push('WordPress AI is not active');
if (!result.addon_active) errors.push('Cloud Addon is not active');
if (!result.abilities_api_present) errors.push('Abilities API is not present');
if (!result.connector_runtime_present) errors.push('Cloud connector runtime is not present');
if (!Array.isArray(result.active_plugin_names) || !result.active_plugin_names.includes('ai/ai.php')) errors.push('ai/ai.php is not active');
if (!Array.isArray(result.active_plugin_names) || !result.active_plugin_names.includes('npcink-cloud-addon/npcink-cloud-addon.php')) errors.push('npcink-cloud-addon is not active');
if (errors.length > 0) {
	console.error(JSON.stringify({ lane, errors, result }, null, 2));
	process.exit(gate === 'non_blocking_warning' ? 0 : 1);
}
console.log(JSON.stringify({ lane, wordpress_version: result.wordpress_version, wordpress_ai_version: result.wordpress_ai_version, php_version: result.php_version, addon_active: result.addon_active, abilities_api_present: result.abilities_api_present, connector_runtime_present: result.connector_runtime_present }));
NODE
then
	if [ "${GATE}" = 'non_blocking_warning' ]; then
		warn "Lane ${LANE} completed with upstream compatibility warnings."
	else
		exit 1
	fi
fi

echo "PASS: ${LANE} compatibility discovery and Addon boundary checks passed."
