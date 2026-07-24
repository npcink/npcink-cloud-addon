#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
PLAYGROUND_CLI_VERSION="${NPCINK_PLAYGROUND_CLI_VERSION:-3.1.43}"
PLAYGROUND_WP_VERSION="${NPCINK_PLAYGROUND_WP_VERSION:-7.0.2}"
PLAYGROUND_PHP_VERSION="${NPCINK_PLAYGROUND_PHP_VERSION:-8.2}"
PLAYGROUND_PORT="${NPCINK_PLAYGROUND_PORT:-9417}"
BLUEPRINT="${ROOT_DIR}/tests/playground/blueprint.json"
MU_PLUGIN_DIR="${ROOT_DIR}/tests/playground/mu-plugins"
RESULT_PATH="/wp-json/npcink-cloud-addon-playground/v1/smoke"
TEMP_DIR=""
SERVER_PID=""

fail() {
	echo "[fail] $*" >&2
	exit 1
}

cleanup() {
	local status=$?
	if [ -n "${SERVER_PID}" ] && kill -0 "${SERVER_PID}" 2>/dev/null; then
		kill "${SERVER_PID}" 2>/dev/null || true
		wait "${SERVER_PID}" 2>/dev/null || true
	fi
	if [ -n "${TEMP_DIR}" ] && [ -d "${TEMP_DIR}" ]; then
		rm -rf "${TEMP_DIR}"
	fi
	exit "${status}"
}
trap cleanup EXIT INT TERM

case "${PLAYGROUND_PORT}" in
	*[!0-9]* | '') fail 'NPCINK_PLAYGROUND_PORT must be a TCP port number.' ;;
esac

if [ "${PLAYGROUND_PORT}" -lt 1024 ] || [ "${PLAYGROUND_PORT}" -gt 65535 ]; then
	fail 'NPCINK_PLAYGROUND_PORT must be between 1024 and 65535.'
fi

command -v node >/dev/null 2>&1 || fail 'Node.js 20.18 or later is required for WordPress Playground CLI.'
command -v npx >/dev/null 2>&1 || fail 'npx is required for WordPress Playground CLI.'
command -v curl >/dev/null 2>&1 || fail 'curl is required for Playground smoke verification.'

NODE_VERSION="$(node -p 'process.versions.node')"
if ! node -e '
const [major, minor] = process.versions.node.split(".").map(Number);
process.exit(major > 20 || (major === 20 && minor >= 18) ? 0 : 1);
'; then
	fail "Node.js 20.18 or later is required; found v${NODE_VERSION}."
fi

if [ ! -f "${BLUEPRINT}" ] || [ ! -d "${MU_PLUGIN_DIR}" ]; then
	fail 'Playground Blueprint or ephemeral MU-plugin fixture is missing.'
fi

if lsof -nP -iTCP:"${PLAYGROUND_PORT}" -sTCP:LISTEN >/dev/null 2>&1; then
	fail "Port ${PLAYGROUND_PORT} is already in use; choose NPCINK_PLAYGROUND_PORT=<free-port>."
fi

TEMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/npcink-cloud-addon-playground.XXXXXX")"
SERVER_LOG="${TEMP_DIR}/server.log"
RESULT_JSON="${TEMP_DIR}/result.json"

echo "== WordPress Playground smoke (CLI ${PLAYGROUND_CLI_VERSION}; WP ${PLAYGROUND_WP_VERSION}; PHP ${PLAYGROUND_PHP_VERSION}) =="
npx --yes "@wp-playground/cli@${PLAYGROUND_CLI_VERSION}" server \
	--port="${PLAYGROUND_PORT}" \
	--site-url="http://127.0.0.1:${PLAYGROUND_PORT}" \
	--wp="${PLAYGROUND_WP_VERSION}" \
	--php="${PLAYGROUND_PHP_VERSION}" \
	--mount-before-install="${ROOT_DIR}:/wordpress/wp-content/plugins/npcink-cloud-addon" \
	--mount-before-install="${MU_PLUGIN_DIR}:/wordpress/wp-content/mu-plugins" \
	--blueprint="${BLUEPRINT}" \
	--verbosity=normal >"${SERVER_LOG}" 2>&1 &
SERVER_PID=$!

for _ in $(seq 1 90); do
	if curl --location --fail --silent --show-error "http://127.0.0.1:${PLAYGROUND_PORT}${RESULT_PATH}" >"${RESULT_JSON}" 2>/dev/null; then
		break
	fi
	if ! kill -0 "${SERVER_PID}" 2>/dev/null; then
		cat "${SERVER_LOG}" >&2
		fail 'WordPress Playground stopped before the smoke route became available.'
	fi
	sleep 1
done

if [ ! -s "${RESULT_JSON}" ]; then
	cat "${SERVER_LOG}" >&2
	fail 'Timed out waiting for the Playground smoke route.'
fi

node -e '
const fs = require("fs");
const result = JSON.parse(fs.readFileSync(process.argv[1], "utf8"));
const expected = {
  plugin_active: true,
  public_api_present: true,
  configured: false,
  runtime_client_available: false,
  verified_runtime_client_available: false,
  connector_marker_present: false,
  write_posture: "connector_only_no_direct_wordpress_write",
};
for (const [key, value] of Object.entries(expected)) {
  if (result[key] !== value) {
    throw new Error(`Unexpected ${key}: ${JSON.stringify(result[key])}`);
  }
}
console.log("[ok] Playground activated the addon and preserved its default fail-closed connector boundary.");
' "${RESULT_JSON}"
