import { describe, expect, it } from "vitest";
import { isExternal, linkTarget } from "../src/lib/links";

describe("isExternal", () => {
  it("treats documentation and GitHub as external", () => {
    expect(isExternal("https://ulams-dev.github.io/ulams/")).toBe(true);
    expect(isExternal("https://github.com/ulams-dev")).toBe(true);
  });

  it("keeps ulams sites, local hosts and relative links in the same tab", () => {
    for (const href of ["https://ulams.app/faq/", "https://demo.ulams.app/", "http://app.localhost:4321/", "/comparison/", "#contact", "mailto:x@y.z", undefined]) {
      expect(isExternal(href)).toBe(false);
    }
  });
});

describe("linkTarget", () => {
  it("opens external links in a new tab without an opener", () => {
    expect(linkTarget("https://github.com/ulams-dev")).toEqual({ target: "_blank", rel: "noopener noreferrer" });
    expect(linkTarget("/faq/")).toEqual({});
  });
});
