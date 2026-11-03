# 0197. SCORM playback is pinned by a browser regression harness with original fixture packages and golden runtime traces

- Status: Proposed
- Date: 2026-10-22
- Plan: `docs/plans/scorm-playback-tests.md`; related ADR 0014 (content origin), Dependabot PR #232 (closed) and the
  ignore rule #431

## Context and problem statement

CLAUDE.md requires SCORM behaviour to stay unchanged. Three players run `scorm-again`: the API content player
(`api/packages/scorm/resources/content-player/player.html` and `player.blade.php`, vendored 2.6.4) used by the
reference front, and the shared React `<ScormPreview>` (`front/src/lib/scorm-player/player.tsx`) used by the legacy
front and the admin preview. Server tests (`ScormTrackApiTest`, `ScormContentOriginApiTest`) cover the HTTP API but
nothing plays a package in a browser. `scorm-again` 3 changes defaults (SCORM 1.2 `mastery_override`, synchronous
commits, 2004 `total_time` and `completion_status` handling, stricter CMI validation) and drops the AICC export, so
the upgrade (PR #232) was closed until a playback test exists.

## Considered options

1. **Browser harness with golden traces**: small original SCORM 1.2 and 2004 packages that script a learner session,
   played by Playwright through each player; the test records the runtime calls, the commits sent to
   `POST /api/scorm/content/{uuid}/track` and the stored tracking, and compares them with golden traces captured on
   2.6.x.
2. Unit tests of `scorm-again` settings in jsdom only.
3. Rely on the Rustici golf examples already in `api/packages/scorm/database/mocks` and manual checks.

## Decision

Option 1. Fixtures are written for ulams (MIT) so their licence is clear; the existing mock zips stay for the upload
and parse tests. Golden traces are JSON files reviewed in pull requests; a change to a trace needs a reason in the
PR. The harness runs in CI against the mock API for the React player and against a seeded API in the nightly
conformance workflow for the content player. The `scorm-again` 3 upgrade must pass with unchanged traces, with every
changed default set explicitly to the 2.6 behaviour.

## Consequences

- Good: SCORM behaviour becomes testable, which unblocks `scorm-again` 3 and later player work.
- Good: the same fixtures check the cmi5 and content-origin paths later.
- Bad: golden traces must be kept stable (timestamps and durations normalised).
- Bad: the content-player job needs the API and is nightly, not per pull request.

## Outcome of the first use (2026-10-23)

The harness showed that scorm-again 3.4.7 cannot reproduce the 2.6 behaviour with settings alone (error codes,
suspend_data limit, time formats, commit event timing); the upgrade is therefore not made and the decision goes
to the owner. See section 11 of the plan.

## Alternatives considered

- Option 2 does not exercise the service worker, the iframe, the content origin or the commit transport.
- Option 3 depends on third-party packages with unclear redistribution terms and does not prove anything automatically.
