import { readFileSync, readdirSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";
import { CATALOG } from "../../src/course/issues.ts";
import { FILE_SCHEMAS, jsonSchemaFor } from "../../src/course/schemas.ts";

const root = join(dirname(fileURLToPath(import.meta.url)), "../..");
const docs = join(root, "../docs-site/src/content/docs/developers");
const format = readFileSync(join(docs, "course-folder-format.mdx"), "utf8");

describe("committed artefacts stay in step with the code", () => {
  it("the JSON Schemas of the files are up to date (run `yarn workspace ulams schemas`)", () => {
    for (const name of Object.keys(FILE_SCHEMAS) as Array<keyof typeof FILE_SCHEMAS>) {
      const committed = readFileSync(join(root, "schemas/course-folder", name), "utf8");
      expect(committed, name).toBe(`${JSON.stringify(jsonSchemaFor(name), null, 2)}\n`);
    }
    expect(readdirSync(join(root, "schemas/course-folder")).sort()).toEqual(Object.keys(FILE_SCHEMAS).sort());
  });

  it("the bundled Blueprint v3 schema is the server's file", () => {
    const server = readFileSync(join(root, "../../api/packages/course-builder/resources/schemas/course-blueprint/v3.json"), "utf8");
    expect(readFileSync(join(root, "src/course/blueprint-v3.json"), "utf8")).toBe(server);
  });

  it("the grammar in the docs is the grammar next to the parser", () => {
    const block = /```ebnf\n[\s\S]*?```/.exec(readFileSync(join(root, "src/course/GRAMMAR.md"), "utf8"))?.[0];
    expect(block).toBeTruthy();
    expect(format).toContain(block as string);
  });

  it("every issue code is in the docs table, with its level, meaning and fix", () => {
    for (const [code, e] of Object.entries(CATALOG)) {
      const row = `| \`${code}\` | ${e.severity}${"fixable" in e && e.fixable ? ", fixable" : ""} | ${e.title} | ${e.hint.replace(/\|/g, "\\|")} |`;
      expect(format, code).toContain(row);
    }
  });

  it("the front matter of every lesson key in the docs table exists in the schema", () => {
    const lesson = jsonSchemaFor("lesson-front-matter.schema.json") as { properties: Record<string, unknown> };
    for (const field of ["id", "key", "title", "summary", "minutes", "objectives", "contentType", "citations", "origin", "status", "flags", "asset", "video", "embed", "selfChecks", "interaction"]) {
      expect(Object.keys(lesson.properties), field).toContain(field);
    }
  });
});
