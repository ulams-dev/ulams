/**
 * Tenant accent colour (`theme.accent` setting) applied on top of a preset.
 *
 * The accent replaces the primary colour of the preset's own mode (`primaryColor`
 * for light presets, `dm__primaryColor` for dark ones). If it is below WCAG AA
 * (4.5:1) against the preset background it is darkened (light) or lightened
 * (dark) in small steps until it passes, so text links and buttons stay readable.
 * No imports, so it can be unit-tested with `node --test`.
 */

const HEX = /^#?([0-9a-f]{3}|[0-9a-f]{6})$/i;

export function parseHex(value: string): [number, number, number] | null {
  const match = HEX.exec(value.trim());
  if (!match) return null;
  let hex = match[1];
  if (hex.length === 3) hex = hex.split("").map((c) => c + c).join("");
  return [0, 2, 4].map((i) => parseInt(hex.slice(i, i + 2), 16)) as [number, number, number];
}

const toHex = (rgb: [number, number, number]) =>
  "#" + rgb.map((c) => Math.round(Math.min(255, Math.max(0, c))).toString(16).padStart(2, "0")).join("").toUpperCase();

const channel = (c: number) => {
  const s = c / 255;
  return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
};

export function luminance(hex: string): number {
  const rgb = parseHex(hex);
  if (!rgb) return 0;
  return 0.2126 * channel(rgb[0]) + 0.7152 * channel(rgb[1]) + 0.0722 * channel(rgb[2]);
}

export function contrastRatio(a: string, b: string): number {
  const la = luminance(a);
  const lb = luminance(b);
  return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
}

/** Moves `color` towards black (or white) until it reaches `min` contrast against `background`. */
export function ensureContrast(color: string, background: string, min = 4.5): string {
  const rgb = parseHex(color);
  if (!rgb || !parseHex(background)) return color;
  const towardsWhite = luminance(background) < 0.18;
  let current = toHex(rgb);
  for (let step = 0; step < 40 && contrastRatio(current, background) < min; step++) {
    const [r, g, b] = parseHex(current) as [number, number, number];
    current = towardsWhite
      ? toHex([r + (255 - r) * 0.06, g + (255 - g) * 0.06, b + (255 - b) * 0.06])
      : toHex([r * 0.94, g * 0.94, b * 0.94]);
  }
  return current;
}

interface AccentTarget {
  mode?: "light" | "dark";
  background: string;
  dm__background: string;
  primaryColor: string;
  dm__primaryColor?: string;
  labelListValueColor?: string;
}

/** Returns a copy of `theme` with the accent applied (or `theme` itself when `accent` is not a hex colour). */
export function withAccent<T extends AccentTarget>(theme: T, accent: unknown): T {
  if (typeof accent !== "string" || !parseHex(accent)) return theme;
  const hex = toHex(parseHex(accent) as [number, number, number]);
  if (theme.mode === "dark") {
    return { ...theme, dm__primaryColor: ensureContrast(hex, theme.dm__background) };
  }
  const primary = ensureContrast(hex, theme.background);
  return {
    ...theme,
    primaryColor: primary,
    labelListValueColor:
      theme.labelListValueColor === theme.primaryColor ? primary : theme.labelListValueColor,
  };
}
