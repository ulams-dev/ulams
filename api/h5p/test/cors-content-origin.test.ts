import { describe, expect, it } from "vitest";

import { DEFAULT_FRONT_ORIGIN_PATTERNS as DEFAULTS } from "../src/config";
import {
  deriveCorsOrigins,
  isContentOrigin,
} from "../src/tenancy/EnvFileTenantResolver";

const DEFAULT_FRONT_ORIGIN_PATTERNS = DEFAULTS.split(",");

describe("CORS allow-list and the content origin", () => {
  it("never contains a content origin, even when a pattern, a static entry or an env file names one", () => {
    const origins = deriveCorsOrigins({
      staticOrigins: [
        "http://localhost:3000",
        "https://content.ulams.app",
        "https://coffee.content.ulams.app",
      ],
      patterns: [
        ...DEFAULT_FRONT_ORIGIN_PATTERNS,
        "https://{slug}.content.ulams.app",
      ],
      slug: "coffee",
      frontendUrl: "https://coffee.ulams.app",
      adminUrl: "https://coffee.content.ulams.app",
    });

    expect(origins).toContain("https://coffee.ulams.app");
    expect(origins).toContain("http://localhost:3000");
    expect(origins.filter(isContentOrigin)).toEqual([]);
  });

  it("does not flag app origins that merely contain the word", () => {
    expect(isContentOrigin("https://coffee.ulams.app")).toBe(false);
    expect(isContentOrigin("https://mycontent.ulams.app")).toBe(false);
    expect(isContentOrigin("https://coffee.content.ulams.app")).toBe(true);
    expect(isContentOrigin("http://content.localhost")).toBe(true);
  });

  it("the default patterns of a fresh install name no content origin", () => {
    expect(
      DEFAULT_FRONT_ORIGIN_PATTERNS.map((p) => p.replace("{slug}", "x")).filter(
        isContentOrigin
      )
    ).toEqual([]);
  });
});
