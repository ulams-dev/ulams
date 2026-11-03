import { mkdtempSync, readFileSync, readdirSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { afterEach, describe, expect, it } from "vitest";
import { mergeDocuments } from "../../src/course/merge.ts";
import { folderForm } from "../../src/course/sync.ts";
import type { Obj } from "../../src/course/types.ts";
import { runCli, type Reply } from "../helpers.ts";
import { fakeCourseAsCode } from "./fake-course-as-code.ts";

/* The response side of the handshake with the real API. `fixtures/real-merge.json` holds what the real endpoints answered
   (api/packages/course-builder/tests/Feature/CourseAsCodeHandshakeTest.php, ULAMS_DUMP_HANDSHAKE=<file>) to the documents
   in api/packages/course-builder/tests/fixtures/handshake/cli-pushes.json, which this CLI built from a folder. Here the CLI
   reads those real answers, and its own merge is checked against the server's on the same documents. */

const here = dirname(fileURLToPath(import.meta.url));
const real = JSON.parse(readFileSync(join(here, "fixtures/real-merge.json"), "utf8")) as {
  version: { data: { id: string; number: number; kind: string; document: Obj } };
  pushes: Record<string, { status: number; response: { data: Obj; code?: string }; baseVersionId: string }>;
  absorb: { export: { data: Obj }; response: Reply["body"] };
};
const pushes = JSON.parse(readFileSync(join(here, "../../../../api/packages/course-builder/tests/fixtures/handshake/cli-pushes.json"), "utf8")) as {
  courseId: number;
  export: { document: Obj };
  scenarios: Array<{ name: string; studio: Obj | null; pushed: Obj; blockId: string }>;
};
const scenario = (name: string) => pushes.scenarios.find((s) => s.name === name) as (typeof pushes.scenarios)[number];

const env = { ULAMS_URL: "http://coffee.localhost", ULAMS_TOKEN: "tok-secret-123456", ULAMS_POLL_MS: "1" };
const cleanups: Array<() => void> = [];
afterEach(() => {
  while (cleanups.length) cleanups.pop()?.();
});
// eslint-disable-next-line @typescript-eslint/no-explicit-any
type Out = Record<string, any>;
const tmp = () => {
  const dir = mkdtempSync(join(tmpdir(), "ulams-real-merge-"));
  cleanups.push(() => rmSync(dir, { recursive: true, force: true }));
  return dir;
};

/** A server holding the real export, whose import endpoint answers with what the real API answered. */
async function setup(name: string | null) {
  const fake = fakeCourseAsCode({ courseId: pushes.courseId, document: pushes.export.document });
  const recorded = name ? real.pushes[name] : undefined;
  const routes = {
    "*": (req: Parameters<typeof fake.handler>[0]) => (recorded && req.method === "POST" && req.path.endsWith("/imports") ? ({ status: recorded.status, body: recorded.response } as Reply) : fake.handler(req)),
  };
  const run = async (...args: string[]) => {
    const r = await runCli(args, { env, routes });
    cleanups.push(r.cleanup);
    return Object.assign(r, { data: (): Out => (r.json().data ?? {}) as Out, error: (): Out => (r.json().error ?? {}) as Out });
  };
  const dir = join(tmp(), "course");
  expect((await run("course", "init", dir, "--from-course", String(pushes.courseId), "--json")).code).toBe(0);
  return { fake, run, dir };
}

/** Writes the folder the way the handshake fixture's author edited it: from the CLI's own pushed document. */
function lessonFile(dir: string): string {
  return join(dir, "modules", readdirSync(join(dir, "modules")).sort()[0] as string, readdirSync(join(dir, "modules", readdirSync(join(dir, "modules")).sort()[0] as string)).filter((n) => n.endsWith(".md")).sort()[0] as string);
}

describe("answers of the real API", () => {
  it("a plain push is a proposed import with no merge", async () => {
    const { run, dir } = await setup("plain");
    const file = lessonFile(dir);
    writeFileSync(file, readFileSync(file, "utf8").replace(/(:::[a-z]+\{[^\n]*\}\n)/, "$1An added sentence. "));
    const r = await run("course", "push", dir, "--json");
    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(r.data()).toMatchObject({ pushed: true, version: { kind: "import", status: "proposed" }, counts: { added: 0, changed: 1, removed: 0 } });
    expect(r.data().merged).toBeNull();
  });

  it("a conflicting push is SYNC_CONFLICT naming the element the real server named", async () => {
    const { run, dir } = await setup("conflict");
    const file = lessonFile(dir);
    writeFileSync(file, readFileSync(file, "utf8").replace(/(:::[a-z]+\{[^\n]*\}\n)[^\n]*/, "$1The author rewrote this paragraph."));
    const r = await run("course", "push", dir, "--json");
    expect(r.code).toBe(6);
    const server = real.pushes.conflict?.response.data.conflicts as Out[];
    expect(r.error()).toMatchObject({ code: "SYNC_CONFLICT", details: { code: "BASE_OUTDATED" } });
    expect(r.error().details.conflicts).toEqual(server);
    expect(r.error().message).toContain(server[0]?.label);
    expect(server[0]?.elementId).toBe(scenario("conflict").blockId);
  });

  it("a push the real server merged shows what came from where", async () => {
    const { run, dir } = await setup("disjoint");
    const file = lessonFile(dir);
    writeFileSync(file, readFileSync(file, "utf8").replace(/(:::[a-z]+\{[^\n]*\}\n)/, "$1An added sentence. "));
    const r = await run("course", "push", dir);
    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(r.stdout).toContain("The server was at version 2: your push was merged with 1 element changed there (1 of yours).");
    expect(r.stdout).toContain("Run `ulams course pull` after approving");
  });

  it("the version payload carries what a merge needs", () => {
    expect(real.version.data).toMatchObject({ number: 1, kind: "content", document: { schemaVersion: 3 } });
    expect(folderForm(real.version.data.document).errors).toEqual([]);
  });

  it("absorb-drift reads the real answer: a proposed author version, remembered in the folder", async () => {
    const { fake, dir } = await setup(null);
    const doc = JSON.parse(JSON.stringify(fake.current().document)) as Out;
    fake.db.absorb = { document: doc, absorbed: [], unmapped: [] };
    const recorded = real.absorb.response as { data: Out };
    const routes = { "*": (req: Parameters<typeof fake.handler>[0]) => (req.path.endsWith("/drift") ? ({ status: 201, body: recorded } as Reply) : fake.handler(req)) };
    const r = await runCli(["course", "absorb-drift", "--dir", dir, "--json"], { env, routes });
    cleanups.push(r.cleanup);
    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(r.json().data).toMatchObject({ version: { kind: "author", status: "proposed" }, absorbed: [{ fields: ["title"] }], unmapped: [] });
    expect(JSON.parse(readFileSync(join(dir, ".ulams/proposal.json"), "utf8"))).toMatchObject({ kind: "absorb", absorbed: [expect.stringMatching(/^course:/)] });
  });

  it("the real export carries the edits an admin made, in the shape status reads", () => {
    expect(real.absorb.export.data.edits).toMatchObject({ absorbable: [{ element: expect.stringMatching(/^course:/), fields: ["title"] }], unmapped: [] });
  });
});

describe("the CLI's merge agrees with the real server's on the same documents", () => {
  const baseDoc = (): Obj => pushes.export.document;

  it("conflict: the same element, the same kind", async () => {
    const s = scenario("conflict");
    const mine = mergeDocuments(baseDoc(), s.pushed, s.studio as Obj);
    const server = real.pushes.conflict?.response.data.conflicts as Out[];
    expect(mine.conflicts.map((c) => [c.id, c.kind])).toEqual(server.map((c) => [c.elementId, c.kind]));
    expect(mine.conflicts[0]?.fields.map((f) => f.field)).toEqual(server[0]?.fields.map((f: Out) => f.field));
  });

  it("delete against edit: the same element, the same kind", () => {
    const s = scenario("delete against edit");
    const mine = mergeDocuments(baseDoc(), s.pushed, s.studio as Obj);
    const server = real.pushes["delete against edit"]?.response.data.conflicts as Out[];
    expect(mine.conflicts.map((c) => [c.id, c.kind])).toEqual(server.map((c) => [c.elementId, c.kind]));
  });

  it("disjoint: no conflict, and the same elements come from each side", () => {
    const s = scenario("disjoint");
    const mine = mergeDocuments(baseDoc(), s.pushed, s.studio as Obj);
    const merged = real.pushes.disjoint?.response.data.merged as Out;
    expect(mine.conflicts).toEqual([]);
    expect(mine.fromOurs).toEqual(merged.fromPush);
    expect(mine.fromTheirs).toEqual(merged.fromServer);
  });

  it("and pull writes the conflict files for exactly the elements the real server refused", async () => {
    const { fake, run, dir } = await setup(null);
    const s = scenario("conflict");
    // the studio changed the block, the author's folder changed it differently
    fake.studioEdit((doc) => {
      const studio = (((s.studio as Obj).modules as Out[])[0] as Out).lessons[0].blocks[0].markdown as string;
      (((doc.modules as Out[])[0] as Out).lessons[0] as Out).blocks[0].markdown = studio;
    });
    const file = lessonFile(dir);
    writeFileSync(file, readFileSync(file, "utf8").replace(/(:::[a-z]+\{[^\n]*\}\n)[^\n]*/, "$1The author rewrote this paragraph."));
    const pull = await run("course", "pull", dir, "--json");
    expect(pull.code, pull.stdout + pull.stderr).toBe(6);
    const server = real.pushes.conflict?.response.data.conflicts as Out[];
    expect(pull.data().conflicts.map((c: Out) => [c.element, c.kind])).toEqual(server.map((c) => [c.elementId, c.kind]));
  });
});
