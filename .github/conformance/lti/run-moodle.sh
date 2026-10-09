#!/bin/sh
# Moodle LTI 1.3 round trips in both directions (nightly-conformance.yml). Run from the
# repository root with Moodle up (compose.yml, profile lti) and the ulams API serving
# http://ulams.test:18000 (see the workflow for the environment it needs).
#
#   ULAMS_EXEC   command prefix that runs PHP in api/ (default: run on the host, cd api)
#   MOODLE_EXEC  command prefix that runs a command in the Moodle container
#   NODE_PATH    must resolve `playwright`
set -eu

MOODLE_EXEC="${MOODLE_EXEC:-docker compose -f .github/conformance/compose.yml exec -T moodle}"
MOODLE_PHP="/opt/bitnami/php/bin/php"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

ulams() {
    if [ -n "${ULAMS_EXEC:-}" ]; then
        $ULAMS_EXEC php ../.github/conformance/lti/ulams-setup.php "$@"
    else
        (cd api && php ../.github/conformance/lti/ulams-setup.php "$@")
    fi
}
moodle() { $MOODLE_EXEC "$MOODLE_PHP" /conformance/moodle-setup.php "$@"; }
field() { node -e "const d=JSON.parse(require('fs').readFileSync(process.argv[1],'utf8'));const v=process.argv[2].split('.').reduce((o,k)=>o?.[k],d);if(v===undefined||v===null)process.exit(1);console.log(typeof v==='object'?JSON.stringify(v):v)" "$1" "$2"; }
step() { echo "::group::$*"; }
done_() { echo "::endgroup::"; }

step "ulams fixture"
ulams seed > "$WORK/seed.json"; cat "$WORK/seed.json"
COURSE_ID=$(field "$WORK/seed.json" course_id)
TOPIC_ID=$(field "$WORK/seed.json" topic_id)
done_

echo "== 1. Moodle (platform) launches ulams (tool), ulams sends the grade back (AGS)"
step "Moodle: register ulams as an external tool"
$MOODLE_EXEC env ULAMS_URL=http://ulams.test:18000 ULAMS_COURSE_ID="$COURSE_ID" "$MOODLE_PHP" /conformance/moodle-setup.php setup > "$WORK/moodle.json"
cat "$WORK/moodle.json"
ulams platform "$(cat "$WORK/moodle.json")"
done_
step "Browser: Moodle student opens the activity"
MOODLE_CMID=$(field "$WORK/moodle.json" cmid) ULAMS_COURSE_ID="$COURSE_ID" ULAMS_TOPIC_ID="$TOPIC_ID" \
    node .github/conformance/lti/moodle-roundtrip.cjs
done_
step "Grade in Moodle's gradebook"
ulams grade-target "$COURSE_ID" | tee "$WORK/target.json"
[ "$(field "$WORK/target.json" sent)" = "true" ] || { echo "ulams did not send the grade"; exit 1; }
moodle grade "$(field "$WORK/moodle.json" cmid)" student | tee "$WORK/grade.json"
[ "$(field "$WORK/grade.json" grade)" = "100" ] || { echo "Moodle has no grade 100 for the student"; exit 1; }
done_

echo "== 2. ulams (platform) launches Moodle (tool), Moodle sends the grade back (AGS)"
step "Moodle: publish a course as an LTI tool"
moodle tool-draft > "$WORK/draft.json"; cat "$WORK/draft.json"
ulams tool "$COURSE_ID" "$(cat "$WORK/draft.json")" > "$WORK/tool.json"
moodle tool-complete "$(field "$WORK/draft.json" uniqueid)" "$(cat "$WORK/tool.json")"
done_
step "Browser: ulams learner starts the Moodle topic"
ULAMS_TOOL_JSON="$WORK/tool.json" MOODLE_COURSE_ID=$(field "$WORK/draft.json" course_id) \
    node .github/conformance/lti/ulams-launches-moodle.cjs
done_
step "Moodle grades the learner and syncs grades"
moodle tool-grade 80
$MOODLE_EXEC "$MOODLE_PHP" /opt/bitnami/moodle/admin/cli/adhoc_task.php --execute
done_
step "Score in ulams"
LTI_TOPIC=$(field "$WORK/tool.json" topic_id)
for i in $(seq 1 30); do
    ulams scores "$LTI_TOPIC" > "$WORK/scores.json"
    if [ "$(field "$WORK/scores.json" scores.0.score_given 2>/dev/null || true)" = "80" ]; then
        cat "$WORK/scores.json"
        done_
        echo "Moodle round trips passed"
        exit 0
    fi
    sleep 2
done
cat "$WORK/scores.json"
echo "ulams received no score 80 from Moodle"
exit 1
