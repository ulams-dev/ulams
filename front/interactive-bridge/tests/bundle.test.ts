import { execFileSync } from "node:child_process";
import { readFileSync } from "node:fs";
import { gzipSync } from "node:zlib";
import { describe, expect, it } from "vitest";

const root = new URL("../", import.meta.url).pathname;

describe("bundle", () => {
  it("is one file with the MIT header, under 3 KB min+gzip, with no import statements", () => {
    execFileSync("node", ["scripts/bundle.mjs"], { cwd: root });
    const code = readFileSync(`${root}dist/interactive-bridge.js`);
    expect(code.toString()).toMatch(/^\/\*! @ulams\/interactive-bridge .* MIT License/);
    expect(code.toString()).not.toMatch(/\bimport\s*[({"']/);
    expect(gzipSync(code, { level: 9 }).length).toBeLessThan(3 * 1024);
  });
});

describe("manifest schema copy", () => {
  it("is identical to the one the API ships (when the API package exists)", () => {
    const here = readFileSync(`${root}schema/ulams-interactive.v1.json`, "utf8");
    let api: string | null;
    try {
      api = readFileSync(`${root}../../api/packages/interactive/resources/schemas/ulams-interactive/v1.json`, "utf8");
    } catch {
      api = null;
    }
    if (api !== null) expect(api).toBe(here);
  });
});
