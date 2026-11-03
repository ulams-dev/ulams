/**
 * Tenant accent colour from API settings (`theme.accent`) → CSS custom properties,
 * rendered on the server. The accent is used as is for large type and fills
 * (`--ulams-color-accent`); for text-size use (`--ulams-color-primary`) it is darkened or
 * lightened until it reaches 5:1 on the theme background (so AA 4.5:1 holds on cards too); the text on it
 * (`--ulams-color-on-primary`) is black or white, whichever contrasts more.
 */
import { contrast, parseHex, readableOn, toHex, type Rgb } from "@ulams/ui/theme/contrast.ts";
import { PLATFORM_THEME, THEME_PRESETS, TENANT_THEMES } from "@ulams/ui/theme/presets.ts";

export { contrast, parseHex, readableOn, toHex };
export type { Rgb };

/** Background of each theme: the tenant presets of the shared list, and the product site's own. */
export const THEME_BACKGROUND: Record<string, string> = {
  ...Object.fromEntries(TENANT_THEMES.map((name) => [name, THEME_PRESETS[name].bg])),
  platform: PLATFORM_THEME.bg,
};

/** `[data-theme="x"]{…}` overriding the accent variables, or "" when the accent is missing or invalid. */
export function accentCss(theme: string, accent: unknown): string {
  const rgb = parseHex(accent);
  const bg = parseHex(THEME_BACKGROUND[theme] ?? "#ffffff");
  if (!rgb || !bg) return "";
  // 5:1 on the page background leaves room for the slightly darker card and surface tones
  const primary = readableOn(rgb, bg, 5);
  const onPrimary: Rgb = contrast(primary, [255, 255, 255]) >= contrast(primary, [0, 0, 0]) ? [255, 255, 255] : [0, 0, 0];
  // large display type needs 3:1
  const accentLarge = readableOn(rgb, bg, 3);
  const vars: Record<string, string> = {
    "--ulams-color-accent": toHex(accentLarge),
    "--ulams-color-primary": toHex(primary),
    "--ulams-color-primary-on-light": toHex(primary),
    "--ulams-color-on-primary": toHex(onPrimary),
    "--ulams-color-accent-bg": toHex(rgb),
  };
  return `[data-theme="${theme}"]{${Object.entries(vars)
    .map(([k, v]) => `${k}:${v}`)
    .join(";")}}`;
}
