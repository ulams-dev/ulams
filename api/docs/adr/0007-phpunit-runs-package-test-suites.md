# 0007. PHPUnit in the API runs the test suites of all escolalms packages

- Status: Accepted (retroactive)
- Date: 2021-04-29

## Context and Problem Statement

With the code split into ~50 packages (ADR-0002), each package has tests that run against
Orchestra Testbench. The integration risk is the combination of package versions installed in
the API. Early on the API also had Behat (BDD) and Cypress (e2e) suites.

## Considered Options

Evidenced in history: Behat + Mink (2021–2022), Cypress e2e against the API (2021–2023),
PHPUnit integration suites.

## Decision Outcome

PHPUnit is the single test runner. `phpunit.xml` defines one `<testsuite>` per installed
package pointing at `vendor/escolalms/<pkg>/tests` (46 suites today) plus an `Integrations`
suite, and coverage includes the package sources. CI (`phpunit-tests.yml`) migrates, seeds
permissions, creates Passport keys and runs all suites against PostgreSQL on several PHP
versions; `phpunit-cc.yml` reports coverage.

- Behat removed: `1a3f594a` (2021-10-31, CI) and `baadb8ba` (2022-04-06, deps, `behat.yml`).
- Cypress e2e added in `ba72d678` (2021-05-15), deleted in `4f6bbe09` (2023-09-29 "cleanup").

### Consequences

- Good: a dependency bump in the API is verified against every package's own tests.
- Good: one tool, one config; no Node toolchain in the API repo after 2023.
- Bad: the full run is slow and needs special migrations
  (`vendor/escolalms/courses/tests/Database/Migrations`).
- Bad: no end-to-end tests across API + front-ends remain in this project.

## Evidence

- `1c0059ba` 2021-04-26 "Attaching h5p lib to API (#15)" — "running test from vendor".
- `f82ba084` 2021-04-29 "packages attached (#18)" — "testsuites".
- `ba72d678` 2021-05-15 "e2e end-to-end tests bootstrap #31 (#32)" — `api/.github/workflows/cypress.yml`.
- `1a3f594a` 2021-10-31 "add test cc. remove behat" — `phpunit-cc.yml`.
- `baadb8ba` 2022-04-06 "Remove behat" — `api/behat.yml`, `api/composer.json`.
- `4f6bbe09` 2023-09-29 "cleanup && init/update.sh script" — deletes `api/cypress/*`, `api/package.json`.
- `api/phpunit.xml`, `api/.github/workflows/phpunit-tests.yml`.
