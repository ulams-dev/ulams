# 0106. The learner interface language follows the course language

- Status: Proposed
- Date: 2026-10-21
- Plan: `docs/plans/phase-5-learner-ui.md` 3.10 (open question O6)

## Context and problem statement

The lesson page now carries strings the API does not write: the "Extra help for you" card (and, in later
milestones, the tutor and the lesson view). Remediations are generated in the course language, so a Polish
course shows Polish explanations. The strings around them (buttons, the "Why am I seeing this?" disclosure,
status text) must be in the same language, or the card reads as two languages. The learner web app has no
per-learner language switch, the lesson player's own strings are still English only, and the API reads the
language from the `X-Locale` header (the `translations` package), not from `Accept-Language`.

## Considered options

1. A per-learner language switch in the account.
2. The tenant default language for every learner page.
3. The language of the course being read, falling back to the tenant default when the course has none.

## Decision

Option 3. `learnerLocale(course.language, tenantDefault)` in `front/web/src/lib/locale.ts` returns `pl` or
`zh` when the course language is that code (`pl`, `pl-PL`, `pl_PL`) or its English name, else `en`. A course
with no language uses the tenant default; account pages use the tenant default.

- Catalogue components take the language as an optional `locale` prop (`uiStrings(locale)`), filled by the
  page, because several lessons in different languages can be served by the same app and the card is
  rendered outside Astro's path-based i18n routing. A card in another language marks itself with `lang`.
- The page sends `X-Locale` with its calls so API texts (the reasons) come back in the same language.
  `learner-insights` has English, Polish and, with this change, Chinese texts.
- `<html lang>` is left as it is until the lesson player's own strings are translated; marking the whole
  page as Polish while its chrome is English would be wrong.
- A per-learner switch stays open as a later item.

## Consequences

- Good: a Polish course reads in Polish end to end on the card; no new setting, no new dependency.
- Good: tests cover the rule, and the interface key tables stay in step across the three languages.
- Bad: a learner who reads a Polish course with an English interface preference cannot choose English until
  the switch exists.
- Open: native review of the Polish and Chinese strings (owner action, as for the landing, ADR 0096).
