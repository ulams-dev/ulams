import { describe, expect, it } from "vitest";
import { ctaEvent } from "../src/lib/analytics.ts";

describe("ctaEvent", () => {
  it("names the demo and docs conversions", () => {
    expect(ctaEvent("#demos")).toBe("demos_scroll");
    expect(ctaEvent("https://coffee.ulams.app/learn/1")).toBe("demo_open");
    expect(ctaEvent("https://github.com/ulams-dev")).toBe("github_open");
    expect(ctaEvent("https://ulams-dev.github.io/ulams/")).toBe("docs_open");
  });

  it("names the two home call-to-action pages in every language", () => {
    for (const prefix of ["", "/pl", "/zh"]) {
      expect(ctaEvent(`${prefix}/content-cost/`)).toBe("cost_page_open");
      expect(ctaEvent(`${prefix}/reporting/`)).toBe("reporting_page_open");
    }
  });

  it("ignores other relative links", () => {
    expect(ctaEvent("/faq/")).toBeUndefined();
    expect(ctaEvent("/reporting/extra/")).toBeUndefined();
  });
});
