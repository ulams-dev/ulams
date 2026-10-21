# Dependabot triage, 2026-10-21

Written for milestone TD-1 of `docs/plans/tech-debt-and-quality-bar.md` (PR #344).
Nothing was merged, rebased or closed: the six pull requests below are still open and the
`.github/dependabot.yml` ignore rules are unchanged. This file is the input for the owner's decision.

CI results are those on each pull request's head commit (see the linked job logs through `gh pr checks <n>`).

| PR | Change | State | Finding | Recommendation |
|---|---|---|---|---|
| [#227](https://github.com/ulams-dev/ulams/pull/227) | `github-actions` group, 2 updates | green (17 checks), mergeable | Touches four workflow files only | Merge after a glance at the two release notes. Re-run `actionlint` first (`docker run --rm -v "$PWD:/repo" -w /repo rhysd/actionlint:latest`). |
| [#232](https://github.com/ulams-dev/ulams/pull/232) | `scorm-again` 2.6.4 to 3.4.5 | red, open since 2026-10-10 | Admin typecheck fails: `front/src/lib/scorm-player/player.tsx(2,36)`: `Module "scorm-again" has no exported member 'AICC'`. The image smoke jobs of admin and front fail on the same install. It is a major bump of the SCORM runtime | Needs a code change (the 3.x export layout) and the SCORM player regression tests; one PR of its own. If it cannot be fixed within an hour, close it and add `scorm-again` major to the `ignore` list with this reason. H5P and SCORM behaviour must not change. |
| [#233](https://github.com/ulams-dev/ulams/pull/233) | `@ant-design/icons` 4.8.3 to 6.3.4 (admin) | CI shows only 7 checks, all green, mergeable | The plan marks it as fine to try. Only 7 checks ran, so the full JS job probably did not run on it | Rebase on `main` to get the full CI, then merge if green. |
| [#234](https://github.com/ulams-dev/ulams/pull/234) | `react-datepicker` 7.6.0 to 9.1.0 (old front) | CI shows only 7 checks, all green, mergeable | Major bump in the old front, which has no component test for the date picker | Rebase for full CI. Check the one screen that uses it by hand or close it; the old front retirement (decision Q4) makes it low value. |
| [#235](https://github.com/ulams-dev/ulams/pull/235) | `jest` and `@types/jest` | red | Admin typecheck fails in `src/components/EmailTemplateEditor/EmailTemplateEditor.test.tsx` (lines 113, 167, 168, 206: `Expected 0 arguments`, `parameter of type 'never'`): the new `@types/jest` types `jest.fn()` strictly | Fix the four test call sites (`jest.fn<...>()` generics) in under an hour, or hold `@types/jest` at the current major with an ignore rule. |
| [#252](https://github.com/ulams-dev/ulams/pull/252) | `npm-minor-patch` group, 25 updates | red | Three separate causes, so the group should be split by the ignore rule, not closed: (1) `@pdfme` 6.2.6: the PDF service test fails with `The requested module '@pdfme/pdf-lib' does not provide an export named 'parseFont'`, and the admin image build fails inside `@pdfme/schemas`; (2) the axe job failed on `429` from Google Cloud Storage (egress limit while downloading a browser, an infrastructure flake: re-run it); (3) the `canvas` optional dependency prints a `gyp` error on every run (a warning, not the cause) | Add an `ignore` rule for `@pdfme/*` `>= 6.2.5` with the reason above (needs the owner's go-ahead because it edits `dependabot.yml`), then let Dependabot recreate the group. Re-run the axe job. |

## Why no config change in this PR

The only safe-looking edit, ignoring `@pdfme/*`, hides a real upstream regression that someone has
to look at; the plan (section TD-1) says red pull requests that cannot be fixed in an hour are closed
with a comment and an ignore rule with a reason, and that is the owner's call. Until then the
open pull requests are harmless: none is auto-merged.
