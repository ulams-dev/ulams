# Theming with CSS custom properties

ulams does not use styled-components. Components are styled with plain CSS (CSS Modules,
`*.module.css` next to the component) and read every themeable value from `--ulams-*` custom
properties. A theme is a `ThemeTokens` object (`types.ts`); `applyTheme()` turns it into
variables at runtime.

## Files

| File | Purpose |
|---|---|
| `types.ts` | `ThemeTokens`: the theme keys (same names as the former styled-components `DefaultTheme`). |
| `cssVars.ts` | The contract: theme key → CSS variable, light/dark resolution, fonts. |
| `applyTheme.ts` | `applyTheme(theme, {mode, name})`, `setThemeMode(mode)`, `useThemeTokens()`. |
| `tokens.css` | Static defaults (orange preset) so the first paint is themed before JS runs. |
| `blue.ts`, `orange.ts`, `red.ts`, `velvet.ts`, `contrast.ts` | Original presets. |
| `experiences.ts` | `coffee`, `oncall`, `nightsky` presets (see `front/docs/design/experiences.md`). The `gravity`, `poland` and `ulam` demos exist in `front/web` only (`@ulams/ui` theme presets); this legacy front is not extended and `themeFor` falls back to coffee for them. |

## Variables

| Variable | Theme key (dark mode uses `dm__key`) |
|---|---|
| `--ulams-color-primary` | `primaryColor` |
| `--ulams-color-primary-on-light` | `primaryColor` / `dm__primaryColorOnLight`. Use it for filled primary surfaces with white content (buttons, banners, active tabs): it keeps AA contrast against white in dark presets, where `dm__primaryColor` is a light tint |
| `--ulams-color-secondary` | `secondaryColor` (falls back to primary) |
| `--ulams-color-header` | `headerColor` (falls back to text) |
| `--ulams-color-text` | `textColor` |
| `--ulams-color-bg` | `background` |
| `--ulams-color-card-bg` | `cardBackgroundColor` |
| `--ulams-color-accent-bg` | `colorBackground` |
| `--ulams-color-error`, `--ulams-color-invert` | `errorColor`, `invertColor` |
| `--ulams-color-white`, `--ulams-color-black` | `white`, `black` |
| `--ulams-gray-1` … `--ulams-gray-5` | `gray1` … `gray5` |
| `--ulams-color-positive`, `--ulams-color-positive-2` | `positive`, `positive2` |
| `--ulams-color-input-bg`, `--ulams-color-input-disabled-bg` | `inputBg`, `inputDisabledBg` |
| `--ulams-color-label-list-value` | `labelListValueColor` |
| `--ulams-color-button-disabled` | `primaryButtonDisabled` |
| `--ulams-color-outline-button`, `--ulams-color-outline-button-invert` | `outlineButtonColor`, `outlineButtonInvertColor` |
| `--ulams-color-breadcrumbs`, `--ulams-color-numerations` | `breadcrumbsColor`, `numerationsColor` |
| `--ulams-radius`, `--ulams-radius-{button,input,note,checkbox,card,modal}` | `radius`, `*Radius` (px) |
| `--ulams-font-family`, `--ulams-font-family-body` | `font`, `bodyFont` |

`<html>` carries `data-mode="light|dark"` and `data-theme="<preset>"`.

### Optional variables

Every colour variable above has an optional twin, `--ulams-opt-<name>` (for example
`--ulams-opt-color-input-bg` next to `--ulams-color-input-bg`). It holds the theme key's
**own** value for the current mode (light: `key`, dark: `dm__key`, with no fallback to
another key) and is `initial`, i.e. unset, when the theme does not define that key. Use it
when a component needs a different fallback than the one baked into `--ulams-<name>`:

```css
/* inputBg when the theme sets it, otherwise gray-5 (not white, the contract fallback) */
background: var(--ulams-opt-color-input-bg, var(--ulams-gray-5));
```

`themeToCss()` writes them together with the regular variables (`applyTheme()` keeps a
single `<style id="ulams-theme">`). `tokens.css` does not define them, so before JS runs the
per-use fallback applies. `optionalVarName(cssVar)` in `cssVars.ts` gives the name.

## Converting a styled component

| styled-components | CSS |
|---|---|
| `styled.div\`…\`` | `.root { … }` in `Component.module.css`, `className={styles.root}` |
| `${({theme}) => theme.primaryColor}` | `var(--ulams-color-primary)` |
| `getStylesBasedOnTheme(theme.mode, theme.dm__x, theme.x, theme.y)` | `var(--ulams-x)`; the dark value is already resolved by the variable. If the dark and light *keys* differ (e.g. dark uses `dm__outlineButtonColor`, light uses `textColor`), write the light rule and add `:global([data-mode="dark"]) .root { … }`. If the fallback key differs from the contract one, use `var(--ulams-opt-x, var(--ulams-y))` (see Optional variables) |
| `${(p) => p.active && css\`…\`}` | modifier class (`styles.active`) or `data-active` attribute + `.root[data-active="true"]` |
| numeric/size props (`$width`, `$color`) | inline CSS variable: `style={{ "--w": `${width}px` }}` and `width: var(--w)` |
| `styled(Component)` | pass `className` through to the wrapped component and style it |
| `as="h4"` | keep a typed `as?: React.ElementType` prop on the component |
| `css\`` / `keyframes\`` | plain CSS / `@keyframes` in the module |
| `createGlobalStyle` | a global stylesheet imported once (`global.css`) using `:root`/element selectors |
| `withTheme`, `ThemeContext`, `useTheme` | remove; use CSS variables, or `useThemeTokens()` only when JS truly needs a raw value |
| `chroma(theme.x).alpha(0.2)` | `color-mix(in srgb, var(--ulams-x) 20%, transparent)` |

Never hard-code a colour that has a theme key; never reintroduce styled-components (the
`no-restricted-imports` lint rule fails on it).
