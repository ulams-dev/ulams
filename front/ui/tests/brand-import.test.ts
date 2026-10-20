import { describe, expect, it } from "vitest";
import { extractColours, parseColour, proposeBrandTheme, themeDiff } from "../src/theme/brand-import.ts";

describe("parseColour", () => {
  it("reads hex, rgb and hsl, and ignores words that look like hex", () => {
    expect(parseColour("#C2552D")).toEqual([194, 85, 45]);
    expect(parseColour("#fff")).toEqual([255, 255, 255]);
    expect(parseColour("#0b0f14cc")).toEqual([11, 15, 20]);
    expect(parseColour("rgb(20, 20, 40)")).toEqual([20, 20, 40]);
    expect(parseColour("hsl(0 100% 50%)")).toEqual([255, 0, 0]);
    expect(parseColour("decade")).toBeNull();
    expect(parseColour("transparent")).toBeNull();
  });
});

describe("extractColours", () => {
  it("reads nested JSON with the key path as the name", () => {
    expect(extractColours('{"primary":"#c2552d","colors":{"text":"hsl(0 0% 10%)"},"name":"Acme"}')).toEqual([
      { name: "primary", hex: "#c2552d" },
      { name: "colors.text", hex: "#1a1a1a" },
    ]);
  });
  it("reads CSS custom properties", () => {
    const css = ":root { --brand-primary: #c2552d; --bg: #ffffff; font-family: fade; }";
    expect(extractColours(css)).toEqual([
      { name: "--brand-primary", hex: "#c2552d" },
      { name: "--bg", hex: "#ffffff" },
    ]);
  });
  it("reads colours from a URL query and from plain text", () => {
    expect(extractColours("https://x.test/brand?primary=%23c2552d&bg=ffffff")).toEqual([
      { name: "primary", hex: "#c2552d" },
      { name: "bg", hex: "#ffffff" },
    ]);
    expect(extractColours("Our colours: #0b0f14 and #58a6ff").map((c) => c.hex)).toEqual(["#0b0f14", "#58a6ff"]);
  });
  it("finds nothing in prose or malformed JSON numbers", () => {
    expect(extractColours("We love coffee")).toEqual([]);
    expect(extractColours("42")).toEqual([]);
  });
});

describe("proposeBrandTheme", () => {
  it("proposes a light preset and keeps an accent that is readable", () => {
    const proposal = proposeBrandTheme('{"primary":"#a84521","background":"#ffffff","text":"#1a1a1a"}')!;
    expect(proposal.accent).toBe("#a84521");
    expect(proposal.theme).toBe("coffee");
    expect(proposal.renderedAccent).toBe("#a84521");
    expect(proposal.settings).toEqual({ theme: { theme: "coffee", accent: "#a84521" } });
    expect(proposal.checks.every((c) => c.pass)).toBe(true);
    expect(proposal.checks.map((c) => c.label)).toContain("Brand text on brand background");
  });

  it("chooses a dark preset from a dark brand background", () => {
    const proposal = proposeBrandTheme("--primary: #3dd6f5; --background: #05070f;")!;
    expect(["oncall", "nightsky", "gravity"]).toContain(proposal.theme);
    expect(proposal.theme).toBe("gravity");
  });

  it("flags a weak accent and says what it renders as", () => {
    const proposal = proposeBrandTheme("--brand: #f5d90a; --bg: #ffffff;")!;
    expect(proposal.checks[0]!.pass).toBe(false);
    expect(proposal.renderedAccent).not.toBe(proposal.accent);
    expect(proposal.checks[1]!.pass).toBe(true);
    expect(proposal.notes.join(" ")).toMatch(/too weak/);
  });

  it("infers the mood from a light text colour when there is no background", () => {
    const proposal = proposeBrandTheme('{"accent":"#ffd23f","text":"#f2f0ff"}')!;
    expect(["oncall", "nightsky", "gravity"]).toContain(proposal.theme);
  });

  it("falls back to the most saturated colour and assumes a light theme", () => {
    const proposal = proposeBrandTheme("#eeeeee #1d3b8f #888888")!;
    expect(proposal.accent).toBe("#1d3b8f");
    expect(proposal.theme).toBe("ulam");
    expect(proposal.notes.join(" ")).toMatch(/light theme is assumed/);
  });

  it("returns null when nothing coloured is in the input", () => {
    expect(proposeBrandTheme("nothing here")).toBeNull();
    expect(proposeBrandTheme("#ffffff #eeeeee #000000")).toBeNull();
  });

  it("reports low contrast of brand text on the brand background", () => {
    const proposal = proposeBrandTheme('{"primary":"#a84521","background":"#ffffff","text":"#bbbbbb"}')!;
    expect(proposal.checks.find((c) => c.label.startsWith("Brand text"))!.pass).toBe(false);
  });
});

describe("themeDiff", () => {
  const proposal = proposeBrandTheme('{"primary":"#a84521","background":"#ffffff"}')!;
  it("lists only what changes", () => {
    expect(themeDiff(proposal, { theme: "coffee", accent: "#C2552D" })).toEqual([{ field: "theme.accent", from: "#C2552D", to: "#a84521" }]);
    expect(themeDiff(proposal, { theme: "coffee", accent: "#A84521" })).toEqual([]);
    expect(themeDiff(proposal, null)).toEqual([
      { field: "theme.theme", from: null, to: "coffee" },
      { field: "theme.accent", from: null, to: "#a84521" },
    ]);
  });
});
