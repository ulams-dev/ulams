# 0002. Package-per-domain architecture (escolalms/* Composer packages)

- Status: Accepted (retroactive)
- Date: 2021-04-29

## Context and Problem Statement

The initial API kept all domain code (courses, users, H5P, DTOs, repositories) inside
`app/` (290 files at `05ceebba`). The team wanted a reusable LMS that could be assembled from
features and tested feature by feature.

## Considered Options

Not recorded.

## Decision Outcome

Domain code was moved out of `app/` into separate Composer packages under the `escolalms/`
vendor (one GitHub repository and Packagist package per domain). The API became a thin
"distribution" that requires the packages and wires them together.

- 2021-03-08 `escolalms/core` extracted (`3c3df8a0`, `0bae14f7`); `escolalms/auth` followed
  (`5678e517`).
- 2021-04-29 `f82ba084` "packages attached (#18)" moved courses/files/categories/auth/H5P code
  out, shrinking `app/` from 221 to 31 files.
- Later features arrived as new packages (payments `a7eff716`, scorm `25acb35c`, settings
  `15474f5d`, reports `b77f9a27`, templates `a8cb997e`, webinar `dbeb4f19`, tasks
  `1f858052`, recommender `9b3e7f7e`, dictionaries `fc2ea933`…). Today `composer.json`
  requires ~50 `escolalms/*` packages and `app/` has ~54 files.
- Packages self-register via Laravel package auto-discovery; each package registers its own
  routes, migrations, permissions and Swagger annotations.
- Module boundaries were visualised with deptrac (`c7ac1f69`, 2023-02-15, `deptrac.yaml`
  over `vendor/escolalms`); the deptrac CI job was removed in `5d4547ae` (2024-08-23,
  "Add sonar") in favour of SonarCloud/Snyk badges.

### Consequences

- Good: features can be enabled per distribution by adding/removing a package.
- Good: each package has its own tests and Swagger docs (ADR-0006, ADR-0007).
- Bad: a cross-cutting change requires coordinated releases of several packages plus a
  `composer update` in the API ("Updating dependencies" commits are frequent).
- Bad: package source is not in the API repo; debugging means reading `vendor/`.
- Bad: packages use `^0` constraints, so breaking changes are not signalled by semver.

## Evidence

- `3c3df8a0` 2021-03-08 "Refactor code to use core package" — `api/app/Dto/*`, `api/composer.json`.
- `f82ba084` 2021-04-29 "packages attached (#18)" — removes `api/app/Dto/*`, adds
  `escolalms/courses`, `escolalms/files`; `api/.github/workflows/phpunit-tests.yml`.
- `c7ac1f69` 2023-02-15 "deptrac diagram" — `api/deptrac.yaml`, `api/deptrac.php`,
  `api/.github/workflows/deptrac.yml`.
- `5d4547ae` 2024-08-23 "Add sonar (#334)" — deletes `deptrac.yml`, adds SonarCloud/Snyk badges.
