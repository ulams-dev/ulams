import { existsSync, mkdtempSync, readFileSync, readdirSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { afterEach, describe, expect, it } from "vitest";
import { readFolder } from "../../src/course/disk.ts";
import { validateFiles } from "../../src/course/validate.ts";
import { runCli } from "../helpers.ts";
import { fakeCourseAsCode, type FakeOptions } from "./fake-course-as-code.ts";

/* The three-way merge through the real command line and the real file system, against an in-memory server with the rules
   of the course-as-code API (milestone CAC-4): `course pull` merges, conflicts become files, `course resolve` settles them,
   `push` refuses while one is open, and `course absorb-drift` reads admin edits back into a proposal. */

const TOKEN = "tok-secret-123456";
const env = { ULAMS_URL: "http://coffee.localhost", ULAMS_TOKEN: TOKEN, ULAMS_POLL_MS: "1" };
const INSTALL = "modules/01-basics/01-install.md";
const PODS = "modules/01-basics/02-pods.md";
const FIRST = "kubectl is the command-line tool for Kubernetes clusters.";
const OURS = "kubectl is the CLI for Kubernetes clusters.";
const THEIRS = "kubectl is the Kubernetes command line.";

const cleanups: Array<() => void> = [];
afterEach(() => {
  while (cleanups.length) cleanups.pop()?.();
});

type Fake = ReturnType<typeof fakeCourseAsCode>;
// eslint-disable-next-line @typescript-eslint/no-explicit-any
type Out = Record<string, any>;

async function cli(fake: Fake, args: string[], extraEnv: Record<string, string> = {}) {
  const r = await runCli(args, { env: { ...env, ...extraEnv }, routes: fake.routes });
  cleanups.push(r.cleanup);
  const body = (): Out => r.json() as Out;
  return Object.assign(r, { body, data: (): Out => (body().data ?? {}) as Out, error: (): Out => (body().error ?? {}) as Out });
}

function tmp(): string {
  const dir = mkdtempSync(join(tmpdir(), "ulams-merge-"));
  cleanups.push(() => rmSync(dir, { recursive: true, force: true }));
  return dir;
}

async function initialised(options: FakeOptions = {}): Promise<{ fake: Fake; dir: string }> {
  const fake = fakeCourseAsCode(options);
  const dir = join(tmp(), "course");
  const r = await cli(fake, ["course", "init", dir, "--from-course", String(fake.courseId), "--json"]);
  expect(r.code, r.stdout + r.stderr).toBe(0);
  return { fake, dir };
}

const read = (dir: string, rel: string) => readFileSync(join(dir, rel), "utf8");
const write = (dir: string, rel: string, text: string) => writeFileSync(join(dir, rel), text);
const edit = (dir: string, rel: string, from: string, to: string) => {
  const text = read(dir, rel);
  expect(text, `${rel} should contain ${from}`).toContain(from);
  write(dir, rel, text.replace(from, to));
};
const base = (dir: string) => JSON.parse(read(dir, ".ulams/base.json")) as Out;
const conflictFiles = (dir: string) => (existsSync(join(dir, ".ulams/conflicts")) ? readdirSync(join(dir, ".ulams/conflicts")).sort() : []);
const learnerText = (fake: Fake) => JSON.stringify(fake.db.live);
const firstBlock = (doc: Out): Out => doc.modules[0].lessons[0].blocks[0];

/** The studio changes the first block of the first lesson (approved, applied). */
const studioBlock = (fake: Fake, text: string) => fake.studioEdit((doc) => void (firstBlock(doc).markdown = text));

/** Both sides changed the first block: the folder to OURS, the studio to THEIRS. Returns the pulled folder with one open conflict. */
async function conflicted() {
  const { fake, dir } = await initialised();
  studioBlock(fake, THEIRS);
  edit(dir, INSTALL, FIRST, OURS);
  const pull = await cli(fake, ["course", "pull", dir, "--json"]);
  expect(pull.code, pull.stdout + pull.stderr).toBe(6);
  const id = pull.data().conflicts[0].element as string;
  return { fake, dir, id, pull };
}

const setResolution = (dir: string, id: string, body: string) => {
  const file = `.ulams/conflicts/${id}.md`;
  const text = read(dir, file);
  const at = text.lastIndexOf("## Resolution");
  const open = text.indexOf("~~~ulams-element\n", at);
  const close = text.lastIndexOf("\n~~~");
  write(dir, file, `${text.slice(0, open)}~~~ulams-element\n${body}${text.slice(close)}`);
};

describe("course pull: a three-way merge", () => {
  it("writes a conflict file for an element both sides changed, keeps yours, moves the base, and exits 6", async () => {
    const { fake, dir, id, pull } = await conflicted();
    expect(pull.data()).toMatchObject({
      state: "conflicts",
      version: { number: 2 },
      base: { versionNumber: 1 },
      conflicts: [{ type: "block", label: "Lesson 1.1 > block 1", kind: "edit-edit", fields: ["markdown"], file: `.ulams/conflicts/${id}.md` }],
    });
    expect(conflictFiles(dir)).toEqual([`${id}.md`]);
    expect(read(dir, INSTALL)).toContain(OURS);
    expect(read(dir, INSTALL)).not.toContain(THEIRS);
    expect(base(dir).baseVersionNumber).toBe(2);
    expect(fake.db.calls.filter((c) => c.startsWith("GET /versions/")).length).toBe(1); // the base version, to merge against

    const file = read(dir, `.ulams/conflicts/${id}.md`);
    expect(file).toContain("# Conflict: Lesson 1.1 > block 1");
    expect(file).toContain("Both sides changed this element differently.");
    expect(file).toContain("## Base (version 1)");
    expect(file).toContain("## Server (version 2)");
    expect(file).toContain(FIRST);
    expect(file).toContain(`-${OURS}`);
    expect(file).toContain(`+${THEIRS}`);
    expect(file).toContain(`ulams course resolve ${id} --take file`);
    expect(file).toMatch(/## Resolution\n\n~~~ulams-element\n:::paragraph\{#[0-9a-z]{26} cite="[^"]+"[^}]*\}\nkubectl is the CLI/);
  });

  it("prints the conflicts in text and tells you what to do", async () => {
    const { fake, dir } = await initialised();
    studioBlock(fake, THEIRS);
    edit(dir, INSTALL, FIRST, OURS);
    const r = await cli(fake, ["course", "pull", dir]);
    expect(r.code).toBe(6);
    expect(r.stdout).toContain("Merged the server's version 2 into your folder (based on version 1)");
    expect(r.stdout).toContain("1 conflict: both sides changed the same element differently.");
    expect(r.stdout).toMatch(/! Lesson 1\.1 > block 1 \(block [0-9a-z]{26}, edit-edit\) {2}-> {2}\.ulams\/conflicts\/[0-9a-z]{26}\.md/);
    expect(r.stdout).toContain("ulams course resolve <element> --take local|remote|file");
  });

  it("status lists the open conflicts, and push and pull refuse until they are settled, before any request", async () => {
    const { fake, dir, id } = await conflicted();
    const status = await cli(fake, ["course", "status", dir, "--json"]);
    expect(status.data().conflicts).toEqual([id]);
    expect(status.data().next[0]).toContain("course resolve");

    const calls = fake.db.calls.length;
    const push = await cli(fake, ["course", "push", dir, "--json"]);
    expect(push.code).toBe(6);
    expect(push.error()).toMatchObject({ code: "SYNC_CONFLICT", details: { code: "OPEN_CONFLICTS", conflicts: [id] } });
    expect(push.requests).toHaveLength(0);
    const dry = await cli(fake, ["course", "push", dir, "--dry-run", "--json"]);
    expect(dry.code).toBe(6);

    fake.studioEdit((doc) => void ((doc.modules as Out[])[1] as Out).lessons[0].minutes++);
    const pull = await cli(fake, ["course", "pull", dir, "--json"]);
    expect(pull.code, pull.stdout + pull.stderr).toBe(6);
    expect(pull.error().details.code).toBe("OPEN_CONFLICTS");
    expect(fake.db.calls.length).toBe(calls + 1); // the pull's own export; no merge request, nothing sent
  });

  it("--dry-run reports the merge and writes nothing", async () => {
    const { fake, dir } = await initialised();
    studioBlock(fake, THEIRS);
    edit(dir, INSTALL, FIRST, OURS);
    const before = read(dir, INSTALL);
    const baseBefore = read(dir, ".ulams/base.json");
    const r = await cli(fake, ["course", "pull", dir, "--dry-run", "--json"]);
    expect(r.code).toBe(6);
    expect(r.data()).toMatchObject({ state: "conflicts", dryRun: true });
    expect(read(dir, INSTALL)).toBe(before);
    expect(read(dir, ".ulams/base.json")).toBe(baseBefore);
    expect(conflictFiles(dir)).toEqual([]);
  });

  it("leaves untouched files exactly as the author wrote them, comments and all", async () => {
    const { fake, dir } = await initialised();
    write(dir, "course.yaml", `# kept by the author\n${read(dir, "course.yaml")}`);
    edit(dir, PODS, "A pod is", "A pod is truly");
    fake.studioEdit((doc) => void (((doc.modules as Out[])[0] as Out).lessons[0].minutes = 31));
    const r = await cli(fake, ["course", "pull", dir, "--json"]);
    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(r.data().state).toBe("merged");
    expect(read(dir, "course.yaml").startsWith("# kept by the author\n")).toBe(true);
    expect(read(dir, PODS)).toContain("A pod is truly");
    expect(read(dir, INSTALL)).toContain("minutes: 31");
    expect(r.data().files).toMatchObject({ updated: [INSTALL], written: [], removed: [] });
  });

  it("takes an element the server added and one it removed, without a conflict", async () => {
    const { fake, dir } = await initialised();
    fake.studioEdit((doc) => {
      const lesson = (((doc.modules as Out[])[0] as Out).lessons as Out[])[1] as Out;
      lesson.blocks.push({ id: "0new0000000000000000000001", kind: "paragraph", markdown: "Added in the studio.", citations: lesson.blocks[0].citations });
      (doc.modules as Out[]).splice(1, 1); // and a whole module removed
    });
    edit(dir, INSTALL, FIRST, OURS);
    const r = await cli(fake, ["course", "pull", dir, "--json"]);
    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(r.data().state).toBe("merged");
    expect(r.data().fromServer.map((x: Out) => x.kind).sort()).toContain("added");
    expect(read(dir, PODS)).toContain("Added in the studio.");
    expect(readdirSync(join(dir, "modules"))).toEqual(["01-basics"]);
    const folder = readFolder(dir);
    expect(validateFiles(folder.files, folder.assets).issues.filter((i) => i.severity === "error")).toEqual([]);
  });
});

describe("course resolve", () => {
  it("--take local keeps your version, deletes the conflict file, and the push goes through", async () => {
    const { fake, dir, id } = await conflicted();
    const r = await cli(fake, ["course", "resolve", id, "--dir", dir, "--take", "local", "--json"]);
    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(r.data()).toMatchObject({ element: id, take: "local", remaining: [] });
    expect(conflictFiles(dir)).toEqual([]);
    expect(existsSync(join(dir, ".ulams/conflicts"))).toBe(false);
    expect(read(dir, INSTALL)).toContain(OURS);

    const push = await cli(fake, ["course", "push", dir, "--apply", "--json"]);
    expect(push.code, push.stdout + push.stderr).toBe(0);
    expect(learnerText(fake)).toContain(OURS);
    expect((await cli(fake, ["course", "status", dir, "--json"])).data()).toMatchObject({ local: { state: "clean" }, conflicts: [], next: [] });
  });

  it("--take remote replaces the element with the server's text", async () => {
    const { fake, dir, id } = await conflicted();
    const r = await cli(fake, ["course", "resolve", id, "--dir", dir, "--take", "remote", "--json"]);
    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(r.data().files.updated).toEqual([INSTALL]);
    expect(read(dir, INSTALL)).toContain(THEIRS);
    expect(read(dir, INSTALL)).not.toContain(OURS);
    expect(conflictFiles(dir)).toEqual([]);
    // the folder now equals the server: nothing to push
    const push = await cli(fake, ["course", "push", dir, "--json"]);
    expect(push.data()).toMatchObject({ unchanged: true, pushed: false });
  });

  it("--take file uses the edited Resolution section and pushes it", async () => {
    const { fake, dir, id } = await conflicted();
    const text = read(dir, `.ulams/conflicts/${id}.md`);
    const directive = /## Resolution\n\n~~~ulams-element\n(:::paragraph\{[^\n]*\}\n)[^\n]*\n:::/.exec(text);
    expect(directive).not.toBeNull();
    setResolution(dir, id, `${directive?.[1]}kubectl is the CLI, the Kubernetes command line.\n:::`);
    const r = await cli(fake, ["course", "resolve", id, "--dir", dir, "--take", "file", "--json"]);
    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(read(dir, INSTALL)).toContain("kubectl is the CLI, the Kubernetes command line.");
    expect(conflictFiles(dir)).toEqual([]);

    const push = await cli(fake, ["course", "push", dir, "--apply", "--json"]);
    expect(push.code, push.stdout + push.stderr).toBe(0);
    expect(push.data()).toMatchObject({ approved: true, applied: true, counts: { changed: 1 } });
    expect(push.data().merged).toBeNull();
    expect(learnerText(fake)).toContain("the CLI, the Kubernetes command line.");
    // the server's own merge rule agrees: the version is a child of the current one
    expect(fake.current().parentId).toBe(fake.db.versions[1]?.id);
  });

  it("refuses a resolution that is not valid, with a sentence, and keeps the conflict", async () => {
    const { fake, dir, id } = await conflicted();
    setResolution(dir, id, "just some words");
    const r = await cli(fake, ["course", "resolve", id, "--dir", dir, "--take", "file", "--json"]);
    expect(r.code).toBe(2);
    expect(r.error().message).toContain("exactly one block directive");
    expect(r.error().hint).toContain(`.ulams/conflicts/${id}.md`);
    expect(conflictFiles(dir)).toEqual([`${id}.md`]);

    setResolution(dir, id, `:::paragraph{#0other00000000000000000001 cite="frg_abcdefabcdef"}\ntext\n:::`);
    const renamed = await cli(fake, ["course", "resolve", id, "--dir", dir, "--take", "file", "--json"]);
    expect(renamed.code).toBe(2);
    expect(renamed.error().message).toContain("id");
    expect(read(dir, INSTALL)).toContain(OURS);
  });

  it("explains an element that has no open conflict", async () => {
    const { fake, dir, id } = await conflicted();
    const r = await cli(fake, ["course", "resolve", "0nothere0000000000000000000", "--dir", dir, "--take", "local", "--json"]);
    expect(r.code).toBe(2);
    expect(r.error().message).toContain("no open conflict");
    expect(r.error().hint).toContain(id);
    expect(conflictFiles(dir)).toEqual([`${id}.md`]);
  });

  it("--dry-run shows the files and keeps the conflict", async () => {
    const { fake, dir, id } = await conflicted();
    const r = await cli(fake, ["course", "resolve", id, "--dir", dir, "--take", "remote", "--dry-run", "--json"]);
    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(r.data()).toMatchObject({ take: "remote", dryRun: true, files: { updated: [INSTALL] } });
    expect(read(dir, INSTALL)).toContain(OURS);
    expect(conflictFiles(dir)).toEqual([`${id}.md`]);
  });

  it("with two conflicts, each is settled on its own and the last one says what comes next", async () => {
    const { fake, dir } = await initialised();
    fake.studioEdit((doc) => {
      firstBlock(doc).markdown = THEIRS;
      (((doc.modules as Out[])[0] as Out).lessons as Out[])[1]!.title = "Run a pod, the studio's way";
    });
    edit(dir, INSTALL, FIRST, OURS);
    edit(dir, PODS, "title: Run a pod", "title: Run a pod, my way");
    const pull = await cli(fake, ["course", "pull", dir, "--json"]);
    expect(pull.code).toBe(6);
    const found = pull.data().conflicts as Out[];
    expect(found.map((c) => c.type).sort()).toEqual(["block", "lesson"]);
    const lesson = found.find((c) => c.type === "lesson") as Out;
    const block = found.find((c) => c.type === "block") as Out;

    const first = await cli(fake, ["course", "resolve", lesson.element, "--dir", dir, "--take", "remote", "--json"]);
    expect(first.data().remaining).toEqual([block.element]);
    expect(read(dir, PODS)).toContain("Run a pod, the studio's way");
    const text = await cli(fake, ["course", "resolve", block.element, "--dir", dir, "--take", "local"]);
    expect(text.stdout).toContain("No conflicts left. Next: `ulams course validate`, then `ulams course push`.");
  });
});

describe("delete against edit, and the elements below a removed one", () => {
  it("you removed a block the server edited: the conflict shows (removed), and --take remote brings the server's block back", async () => {
    const { fake, dir } = await initialised();
    studioBlock(fake, THEIRS);
    const text = read(dir, INSTALL);
    const start = text.indexOf(":::paragraph");
    const end = text.indexOf("\n:::\n", start) + 5;
    write(dir, INSTALL, text.slice(0, start) + text.slice(end).replace(/^\n+/, ""));
    const pull = await cli(fake, ["course", "pull", dir, "--json"]);
    expect(pull.code, pull.stdout + pull.stderr).toBe(6);
    const c = pull.data().conflicts[0] as Out;
    expect(c).toMatchObject({ kind: "removed-ours", type: "block" });
    expect(read(dir, INSTALL)).not.toContain(THEIRS);
    const file = read(dir, c.file as string);
    expect(file).toContain("You removed this element");
    expect(file).toMatch(/## Yours \(folder\)\n\n~~~ulams-element\n\(removed\)\n~~~/);
    expect(file).toContain(THEIRS);

    const r = await cli(fake, ["course", "resolve", c.element, "--dir", dir, "--take", "remote", "--json"]);
    expect(r.code, r.stdout + r.stderr).toBe(0);
    const body = read(dir, INSTALL);
    expect(body).toContain(THEIRS);
    expect(body.indexOf(THEIRS)).toBeLessThan(body.indexOf("kubectl version --client")); // back in first place
    const folder = readFolder(dir);
    expect(validateFiles(folder.files, folder.assets).issues.filter((i) => i.severity === "error")).toEqual([]);
  });

  it("the server removed a lesson you edited: yours stays whole, --take remote removes it with everything in it", async () => {
    const { fake, dir } = await initialised();
    fake.studioEdit((doc) => void (((doc.modules as Out[])[0] as Out).lessons as Out[]).splice(1, 1));
    edit(dir, PODS, "A pod is", "A pod is truly");
    const pull = await cli(fake, ["course", "pull", dir, "--json"]);
    expect(pull.code, pull.stdout + pull.stderr).toBe(6);
    expect(pull.data().conflicts).toHaveLength(1);
    const c = pull.data().conflicts[0] as Out;
    expect(c).toMatchObject({ kind: "removed-theirs", type: "lesson", held: expect.any(Number) });
    expect(c.held).toBeGreaterThan(0);
    expect(existsSync(join(dir, PODS))).toBe(true);
    expect(read(dir, PODS)).toContain("A pod is truly");

    const r = await cli(fake, ["course", "resolve", c.element, "--dir", dir, "--take", "remote", "--json"]);
    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(existsSync(join(dir, PODS))).toBe(false);
    expect(existsSync(join(dir, "modules/01-basics/02-pods.quiz.yaml"))).toBe(false);
    expect((await cli(fake, ["course", "push", dir, "--json"])).data().unchanged).toBe(true);
  });
});

describe("a push that conflicts on the server", () => {
  it("lists the conflicting elements, and pull then writes the same conflicts as files", async () => {
    const { fake, dir } = await initialised();
    studioBlock(fake, THEIRS);
    edit(dir, INSTALL, FIRST, OURS);
    const push = await cli(fake, ["course", "push", dir, "--json"]);
    expect(push.code).toBe(6);
    const listed = push.error().details.conflicts as Out[];
    expect(listed).toHaveLength(1);
    const pull = await cli(fake, ["course", "pull", dir, "--json"]);
    expect(pull.data().conflicts.map((c: Out) => c.element)).toEqual(listed.map((c) => c.elementId));
  });
});

describe("course absorb-drift", () => {
  const drifted = async () => {
    const { fake, dir } = await initialised();
    const doc = JSON.parse(JSON.stringify(fake.current().document)) as Out;
    doc.course.title = "Kubernetes 101, the admin's title";
    const key = `course:${doc.course.id as string}`;
    fake.db.drift = [{ element: key, title: "Kubernetes 101" }];
    fake.db.edits = { absorbable: [{ element: key, title: "Kubernetes 101", fields: ["title"] }], unmapped: [] };
    fake.db.absorb = { document: doc, absorbed: [{ key, element: doc.course.id as string, title: "Kubernetes 101", fields: ["title"] }], unmapped: [] };
    return { fake, dir, key };
  };

  it("status points at the edits made in the LMS and the command that reads them back", async () => {
    const { fake, dir } = await drifted();
    const s = await cli(fake, ["course", "status", dir, "--json"]);
    expect(s.data().remote.edits.absorbable).toHaveLength(1);
    expect(s.data().next.join(" ")).toContain("ulams course absorb-drift");
    const text = await cli(fake, ["course", "status", dir]);
    expect(text.stdout).toContain("LMS edits: 1 can be read back");
  });

  it("--preview lists what would be read back and stores nothing", async () => {
    const { fake, dir } = await drifted();
    const before = fake.db.versions.length;
    const r = await cli(fake, ["course", "absorb-drift", "--dir", dir, "--preview", "--json"]);
    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(r.requests.find((q) => q.path.endsWith("/drift"))?.body).toEqual({ dryRun: true });
    expect(r.data()).toMatchObject({ version: null, dryRun: true, absorbed: [{ fields: ["title"] }] });
    expect(fake.db.versions.length).toBe(before);
    expect(existsSync(join(dir, ".ulams/proposal.json"))).toBe(false);
  });

  it("proposes a version, remembers it, and apply --approve approves it and writes the same values back", async () => {
    const { fake, dir, key } = await drifted();
    const r = await cli(fake, ["course", "absorb-drift", "--dir", dir, "--json"]);
    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(r.data()).toMatchObject({ version: { kind: "author", status: "proposed", number: 2 }, counts: { changed: 1 } });
    expect(JSON.parse(read(dir, ".ulams/proposal.json"))).toMatchObject({ kind: "absorb", versionNumber: 2, absorbed: [key] });
    const text = await cli(fake, ["course", "absorb-drift", "--dir", dir, "--preview"]);
    expect(text.stdout).toContain("~ Kubernetes 101 (title)");

    // nothing is approved until a person does it, and approving writes the same values back (overwrite), once
    const apply = await cli(fake, ["course", "apply", "--dir", dir, "--approve", "--json"]);
    expect(apply.code, apply.stdout + apply.stderr).toBe(0);
    expect(fake.db.applyCalls).toEqual([{ overwrite: true }]);
    expect(base(dir).baseVersionNumber).toBe(1); // the folder is not the absorbed version: the base waits for a pull
    expect(learnerText(fake)).toContain("the admin's title");

    // pull brings the title into the folder
    const pull = await cli(fake, ["course", "pull", dir, "--json"]);
    expect(pull.code).toBe(0);
    expect(read(dir, "course.yaml")).toContain("the admin's title");
    expect(existsSync(join(dir, ".ulams/proposal.json"))).toBe(false);
  });

  it("does not overwrite anything the absorption did not take: other drifted elements keep apply refused", async () => {
    const { fake, dir } = await drifted();
    fake.db.drift.push({ element: "lesson:0unmapped0000000000000001", title: "Something else an admin edited" });
    await cli(fake, ["course", "absorb-drift", "--dir", dir, "--json"]);
    const apply = await cli(fake, ["course", "apply", "--dir", dir, "--approve", "--json"]);
    expect(apply.code).toBe(0);
    expect(fake.db.applyCalls).toEqual([{ overwrite: false }]);
    // an explicit --overwrite is still the person's call
    await cli(fake, ["course", "apply", "--dir", dir, "--overwrite", "--json"]);
    expect(fake.db.applyCalls.at(-1)).toEqual({ overwrite: true });
  });

  it("an agent token can absorb and propose, and is refused when it tries to approve; the proposal stays", async () => {
    const { fake, dir } = await drifted();
    const agent = fakeCourseAsCode({ agentToken: true });
    // reuse the same data on a server that refuses approvals
    agent.db.absorb = fake.db.absorb;
    const r = await cli(agent, ["course", "absorb-drift", "--dir", dir, "--json"]);
    expect(r.code, r.stdout + r.stderr).toBe(0);
    const apply = await cli(agent, ["course", "apply", "--dir", dir, "--approve", "--json"]);
    expect(apply.code).toBe(4);
    expect(apply.error()).toMatchObject({ code: "FORBIDDEN", details: { code: "APPROVAL_REQUIRES_HUMAN" } });
    expect(JSON.parse(read(dir, ".ulams/proposal.json")).kind).toBe("absorb");
  });

  it("says so when nothing can be read back, and lists what could not be mapped", async () => {
    const { fake, dir } = await initialised();
    fake.db.absorb = { document: fake.current().document, absorbed: [], unmapped: [{ key: "topic:0x", element: "0x", title: "Install kubectl", reason: "The lesson text now holds HTML (the visual editor does that)." }] };
    const r = await cli(fake, ["course", "absorb-drift", "--dir", dir]);
    expect(r.code).toBe(0);
    expect(r.stdout).toContain("No edits made in the LMS can be read back into the course.");
    expect(r.stdout).toContain("! Install kubectl: The lesson text now holds HTML");
  });

  it("--dry-run shows the request and sends nothing", async () => {
    const { fake, dir } = await drifted();
    const r = await cli(fake, ["course", "absorb-drift", "--dir", dir, "--dry-run", "--json"]);
    expect(r.code).toBe(0);
    expect(r.requests).toHaveLength(0);
    expect(r.data()).toMatchObject({ dryRun: true, request: { method: "POST", path: expect.stringContaining("/drift") } });
  });
});
