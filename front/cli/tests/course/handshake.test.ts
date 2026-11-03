import { existsSync, mkdirSync, readFileSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";
import { folderFromBlueprint } from "../../src/course/from-blueprint.ts";
import { type Obj } from "../../src/course/types.ts";
import { validateFiles } from "../../src/course/validate.ts";

/* The request side of the handshake with the real API. `api/packages/course-builder/tests/fixtures/handshake/cli-pushes.json`
   holds documents this CLI builds from a course folder (the folder written from a real export, then edited as an author
   would), and the server-side edits they are pushed against. `CourseAsCodeHandshakeTest` (PHPUnit) replays them against the
   real endpoints and asserts the answers; this test keeps the file in step with the CLI (UPDATE_GOLDEN=1 rewrites it). */

const here = dirname(fileURLToPath(import.meta.url));
const exported = JSON.parse(readFileSync(join(here, "fixtures/real-export.json"), "utf8")) as { courseId: number; document: Obj };
const target = join(here, "../../../../api/packages/course-builder/tests/fixtures/handshake/cli-pushes.json");

const clone = <T>(x: T): T => JSON.parse(JSON.stringify(x)) as T;
type Mod = { lessons: Array<{ title: string; blocks: Array<{ id: string; markdown: string }> }> };
const modules = (doc: Obj): Mod[] => doc.modules as unknown as Mod[];

/** What `course push` sends for a folder: written from the export, edited in place, loaded back. */
function pushed(edit: (files: Map<string, string>) => void): Obj {
  const files = folderFromBlueprint(exported.document);
  edit(files);
  const { doc, issues } = validateFiles(files, () => null);
  expect(issues.filter((i) => i.severity === "error")).toEqual([]);
  return doc;
}

const lessonFile = (files: Map<string, string>, n: number): string => [...files.keys()].filter((p) => /^modules\/[^/]+\/[^/]+\.md$/.test(p)).sort()[n] as string;

/** Replaces the first line of the text of the `index`-th block of lesson `n`. */
function editBlock(files: Map<string, string>, n: number, index: number, text: (before: string) => string): void {
  const path = lessonFile(files, n);
  const lines = (files.get(path) as string).split("\n");
  let seen = -1;
  for (let i = 0; i < lines.length; i++) {
    if (/^:::[a-z]+\{/.test(lines[i] as string) && ++seen === index) {
      lines[i + 1] = text(lines[i + 1] as string);
      break;
    }
  }
  files.set(path, lines.join("\n"));
}

function removeBlock(files: Map<string, string>, n: number, index: number): void {
  const path = lessonFile(files, n);
  const lines = (files.get(path) as string).split("\n");
  let seen = -1;
  for (let i = 0; i < lines.length; i++) {
    if (/^:::[a-z]+\{/.test(lines[i] as string) && ++seen === index) {
      let end = i + 1;
      while (!/^:::\s*$/.test(lines[end] as string)) end++;
      lines.splice(i, end - i + 1 + (lines[end + 1] === "" ? 1 : 0));
      break;
    }
  }
  files.set(path, lines.join("\n"));
}

function scenarios() {
  const studioTitle = clone(exported.document);
  (modules(studioTitle)[1] as Mod).lessons[0]!.title = "Measuring your coffee, retitled in the studio";
  const studioBlock = clone(exported.document);
  (modules(studioBlock)[0] as Mod).lessons[0]!.blocks[0]!.markdown = "The studio rewrote this paragraph.";
  const studioSecond = clone(exported.document);
  (modules(studioSecond)[0] as Mod).lessons[0]!.blocks[1]!.markdown = "The studio rewrote the second paragraph.";
  return [
    { name: "plain", description: "based on the current version: a proposed import", studio: null, pushed: pushed((f) => editBlock(f, 0, 0, (t) => `${t} An added sentence.`)), blockId: modules(exported.document)[0]?.lessons[0]?.blocks[0]?.id },
    { name: "disjoint", description: "the studio retitled another lesson: one merged proposal on top of it", studio: studioTitle, pushed: pushed((f) => editBlock(f, 0, 0, (t) => `${t} An added sentence.`)), blockId: modules(exported.document)[0]?.lessons[0]?.blocks[0]?.id },
    { name: "conflict", description: "both sides rewrote the same block: BASE_OUTDATED with the element", studio: studioBlock, pushed: pushed((f) => editBlock(f, 0, 0, () => "The author rewrote this paragraph.")), blockId: modules(exported.document)[0]?.lessons[0]?.blocks[0]?.id },
    { name: "delete against edit", description: "the author removed a block the studio edited", studio: studioSecond, pushed: pushed((f) => removeBlock(f, 0, 1)), blockId: modules(exported.document)[0]?.lessons[0]?.blocks[1]?.id },
  ];
}

describe("documents the CLI pushes, as the API handshake fixture holds them", () => {
  const fresh = { courseId: exported.courseId, export: exported, scenarios: scenarios() };
  it("are the documents this CLI builds today (UPDATE_GOLDEN=1 rewrites the file)", () => {
    if (process.env.UPDATE_GOLDEN === "1" || !existsSync(target)) {
      mkdirSync(dirname(target), { recursive: true });
      writeFileSync(target, `${JSON.stringify(fresh, null, 1)}\n`);
    }
    expect(JSON.parse(readFileSync(target, "utf8"))).toEqual(JSON.parse(JSON.stringify(fresh)));
  });

  it("differ from the export only where the author edited", () => {
    const plain = fresh.scenarios[0] as { pushed: Obj };
    expect(modules(plain.pushed)[0]?.lessons[0]?.blocks[0]?.markdown).toContain("An added sentence.");
    expect(modules(plain.pushed)[1]).toEqual(modules(exported.document)[1]);
  });
});
