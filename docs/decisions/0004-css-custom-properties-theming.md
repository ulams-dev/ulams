# 0004. Theming with CSS custom properties, no styled-components

- Status: Proposed
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
