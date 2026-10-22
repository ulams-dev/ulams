import { describe, expect, it } from "vitest";
import { applyPlan, changedPaths, formatFiles, planFormat } from "../../src/course/fmt.ts";
import { validateFiles } from "../../src/course/validate.ts";
import type { Files } from "../../src/course/types.ts";
import { counterMint, example, goldenFiles, loadFixture, sortedEntries } from "./helpers.ts";
import { readFolder } from "../../src/course/disk.ts";
import { folderFromBlueprint } from "../../src/course/from-blueprint.ts";
import { loadCourse } from "../../src/course/load.ts";

const fmt = (files: Files, assets: (p: string) => { bytes: number; sha256: string } | null = () => null) => formatFiles(files, { mint: counterMint(), assets });

describe("fmt: the messy fixture", () => {
  const folder = loadFixture("messy");
  const { files: formatted, plan } = fmt(folder.files, folder.assets);

  it("has nothing blocking", () => expect(plan.blocking).toEqual([]));

  it("renumbers folders and files, keeping the name part", () => {
    expect(plan.dirRenames).toEqual([
      { from: "modules/2-intro", to: "modules/01-intro" },
      { from: "modules/setup", to: "modules/02-setup" },
    ]);
    expect([...plan.fileRenames]).toEqual([
      ["modules/01-intro/10-pods.md", "modules/01-intro/01-pods.md"],
      ["modules/01-intro/install.md", "modules/01-intro/02-install.md"],
      ["modules/01-intro/install.quiz.yaml", "modules/01-intro/02-install.quiz.yaml"],
      ["modules/02-setup/guide.md", "modules/02-setup/01-guide.md"],
    ]);
  });

  it("matches the golden output", () => {
    const { expected } = goldenFiles("messy.expected", formatted);
    expect(sortedEntries(formatted)).toEqual(sortedEntries(expected));
  });

  it("keeps comments, mints ids, wraps loose Markdown, expands the asset and prints LF line ends", () => {
    const pods = formatted.get("modules/01-intro/01-pods.md") as string;
    expect(pods).toContain("# a comment inside the front matter survives");
    expect(pods).toMatch(/^---\nid: m\d+\ntitle: Pods\nminutes: 10\nobjectives:/);
    expect(pods).toContain(":::paragraph{#m");
    expect(pods).toContain("origin=author}\nLoose after a directive.\n:::");
    expect(formatted.get("course.yaml")).toContain("# Course-level fields");
    expect(formatted.get("ulams.yaml")).toMatch(/^# the course this folder is bound to|format: 1/m);
    expect(formatted.get("modules/02-setup/01-guide.md")).toMatch(/asset:\n {2}path: notes.txt\n {2}sha256: [0-9a-f]{64}\n {2}bytes: 15\n {2}mediaType: text\/plain/);
    for (const [, text] of formatted) expect(text).not.toContain("\r");
  });

  it("the result is formatted: fmt changes nothing the second time", () => {
    const again = fmt(formatted, folder.assets);
    expect(again.plan.writes.size).toBe(0);
    expect(again.plan.dirRenames).toEqual([]);
    expect([...again.plan.fileRenames]).toEqual([]);
    expect(sortedEntries(again.files)).toEqual(sortedEntries(formatted));
  });

  it("the result validates (the messy folder only lacked formatting, ids and prefixes)", () => {
    const before = validateFiles(folder.files, folder.assets).issues.filter((i) => i.severity === "error").map((i) => i.code);
    expect(new Set(before)).toEqual(new Set(["ID_MISSING", "CITATION_MISSING"]));
    const after = validateFiles(formatted, folder.assets).issues.filter((i) => i.severity === "error");
    expect(after.map((i) => i.code)).toEqual(["CITATION_MISSING"]);
  });
});

describe("fmt and the blueprint agree on one canonical form", () => {
  // File names carry a slug the document does not hold, so compare by position: module/lesson numbers and content.
  // YAML comments are the author's own and not part of the document, so they are left out of the comparison.
  const noComments = (text: string) => text.replace(/\n*^[ \t]*#.*$/gm, "");
  const byPosition = (files: Files) =>
    sortedEntries(files)
      .filter(([path]) => path !== "ulams.yaml")
      .map(([path, text]) => [path.replace(/^modules\/(\d+)-[^/]*\/(\d+)-[^.]*/, "modules/$1/$2").replace(/^modules\/(\d+)-[^/]*\//, "modules/$1/"), noComments(text)]);

  it("from(to(fmt(folder))) is byte-identical to fmt(folder), for the messy fixture", () => {
    const folder = loadFixture("messy");
    const { files: formatted } = fmt(folder.files, folder.assets);
    const doc = loadCourse(formatted, folder.assets).doc;
    expect(byPosition(folderFromBlueprint(doc))).toEqual(byPosition(formatted));
  });

  it("and for the example course", () => {
    const folder = readFolder(example);
    const { files: formatted } = fmt(folder.files, folder.assets);
    const doc = loadCourse(formatted, folder.assets).doc;
    expect(byPosition(folderFromBlueprint(doc))).toEqual(byPosition(formatted));
  });
});

describe("fmt: behaviour", () => {
  it("a formatted course is left alone (the example)", () => {
    const folder = readFolder(example);
    const { plan } = fmt(folder.files, folder.assets);
    expect(plan.blocking).toEqual([]);
    expect(changedPaths(folder.files, plan)).toEqual([]);
  });

  it("refuses to touch anything while a file has a syntax error, and says why", () => {
    const folder = loadFixture("broken-files");
    const plan = planFormat(folder.files, { mint: counterMint(), assets: folder.assets });
    expect(plan.blocking.length).toBeGreaterThan(0);
    expect(plan.writes.size).toBe(0);
    expect(plan.blocking.every((i) => i.severity === "error" && i.line >= 1)).toBe(true);
    expect(plan.blocking.map((i) => i.code)).toContain("DIRECTIVE_UNCLOSED");
  });

  it("changedPaths lists rewritten and moved files by their final names", () => {
    const folder = loadFixture("messy");
    const plan = planFormat(folder.files, { mint: counterMint(), assets: folder.assets });
    expect(changedPaths(folder.files, plan)).toContain("modules/01-intro/02-install.md");
    expect(changedPaths(folder.files, plan)).toContain("course.yaml");
  });

  it("renumbering is stable and compacts gaps", () => {
    const front = (n: number) => `---\nid: 01jb0000000000000000000l0${n}\ntitle: L${n}\nminutes: 1\norigin: author\nobjectives:\n  - id: 01jb0000000000000000000o0${n}\n    text: Objective ${n}\n---\n`;
    const files: Files = new Map([
      ["ulams.yaml", "format: 1\nblueprint: 3\n"],
      ["course.yaml", "id: 01jb00000000000000000000c1\ntitle: Course\nlanguage: en\n"],
      ["modules/05-a/module.yaml", "id: 01jb00000000000000000000m1\ntitle: A\n"],
      ["modules/05-a/03-x.md", front(1)],
      ["modules/05-a/03-x.quiz.yaml", "id: 01jb00000000000000000q01\nquestions: []\n"],
      ["modules/05-a/07-y.md", front(2)],
      ["modules/05-a/notes.txt", ""],
    ]);
    const { files: out, plan } = fmt(files);
    expect(plan.blocking).toEqual([]);
    expect([...out.keys()].sort()).toEqual(["course.yaml", "modules/01-a/01-x.md", "modules/01-a/01-x.quiz.yaml", "modules/01-a/02-y.md", "modules/01-a/module.yaml", "modules/01-a/notes.txt", "ulams.yaml"]);
    expect(fmt(out).plan.dirRenames).toEqual([]);
  });

  it("applyPlan moves everything under a renamed module, including files the format does not use", () => {
    const files: Files = new Map([["modules/9-a/module.yaml", "id: 01jb00000000000000000000m1\ntitle: A\n"], ["modules/9-a/readme.txt", ""]]);
    const plan = planFormat(files, { mint: counterMint(), assets: () => null });
    expect([...applyPlan(files, plan).keys()].sort()).toEqual(["modules/01-a/module.yaml", "modules/01-a/readme.txt"]);
  });

  it("a block's attributes keep their values and come out in one order", () => {
    const folder = loadFixture("messy");
    const { files } = fmt(folder.files, folder.assets);
    const pods = files.get("modules/01-intro/01-pods.md") as string;
    expect(pods).toContain(':::paragraph{#01jb0000000000000000000b01 origin=author}');
    expect(pods).toContain('cite="frg_abcdefabcdef" objectives="01jb00000000000000000000o4"');
  });
});
