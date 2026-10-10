// @vitest-environment jsdom
import { describe, expect, it } from "vitest";
import { syncLanguageLinks, withHash } from "../../../ui/src/elements/lang-switch.ts";

describe("language switcher keeps the section", () => {
  it("adds the hash to the language home", () => {
    expect(withHash("/pl/", "#compare")).toBe("/pl/#compare");
    expect(withHash("/", "#demos")).toBe("/#demos");
    expect(withHash("/zh/", "")).toBe("/zh/");
    expect(withHash("/zh/", "#")).toBe("/zh/");
    expect(withHash("/pl/#old", "#new")).toBe("/pl/#new");
  });

  it("updates every switcher link from the current hash, and drops it again", () => {
    document.body.innerHTML = `<nav><a data-lang-switch data-base="/" href="/">EN</a><a data-lang-switch data-base="/pl/" href="/pl/">PL</a></nav><a href="/x">other</a>`;
    syncLanguageLinks(document, "#self-host");
    expect([...document.querySelectorAll("a[data-lang-switch]")].map((a) => a.getAttribute("href"))).toEqual(["/#self-host", "/pl/#self-host"]);
    expect(document.querySelector('a[href="/x"]')).not.toBeNull();
    syncLanguageLinks(document, "");
    expect([...document.querySelectorAll("a[data-lang-switch]")].map((a) => a.getAttribute("href"))).toEqual(["/", "/pl/"]);
  });
});
