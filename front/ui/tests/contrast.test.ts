import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";

/** WCAG 2.2 contrast of the theme tokens that carry text or essential icons. */
const dir = fileURLToPath(new URL("../src/styles/themes/", import.meta.url));

function tokens(theme: string): Record<string, string> {
  const css = readFileSync(`${dir}${theme}.css`, "utf8");
  const block = css.slice(css.indexOf(`[data-theme="${theme}"] {`), css.indexOf("}", css.indexOf(`[data-theme="${theme}"] {`)));
  return Object.fromEntries([...block.matchAll(/--ulams-([\w-]+):\s*(#[0-9a-f]{6})/gi)].map((m) => [m[1]!, m[2]!.toLowerCase()]));
}

function luminance(hex: string): number {
  const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255).map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));
  return 0.2126 * r! + 0.7152 * g! + 0.0722 * b!;
}

export function contrast(a: string, b: string): number {
  const [x, y] = [luminance(a), luminance(b)].sort((m, n) => n - m);
  return (x! + 0.05) / (y! + 0.05);
}

const TEXT_PAIRS: Array<[string, string]> = [
  ["color-text", "color-bg"],
  ["color-muted", "color-bg"],
  ["color-text", "color-card-bg"],
  ["color-muted", "color-card-bg"],
  ["color-primary-on-light", "color-bg"],
  ["color-on-primary", "color-primary"],
  ["color-header", "color-bg"],
  ["color-secondary", "color-bg"],
];
const UI_PAIRS: Array<[string, string]> = [
  ["color-bg", "color-positive"],
  ["color-primary", "color-bg"],
  // the icon of the update notices sits on a card
  ["color-primary-on-light", "color-card-bg"],
];

describe("theme contrast (WCAG 2.2 AA)", () => {
  for (const theme of ["coffee", "oncall", "nightsky", "gravity", "poland", "ulam"]) {
    const t = tokens(theme);
    it(`${theme}: body text pairs reach 4.5:1`, () => {
      for (const [fg, bg] of TEXT_PAIRS) {
        expect(t[fg], `${theme} --ulams-${fg}`).toBeDefined();
        expect(contrast(t[fg]!, t[bg]!), `${theme} ${fg} on ${bg}`).toBeGreaterThanOrEqual(4.5);
      }
    });
    it(`${theme}: icons and controls reach 3:1`, () => {
      for (const [fg, bg] of UI_PAIRS) expect(contrast(t[fg]!, t[bg]!), `${theme} ${fg} on ${bg}`).toBeGreaterThanOrEqual(3);
    });
  }
});
