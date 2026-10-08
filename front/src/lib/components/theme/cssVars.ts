import type { ThemeFont, ThemeTokens } from "./types";

/**
 * Maps theme keys to CSS custom properties. This table is the contract between
 * presets / API settings and every stylesheet: components only ever read
 * `var(--ulams-…)`, never theme objects.
 *
 * `fallback` names the key used when a theme leaves the key undefined; for dark
 * mode the lookup order is `dm__key`, then `key`, then the fallback key (the same
 * resolution the former `getStylesBasedOnTheme(mode, dark, light, fallback)` did).
 */
type ColorKey = Exclude<
  keyof ThemeTokens,
  | `dm__${string}`
  | "theme"
  | "mode"
  | "font"
  | "bodyFont"
  | "radius"
  | `${string}Radius`
>;

export const COLOR_VARS: ReadonlyArray<{
  key: ColorKey;
  cssVar: string;
  fallback?: ColorKey;
}> = [
  { key: "primaryColor", cssVar: "--ulams-color-primary" },
  { key: "secondaryColor", cssVar: "--ulams-color-secondary", fallback: "primaryColor" },
  { key: "headerColor", cssVar: "--ulams-color-header", fallback: "textColor" },
  { key: "textColor", cssVar: "--ulams-color-text" },
  { key: "background", cssVar: "--ulams-color-bg" },
  { key: "cardBackgroundColor", cssVar: "--ulams-color-card-bg" },
  { key: "colorBackground", cssVar: "--ulams-color-accent-bg", fallback: "primaryColor" },
  { key: "errorColor", cssVar: "--ulams-color-error" },
  { key: "invertColor", cssVar: "--ulams-color-invert" },
  { key: "white", cssVar: "--ulams-color-white" },
  { key: "black", cssVar: "--ulams-color-black" },
  { key: "gray1", cssVar: "--ulams-gray-1" },
  { key: "gray2", cssVar: "--ulams-gray-2" },
  { key: "gray3", cssVar: "--ulams-gray-3" },
  { key: "gray4", cssVar: "--ulams-gray-4" },
  { key: "gray5", cssVar: "--ulams-gray-5" },
  { key: "positive", cssVar: "--ulams-color-positive" },
  { key: "positive2", cssVar: "--ulams-color-positive-2" },
  { key: "inputBg", cssVar: "--ulams-color-input-bg", fallback: "white" },
  { key: "inputDisabledBg", cssVar: "--ulams-color-input-disabled-bg", fallback: "gray4" },
  { key: "labelListValueColor", cssVar: "--ulams-color-label-list-value", fallback: "primaryColor" },
  { key: "primaryButtonDisabled", cssVar: "--ulams-color-button-disabled", fallback: "gray3" },
  { key: "outlineButtonColor", cssVar: "--ulams-color-outline-button", fallback: "textColor" },
  { key: "outlineButtonInvertColor", cssVar: "--ulams-color-outline-button-invert", fallback: "textColor" },
  { key: "breadcrumbsColor", cssVar: "--ulams-color-breadcrumbs", fallback: "textColor" },
  { key: "numerationsColor", cssVar: "--ulams-color-numerations", fallback: "primaryColor" },
];

export const RADIUS_VARS: ReadonlyArray<{
  key: "radius" | "buttonRadius" | "inputRadius" | "noteRadius" | "checkboxRadius" | "cardRadius" | "modalRadius";
  cssVar: string;
}> = [
  { key: "radius", cssVar: "--ulams-radius" },
  { key: "buttonRadius", cssVar: "--ulams-radius-button" },
  { key: "inputRadius", cssVar: "--ulams-radius-input" },
  { key: "noteRadius", cssVar: "--ulams-radius-note" },
  { key: "checkboxRadius", cssVar: "--ulams-radius-checkbox" },
  { key: "cardRadius", cssVar: "--ulams-radius-card" },
  { key: "modalRadius", cssVar: "--ulams-radius-modal" },
];

/** Only the primary colour has a separate "on light surfaces" dark-mode variant. */
export const PRIMARY_ON_LIGHT_VAR = "--ulams-color-primary-on-light";

