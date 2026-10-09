# 0043. An enforced morph map with stable aliases for polymorphic types

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/leftovers-0-2.md` (L0-10)

## Context and problem statement

Polymorphic columns store PHP class names: `topics.topicable_type`, notifications, tags, cart and
payments, model fields, Spatie role and permission tables, and others. The 2026 rename from
`EscolaLms\` to `Ulams\` needed a data migration that rewrote every such column, and every future
class rename would need the same. The admin, the SDK and the Astro front also compare class-name
strings.

## Considered options

1. `Relation::enforceMorphMap` with stable snake-case aliases, plus a data migration.
2. A non-enforced `morphMap` (aliases optional).
3. Keep class names.

## Decision

Option 1:

- **Registration.** Packages register aliases with `MorphMap::register()` in their providers, and the
  app calls `enforceMorphMap` once all providers have booted.
- **Alias names.** Topic contents use `topic.<type>`. Other models use their singular names (`course`,
  `user`, `product`, …).
- **Data migration.** A per-tenant migration (run by `ulams:upgrade`) rewrites existing values.
- **API compatibility.** Topic resources return the alias in `topicable_type`, plus a deprecated
  `topicable_class` for one release. Requests and course imports accept both forms.

## Consequences

- Good: class renames never orphan data, and clients compare short stable strings.
- Bad: one broad PR touching the API, admin and SDK together, and a data migration in every tenant.
- Bad: external API consumers must switch to aliases within one release.
