# 0003. API authentication with Laravel Passport bearer tokens

- Status: Accepted (retroactive)
- Date: 2021-03-03

## Context and Problem Statement

A headless API (ADR-0001) used by separate SPA front-ends on other origins cannot rely on
session cookies alone. Clients need a token they can send with every request.

## Considered Options

Not recorded. (Laravel Sanctum was not evaluated in the history.)

## Decision Outcome

`laravel/passport` is the `api` guard driver (`config/auth.php`: `'driver' => 'passport'`).
Inferred (from the `--personal` client created in CI/init): front-ends receive a Passport
personal access token at login and send it as `Authorization: Bearer`.
Authentication endpoints live in the `escolalms/auth` package (ADR-0002); roles/permissions use
`spatie/laravel-permission` and `escolalms/permissions`.

- Passport was present from the first commit (`^10`), upgraded to `^11` with Laravel 9.
- 2021-04-01 OAuth tables were migrated inside the API as a "temporary fix" (`aa016888`).
- Passport keys are generated at container start and in CI (`php artisan passport:keys --force`,
  `passport:client --personal`) instead of being committed; package test suites copy the keys
  into Testbench (`f82ba084`).

### Consequences

- Good: stateless, cross-origin friendly authentication for all front-ends.
- Good: works per tenant in multi-domain mode, since keys and clients are created by the
  init scripts (ADR-0010, ADR-0013).
- Bad: every environment (CI, each domain) must bootstrap keys and a personal client before
  the API works; forgetting it gives opaque 500/401 errors.
- Bad: full OAuth2 server is heavier than needed for first-party SPAs.

## Evidence

- `05ceebba` 2021-03-03 "initical commit" — `api/composer.json` (`laravel/passport ^10.0`).
- `aa016888` 2021-04-01 "temporary fix with oauth migration inside API" — `oauth_*` migrations.
- `f82ba084` 2021-04-29 "packages attached (#18)" — "Copy passport keys to Orchestra/Testbench".
- `86b9e226` 2024-02-14 "update laravel to l9" — `laravel/passport ^11`.
- `api/.github/workflows/phpunit-tests.yml` — `passport:keys --force`, `passport:client --personal`.
