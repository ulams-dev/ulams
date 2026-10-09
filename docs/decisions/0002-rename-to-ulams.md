# 0002. Rename EscolaLMS / Wellms to ulams

- Status: Accepted (2026-10-08)
- Date: 2026-10-08

## Context and problem statement

The product is now ulams. The code still carried the EscolaLMS and Wellms names in PHP namespaces,
class and file names, config keys, package names, UI strings and infrastructure names.

## Decision

- PHP namespace `EscolaLms\` → `Ulams\` (classes such as `EscolaLmsCourseServiceProvider` →
  `UlamsCourseServiceProvider`), config keys `escolalms_*` → `ulams_*`, composer name `ulams/api`,
  package keys `ulams/<name>`, JS alias `@ulams/*`, docker network, Redis and bucket names `ulams`.
- Existing databases are migrated: a migration rewrites stored class names (polymorphic columns,
  JSON) and config keys; migration history rows whose file names carried the old prefix are renamed
  before any `migrate*` command runs (`App\Support\LegacyMigrationNames`).
- Kept on purpose for now: ADR evidence, upstream provenance links, the `escolalms/php` and
  `escolalms/reportbro-server` Docker Hub images. Tracked in `docs/ROADMAP-TODO.md`.

## Consequences

- Good: one consistent name across code, UI and infrastructure.
- Bad: external integrations that relied on old class names or config keys must be updated.
- Bad: the default admin e-mail changed to `admin@ulams.app`; `ulams.app` is a placeholder domain
  until the real domain is chosen.
