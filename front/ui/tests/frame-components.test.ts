import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";
import { SANDBOX_H5P, SANDBOX_INTERACTIVE, SANDBOX_LIASCRIPT, SANDBOX_SCORM, SANDBOX_THIRD_PARTY } from "@ulams/sdk/frames";

const dir = join(dirname(fileURLToPath(import.meta.url)), "..", "src", "components");
const source = (name: string) => readFileSync(join(dir, name), "utf8");

describe("player frames in @ulams/ui", () => {
  it.each([
    ["PackageFrame.astro", "SANDBOX_SCORM", SANDBOX_SCORM],
    ["LiaScriptLesson.astro", "SANDBOX_LIASCRIPT", SANDBOX_LIASCRIPT],
    ["H5PFrame.astro", "SANDBOX_H5P", SANDBOX_H5P],
    ["Embed.astro", "SANDBOX_THIRD_PARTY", SANDBOX_THIRD_PARTY],
    ["InteractiveLesson.astro", "SANDBOX_INTERACTIVE", SANDBOX_INTERACTIVE],
  ])("%s uses %s", (file, name, policy) => {
    expect(source(file)).toContain(`sandbox={${name}}`);
    expect(policy).toContain("allow-scripts");
    expect(policy).not.toContain("allow-top-navigation");
  });

  it("both branches of the package frame (content origin and legacy API-origin player) are sandboxed", () => {
    const html = source("PackageFrame.astro");
    expect(html.match(/<iframe/g)?.length).toBe(2);
    expect(html.match(/sandbox=\{SANDBOX_SCORM\}/g)?.length).toBe(2);
  });

  it("the interactive frame runs in an opaque origin: never allow-same-origin, forms or modals", () => {
    const html = source("InteractiveLesson.astro");
    expect(html.match(/<iframe/g)?.length).toBe(1);
    expect(html).not.toContain("allow-same-origin");
    expect(html).toContain('referrerpolicy="no-referrer"');
    expect(SANDBOX_INTERACTIVE).not.toMatch(/allow-same-origin|allow-forms|allow-modals|allow-downloads/);
  });
});
