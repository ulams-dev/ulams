# SCORM playback fixtures

Small SCORM 1.2 and SCORM 2004 packages written for ulams (MIT, no third-party content). Each one
runs a scripted learner session against the SCORM API and records every call, return value and
error code, so a browser test can pin what a player does. Plan: `docs/plans/scorm-playback-tests.md`,
decision: `docs/decisions/0197-scorm-playback-regression-harness.md`.

| Package | Version | What the SCO does |
|---|---|---|
| `scorm12-session` | 1.2 | initialises, reads student id, entry, status, location, total time and suspend data; sets location, score min/max/raw, status `incomplete`, a 4096 character suspend_data and a session time; commits; sets `passed` or `failed`; commits; provokes a read-only, a type, a range and a vocabulary error; finishes. `adlcp:datafromlms` is set in the manifest. |
| `scorm12-mastery` | 1.2 | the manifest has `adlcp:masteryscore` 70; the SCO sets a score and never sets `lesson_status`, so the status shows whether the player derives it from the score (`mastery_override`). |
| `scorm2004-session` | 2004 4th Edition | the same session through `cmi.location`, `cmi.score.scaled/raw`, `cmi.progress_measure`, `cmi.completion_status`, `cmi.success_status`, `cmi.session_time`, `cmi.suspend_data` and `cmi.interactions.0.*`; `Commit`, `Terminate`; the manifest has `adlcp:completionThreshold` 0.8. |

## Query parameters

The test passes them in the launch URL; the service worker and the content origin ignore the query
when they look up the file.

| Parameter | Packages | Values |
|---|---|---|
| `outcome` | `scorm12-session`, `scorm2004-session` | `passed` (default), `failed` |
| `phase` | `scorm12-session`, `scorm2004-session` | `first` (default), `resume` (reads what the LMS returned, sets `completed`, finishes) |
| `scenario` | `scorm2004-session` | `full` (default), `no-completion` (never sets completion or success status, to pin the defaults) |
| `score` | `scorm12-mastery` | raw score, default `65` |

## Trace

`window.__scormTrace` is a list of `{ fn, args, result, error }` (or `{ note, value }`). The runner
(`_shared/runner.js`) also posts `{ source: 'ulams-scorm-fixture', type: 'done', trace }` to the top
window when the session ends, so a harness waits for that message and never for a timer.

`trace.mjs` normalises a trace (sorted keys, ISO dates replaced, fractions of a second dropped from
durations, listed origins replaced) and compares it with a golden file in `golden/<player>/<fixture>.json`.
`UPDATE_GOLDEN=1` rewrites golden files; a changed golden file needs a reason in the pull request.

## Editing

The zips in `packages/` are committed because the PHPUnit and browser tests only read them. After
editing a fixture, rebuild them (deterministic, no dependency):

```bash
node api/packages/scorm/tests/fixtures/playback/build.mjs          # rewrite packages/*.zip
node api/packages/scorm/tests/fixtures/playback/build.mjs --check  # fail when a zip is stale
node --test api/packages/scorm/tests/fixtures/playback/playback.test.mjs
```

`ScormPlaybackFixturesTest` (PHPUnit, `--testsuite scorm`) checks that every package passes the
package parser and the upload endpoint. The existing `database/mocks` packages stay for the upload
and parse tests.