export const FONTS: Record<ThemeFont, { links: string[]; fontFamily: string }> = {
  Inter: {
    links: ["https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap"],
    fontFamily: "'Inter', sans-serif",
  },
  Mulish: {
    links: ["https://fonts.googleapis.com/css2?family=Mulish:wght@400;500;700&display=swap"],
    fontFamily: "'Mulish', sans-serif",
  },
  Titillium: {
    links: ["https://fonts.googleapis.com/css2?family=Titillium+Web:wght@400;600;700&display=swap"],
    fontFamily: "'Titillium Web', sans-serif",
  },
  Lato: {
    links: ["https://fonts.googleapis.com/css2?family=Lato:wght@400;700&display=swap"],
    fontFamily: "'Lato', sans-serif",
  },
  Fraunces: {
    links: ["https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&display=swap"],
    fontFamily: "'Fraunces', Georgia, serif",
  },
  "Space Grotesk": {
    links: ["https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&display=swap"],
    fontFamily: "'Space Grotesk', system-ui, sans-serif",
  },
  "Baloo 2": {
    links: ["https://fonts.googleapis.com/css2?family=Baloo+2:wght@400;600;800&display=swap"],
    fontFamily: "'Baloo 2', system-ui, sans-serif",
  },
};

const px = (n: number | undefined, fallback: number | undefined): string | undefined => {
  const v = n ?? fallback;
  return v === undefined ? undefined : `${v}px`;
};

function lightValue(theme: ThemeTokens, key: ColorKey, fallback?: ColorKey): string | undefined {
  return (theme[key] as string | undefined) ?? (fallback ? (theme[fallback] as string | undefined) : undefined);
}

function darkValue(theme: ThemeTokens, key: ColorKey, fallback?: ColorKey): string | undefined {
  const dm = theme[`dm__${key}` as keyof ThemeTokens] as string | undefined;
  if (dm !== undefined) return dm;
  const own = theme[key] as string | undefined;
  if (own !== undefined) return own;
  // No value of its own: follow the fallback key's *dark* value (e.g. outline buttons use the dark text colour).
  return fallback ? darkValue(theme, fallback) : undefined;
}

/** Light-mode custom properties for a theme. */
export function themeToVars(theme: ThemeTokens): Record<string, string> {
  const vars: Record<string, string> = {};
  for (const { key, cssVar, fallback } of COLOR_VARS) {
    const v = lightValue(theme, key, fallback);
    if (v !== undefined) vars[cssVar] = v;
  }
  vars[PRIMARY_ON_LIGHT_VAR] = theme.primaryColor;
  for (const { key, cssVar } of RADIUS_VARS) {
    // Every radius variable is always defined (default 0) so stylesheets never need fallbacks.
    vars[cssVar] = px(theme[key], key === "radius" ? 0 : theme.radius ?? 0) as string;
  }
  vars["--ulams-font-family"] = (FONTS[theme.font] ?? FONTS.Inter).fontFamily;
  vars["--ulams-font-family-body"] = (FONTS[theme.bodyFont ?? theme.font] ?? FONTS.Inter).fontFamily;
  return vars;
}

/** Dark-mode overrides (only the variables whose value differs in dark mode). */
export function themeToDarkVars(theme: ThemeTokens): Record<string, string> {
  const light = themeToVars(theme);
  const vars: Record<string, string> = {};
  for (const { key, cssVar, fallback } of COLOR_VARS) {
    const v = darkValue(theme, key, fallback);
    if (v !== undefined && v !== light[cssVar]) vars[cssVar] = v;
  }
  const onLight = theme.dm__primaryColorOnLight ?? theme.dm__primaryColor ?? theme.primaryColor;
  if (onLight !== light[PRIMARY_ON_LIGHT_VAR]) vars[PRIMARY_ON_LIGHT_VAR] = onLight;
  return vars;
}

const block = (selector: string, vars: Record<string, string>): string =>
  `${selector}{${Object.entries(vars)
    .map(([k, v]) => `${k}:${v};`)
    .join("")}}`;

/** Full stylesheet text for a theme: light values on `selector`, dark values under `[data-mode="dark"]`. */
export function themeToCss(theme: ThemeTokens, selector = ":root"): string {
  return (
    block(selector, themeToVars(theme)) +
    block(`${selector}[data-mode="dark"]`, themeToDarkVars(theme))
  );
}
