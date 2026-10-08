# 0006. OpenAPI documentation generated from Swagger annotations

- Status: Accepted (retroactive)
- Date: 2021-04-28

## Context and Problem Statement

A headless API (ADR-0001) split across many packages (ADR-0002) needs one browsable,
machine-readable contract for the admin panel, the learner front-end and the TypeScript
SDK/models that are generated or written against it.

## Considered Options

Not recorded.

## Decision Outcome

Use `darkaonline/l5-swagger` (present since the first commit). Endpoints are documented with
`@OA\…` annotations in controllers / `*Swagger.php` interfaces. `config/l5-swagger.php` lists
`app/` **and every `vendor/escolalms/*/src`** directory in `annotations`, so one OpenAPI document
covers the whole distribution; every new package PR adds its path there. A GitHub Actions
workflow (`swagger.yml`, added in PR #17) generates the document on pushes to
`main`/`master`/`develop` and publishes it to GitHub Pages; each package also publishes its own.

### Consequences

- Good: a single up-to-date contract for all clients; front-end teams can work from it.
- Good: docs live next to the code and change in the same PR.
- Bad: annotations are not validated against real responses; drift is possible.
- Bad: forgetting to add a new package to `l5-swagger.php` silently omits its endpoints.

## Evidence

- `05ceebba` 2021-03-03 "initical commit" — `darkaonline/l5-swagger ^8.0.0`.
- `7fb4743c` 2021-04-28 "Feature/h5p (#17)" — adds `api/.github/workflows/swagger.yml`.
- `823840d9` 2021-04-30 "Add tags to swagger for api"; `1bf5a839` 2021-07-01 "swagger config".
- `e7aed9bd`, `3035e33e`, `15474f5d` — examples of package PRs adding paths to
  `api/config/l5-swagger.php`.
- `api/.github/workflows/swagger.yml` — `peaceiris/actions-gh-pages@v3`.
