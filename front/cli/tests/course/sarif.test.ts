import { readFileSync } from "node:fs";
import { join } from "node:path";
import Ajv from "ajv";
import { describe, expect, it } from "vitest";
import { CATALOG } from "../../src/course/issues.ts";
import { toSarif, toText } from "../../src/course/sarif.ts";
import { validateFiles } from "../../src/course/validate.ts";
import { fixtures, loadFixture } from "./helpers.ts";

// The SARIF 2.1.0 JSON Schema (draft-07) from https://json.schemastore.org/sarif-2.1.0.json, an OASIS specification.
const schema = JSON.parse(readFileSync(join(fixtures, "sarif-schema-2.1.0.json"), "utf8"));
const validate = new Ajv({ strict: false, allErrors: true, validateFormats: false }).compile(schema);

const issuesOf = (name: string) => {
  const folder = loadFixture(name);
  return validateFiles(folder.files, folder.assets).issues;
};

describe("SARIF output", () => {
  for (const name of ["broken-files", "broken-semantic", "messy"]) {
    it(`${name} is valid SARIF 2.1.0`, () => {
      const sarif = toSarif(issuesOf(name), "0.2.0");
      expect(validate(sarif), JSON.stringify(validate.errors?.slice(0, 3))).toBe(true);
    });
  }

  it("an empty result is valid too", () => {
    expect(validate(toSarif([], "0.2.0"))).toBe(true);
  });

  it("maps severities, rules and locations", () => {
    const sarif = toSarif(issuesOf("broken-semantic"), "0.2.0") as { runs: Array<{ tool: { driver: { rules: Array<{ id: string }> } }; results: Array<Record<string, unknown>> }> };
    const run = sarif.runs[0] as (typeof sarif.runs)[number];
    expect(run.tool.driver.rules.map((r) => r.id)).toEqual(Object.keys(CATALOG));
    const html = run.results.find((r) => r.ruleId === "MARKUP_HTML") as Record<string, unknown>;
    expect(html).toMatchObject({
      level: "error",
      ruleIndex: Object.keys(CATALOG).indexOf("MARKUP_HTML"),
      locations: [{ physicalLocation: { artifactLocation: { uri: "modules/01-a/01-first.md", uriBaseId: "%SRCROOT%" }, region: { startLine: 19, startColumn: 1 } } }],
    });
    expect(run.results.find((r) => r.ruleId === "UNCITED_AUTHOR_BLOCK")).toMatchObject({ level: "warning" });
    const notes = toSarif(issuesOf("broken-files"), "0.2.0") as typeof sarif;
    expect(notes.runs[0]?.results.find((r) => r.ruleId === "LOOSE_MARKDOWN")).toMatchObject({ level: "note" });
  });

  it("a path prefix lets a repository with the course in a subfolder point at the right files", () => {
    const sarif = toSarif(issuesOf("broken-semantic").slice(0, 1), "0.2.0", "courses/k8s/") as { runs: Array<{ results: Array<{ locations: Array<{ physicalLocation: { artifactLocation: { uri: string } } }> }> }> };
    expect(sarif.runs[0]?.results[0]?.locations[0]?.physicalLocation.artifactLocation.uri).toBe("courses/k8s/modules/01-a/01-first.md");
  });
});

describe("text output", () => {
  it("is one line per issue with its hint, then a summary with plurals", () => {
    const text = toText(issuesOf("broken-semantic"), 9);
    expect(text).toContain("modules/01-a/01-first.md:12:66  error   OBJECTIVE_UNKNOWN  ");
    expect(text.split("\n").at(-1)).toBe("15 errors, 1 warning, 0 notes in 9 files");
    expect(toText([], 1)).toBe("0 errors, 0 warnings, 0 notes in 1 file");
  });
});
