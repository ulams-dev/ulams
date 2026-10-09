import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";
import { adjustAccent, contrast, parseHex } from "../src/theme/contrast.ts";
import { THEME_PRESETS } from "../src/theme/presets.ts";

describe("theme presets and contrast", () => {
  it("mirror the CSS tokens of each theme", () => {
    for (const [name, preset] of Object.entries(THEME_PRESETS)) {
      const css = readFileSync(fileURLToPath(new URL(`../src/styles/themes/${name}.css`, import.meta.url)), "utf8");
      const token = (n: string) => css.match(new RegExp(`--ulams-color-${n}:\\s*(#[0-9a-f]{6})`, "i"))![1]!.toLowerCase();
      expect({ bg: token("bg"), card: token("card-bg"), text: token("text"), muted: token("muted"), primary: token("primary"), onPrimary: token("on-primary"), accent: token("accent"), border: token("border") }, name).toEqual({
        bg: preset.bg, card: preset.card, text: preset.text, muted: preset.muted, primary: preset.primary, onPrimary: preset.onPrimary, accent: preset.accent, border: preset.border,
      });
    }
  });

  it("adjusts an accent that fails AA and keeps one that passes", () => {
    const bad = adjustAccent("#f5f0e6", "#f6f1e9")!;
    expect(bad.adjusted).toBe(true);
    expect(contrast(parseHex(bad.value)!, parseHex("#f6f1e9")!)).toBeGreaterThanOrEqual(5);
    expect(adjustAccent("#a84521", "#f6f1e9")).toEqual({ value: "#a84521", adjusted: false });
    expect(adjustAccent("nonsense", "#f6f1e9")).toBeNull();
  });
});
