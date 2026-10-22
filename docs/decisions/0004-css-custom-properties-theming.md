# 0004. Theming with CSS custom properties, no styled-components

- Status: Accepted (2026-10-08)
- Date: 2026-10-08

## Context and problem statement

The front-end and its component library used styled-components with a JS theme object
(`ThemeProvider`, about 540 styled declarations, 289 `getStylesBasedOnTheme` calls). The roadmap needs
per-tenant themes from presets plus an accent colour (Course Builder, Phase 2.3), framework-agnostic
web components themeable with CSS custom properties (Phase 5.2) and design tokens exported from the
design tool. A runtime CSS-in-JS library makes all of these harder and adds bundle weight.

## Decision

- Themes are `ThemeTokens` objects (same keys as the former `DefaultTheme`). One mapping
  (`front/src/lib/components/theme/cssVars.ts`) turns them into `--ulams-*` custom properties: light
  values on `:root`, dark values (`dm__*` keys) under `[data-mode="dark"]`.
- `applyTheme()` replaces `ThemeProvider` and also applies custom colours from API settings.
- Components use CSS Modules and read only `var(--ulams-…)`. Presets include the three experience
  themes `coffee`, `oncall` and `nightsky`.
- styled-components is removed from every workspace and blocked by a lint rule.

## Consequences

- Good: themes work in any framework and in web components; design tokens map directly to variables.
- Good: no runtime style injection; smaller bundles.
- Bad: a large one-time conversion of the component library and the front application.

## Amendment (2026-10, Phase 5 remaining, P5R-2)

- The tenant presets are one list, `front/ui/src/theme/presets.ts`, written to `front/ui/catalogue/themes.json` and to the
  API's copy `api/packages/settings/resources/themes.json` by `yarn workspace @ulams/ui theme-manifest` (`yarn test` fails
  when either is stale). The learner site, the admin Theme screen, the course builder and the settings validation read it;
  a new preset is one CSS file plus one entry.
- A tenant sets its look in the public settings `theme.theme`, `theme.accent`, `theme.logo` and `theme.logoAlt`. The admin
  screen refuses an accent that fails WCAG 2.2 AA on the chosen preset (4.5:1 as text, 3:1 as a border or icon). The logo
  is a PNG or an allow-list sanitised SVG of at most 200 KB, stored in the tenant's storage.
