# 0001. Monorepo with vendored packages

- Status: Proposed
- Date: 2026-10-08

## Context and problem statement

ulams started as three repositories (Laravel API, React admin, React front) glued together by about
57 published packages: 50 `escolalms/*` composer packages and 7 `@escolalms/*` npm libraries. Every
change to domain code meant a release in another repository, version bumps and lockfile churn, and
the roadmap (Living Course, Course Builder, learner insights) needs coordinated changes across the API
and both frontends.

## Decision

- One git repository with three applications: `api/`, `admin/`, `front/` (plus `docs/` and root
  tooling files). The history of the three original repositories is preserved, rewritten into their
  folders with `git filter-repo`.
- All 50 PHP packages live as source in `api/packages/<name>` (copied from the exact locked commits).
  `api/composer.json` has no dependency on them: namespaces are autoloaded via PSR-4 and service
  providers are registered explicitly in `config/app.php`. Their third-party requirements are merged
  into the root `composer.json`.
- The JS libraries live as source: `components`, `sdk`, `ts-models` and `scorm-player` in
  `front/src/lib`, `gift-pegjs` and `markdown-editor` in `admin/src/lib`, imported through the
  `@ulams/*` alias. Admin reuses `ts-models` and `scorm-player` from `front/src/lib` (single copy).
- Each vendored package keeps a provenance note (upstream repository, version, commit).

## Consequences

- Good: one pull request can change domain code, API and UI together; no package releases.
- Good: local development and CI build everything from one checkout.
- Bad: no more independent package versioning; `/api/core/packages` reads `api/packages/versions.json`.
- Bad: package test fixtures make the repository larger (~81 MB in `api/packages`).
- Follow-up: per-package licence obligations still apply (see the licence audit, Phase 0.1).
