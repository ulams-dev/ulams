/**
 * Tenant accent colour from API settings (`theme.accent`) → CSS custom properties,
 * rendered on the server. The accent is used as is for large type and fills
 * (`--ulams-color-accent`); for text-size use (`--ulams-color-primary`) it is darkened or
 * lightened until it reaches 5:1 on the theme background (so AA 4.5:1 holds on cards too); the text on it
 * (`--ulams-color-on-primary`) is black or white, whichever contrasts more.
 */

export type Rgb = [number, number, number];

export function parseHex(value: unknown): Rgb | null {
  if (typeof value !== "string") return null;
  const m = value.trim().match(/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i);
  if (!m) return null;
  const hex = m[1]!.length === 3 ? [...m[1]!].map((c) => c + c).join("") : m[1]!;
  return [0, 2, 4].map((i) => parseInt(hex.slice(i, i + 2), 16)) as Rgb;
}

export const toHex = (rgb: Rgb): string => `#${rgb.map((c) => Math.round(Math.max(0, Math.min(255, c))).toString(16).padStart(2, "0")).join("")}`;

function luminance([r, g, b]: Rgb): number {
  const lin = (c: number) => {
    const s = c / 255;
    return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
  };
  return 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
}

export function contrast(a: Rgb, b: Rgb): number {
  const [x, y] = [luminance(a), luminance(b)].sort((m, n) => n - m);
  return (x! + 0.05) / (y! + 0.05);
}

const mix = (a: Rgb, b: Rgb, t: number): Rgb => [0, 1, 2].map((i) => a[i]! + (b[i]! - a[i]!) * t) as Rgb;

/** The accent moved towards black (light backgrounds) or white (dark ones) until it reaches `ratio`. */
export function readableOn(accent: Rgb, background: Rgb, ratio = 4.5): Rgb {
  if (contrast(accent, background) >= ratio) return accent;
  const target: Rgb = luminance(background) > 0.5 ? [0, 0, 0] : [255, 255, 255];
  for (let t = 0.04; t <= 1; t += 0.04) {
    const candidate = mix(accent, target, t);
    if (contrast(candidate, background) >= ratio) return candidate;
  }
  return target;
}

/** Background of each tenant theme (must match src/styles/themes in @ulams/ui). */
export const THEME_BACKGROUND: Record<string, string> = {
  coffee: "#f6f1e9",
  oncall: "#0b0f14",
  nightsky: "#13153a",
  platform: "#fafaf9",
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
