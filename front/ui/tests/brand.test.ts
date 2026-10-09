import { readdirSync, readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";
import { BRAND_INDIGO, BRAND_ORANGE } from "../src/brand/paths.ts";

/** Orbital Folio (ADR 0038): the colour tokens agree everywhere and the colour rules hold. */
const repo = (path: string) => fileURLToPath(new URL(`../../../${path}`, import.meta.url));
const read = (path: string) => readFileSync(repo(path), "utf8");

function luminance(hex: string): number {
  const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255).map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));
  return 0.2126 * r! + 0.7152 * g! + 0.0722 * b!;
}
function contrast(a: string, b: string): number {
  const [x, y] = [luminance(a), luminance(b)].sort((m, n) => n - m);
  return (x! + 0.05) / (y! + 0.05);
}

const TOKEN_FILES = ["front/ui/src/styles/base.css", "front/docs-site/src/styles/custom.css", "admin/src/global.less"];

describe("brand tokens", () => {
  for (const file of TOKEN_FILES) {
    it(`${file} declares the brand colours`, () => {
      const css = read(file).toLowerCase();
      expect(css).toContain(`--ulams-brand-indigo: ${BRAND_INDIGO.toLowerCase()};`);
      expect(css).toContain(`--ulams-brand-orange: ${BRAND_ORANGE.toLowerCase()};`);
    });
  }

  it("the three token files carry the same blue and sky tints", () => {
    const pick = (file: string, name: string) => read(file).match(new RegExp(`--ulams-brand-${name}:\\s*(#[0-9a-f]{6})`, "i"))?.[1]?.toLowerCase();
    for (const name of ["blue", "sky"]) {
      const values = new Set(TOKEN_FILES.map((f) => pick(f, name)));
      expect(values.size, `${name} differs between files`).toBe(1);
      expect([...values][0]).toBeTruthy();
    }
  });
});

describe("brand contrast (WCAG 2.2 AA)", () => {
  it("indigo carries text on white and on the light page", () => {
    expect(contrast(BRAND_INDIGO, "#ffffff")).toBeGreaterThanOrEqual(4.5);
    expect(contrast(BRAND_INDIGO, "#fafaf9")).toBeGreaterThanOrEqual(4.5);
  });

  it("white text on indigo passes", () => {
    expect(contrast("#ffffff", BRAND_INDIGO)).toBeGreaterThanOrEqual(4.5);
  });

  it("orange fails as text on white: it is an accent and a graphic only", () => {
    expect(contrast(BRAND_ORANGE, "#ffffff")).toBeLessThan(3);
  });

  it("on orange fills, text is indigo, never white", () => {
    expect(contrast(BRAND_INDIGO, BRAND_ORANGE)).toBeGreaterThanOrEqual(4.5);
    expect(contrast("#ffffff", BRAND_ORANGE)).toBeLessThan(4.5);
  });

  it("orange is readable on indigo and on the dark docs page", () => {
    expect(contrast(BRAND_ORANGE, BRAND_INDIGO)).toBeGreaterThanOrEqual(4.5);
    expect(contrast(BRAND_ORANGE, "#0e0e10")).toBeGreaterThanOrEqual(4.5);
  });

  it("the tints used for accent text pass on their backgrounds", () => {
    const blue = read("front/ui/src/styles/base.css").match(/--ulams-brand-blue:\s*(#[0-9a-f]{6})/i)![1]!;
    const sky = read("front/ui/src/styles/base.css").match(/--ulams-brand-sky:\s*(#[0-9a-f]{6})/i)![1]!;
    expect(contrast(blue, "#fafaf9")).toBeGreaterThanOrEqual(4.5);
    expect(contrast(sky, "#0e0e10")).toBeGreaterThanOrEqual(4.5);
    expect(contrast(sky, BRAND_INDIGO)).toBeGreaterThanOrEqual(4.5);
  });
});

describe("brand svg masters", () => {
  const dir = repo("front/docs/design/brand/svg");
  const files = readdirSync(dir).filter((f) => f.endsWith(".svg"));

  it("ships every lockup and colourway", () => {
    for (const form of ["symbol", "wordmark", "logo-horizontal", "logo-stacked"]) {
      for (const way of ["", "-reversed", "-black", "-white"]) expect(files).toContain(`ulams-${form}${way}.svg`);
    }
    for (const f of ["ulams-app-icon-light.svg", "ulams-app-icon-dark.svg", "ulams-app-icon-maskable.svg", "favicon.svg"]) expect(files).toContain(f);
  });

  for (const f of files) {
    it(`${f}: has a viewBox, no raster and no font`, () => {
      const svg = readFileSync(`${dir}/${f}`, "utf8");
      expect(svg).toMatch(/viewBox="[\d. ]+"/);
      expect(svg).not.toMatch(/<image|data:image|<text|font-family|<script|href=/i);
    });
  }
});
