# ulams course action

A composite GitHub Action for a course repository that holds a course folder (`ulams course init`). It runs the
[`ulams` CLI](../../front/cli) from the `ghcr.io/ulams-dev/ulams-cli` image, so the Node version of the runner does not matter.

```yaml
- uses: actions/checkout@v4
- uses: ulams-dev/ulams/actions/course@cli-v1
  with: { command: validate, path: course }
```

Commands: `validate` (SARIF report, no secrets needed), `diff` (course against the server, in the job summary), `preview`
(with a preview platform, `ULAMS_PREVIEW_URL` and `ULAMS_PREVIEW_TOKEN`, builds the folder into a throwaway preview academy named after the pull request; without one it proposes the folder as a version, never approving; one sticky pull request comment either way), `preview-cleanup` (deletes the pull request's preview academy when it closes; the reaper deletes it after its time anyway), `push` (on the default branch; `apply: true`
only when the academy lets `ci` tokens approve), `pull-pr` (pulls changes made in ulams and opens or updates the pull request
`ulams/sync`; never touches `sources/`). `ulams course init --ci github` writes a workflow that uses all of them.

Secrets of the repository: `ULAMS_URL` and `ULAMS_TOKEN` (a `ci` token). Nothing is published from here: the action is used by
tag once the release workflow publishes the image.

`entrypoint.mjs` is the whole logic and is unit tested (`node --test actions/course/test`).
