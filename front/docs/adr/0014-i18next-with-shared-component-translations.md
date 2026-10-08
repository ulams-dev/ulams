# 0014. i18next for UI strings, merged with translations shipped by Components

- Status: Accepted (retroactive)
- Date: 2021-09-17 (i18next); 2022-06-07 (merge with Components resources)

## Context and Problem Statement

Wellms is used by Polish and international customers; the demo has to be translatable,
and since 2022 part of the UI comes from `@escolalms/components`, which has its own strings.

## Considered Options

Not recorded.

## Decision Outcome

`i18next` + `react-i18next`, initialised in `src/i18n.ts` with in-code resource objects.
Since 2022-06 the front spreads `resources` exported by
`@escolalms/components/lib/styleguide/i18n` into its own `en`/`pl` resources and overrides
or extends keys locally. No language detector or lazy-loaded JSON namespaces are used.

### Consequences

- Good: one i18n instance for app and library components; library strings can be overridden
  per app.
- Bad: `src/i18n.ts` is a single ~65 KB TypeScript file bundled into the main chunk;
  translators must edit code.

## Evidence

- `6cb7cc44` 2021-09-17 "copied" - `front/package.json` (`i18next`), `front/src/i18n.ts`
- `51f07daa` 2021-11-04 "update i18next"
- `7a2db035` 2022-06-07 "my data edit form (#194)" - first `ComponentTranslations` import in `front/src/i18n.ts`
- `70a9fc70` 2022-06-29 "feature/translations, fix courses slider (#256)"
- Components repo: `01dccef` 2022-04-26 "i18n (#13)"
