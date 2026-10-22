import { mkdtempSync, readFileSync, readdirSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { afterEach, describe, expect, it } from "vitest";
import { readFolder } from "../../src/course/disk.ts";
import { folderFromBlueprint } from "../../src/course/from-blueprint.ts";
import { compareDocuments, documentHash, folderForm } from "../../src/course/sync.ts";
import type { Obj } from "../../src/course/types.ts";
import { validateFiles } from "../../src/course/validate.ts";
import { runCli } from "../helpers.ts";
import { fakeCourseAsCode } from "./fake-course-as-code.ts";

/* `fixtures/real-export.json` is the payload `GET /api/admin/course-as-code/courses/{id}/blueprint` returned for a course the builder
   generated from api/packages/course-builder/resources/fixtures/coffee-brewing.md (fake AI driver, quiz and final test on). Against
   that real server (CourseAsCodeTest setup, 2026-10-22) the documents this CLI builds from the folder behaved like this:
     - the folder written from the export and read back, pushed untouched  -> 200 unchanged: true
     - one sentence added to the first block of the first lesson, pushed   -> 201, counts {changed: 1}, only that block
     - approve -> 200, apply -> 202, export: appliedVersionId == versionId, publish -> 200
   The tests below pin the CLI side of that contract with the same document. */

const fixture = JSON.parse(readFileSync(join(dirname(fileURLToPath(import.meta.url)), "fixtures/real-export.json"), "utf8")) as { courseId: number; document: Obj };
const doc = fixture.document;
const env = { ULAMS_URL: "http://coffee.localhost", ULAMS_TOKEN: "tok-secret-123456", ULAMS_POLL_MS: "1" };

const cleanups: Array<() => void> = [];
afterEach(() => {
  while (cleanups.length) cleanups.pop()?.();
});

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type Out = Record<string, any>;
const tmp = () => {
  const dir = mkdtempSync(join(tmpdir(), "ulams-real-"));
  cleanups.push(() => rmSync(dir, { recursive: true, force: true }));
  return dir;
};

describe("a course exported by the real API", () => {
  it("is written to a folder that validates with no issue at all and reads back to the same document", () => {
    const form = folderForm(doc);
    expect(form.errors).toEqual([]);
    expect(documentHash(form.doc)).toBe(documentHash(doc));
    expect(compareDocuments(doc, form.doc)).toEqual([]);
    const { issues } = validateFiles(folderFromBlueprint(doc), () => null);
    expect(issues).toEqual([]);
  });

  it("goes through init, edit, push, apply and publish without a spurious change", async () => {
    const fake = fakeCourseAsCode({ courseId: fixture.courseId, document: doc });
    const dir = join(tmp(), "coffee");
    const run = async (...args: string[]) => {
      const r = await runCli(args, { env, routes: fake.routes });
      cleanups.push(r.cleanup);
      return Object.assign(r, { data: (): Out => (r.json().data ?? {}) as Out });
    };
    expect((await run("course", "init", dir, "--from-course", String(fixture.courseId), "--json")).code).toBe(0);
    expect((await run("course", "diff", dir, "--exit-code", "--json")).code).toBe(0);
    expect((await run("course", "diff", dir, "--remote", "--exit-code", "--json")).code).toBe(0); // the folder equals the server field by field
    expect((await run("course", "push", dir, "--json")).data().unchanged).toBe(true);

    const lesson = readdirSync(join(dir, "modules/01-how-coffee-brewing-works")).find((n) => n.endsWith(".md")) as string;
    const file = join(dir, "modules/01-how-coffee-brewing-works", lesson);
    writeFileSync(file, readFileSync(file, "utf8").replace(/(:::paragraph\{[^\n]*\}\n)/, "$1An added sentence from the repository. "));

    const pushed = await run("course", "push", dir, "--apply", "--json");
    expect(pushed.code, pushed.stdout + pushed.stderr).toBe(0);
    expect(pushed.data()).toMatchObject({ counts: { added: 0, changed: 1, removed: 0 }, approved: true, applied: true });
    expect(pushed.data().changes).toHaveLength(1);
    expect(pushed.data().changes[0]).toMatchObject({ kind: "changed", type: "block", fields: [{ field: "markdown" }] });
    expect(JSON.stringify(fake.db.live)).toContain("An added sentence from the repository.");

    expect((await run("course", "publish", "--dir", dir, "--json")).data().published).toBe(true);
    const status = await run("course", "status", dir, "--json");
    expect(status.data()).toMatchObject({ local: { state: "clean" }, remote: { state: "in-sync", applied: true }, next: [] });
    // the folder after the loop is a valid course folder again
    const folder = readFolder(dir);
    expect(validateFiles(folder.files, folder.assets).issues.filter((i) => i.severity === "error")).toEqual([]);
  });
});
