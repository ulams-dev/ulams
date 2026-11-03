import { describe, expect, it } from "vitest";
import { contrastReport, normaliseAccent } from "../src/theme/report.ts";
import { THEME_PRESETS, TENANT_THEMES } from "../src/theme/presets.ts";

describe("contrastReport", () => {
  it("passes an accent that is readable on the preset and says so with the ratios", () => {
    const report = contrastReport("coffee", "#a84521");
    expect(report.valid).toBe(true);
    expect(report.failures).toEqual([]);
    expect(report.suggestion).toBeNull();
    const text = report.checks.find((c) => c.id === "accent-text-page")!;
    expect(text.ratio).toBeGreaterThanOrEqual(4.5);
    expect(text.required).toBe(4.5);
    expect(text.criterion).toBe("1.4.3");
    expect(text.message).toMatch(/\d+(\.\d+)?:1 \(needs 4\.5:1/);
  });

  it("says exactly what fails for a pale accent, per background, with the criterion", () => {
    const report = contrastReport("coffee", "#f5c06a");
    const failed = report.failures.map((c) => c.id);
    expect(failed).toContain("accent-text-page");
    expect(failed).toContain("accent-text-card");
    expect(failed).toContain("accent-ui-page");
    const page = report.failures.find((c) => c.id === "accent-text-page")!;
    expect(page.message).toContain("#f5c06a");
    expect(page.message).toContain("below the 4.5:1");
    expect(page.message).toContain("1.4.3");
    expect(report.failures.find((c) => c.id === "accent-ui-page")!.message).toContain("1.4.11");
  });

  it("suggests the nearest shade that passes every blocking check, and that shade passes", () => {
    const report = contrastReport("coffee", "#f5c06a");
    expect(report.suggestion).toMatch(/^#[0-9a-f]{6}$/);
    expect(contrastReport("coffee", report.suggestion).failures).toEqual([]);
  });

  it("judges against the dark presets' own backgrounds", () => {
    // a dark blue is fine on paper and unreadable on night blue
    expect(contrastReport("coffee", "#1d3b8f").failures).toEqual([]);
    const dark = contrastReport("nightsky", "#1d3b8f");
    expect(dark.failures.map((c) => c.id)).toContain("accent-text-page");
    expect(contrastReport("nightsky", dark.suggestion).failures).toEqual([]);
  });

  it("measures the text on an accent button with the better of white and black", () => {
    const onAccent = contrastReport("coffee", "#c2552d").checks.find((c) => c.id === "on-accent")!;
    expect(onAccent.pass).toBe(true);
    expect(["#ffffff", "#000000"]).toContain(onAccent.foreground);
  });

  it("never blocks on the preset's own rows, and every preset's own text passes", () => {
    for (const name of TENANT_THEMES) {
      const rows = contrastReport(name, "#000000").checks.filter((c) => c.id.startsWith("preset-"));
      expect(rows.every((c) => !c.blocking && c.pass), name).toBe(true);
    }
  });

  it("reports nothing to measure for an invalid accent, still showing the preset rows", () => {
    for (const bad of ["", "red", "#12", "#12345", undefined, null, 5]) {
      const report = contrastReport("poland", bad);
      expect(report.valid).toBe(false);
      expect(report.failures).toEqual([]);
      expect(report.checks.map((c) => c.id)).toEqual(["preset-text", "preset-muted"]);
    }
  });

  it("accepts the preset's own accent on every preset", () => {
    for (const name of TENANT_THEMES) expect(contrastReport(name, THEME_PRESETS[name].primary).failures, name).toEqual([]);
  });
});

describe("normaliseAccent", () => {
  it("lower-cases and expands short hex, refuses the rest", () => {
    expect(normaliseAccent("#C2552D")).toBe("#c2552d");
    expect(normaliseAccent("c2552d")).toBe("#c2552d");
    expect(normaliseAccent("#fff")).toBe("#ffffff");
    expect(normaliseAccent("rgb(1,2,3)")).toBeNull();
    expect(normaliseAccent(null)).toBeNull();
  });
});
