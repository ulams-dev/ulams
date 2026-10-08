# 0004. Shared UI library `@escolalms/components` with styled-components theming

- Status: Accepted (retroactive)
- Date: 2022-05-25

## Context and Problem Statement

Until 2022-05 the front's UI was built from in-repo components and SCSS
(`src/style/scss`). Wellms is sold as a headless LMS that customers re-skin, and several
front-ends needed the same learner widgets.

## Considered Options

Not recorded. (The pre-restart code used Storybook; the new library chose
React Styleguidist - `styleguidist server/build`, published at components.wellms.io.)

## Decision Outcome

A separate `EscolaLMS/Components` repo (first commit 2022-04-11) provides React components
written with `styled-components`, a `GlobalThemeProvider` and named themes (blue, orange,
red, velvet, contrast) with light/dark mode (`theme.mode`, `dm__*` tokens). The front was
switched to it on 2022-05-25: `index.tsx` wraps the app in `GlobalThemeProvider`, `App.tsx`
maps a theme name from API settings to `themes[...]`, and a lazy `ThemeCustomizer` lets demo
users change themes at runtime. The library also ships i18n resources (see [0014](0014-i18next-with-shared-component-translations.md))
and runs a11y (axe) checks in CI.

### Consequences

- Good: visual identity is configurable per tenant without code changes.
- Good: components are documented and a11y-tested outside the app.
- Bad: most UI changes require a Components release and a front bump (many
  "Update components" commits); [0005](0005-switchable-local-or-published-components-source.md) was added to ease this.
- Bad: the front mixes legacy SCSS with styled-components.

## Evidence

- `90949f30` 2022-05-25 "configure with components package, prepare login page" - `front/package.json` (`@escolalms/components`, `styled-components`, `babel-plugin-styled-components`)
- `91b86fa0` 2022-05-25 "finished login, register, forgot password page" - `GlobalThemeProvider`, `ThemeCustomizer`
- `f7738959` 2022-09-23 "theme customizer default theme fix"
- Components repo: `9f7e44c` 2022-04-11 "Initial commit", `8f04129` "theme provider", `01dccef` "i18n (#13)", `03ad2d0` 2022-09-15 "Create contrast theme (#240)", `styleguide.config.js`, `.github/workflows/a11y.yml`
