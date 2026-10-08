# Architecture Decision Records — API (Wellms / Escola LMS)

This folder holds Architecture Decision Records (ADRs) for the Laravel headless LMS API in
`api/`, written in [MADR](https://adr.github.io/madr/) format. An ADR captures one significant
decision: its context, the options considered, the outcome and its consequences. ADRs
`0001`–`0099` are **retroactive**: they were reconstructed in 2026 from the git history of the
former `EscolaLMS/API` repository after it was imported into this monorepo, and they cite the
rewritten monorepo commit hashes as evidence. "Retroactive" means the decision was made and
implemented earlier without a written record; anything not stated in a commit, diff or doc is
marked `Inferred:` and the date is that of the decisive commit. Numbers `0100` and up are
reserved for decisions taken in the monorepo era. To add one, copy the structure of an
existing ADR, take the next free number from `0100` up, name it `NNNN-kebab-case-title.md`,
set the status (`Proposed`, `Accepted`, `Superseded by NNNN`), add it to the table below and
submit it in the same PR as the change it describes. Do not rewrite accepted ADRs; supersede
them with a new one.

| #    | Title | Status | Date |
|------|-------|--------|------|
| [0000](0000-record-architecture-decisions.md) | Record architecture decisions | Accepted | 2026-10-08 |
| [0001](0001-headless-rest-api-on-laravel.md) | Headless LMS exposed as a REST API built on Laravel | Accepted (retroactive) | 2021-03-03 |
| [0002](0002-package-per-domain-architecture.md) | Package-per-domain architecture (escolalms/* Composer packages) | Accepted (retroactive) | 2021-04-29 |
| [0003](0003-oauth2-tokens-with-laravel-passport.md) | API authentication with Laravel Passport bearer tokens | Accepted (retroactive) | 2021-03-03 |
| [0004](0004-headless-h5p.md) | Headless H5P via the escolalms/headless-h5p package | Accepted (retroactive) | 2021-04-26 |
| [0005](0005-runtime-settings-stored-in-database.md) | Administrable runtime settings stored in the database | Accepted (retroactive) | 2021-09-03 |
| [0006](0006-openapi-docs-from-swagger-annotations.md) | OpenAPI documentation generated from Swagger annotations | Accepted (retroactive) | 2021-04-28 |
| [0007](0007-phpunit-runs-package-test-suites.md) | PHPUnit in the API runs the test suites of all escolalms packages | Accepted (retroactive) | 2021-04-29 |
| [0008](0008-postgresql-as-primary-database.md) | PostgreSQL as the primary database | Accepted (retroactive) | 2025-01-08 |
| [0009](0009-redis-queues-with-horizon.md) | Redis queues run by Horizon or a per-domain queue:work loop | Accepted (retroactive); partially superseded by 0013 | 2021-09-22 |
| [0010](0010-stateless-config-via-laravel-prefixed-env-vars.md) | Stateless containers configured by LARAVEL_-prefixed env vars | Accepted (retroactive) | 2022-08-02 |
| [0011](0011-docker-image-php-fpm-supervisor-and-caddy.md) | Docker image with PHP-FPM + Supervisor, Caddy as reverse proxy | Accepted (retroactive) | 2023-02-13 |
| [0012](0012-laravel-9-and-php-8-baseline.md) | Upgrade to Laravel 9 and a PHP 8.1+ baseline | Accepted (retroactive) | 2024-02-14 |
| [0013](0013-multi-tenancy-with-laravel-multidomain.md) | Multi-tenancy (SaaS) with gecche/laravel-multidomain | Accepted (retroactive) | 2024-02-07 |
| [0014](0014-s3-compatible-object-storage.md) | S3-compatible object storage (MinIO locally) | Accepted (retroactive) | 2024-02-16 |
| [0015](0015-observability-sentry-and-health-checks.md) | Observability with Sentry and health checks | Accepted (retroactive) | 2021-10-12 |
