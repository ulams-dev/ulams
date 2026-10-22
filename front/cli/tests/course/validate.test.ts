import { describe, expect, it } from "vitest";
import { CATALOG } from "../../src/course/issues.ts";
import { validateFiles } from "../../src/course/validate.ts";
import type { Files } from "../../src/course/types.ts";
import { example, goldenJson, loadFixture } from "./helpers.ts";
import { readFolder } from "../../src/course/disk.ts";

const assets = () => null;
const run = (files: Files) => validateFiles(files, assets).issues;
const lesson = (front: string, body = "") => `---\n${front}\n---\n${body}`;
const baseFiles = (extra: Record<string, string> = {}): Files =>
  new Map(
    Object.entries({
      "ulams.yaml": "format: 1\nblueprint: 3\n",
      "course.yaml": "id: 01jb00000000000000000000c1\ntitle: Course\nlanguage: en\nobjectives: []\n",
      "modules/01-m/module.yaml": "id: 01jb00000000000000000000m1\ntitle: Module\n",
      "modules/01-m/01-l.md": lesson("id: 01jb00000000000000000000l1\ntitle: Lesson\nminutes: 5\norigin: author\nobjectives:\n  - id: 01jb00000000000000000000o1\n    text: Learn it", "\n:::paragraph{#01jb00000000000000000000b1 origin=author cite=\"frg_abcdefabcdef\"}\nHello\n:::\n"),
      ...extra,
    })
  );

describe("validate: golden issue lists", () => {
  for (const name of ["broken-files", "broken-semantic"]) {
    it(name, () => {
      const folder = loadFixture(name);
      const actual = validateFiles(folder.files, folder.assets).issues;
      const golden = goldenJson(`${name}.expected.json`);
      if (process.env.UPDATE_GOLDEN === "1") golden.write(actual);
      expect(actual).toEqual(golden.read());
    });
  }

  it("every code in the catalogue shows up in a golden file or a targeted test below", () => {
    const seen = new Set<string>();
    for (const name of ["broken-files", "broken-semantic"]) for (const i of goldenJson(`${name}.expected.json`).read() as Array<{ code: string }>) seen.add(i.code);
    const targeted = ["FOLDER_NOT_COURSE", "ID_DUPLICATE", "FRONT_MATTER_UNCLOSED", "ORDER_PREFIX_MISSING", "DIRECTIVE_SYNTAX", "BLUEPRINT_SCHEMA", "ASSET_HASH_MISMATCH", "ID_MISSING", "LOOSE_MARKDOWN", "UNCITED_AUTHOR_BLOCK"];
    const missing = Object.keys(CATALOG).filter((c) => !seen.has(c) && !targeted.includes(c));
    expect(missing).toEqual([]);
  });
});

describe("validate: the example course", () => {
  it("has no errors, only the warnings that show what the output looks like", () => {
    const folder = readFolder(example);
    const issues = validateFiles(folder.files, folder.assets).issues;
    expect(issues.filter((i) => i.severity === "error")).toEqual([]);
    expect(new Set(issues.map((i) => i.code))).toEqual(new Set(["UNCITED_AUTHOR_BLOCK"]));
  });
});

