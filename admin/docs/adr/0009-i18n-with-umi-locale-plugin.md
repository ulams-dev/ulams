# 0009. Internationalisation with the umi locale plugin (en-US, pl-PL, fr-FR)

- Status: Accepted (retroactive)
- Date: 2021-09-08

## Context and Problem Statement

The panel had to be usable in at least English and Polish (Inferred: Polish customers plus an
international open-source audience). The Ant Design Pro template already shipped the umi `locale` plugin with
`src/locales/<lang>/*.ts` message files and `<FormattedMessage>` / `useIntl()`.

## Considered Options

Not recorded.

## Decision Outcome

UI strings go through the umi locale plugin (`locale: { antd: true, baseNavigator: true }` in
`config/config.ts`, so the language follows the browser). English came with the template; Polish
(`src/locales/pl-PL*`) was added in September 2021 together with a pass that replaced hard-coded strings
in components; French was contributed by Pascal Braconnier in April 2023 (PR #751). Per-feature message files
(`notifications.ts`, `templates.ts`, `consultations.ts`, …) are added next to each new feature.

### Consequences

- Good: new languages can be added without code changes; antd components are localised too.
- Bad: template locales (`zh-CN` as the configured default, `bn-BD`, `fa-IR`, `id-ID`, `ja-JP`, `pt-BR`,
  `zh-TW`) remain with only a few template strings each.
- Bad (Inferred): nothing checks key completeness across locales, so gaps show up only at runtime.
- Note: translation strings stored in the API (`/api/admin/translations`, edited through
  `src/services/escola-lms/translations.ts`) are a separate mechanism for the learner front-end.

## Evidence

- `1c53acd3` 2021-02-26 "initial push" — `admin/src/locales/en-US*`, template locales.
- `f289c4f2` 2021-09-08 "Issue/81 (#97)" — `admin/src/locales/pl-PL.ts`, `admin/src/locales/pl-PL/*`,
  components switched to `FormattedMessage`.
- `f76db68e` 2023-04-04 "Add fr-FR locales (#751)" — `admin/src/locales/fr-FR*`.
