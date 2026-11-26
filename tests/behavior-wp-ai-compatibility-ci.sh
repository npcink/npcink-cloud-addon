#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
scratch="$(mktemp -d)"
trap 'rm -rf "$scratch"' EXIT
scope="$root/scripts/ci-wp-ai-compatibility-scope.sh"

expect() {
	if [[ "$1" != "$2" ]]; then
		printf 'Expected %s, got %s (%s)\n' "$1" "$2" "$3" >&2
		exit 1
	fi
}

git init -q "$scratch/repo"
cd "$scratch/repo"
git config user.name 'Compatibility CI test'
git config user.email 'compatibility@example.invalid'
git commit -qm base --allow-empty
base="$(git rev-parse HEAD)"
mkdir -p docs/nested
touch README.md docs/guide.md docs/nested/guide.md
git add -- README.md docs
git commit -qm docs
docs="$(git rev-parse HEAD)"
mv docs/guide.md renamed.php
git add -- docs renamed.php
git commit -qm mixed
mixed="$(git rev-parse HEAD)"

classify() {
	local event="$1" head="$2" expected_head="${3:-$2}"
	git switch --quiet --detach "$(git commit-tree "$head^{tree}" -p "$base" -p "$head" -m merge)"
	GITHUB_EVENT_NAME="$event" PR_BASE_SHA="$base" PR_HEAD_SHA="$expected_head" bash "$scope"
}

expect docs_only=true "$(classify pull_request "$docs")" 'root and nested Markdown'
expect docs_only=false "$(classify pull_request "$mixed")" 'Markdown renamed into PHP'
expect docs_only=false "$(classify schedule "$docs")" 'nightly'
expect docs_only=false "$(classify push "$docs")" 'push'
expect docs_only=false "$(classify pull_request "$docs" "$mixed")" 'mismatched PR head'
git switch --quiet --detach "$mixed"
GITHUB_EVENT_NAME=pull_request PR_BASE_SHA="$base" PR_HEAD_SHA="$docs" bash "$scope" > "$scratch/scope"
expect docs_only=false "$(< "$scratch/scope")" 'wrong checked-out merge commit'
git switch --quiet --detach "$(git commit-tree "$base^{tree}" -p "$base" -p "$docs" -m empty)"
GITHUB_EVENT_NAME=pull_request PR_BASE_SHA="$base" PR_HEAD_SHA="$docs" bash "$scope" > "$scratch/scope"
expect docs_only=false "$(< "$scratch/scope")" 'empty diff'
expect docs_only=false "$(cd "$scratch" && GITHUB_EVENT_NAME=pull_request PR_BASE_SHA="$base" PR_HEAD_SHA="$docs" bash "$scope" 2>/dev/null)" 'Git failure'

# Mock archives, the CLI and localhost HTTP. Advance only the smoke shell's
# clock so both timeout attempts run without real sleeps or network calls.
export FIXTURE_DIR="$scratch"
export HOME="$scratch/home"
export PATH="$scratch/bin:$PATH"
mkdir -p "$scratch/bin" "$HOME/.wordpress-playground" "$scratch/archive/ai"
touch "$scratch/archive/ai/ai.php"
export NPCINK_COMPAT_WORDPRESS_URL=https://example.invalid/wordpress.zip
wp_key="$(node -e 'process.stdout.write(require("crypto").createHash("sha1").update(process.argv[1]).digest("hex").slice(0,8))' "$NPCINK_COMPAT_WORDPRESS_URL")"
(cd "$scratch/archive" && zip -q "$HOME/.wordpress-playground/ai-1.4.0.zip" ai/ai.php)
cp "$HOME/.wordpress-playground/ai-1.4.0.zip" "$HOME/.wordpress-playground/custom-$wp_key.zip"

printf '%s\n' 'sleep() { SECONDS=$((SECONDS + 60)); }' > "$scratch/clock"
printf '%s\n' '#!/bin/bash' 'printf "%s\n" "$FIXTURE_DIR/bin/fixture-cli"' > "$scratch/bin/npx"
printf '%s\n' '#!/bin/bash' 'exit 1' > "$scratch/bin/lsof"
cat > "$scratch/bin/fixture-cli" <<'CLI'
#!/bin/bash
echo "$$" >> "$FIXTURE_DIR/attempts"
[[ "$SCENARIO" != exit ]] || exit 17
if [[ "$SCENARIO" == ignores-term ]]; then
	trap '' TERM
