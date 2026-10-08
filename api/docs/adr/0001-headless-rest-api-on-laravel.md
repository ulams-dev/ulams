# 0001. Headless LMS exposed as a REST API built on Laravel

- Status: Accepted (retroactive)
- Date: 2021-03-03

## Context and Problem Statement

Escola LMS needed a backend that could serve several independent clients: the React admin
panel (`admin/`), the learner front-end (`front/`) and third-party front-ends. The question was
whether the backend renders UI itself or only serves data.

## Considered Options

Not recorded.

## Decision Outcome

The backend is a **headless** Laravel application. `composer.json` from the first commit
describes the project as `"Headless LMS"` with `laravel/framework ^8.0` on `php ^7.4`. All
features are delivered as JSON REST endpoints under `routes/api.php`; there are no Blade-rendered
learner/admin screens in the API. The front-ends live in separate projects and consume the API
(docker-compose ships `escolalms/admin` and `escolalms/demo` images next to the API).

Inferred: the AdminLTE/InfyOm generator packages in the first commit were scaffolding leftovers;
they were dropped as code moved into packages (see ADR-0002).

### Consequences

- Good: any front-end technology can be used; admin and learner apps evolve independently.
- Good: the same API can back several tenants and clients (later ADR-0013).
- Bad: every UI feature needs an API contract, documentation (ADR-0006) and a client SDK
  (maintained on the front-end side).
- Bad: features that are usually server-rendered (H5P, SCORM players) had to be redesigned
  as headless (ADR-0004).

## Evidence

- `05ceebba` 2021-03-03 "initical commit" — `api/composer.json` (`"description": "Headless LMS"`,
  Laravel 8, PHP 7.4, Passport, l5-swagger, Sentry).
- `api/docker-compose.yml` — `admin` (`escolalms/admin`) and `front` (`escolalms/demo`) services.
- `api/docs/multidomain.md` — "You can attach your first front to this headless LMS."
