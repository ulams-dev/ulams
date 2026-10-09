import { describe, it } from "node:test";
import assert from "node:assert/strict";
import { selectLanding } from "../src/pages/landing/selectLanding.ts";
import { experienceFromThemeKey } from "../src/lib/components/theme/experienceKey.ts";
import { contrastRatio, withAccent } from "../src/lib/components/theme/accent.ts";

describe("experienceFromThemeKey", () => {
  it("accepts short keys and registry keys", () => {
    assert.equal(experienceFromThemeKey("coffee"), "coffee");
    assert.equal(experienceFromThemeKey("coffeeTheme"), "coffee");
    assert.equal(experienceFromThemeKey(" OnCall "), "oncall");
    assert.equal(experienceFromThemeKey("nightskyTheme"), "nightsky");
  });

  it("rejects other presets and non-strings", () => {
    for (const value of ["orangeTheme", "contrastTheme", "", null, undefined, 3, {}]) {
      assert.equal(experienceFromThemeKey(value), null);
    }
  });
});

describe("selectLanding", () => {
  it("picks the experience landing as soon as the theme is known", () => {
    assert.equal(selectLanding("coffee", false), "coffee");
    assert.equal(selectLanding("oncallTheme", true), "oncall");
    assert.equal(selectLanding("nightsky", true), "nightsky");
  });

  it("falls back to the default home only once settings are ready", () => {
    assert.equal(selectLanding("orangeTheme", true), "default");
    assert.equal(selectLanding(undefined, true), "default");
    assert.equal(selectLanding(undefined, false), null);
  });
});

describe("withAccent", () => {
  const light = { mode: "light" as const, background: "#F6F1E9", dm__background: "#1E1611", primaryColor: "#C2552D", labelListValueColor: "#C2552D" };
  const dark = { mode: "dark" as const, background: "#F6F8FA", dm__background: "#0B0F14", primaryColor: "#1F6FEB", dm__primaryColor: "#58A6FF" };

  it("replaces the primary colour of the preset's own mode", () => {
    assert.equal(withAccent(dark, "#58A6FF").dm__primaryColor, "#58A6FF");
    assert.equal(withAccent(dark, "#58A6FF").primaryColor, "#1F6FEB");
  });

  it("adjusts an accent that fails AA against the background", () => {
    const themed = withAccent(light, "#C2552D");
    assert.ok(contrastRatio(themed.primaryColor, light.background) >= 4.5);
    assert.equal(themed.labelListValueColor, themed.primaryColor);
  });

  it("ignores values that are not hex colours", () => {
    assert.equal(withAccent(light, "red"), light);
    assert.equal(withAccent(light, undefined), light);
  });
});