describe("validate: targeted", () => {
  it("accepts the smallest valid course", () => expect(run(baseFiles())).toEqual([]));

  it("an empty folder is not a course", () => {
    const issues = run(new Map());
    expect(issues.map((i) => [i.code, i.file])).toEqual([["FOLDER_NOT_COURSE", "course.yaml"], ["FOLDER_NOT_COURSE", "ulams.yaml"]]);
  });

  it("a course without modules fails the Blueprint v3 schema, located in course.yaml's neighbour", () => {
    const files = baseFiles();
    files.delete("modules/01-m/module.yaml");
    files.delete("modules/01-m/01-l.md");
    expect(run(files)).toMatchObject([{ code: "BLUEPRINT_SCHEMA", message: expect.stringContaining("/modules") }]);
  });

  it("Blueprint v3 rules the file schemas cannot see: a SCORM lesson needs its asset", () => {
    const files = baseFiles({ "modules/01-m/01-l.md": lesson("id: 01jb00000000000000000000l1\ntitle: Lesson\nminutes: 5\norigin: author\ncontentType: scorm\nobjectives:\n  - id: 01jb00000000000000000000o1\n    text: Learn it") });
    expect(run(files)).toMatchObject([{ code: "BLUEPRINT_SCHEMA", file: "modules/01-m/01-l.md", line: 2, message: '/modules/0/lessons/0: missing "asset"' }]);
  });

  it("an asset that exists passes, and its hash is checked against the file", () => {
    const front = (sha: string) => `id: 01jb00000000000000000000l1\ntitle: Lesson\nminutes: 5\norigin: author\ncontentType: file\nasset:\n  path: a.txt\n  sha256: "${sha}"\n  bytes: 3\n  mediaType: text/plain\nobjectives:\n  - id: 01jb00000000000000000000o1\n    text: Learn it`;
    const lookup = (p: string) => (p === "assets/a.txt" ? { bytes: 3, sha256: "b".repeat(64) } : null);
    expect(validateFiles(baseFiles({ "modules/01-m/01-l.md": lesson(front("b".repeat(64))) }), lookup).issues).toEqual([]);
    expect(validateFiles(baseFiles({ "modules/01-m/01-l.md": lesson(front("c".repeat(64))) }), lookup).issues).toMatchObject([{ code: "ASSET_HASH_MISMATCH", line: 7 }]);
  });

  it("a shorthand asset path is expanded from the file and passes", () => {
    const front = "id: 01jb00000000000000000000l1\ntitle: Lesson\nminutes: 5\norigin: author\ncontentType: file\nasset: a.txt\nobjectives:\n  - id: 01jb00000000000000000000o1\n    text: Learn it";
    const lookup = (p: string) => (p === "assets/a.txt" ? { bytes: 3, sha256: "b".repeat(64) } : null);
    const r = validateFiles(baseFiles({ "modules/01-m/01-l.md": lesson(front) }), lookup);
    expect(r.issues).toEqual([]);
    expect(((r.doc.modules as Array<{ lessons: Array<{ asset: unknown }> }>)[0] as { lessons: Array<{ asset: unknown }> }).lessons[0]?.asset).toEqual({ path: "a.txt", sha256: "b".repeat(64), bytes: 3, mediaType: "text/plain" });
  });

  it("unclosed front matter", () => {
    const files = baseFiles({ "modules/01-m/01-l.md": "---\ntitle: x\n" });
    expect(run(files).map((i) => i.code)).toContain("FRONT_MATTER_UNCLOSED");
  });

  it("missing numeric prefixes are a warning on folders and files", () => {
    const files = baseFiles();
    files.set("modules/extra/module.yaml", "id: 01jb00000000000000000000m9\ntitle: Extra\n");
    files.set("modules/extra/lesson.md", files.get("modules/01-m/01-l.md") as string);
    const codes = run(files).map((i) => `${i.code}:${i.severity}:${i.file}`);
    expect(codes).toContain("ORDER_PREFIX_MISSING:warning:modules/extra/module.yaml");
    expect(codes).toContain("ORDER_PREFIX_MISSING:warning:modules/extra/lesson.md");
  });

  it("a duplicated id across files points at the second place", () => {
    const files = baseFiles();
    files.set("modules/02-n/module.yaml", "id: 01jb00000000000000000000m1\ntitle: Other\n");
    files.set("modules/02-n/01-x.md", (files.get("modules/01-m/01-l.md") as string).replaceAll("l1", "l2").replaceAll("o1", "o2").replaceAll("b1", "b2"));
    expect(run(files)).toMatchObject([{ code: "ID_DUPLICATE", file: "modules/02-n/module.yaml", line: 1 }]);
  });

  it("uncited author blocks follow the lint level in ulams.yaml: warn, error, off", () => {
    const body = "\n:::paragraph{#01jb00000000000000000000b1 origin=author}\nHello\n:::\n";
    const front = "id: 01jb00000000000000000000l1\ntitle: Lesson\nminutes: 5\norigin: author\nobjectives:\n  - id: 01jb00000000000000000000o1\n    text: Learn it";
    const withLevel = (level: string) => baseFiles({ "ulams.yaml": `format: 1\nblueprint: 3\nlint:\n  uncitedAuthorBlocks: ${level}\n`, "modules/01-m/01-l.md": lesson(front, body) });
    expect(run(withLevel("warn")).map((i) => [i.code, i.severity])).toEqual([["UNCITED_AUTHOR_BLOCK", "warning"]]);
    expect(run(withLevel("error")).map((i) => [i.code, i.severity])).toEqual([["UNCITED_AUTHOR_BLOCK", "error"]]);
    expect(run(withLevel("off"))).toEqual([]);
  });

  it("loose Markdown is a note, not a failure, and still becomes a block", () => {
    const files = baseFiles({ "modules/01-m/01-l.md": lesson("id: 01jb00000000000000000000l1\ntitle: Lesson\nminutes: 5\norigin: author\nobjectives:\n  - id: 01jb00000000000000000000o1\n    text: Learn it", "\nJust a sentence.\n") });
    const r = validateFiles(files, assets);
    expect(r.issues).toMatchObject([{ code: "LOOSE_MARKDOWN", severity: "info", line: 11 }]);
    expect(JSON.stringify(r.doc)).toContain("Just a sentence.");
  });

  it("issues are sorted by file, line and column", () => {
    const issues = validateFiles(loadFixture("broken-files").files, assets).issues;
    const keys = issues.map((i) => `${i.file}\u0000${String(i.line).padStart(6, "0")}${String(i.column).padStart(6, "0")}`);
    expect(keys).toEqual([...keys].sort());
  });

  it("every issue has a 1-based line and column, a hint and a catalogued code", () => {
    for (const name of ["broken-files", "broken-semantic"]) {
      for (const i of validateFiles(loadFixture(name).files, assets).issues) {
        expect(i.line).toBeGreaterThanOrEqual(1);
        expect(i.column).toBeGreaterThanOrEqual(1);
        expect(i.hint.length).toBeGreaterThan(5);
        expect(i.code in CATALOG).toBe(true);
      }
    }
  });
});
