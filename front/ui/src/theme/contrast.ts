/**
 * WCAG contrast helpers shared by the site (accent → CSS variables, front/web) and the studio's
 * theme picker, so an accent is judged by one function everywhere.
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

/** Accent as it will render: unchanged when it reaches `ratio` on the background, else the nearest readable shade. */
export function adjustAccent(accent: string, background: string, ratio = 5): { value: string; adjusted: boolean } | null {
  const rgb = parseHex(accent);
  const bg = parseHex(background);
  if (!rgb || !bg) return null;
  const out = readableOn(rgb, bg, ratio);
  const value = toHex(out);
  return { value, adjusted: value !== toHex(rgb) };
}