fi
exec /bin/sleep 10
CLI
cat > "$scratch/bin/curl" <<'CURL'
#!/bin/bash
set -eu
output=''
while (( $# )); do
	case "$1" in
		--output) output="$2"; shift ;;
		http://127.0.0.1:*/wp-json/npcink-cloud-addon-playground/v1/compatibility) ;;
		http*) exit 90 ;; # No downloads may escape the fixture.
	esac
	shift
done
[[ -n "$output" ]] || exit 91
for ((i=0; i<100; i++)); do
	pid="$(tail -n 1 "$FIXTURE_DIR/attempts")"
	if [[ -n "$pid" ]] && { [[ "$SCENARIO" == exit ]] || kill -0 "$pid" 2>/dev/null; }; then
		break
	fi
	/bin/sleep 0.01
done
if [[ "$SCENARIO" == exit ]]; then
	# Let the first CLI invocation finish before returning the first probe.
	while [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null; do /bin/sleep 0.01; done
fi
attempts="$(awk 'END {print NR}' "$FIXTURE_DIR/attempts")"
if [[ "$SCENARIO" == http-error || ( "$SCENARIO" == retry-http-error && "$attempts" == 2 ) ]]; then
	printf 500
	exit 22
fi
if [[ "$SCENARIO" == bad-json || ( "$SCENARIO" == recovery && "$attempts" == 2 ) ]]; then
	if [[ "$SCENARIO" == bad-json ]]; then
		printf '<html>invalid</html>' > "$output"
	else
		node -e 'console.log(JSON.stringify({wordpress_version:"7.0.4", php_version:"8.0", wordpress_ai_version:"1.4.0", wordpress_ai_active:true, addon_active:true, abilities_api_present:true, connector_runtime_present:true, active_plugin_names:["ai/ai.php","npcink-cloud-addon/npcink-cloud-addon.php"], context_execution_checks:Object.fromEntries(Array.from({length:13},(_,i)=>[i,true]))}))' > "$output"
	fi
	printf 200
	exit 0
fi
printf 000
exit 7
CURL
chmod +x "$scratch/bin/"*

check_lane() {
	local scenario="$1" count="$2" result="$3" text="$4" ci="${5:-1}" status=0
	: > "$scratch/attempts"
	SCENARIO="$scenario" BASH_ENV="$scratch/clock" NPCINK_COMPAT_CI_RETRY_BOOTSTRAP="$ci" NPCINK_COMPAT_PLAYGROUND_PORT=9427 \
		bash "$root/scripts/smoke-wordpress-ai-compatibility.sh" stable-primary > "$scratch/log" 2>&1 || status=$?
	if [[ "$result" != "$status" ]] || ! grep -Fq "$text" "$scratch/log"; then
		cat "$scratch/log" >&2
		printf '%s: expected exit %s and %s; got %s\n' "$scenario" "$result" "$text" "$status" >&2
		exit 1
	fi
	expect "$count" "$(awk 'END {print NR}' "$scratch/attempts")" "$scenario CLI starts"
	while read -r pid; do
		if kill -0 "$pid" 2>/dev/null; then
			printf '%s: CLI %s survived cleanup\n' "$scenario" "$pid" >&2
			exit 1
		fi
	done < "$scratch/attempts"
}

check_lane recovery 2 0 'PASS: stable-primary'
check_lane timeout 2 75 'compatibility was not verified'
check_lane ignores-term 1 1 'probe status 75' 0
check_lane retry-http-error 2 1 'probe status 1'
check_lane http-error 1 1 'probe status 1'
check_lane exit 1 1 'probe status 1'
check_lane bad-json 1 1 'SyntaxError'
check_lane timeout 1 1 'probe status 75' 0
echo 'WordPress AI compatibility CI policy: ok'
