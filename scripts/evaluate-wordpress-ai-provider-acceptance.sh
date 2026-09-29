#!/usr/bin/env bash
set -euo pipefail

ROOT="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
EVAL_LAB_PATH="${NPCINK_EVAL_LAB_PATH:-$(dirname "$ROOT")/npcink-eval-lab}"
STAMP="${WP_AI_ACCEPTANCE_STAMP:-$(date -u +%Y%m%dT%H%M%SZ)}"
REPORT="${WP_AI_ACCEPTANCE_REPORT:-$ROOT/wordpress-ai-provider/generated/acceptance-$STAMP.json}"
QUALITY_REPORT="${WP_AI_ACCEPTANCE_QUALITY_REPORT:-$ROOT/wordpress-ai-provider/generated/quality-$STAMP.json}"
SAMPLES="${WP_AI_ACCEPTANCE_QUALITY_SAMPLES:-$EVAL_LAB_PATH/wordpress-ai-provider/fixtures/quality-samples.json}"

if [[ ! -d "$EVAL_LAB_PATH" ]]; then
	echo "Npcink Eval Lab not found: $EVAL_LAB_PATH" >&2
	echo "Set NPCINK_EVAL_LAB_PATH=/path/to/npcink-eval-lab." >&2
	exit 1
fi

mkdir -p "$(dirname "$REPORT")" "$(dirname "$QUALITY_REPORT")"

acceptance_status=0
if [[ -n "${WP_AI_ACCEPTANCE_INPUT:-}" ]]; then
	if [[ ! -f "$WP_AI_ACCEPTANCE_INPUT" ]]; then
		echo "Acceptance input not found: $WP_AI_ACCEPTANCE_INPUT" >&2
		exit 1
	fi
	cp "$WP_AI_ACCEPTANCE_INPUT" "$REPORT"
else
	set +e
	composer run --no-interaction acceptance:wp-ai-provider >"$REPORT"
	acceptance_status=$?
	set -e
fi

set +e
php "$EVAL_LAB_PATH/wordpress-ai-provider/evaluate.php" \
	"input=$REPORT" \
	"samples=$SAMPLES" \
	"output=$QUALITY_REPORT" \
	>"$QUALITY_REPORT.stdout"
quality_command_status=$?
set -e

if [[ "$quality_command_status" -ne 0 ]]; then
	echo "WordPress AI quality evaluation failed; inspect $QUALITY_REPORT.stdout" >&2
	exit "$quality_command_status"
fi

quality_status="$(php -r '$report=json_decode((string)file_get_contents($argv[1]),true); if(!is_array($report)){exit(2);} echo (string)($report["quality_status"]??"");' "$QUALITY_REPORT")"
if [[ -z "$quality_status" ]]; then
	echo "WordPress AI quality report is invalid: $QUALITY_REPORT" >&2
	exit 1
fi

php -r '$report=json_decode((string)file_get_contents($argv[1]),true); if(!is_array($report)){exit(2);} echo json_encode(array("acceptance_status"=>(int)$argv[2],"quality_status"=>(string)($report["quality_status"]??""),"passed"=>(int)($report["passed"]??0),"needs_review"=>(int)($report["needs_review"]??0),"failed"=>(int)($report["failed"]??0),"human_review_required"=>(bool)($report["human_review_required"]??false),"acceptance_report"=>$argv[3],"quality_report"=>$argv[1]),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),PHP_EOL;' "$QUALITY_REPORT" "$acceptance_status" "$REPORT" "$QUALITY_REPORT"

if [[ "$acceptance_status" -ne 0 ]]; then
	exit "$acceptance_status"
fi
if [[ "$quality_status" == "failed" ]]; then
	exit 1
fi
if [[ "$quality_status" == "needs_review" ]]; then
	exit 2
fi
exit 0
