# 0043. An enforced morph map with stable aliases for polymorphic types

- Status: Proposed (data layer implemented 2026-10-21, see "Implementation")
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

## Implementation (2026-10-21)

The data layer is implemented; the API layer deliberately differs from the decision above.

- `Ulams\Core\Support\MorphMap` collects the aliases; every package registers its own in its service
  provider, and the core provider calls `Relation::enforceMorphMap` after all providers booted.
  `HasMorphAlias` makes a model, and every subclass of it, store the alias of its nearest mapped ancestor.
- Owners of a `*_type` column use `HasMorphTypeColumns`: the database stores the alias, the model attribute (and
  so the API) keeps showing the class name, assigning either form stores the alias, and queries on the column
  accept either. Raw queries (`DB::table`, joins, SQL) must use the alias.
- Data migration `2026_10_23_000000_store_morph_aliases` rewrites every `<name>_type` column that has a
  `<name>_id` sibling, from the frozen list in `App\Support\LegacyMorphClassNames`. It is idempotent and reversible
  and runs in every tenant through `migrate`.
- `tests/Integrations/MorphMapCoverageTest` fails when a topic content class, a productable class or a model with a
  `morphTo()` relation is added without an alias or type-column declaration.
- **API compatibility.** `topicable_type` and the other type fields keep returning class names, and requests accept
  class names or aliases. The admin, the SDK and the Astro front are unchanged, so no `topicable_class` field and
  no client migration are needed. Switching the API to aliases remains possible later as a separate, breaking change.
