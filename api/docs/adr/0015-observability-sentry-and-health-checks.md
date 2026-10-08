# 0015. Observability with Sentry and spatie/laravel-health checks

- Status: Accepted (retroactive)
- Date: 2021-10-12

## Context and Problem Statement

A headless API running in containers across environments (and later many tenants) needs error
reporting and a liveness signal for orchestrators.

## Considered Options

Evidenced: Sentry (kept), Laravel Telescope (tried and removed), spatie/laravel-health.

## Decision Outcome

- `sentry/sentry-laravel` from the first commit; exception reporting was wired through
  `app/Exceptions/Handler.php` and `config/sentry.php` in `71cc950b` (2021-10-12). DSN and
  environment come from env vars. SDK upgraded to ^4.2 with Laravel 9.
- Laravel Telescope was added (`a797a937`, 2022-03-15) and removed five weeks later
  (`54fb87bd`, 2022-04-21). Reason not recorded. Inferred: unsuitable for a stateless production
  image.
- `spatie/laravel-health` with a `HealthCheckProvider` and an API route, used by the Docker
  `HEALTHCHECK` (`b49b22bb`, 2024-10-04).
- Multi-domain mode got a default domain configuration "to resolve sentry issues" (`b6f90be3`).

### Consequences

- Good: production errors are captured centrally; containers report health.
- Bad: no tracing/metrics stack beyond Sentry; Telescope-style local introspection is absent.
- Bad: in multi-domain mode Sentry events need the domain as context to be actionable.

## Evidence

- `05ceebba` 2021-03-03 "initical commit" — `sentry/sentry-laravel ^2.3`.
- `71cc950b` 2021-10-12 "Sentry update" — `api/config/sentry.php`, `api/app/Exceptions/Handler.php`.
- `a797a937` 2022-03-15 "Add telescope"; `54fb87bd` 2022-04-21 "remove telescope".
- `b49b22bb` 2024-10-04 — `api/app/Providers/HealthCheckProvider.php`, `api/config/health.php`.
- `b6f90be3` 2024-12-17 "defautl setup for multidomain to resolve sentry issues".
