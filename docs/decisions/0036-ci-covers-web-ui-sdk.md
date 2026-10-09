# 0036. CI typechecks, lints and tests the web app, ui and sdk

- Status: Proposed
- Date: 2026-10-09

## Context and problem statement

The `js` job of `ci.yml` filtered on `admin`, `front`, `api-h5p` and `api-pdf`. A change under
`front/web`, `front/ui` or `front/sdk` started the job (the path filter matches `front/**`) but ran none
of their tasks, so the reference frontend, its component library and the SDK could break unnoticed.

## Decision

Add a step that runs `turbo run typecheck lint test` for `@ulams/web`, `@ulams/ui` and `@ulams/sdk` to the
`js` job (about 10 s). Not added: Playwright (end-to-end, visual and accessibility suites need the full
stack and stay local) and the `@ulams/docs` build (the documentation workflow does it).

## Consequences

- Good: failures in the studio, the catalogue and the SDK block the merge.
- Bad: a few seconds more on every JavaScript change; the `ui` and `web` tests use jsdom, which needs the
  optional native `canvas` build on the runner (available on ubuntu-latest).
