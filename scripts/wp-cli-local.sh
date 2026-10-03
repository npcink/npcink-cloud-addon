#!/bin/sh
# Shared local WordPress CLI runner for Local by Flywheel development sites.
#
# Resolution order for every variable:
#   1. an inherited environment value (WP_PATH, WP_CLI_BIN, WP_CLI_PHP, WP_DB_SOCKET, ...)
#   2. per-developer defaults from scripts/.local-env (gitignored; see .local-env.example)
#   3. deterministic discovery under $HOME when exactly one candidate exists
#
# No developer-specific absolute path is baked into this script or composer.json;
# ambiguous or missing values fail with guidance instead of guessing.
#
# Usage: sh scripts/wp-cli-local.sh [--require VAR]... <eval-file-script.php> [wp-cli args...]
set -eu

script_root=$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd)
# Apply scripts/.local-env only to variables the environment has not already
# set, so inherited values keep winning as documented. The per-line parse also
# accepts quoted values the way scripts/local-env.php and the .mjs helper do.
if [ -f "$script_root/.local-env" ]; then
	while IFS= read -r local_env_line || [ -n "$local_env_line" ]; do
		case "$local_env_line" in ''|'#'*) continue ;; esac
		local_env_key=${local_env_line%%=*}
		[ "$local_env_key" = "$local_env_line" ] && continue
		case "$local_env_key" in ''|*[!A-Za-z0-9_]*) continue ;; esac
		local_env_present=$(eval "printf '%s' \"\${$local_env_key+x}\"")
		if [ "$local_env_present" = '' ]; then
			eval "$local_env_line"
			export "$local_env_key"
		fi
	done < "$script_root/.local-env"
fi

fail() {
	echo "wp-cli-local: $1" >&2
	echo "wp-cli-local: set it in your environment or scripts/.local-env (copy scripts/.local-env.example)." >&2
	exit 1
}

required=''
while [ $# -gt 0 ]; do
	case "$1" in
		--require)
			required="$required $2"
			shift 2
			;;
		--) shift; break ;;
		*) break ;;
	esac
done
[ $# -ge 1 ] || fail 'usage: wp-cli-local.sh [--require VAR]... <eval-file-script.php>'

# Prints the sole non-empty line of stdin, or nothing when there are zero or several.
# Buffers stdin and neutralizes grep's no-match exit status so `set -e`
# cannot kill the caller before its guidance message runs.
single_line() {
	_input=$(cat) || return 0
	[ -n "$_input" ] || return 0
	_count=$(printf '%s\n' "$_input" | grep -c '^') || _count=0
	[ "$_count" -eq 1 ] && printf '%s\n' "$_input"
	return 0
}

if [ "${WP_PATH:-}" = '' ]; then
	WP_PATH=$(find "$HOME/Local Sites" -maxdepth 3 -type d -path '*/app/public' -print 2>/dev/null | single_line)
fi
[ -n "${WP_PATH:-}" ] || fail 'WP_PATH is unset and no single Local site was found under ~/Local Sites.'

if [ "${WP_DB_SOCKET:-}" = '' ]; then
	WP_DB_SOCKET=$(find "$HOME/Library/Application Support/Local/run" -maxdepth 3 -type s -name mysqld.sock -print 2>/dev/null | single_line)
fi
[ -n "${WP_DB_SOCKET:-}" ] || fail 'WP_DB_SOCKET is unset and no single running Local MySQL socket was found; start the intended site or set WP_DB_SOCKET.'

if [ "${WP_CLI_BIN:-}" = '' ]; then
	if command -v wp >/dev/null 2>&1; then
		WP_CLI_BIN=$(command -v wp)
	elif [ -x /opt/homebrew/bin/wp ]; then
		WP_CLI_BIN=/opt/homebrew/bin/wp
	else
		fail 'WP_CLI_BIN is unset and no wp binary was found on PATH.'
	fi
fi

if [ "${WP_CLI_PHP:-}" = '' ]; then
	# Prefer the newest bundled Local lightning PHP (it matches the bundled
	# MySQL); fall back to the system PHP binary. The binary sits five levels
	# below lightning-services (php-<ver>/bin/<arch>/bin/php). Prefer GNU
	# version sort when available; byte sort is only a last-resort fallback.
	WP_CLI_PHP=$(find "$HOME/Library/Application Support/Local/lightning-services" -maxdepth 5 -type f -path '*/bin/*/bin/php' -print 2>/dev/null | { sort -V 2>/dev/null || sort; } | tail -n 1)
	if [ "$WP_CLI_PHP" = '' ] && command -v php >/dev/null 2>&1; then
		WP_CLI_PHP=$(command -v php)
	fi
fi
[ -n "${WP_CLI_PHP:-}" ] || fail 'WP_CLI_PHP is unset and neither a Local lightning PHP nor a system PHP was found.'

for required_name in $required; do
	required_value=$(eval "printf '%s' \"\${$required_name:-}\"")
	[ -n "$required_value" ] || fail "$required_name is required by this workflow but is unset."
done

exec "$WP_CLI_PHP" -d display_errors=0 -d error_reporting=-1 -d mysqli.default_socket="$WP_DB_SOCKET" "$WP_CLI_BIN" --path="$WP_PATH" --no-color eval-file "$@"
