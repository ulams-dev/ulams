# SCORM playback regression tests (unblocks scorm-again 3)

Status: Proposed (2026-10-22). The owner approved the recommended defaults of section 9 on 2026-10-23
(own MIT fixtures; scorm-again 3 keeps the 2.6 behaviour). Progress: SC-1 (#448), SC-2 (#449), SC-3 (#450) in review; SC-4 stopped, see section 11. ADR 0197 (Proposed) records the decision.

## 1. Goal

Pin today's SCORM 1.2 and 2004 playback behaviour with automated browser tests, then upgrade `scorm-again` from 2.6.x
to 3.x with identical results. Dependabot PR #232 was closed for exactly this reason (comment on #232, ignore rule
#431): "a playback test for `front/src/lib/scorm-player` against a SCORM 1.2 and a 2004 package, then a PR that drops
AICC and sets `mastery_override`, `useAsynchronousCommits` explicitly."

## 2. TODO items covered

| TODO item | Where |
|---|---|
| Quality bar "Existing tests and linters green; H5P/SCORM unchanged" (the SCORM part, made testable) | SC-1 to SC-3 |
| Phase 1 "(new) SCORM playback regression tests and the scorm-again 3 upgrade" (added by this plan) | SC-1 to SC-4 |
| `tech-debt-and-quality-bar.md` TD-1 (`scorm-again` 3 "needs the SCORM player regression tests") | SC-4 |

## 3. Current state (read on `main` at `602f94174`)

| Player | Where | scorm-again |
|---|---|---|
| API content player (reference front iframe, content origin) | `api/packages/scorm/resources/content-player/player.html`, `resources/views/player.blade.php`, `player-serverless.blade.php`; served from `GET /api/scorm/assets/scorm-again.min.js` | vendored 2.6.4 (`resources/js/vendor/scorm-again`) |
| React `<ScormPreview>` (legacy front, admin preview) | `front/src/lib/scorm-player/player.tsx`, service worker `public/service-worker-scorm.js` in each app | npm 2.6.x in `front/package.json` and `admin/package.json` |

Server side: `POST /api/scorm/launch/{uuid}`, `GET /api/scorm/content/{uuid}`, `POST /api/scorm/content/{uuid}/track`;
tracking in `ScoTracking` (`api/packages/laravel-scorm`). Tests: `ScormTrackApiTest`, `ScormContentOriginApiTest`,
`ScormAdminApiTest`, `ScormFileApiTest`; the e2e `front/web/tests/e2e/content-frames.spec.ts` checks the frame, not the
runtime. Mock packages: `RuntimeBasicCalls_SCORM12.zip`, `RuntimeBasicCalls_SCORM20043rdEdition.zip` (Rustici golf
examples) and two generic `scorm.zip` files.

## 4. Design

### 4.1 Fixture packages (`api/packages/scorm/tests/fixtures/playback/`, MIT, written for ulams)

- `scorm12-session`: one SCO whose script calls `LMSInitialize`, reads `cmi.core.student_id`, `cmi.core.lesson_status`
  and `cmi.suspend_data`, sets `cmi.core.lesson_location`, `cmi.core.score.raw/min/max`, `cmi.core.lesson_status`
  (`incomplete` then `passed` or `failed` from a query flag), `cmi.core.session_time`, `cmi.suspend_data` (4 KB),
  calls `LMSCommit` and `LMSFinish`, and a second launch that resumes from `suspend_data`.
- `scorm12-mastery`: a manifest with `adlcp:masteryscore` and a score below and above it, without setting
  `lesson_status`, to pin the `mastery_override` behaviour.
- `scorm2004-session`: `Initialize`, `cmi.location`, `cmi.score.scaled/raw`, `cmi.completion_status`,
  `cmi.success_status`, `cmi.progress_measure`, `cmi.session_time`, `cmi.suspend_data`, interactions with
  `cmi.interactions.n.*`, `Commit`, `Terminate`, and a resume launch; one variant that never sets
  `completion_status` to pin the default.
- Each SCO writes every call and return value to `window.__scormTrace` and to `postMessage` for the harness.

### 4.2 Harness

- **React player (per pull request, CI)**: a Playwright spec in `front` (legacy app) and one in `admin`, each
  mounting `<ScormPreview>` on a test page with the existing mocked API pattern (`admin/src/e2e/mocked`); the mock
  records every `track` request body.
  *Implemented (SC-2):* one spec, `front/tests/scorm-playback/react-player.spec.ts`, runs as two Playwright projects
  (legacy front and admin), each with its own service worker, against one set of golden traces. A small server
  (`server.mjs`) builds the harness page with vite and mocks the three LMS endpoints, so the admin build is not
  needed and the `@playwright/test` dev dependency is added to `front` (same range as `admin`). CI job
  "SCORM playback (Playwright)", part of `ci-ok`.
- **Content player (nightly)**: a job in `nightly-conformance.yml` that seeds a tenant, uploads the fixtures through
  `POST /api/admin/scorm/upload`, launches them in the reference front and reads the stored `ScoTracking` through an
  admin endpoint or a test-only artisan command.
  *Implemented (SC-3), deviation from the plan:* the content player is not run in the nightly conformance workflow
  against a seeded API. `content-player.spec.ts` plays the real `player.html`/`player.js` with the vendored
  scorm-again against the same mock server (canned launch `cmi`, recorded track requests) on every pull request
  that touches the players, because that needs no API stack and no network and catches the same regressions in
  the player. The PHP tracking stays covered by `ScormTrackApiTest` and `ScormContentOriginApiTest`. A seeded,
  end-to-end nightly run remains possible later; it is not needed to unblock scorm-again 3.
- **Golden traces**: `tests/fixtures/playback/golden/<player>/<fixture>.json` with the API call log, the commit
  bodies and the final stored tracking; timestamps and durations are normalised (`PT…` rounded, ISO dates replaced).
  `UPDATE_GOLDEN=1` rewrites them; a changed trace needs a reason in the PR description.

### 4.3 The upgrade (SC-4)

- Bump `scorm-again` to 3.x in `front`, `admin` and the vendored copy (with the vendored README updated).
- Remove the AICC import in `player.tsx` (and its type), set explicitly the 2.6 behaviour for every changed default:
  `mastery_override: false`, `useAsynchronousCommits: true` (or the 2.6 transport equivalent), 2004 `total_time` and
  `completion_status` handling, and any validation option that keeps 2.6 acceptance.
- Golden traces must match unchanged; if 3.x cannot reproduce one, the PR stops and the difference goes to the owner.
- Remove the Dependabot ignore rule (#431).

## 5. Dependencies

No new runtime dependency. Playwright and the mocked-API setup already exist in `admin` and `front/web`; the legacy
`front` app gets `@playwright/test` as a dev dependency only if it has none (same version as `front/web`, Apache-2.0,
dev only, no runtime or self-hosting impact).

## 6. Migrations

None.

## 7. Tests

The plan is tests. In addition: the golden-trace normaliser has unit tests; the fixtures pass `api/packages/scorm`'s
parser (`POST /api/admin/scorm/parse`) in PHPUnit.

## 8. Risks

- Timing-dependent commits make traces flaky: wait for the SCO's `done` message, not timers; commit order is
  compared, not timing.
- The service worker differs per app: each app is tested with its own worker.
- 3.x may not reproduce a 2.6 default at all: the plan stops before shipping a behaviour change.

## 9. Open questions (recommended default in bold)

1. Fixtures: write our own or reuse the Rustici golf examples? **Write our own (MIT)**; keep the existing zips only for
   upload and parse tests.
2. Run the content-player job on every pull request? **Nightly**, because it needs the API stack; the React player
   specs run on every pull request.
3. If 3.x changes a trace that is arguably a bug fix in 2.6 (for example 2004 `total_time`)? **Keep 2.6 behaviour now**;
   a behaviour change is a separate, owner-approved PR.

## 10. Milestones (one branch and PR each, based on `main`)

| # | Slice | Demo |
|---|---|---|
| SC-1 | Fixture packages, trace format, normaliser, parser test | the fixtures upload and parse in PHPUnit |
| SC-2 | React player specs in `admin` and `front` with mocked API and golden traces on 2.6.x | CI shows the traces for 1.2 and 2004 |
| SC-3 | Content player job in the nightly conformance workflow with golden traces | the nightly run plays both packages through the reference front |
| SC-4 | `scorm-again` 3 upgrade with explicit defaults, AICC removed, ignore rule #431 dropped | all traces unchanged on 3.x |

## 11. SC-4 result (2026-10-23): scorm-again 3.4.7 cannot reproduce the 2.6 behaviour

As section 4.3 requires, the upgrade stops here and the difference goes to the owner. The golden traces were run
against scorm-again 3.4.7 (`SCORM_AGAIN_DIR=<unpacked package> yarn workspace front test:scorm`, which swaps the
library in the React player and the vendored content-player file) after setting every changed default
explicitly (`mastery_override: false`, `useAsynchronousCommits: true`, `throttleCommits: false`,
AICC import removed). The settings block of every trace then matched. These differences remain, none of
them reachable through a setting:

| # | Difference in 3.4.7 | Seen by | Learner or LMS impact |
|---|---|---|---|
| 1 | Validation errors are specific: 403/404/405/406/407 instead of 101 for read-only, type, range and vocabulary errors | SCO | A SCO that checks `LMSGetLastError` after a rejected write sees another code; the write is rejected in both |
| 2 | SCORM 1.2 `cmi.suspend_data` of 4097 characters is accepted (2.6 rejected it with 101) | SCO, LMS | Larger suspend data reaches the LMS; the 4096 limit of the standard is no longer enforced |
| 3 | SCORM 1.2 `session_time` and `total_time` are normalised (`0000:01:30` becomes `00:01:30`) | SCO, LMS | The value posted to `/track` has another format; `convertTimeInHundredth` parses both |
| 4 | SCORM 2004 `GetValue` of `cmi.location`, `cmi.suspend_data` and `cmi.score.scaled` before they are set returns "" with error 403 (2.6: error 0) | SCO | Spec-conforming; a SCO that treats a non-zero code as failure changes behaviour |
| 5 | SCORM 2004 `cmi.total_time` reads `PT0S` at the start (2.6: empty) | SCO | Minor |
| 6 | After a rejected write, a later successful 2004 `GetValue` still reports the old error (406) | SCO | Stale error code |
| 7 | The `LMSCommit`/`Commit` events fire when the SCO calls Commit (2.6: late, after the commit settled), and SCORM 2004 `Commit` now fires the event at all (2.6: never in the traces) | LMS | The content player posts the whole CMI at those moments: other order, and two extra posts per 2004 session. `ScormTrackService::updateScoTracking` reads dotted keys, so a nested whole-CMI body is not what it was written for |
| 8 | Community mode (`onScormPost`/`onScormGet`): other order of posts | LMS | Same cause as 7 |
| 9 | `renderCommitObject(false)` reports `totalTimeSeconds` 0 (2.6: the session time) and adds `globalObjectives` | none today | Neither player sets `lmsCommitUrl`, so no commit object is sent |
| 10 | The bundle grows from 128 KB to 595 KB (vendored `scorm-again.min.js`) | operators | Air-gapped installs carry more |

Decision for the owner:

1. **Stay on 2.6.x** (recommended while the LMS keeps its current tracking): the Dependabot ignore rule stays, its
   reason now cites this section; nothing changes for learners. 2.6.x still gets fixes.
2. **Accept 3.x**: a separate, owner-approved PR that upgrades both apps and the vendored file, sets the explicit
   settings from `front/src/lib/scorm-player/settings.ts`, rewrites the golden traces with the differences above and
   first makes `ScormTrackService` safe for whole-CMI commit bodies (difference 7).

What this PR delivers instead: the explicit settings (a no-op on 2.6, identical goldens), the AICC import removed
from `player.tsx`, the `SCORM_AGAIN_DIR` switch so any scorm-again release can be played against the goldens, a
complete difference listing in a failing trace, and this analysis.
