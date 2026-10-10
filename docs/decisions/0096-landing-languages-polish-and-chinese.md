# 0096. The product landing in Polish and Simplified Chinese: Astro i18n routing, one document per language

- Status: Proposed
- Date: 2026-10-10

## Context and problem statement

The owner asked for the platform product landing (`front/web`, platform host) in Polish and Chinese next to
English. The landing is a catalogue document (`src/docs/platform.json`) plus three data files (comparison,
workflows, demo cards) and about fifty fixed strings inside the catalogue components (badges, button labels,
accessible names). We need routes, language metadata for search engines, a switcher, fonts for Chinese, and a way
to keep three languages from drifting apart. No native speaker reviews the copy inside this change.

## Decision

- **Routes: Astro's built-in i18n routing.** `i18n: { defaultLocale: "en", locales: ["en", "pl", "zh"], routing:
  { prefixDefaultLocale: false } }` in `astro.config.mjs`. English stays at `/`, Polish is `/pl/`, Chinese is `/zh/`.
  It fits the SSR setup: the pages are ordinary routes (`src/pages/pl/index.astro`, `src/pages/zh/index.astro`), the
  platform-host check is unchanged (on a tenant host both answer 404; tenant sites have no language prefix), and
  components read the language from `Astro.currentLocale`, so no prop has to be threaded through the catalogue.
  Query-string or cookie based switching was rejected: a URL per language is what hreflang, sharing and caching need.
- **One document per language, parallel by test.** `src/docs/i18n/platform.pl.json` and `platform.zh.json` have the
  same tree as `platform.json`. A unit test walks both against English and fails on any difference in components,
  order, ids, anchors, icons, statuses, bindings or numbers, on a translation that is missing or still English, on
  code, commands, file names and example data that were touched, and on a product or standard name (ulams, Claude,
  MCP, H5P, SCORM, ...) that the translation lost. One document with per-locale strings was rejected: the catalogue
  documents are the shape the Course Builder generates, and a per-string table would hide structural drift.
- **Data files follow the same rule.** `src/data/i18n/workflows.<lang>.json` (same transcript, translated prose, the
  same commands and tool calls) and `comparison.<lang>.json` (labels, descriptors and value words only). Competitor
  facts do not change in translation: product names, cell notes, sources and check dates stay as they are (notes stay
  in English); a translated cell keeps the kind of the English value (`kind`), so marks and colours do not depend on
  a word. The display status (`ULAMS_LANDING_STATUS`) is applied to the English values first and translated after,
  so `final` and `actual` behave identically in all three languages.
- **Fixed interface strings** of the catalogue components live in `front/ui/src/lib/i18n.ts` (English, Polish,
  Chinese) and are chosen by `Astro.currentLocale`; without i18n routing they are English, so tenant sites are
  unchanged. The story players read their Pause/Play labels from `data-label-*` attributes instead of constants.
- **Language metadata.** `<html lang>` (`en`, `pl`, `zh-Hans`), a canonical URL per language, `hreflang` alternates for
  all three plus `x-default` (English), and `og:locale`. The origin comes from the forwarded host and scheme.
- **Switcher.** A separate control in the site header (`languages`, `homeHref` props of `SiteHeader`: EN, PL, 中文,
  current one `aria-current`), not one of the six navigation links. Links work without JavaScript; a 0.4 KB module
  (`elements/lang-switch.ts`) adds the current `#anchor` to them so the reader stays in the same section.
- **Fonts.** Chinese uses the system CJK stack (PingFang SC, Hiragino Sans GB, Microsoft YaHei, Noto Sans SC) after
  the Latin web fonts, so no CJK web font (hundreds of KB) is shipped. For Chinese the CSS drops synthetic italics and
  negative tracking and adds leading; Polish gets hyphenation.
- **Quality control.** The copy is written as marketing copy, not literal translation, but nobody native reviews it in
  this change. A glossary (`front/web/src/i18n/GLOSSARY.md`) fixes the terms (Living Course, course builder,
  academy/tenant, white-label, citations, ...), and an `owner-action` issue asks for a native review of PL and ZH.
  Default until then: published as is.

## Consequences

- Adding a language is a new locale in three lists (Astro config, `locales.ts`, `i18n.ts`), two data files, one
  document and one route; the parity test then tells what is missing.
- Changing the English landing now means changing three documents; the parity test fails until they follow.
- Chinese rendering depends on the visitor's installed CJK font, which every mainstream OS has.
- Tenant landing pages are not translated; they are course content, not product marketing.
