# 0008. PostgreSQL as the primary database (MySQL/MariaDB no longer tested)

- Status: Accepted (retroactive)
- Date: 2025-01-08

## Context and Problem Statement

The API is a Laravel app and could run on MySQL/MariaDB or PostgreSQL. Supporting both doubles
the CI matrix and constrains SQL used by packages.

## Considered Options

- Support both MySQL/MariaDB and PostgreSQL (state 2021–2024).
- Standardise on PostgreSQL.

## Decision Outcome

PostgreSQL became the de-facto and then only tested engine.

- From the first commit CI ran both MySQL and Postgres suites (`f13ad63e` "mysql & postgres
  rtests"); MySQL was replaced with MariaDB 10.5 in CI (`7ddeb79c`, 2021-12-23).
- `.env.example` and `docker-compose.yml` default to `DB_CONNECTION=pgsql` / `postgres:12`;
  the multi-domain tooling creates per-tenant Postgres databases (`DB_ROOT_HOST=postgres`).
- 2025-01-08 `3dc533be` commented out the `phpunit-mysql-php81` job; active CI jobs are only
  `phpunit-postgres-php82/83/84`.

Remaining MySQL traces: `phpunit-cc.yml` and `swagger.yml` still start MariaDB/MySQL services.

### Consequences

- Good: one engine to support and a smaller CI matrix.
- Bad: MySQL users get no CI guarantee; compatibility may silently break.
- Bad: CI is inconsistent until coverage/swagger jobs also move to Postgres.

## Evidence

- `05ceebba` 2021-03-03 "initical commit"; `f13ad63e` 2021-03-03 "mysql & postgres rtests";
  `a657ee49` 2021-03-03 "mysql/posgres docker update" — `api/docker/envs/.env.ci.{mysql,postgres}`.
- `7ddeb79c` 2021-12-23 "replacing mysql with mariadb" — `api/.github/workflows/*.yml`.
- `3dc533be` 2025-01-08 "8.3 alpine" — `api/.github/workflows/phpunit-tests.yml`.
- `api/.env.example` (`DB_CONNECTION=pgsql`), `api/docker-compose.yml` (`postgres:12`).
