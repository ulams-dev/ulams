import { describe, expect, it } from "vitest";
import { THEME_PRESETS } from "@ulams/ui/theme/presets.ts";
import { accentCss, contrast, parseHex, readableOn, THEME_BACKGROUND } from "../../src/lib/accent.ts";

const varOf = (css: string, name: string) => css.match(new RegExp(`${name}:(#[0-9a-f]{6})`))?.[1];

describe("tenant accent from settings", () => {
  it("parses hex colours and rejects anything else", () => {
    expect(parseHex("#C2552D")).toEqual([194, 85, 45]);
    expect(parseHex("fd3")).toEqual([255, 221, 51]);
    expect(parseHex("red")).toBeNull();
    expect(parseHex("#12345g")).toBeNull();
    expect(accentCss("coffee", "javascript:alert(1)")).toBe("");
    expect(accentCss("coffee", undefined)).toBe("");
  });

  it.each([
    ["coffee", "#C2552D"],
    ["oncall", "#58A6FF"],
    ["nightsky", "#FFD23F"],
    ["gravity", "#3DD6F5"],
    ["poland", "#C8102E"],
    ["ulam", "#1D3B8F"],
    ["coffee", "#FFE600"],
    ["oncall", "#1A1A2E"],
    ["nightsky", "#C2552D"],
    ["gravity", "#C2552D"],
    ["coffee", "#0B6E4F"],
    ["poland", "#FFD23F"],
  ])("%s with %s: text colour reaches AA on the background, on-primary on the fill", (theme, accent) => {
    const css = accentCss(theme, accent);
    const bg = parseHex(THEME_BACKGROUND[theme])!;
    const primary = parseHex(varOf(css, "--ulams-color-primary"))!;
    const onPrimary = parseHex(varOf(css, "--ulams-color-on-primary"))!;
    const large = parseHex(varOf(css, "--ulams-color-accent"))!;
    expect(contrast(primary, bg)).toBeGreaterThanOrEqual(5);
    expect(contrast(onPrimary, primary)).toBeGreaterThanOrEqual(4.5);
    // small text is also set in the accent (format tags, the letter signature): AA for normal text
    expect(contrast(large, bg)).toBeGreaterThanOrEqual(4.5);
    // and on the cards (lighter than the page on a dark theme, darker on a light one), where text in the accent sits too
    const card = parseHex(THEME_PRESETS[theme as keyof typeof THEME_PRESETS].card)!;
    expect(contrast(primary, card)).toBeGreaterThanOrEqual(4.5);
    expect(contrast(large, card)).toBeGreaterThanOrEqual(4.5);
    expect(css.startsWith(`[data-theme="${theme}"]{`)).toBe(true);
  });

  it("keeps an accent that already contrasts", () => {
    expect(readableOn([88, 166, 255], parseHex("#0b0f14")!)).toEqual([88, 166, 255]);
  });
});
