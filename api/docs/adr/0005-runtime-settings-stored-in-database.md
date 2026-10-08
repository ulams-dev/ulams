# 0005. Administrable runtime settings stored in the database

- Status: Accepted (retroactive)
- Date: 2021-09-03

## Context and Problem Statement

Many values (branding, integrations, feature flags of packages) must be changeable by an
administrator from the admin panel without redeploying, and the API must stay stateless
(ADR-0010) so values cannot be written into `.env` or `config/*.php` at runtime.

## Considered Options

Not recorded in the API repo. The settings package supports two modes (`use_database`
true/false: DB vs. overwriting config files).

## Decision Outcome

The ad-hoc `app/Http/Controllers/SettingsController.php` was removed and replaced by the
`escolalms/settings` package (PR #52, "fields"). Each package registers its administrable
keys in its service provider via `AdministrableConfig::registerConfig($key, $rules, $public,
$readonly)`; values are validated, persisted in the database and loaded over Laravel config.
Public keys are readable anonymously (front-ends read them for branding), the rest require
the settings permission.

`api/docs/multidomain.md` states the rule: "all configuration is stored either in database or
in environmental variables, not inside `.env` or `storage` folder".

### Consequences

- Good: admins change configuration in the admin panel; no deploy needed.
- Good: per-tenant configuration falls out naturally because each domain has its own DB
  (ADR-0013).
- Bad: effective configuration is split between env vars and DB rows; debugging needs both.
- Bad: config keys are spread across ~50 packages' service providers.

## Evidence

- `15474f5d` 2021-09-03 "fields (#52)" — deletes `api/app/Http/Controllers/SettingsController.php`,
  adds `escolalms/settings` to `api/composer.json`, `api/database/seeds/PermissionsSeeder.php`.
- `api/vendor/escolalms/settings/README.md` — `AdministrableConfig`, `use_database`.
- `api/docs/multidomain.md` — "stateless and easy to scale".
