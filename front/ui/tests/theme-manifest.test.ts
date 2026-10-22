import { readFileSync, readdirSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";
import { PLATFORM_THEME, TENANT_THEMES, THEME_PRESETS, themeColorOf, themeManifest } from "../src/theme/presets.ts";
import { THEMES } from "../src/registry.ts";

const here = (path: string) => fileURLToPath(new URL(path, import.meta.url));

describe("the one theme preset list", () => {
  it("has a stylesheet for every preset and a preset for every tenant stylesheet", () => {
    const css = readdirSync(here("../src/styles/themes"))
      .filter((f) => f.endsWith(".css"))
      .map((f) => f.replace(/\.css$/, ""))
      .filter((n) => n !== "platform")
      .sort();
    expect([...TENANT_THEMES].sort()).toEqual(css);
  });

  it("is what the component catalogue calls a theme, plus the product site", () => {
    expect([...THEMES]).toEqual([...TENANT_THEMES, "platform"]);
  });

  it("is written to the catalogue manifest and the API copy, byte for byte", () => {
    const expected = `${JSON.stringify(themeManifest(), null, 2)}\n`;
    expect(readFileSync(here("../catalogue/themes.json"), "utf8")).toBe(expected);
    expect(readFileSync(here("../../../api/packages/settings/resources/themes.json"), "utf8")).toBe(expected);
  });

  it("lists the presets in display order with every token and the browser chrome colour", () => {
    const manifest = JSON.parse(readFileSync(here("../catalogue/themes.json"), "utf8")) as ReturnType<typeof themeManifest>;
    expect(manifest.presets.map((p) => p.name)).toEqual(["coffee", "oncall", "nightsky", "gravity", "poland", "ulam"]);
    for (const preset of manifest.presets) {
      for (const token of ["bg", "card", "text", "muted", "primary", "onPrimary", "accent", "border", "themeColor"] as const) {
        expect(preset[token], `${preset.name}.${token}`).toMatch(/^#[0-9a-f]{6}$/);
      }
      expect(preset.themeColor).toBe(THEME_PRESETS[preset.name as keyof typeof THEME_PRESETS].bg);
    }
    expect(manifest.platform).toEqual(PLATFORM_THEME);
    expect(themeColorOf("poland")).toBe("#f4efe6");
  });
});
