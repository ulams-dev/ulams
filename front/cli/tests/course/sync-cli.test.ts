import { existsSync, mkdirSync, mkdtempSync, readFileSync, readdirSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { afterEach, describe, expect, it } from "vitest";
import { readFolder } from "../../src/course/disk.ts";
import { validateFiles } from "../../src/course/validate.ts";
import { runCli } from "../helpers.ts";
import { fakeCourseAsCode, type FakeOptions } from "./fake-course-as-code.ts";

/* The folder workflow end to end through the real command line and the real file system, against an in-memory
   server with the rules of the course-as-code API: init -> edit -> status/diff -> push -> approve -> apply -> publish. */

const TOKEN = "tok-secret-123456";
const env = { ULAMS_URL: "http://coffee.localhost", ULAMS_TOKEN: TOKEN, ULAMS_POLL_MS: "1" };
const INSTALL = "modules/01-basics/01-install.md";
const PODS = "modules/01-basics/02-pods.md";

const cleanups: Array<() => void> = [];
afterEach(() => {
  while (cleanups.length) cleanups.pop()?.();
});

type Fake = ReturnType<typeof fakeCourseAsCode>;
// eslint-disable-next-line @typescript-eslint/no-explicit-any
type Out = Record<string, any>;

async function cli(fake: Fake, args: string[]) {
  const r = await runCli(args, { env, routes: fake.routes });
  cleanups.push(r.cleanup);
  const body = (): Out => r.json() as Out;
  return Object.assign(r, { body, data: (): Out => (body().data ?? {}) as Out, error: (): Out => (body().error ?? {}) as Out });
}

function tmp(): string {
  const dir = mkdtempSync(join(tmpdir(), "ulams-sync-"));
  cleanups.push(() => rmSync(dir, { recursive: true, force: true }));
  return dir;
}

/** A fresh folder initialised from the fake server's course. */
async function initialised(options: FakeOptions = {}): Promise<{ fake: Fake; dir: string }> {
  const fake = fakeCourseAsCode(options);
  const dir = join(tmp(), "course");
  const r = await cli(fake, ["course", "init", dir, "--from-course", String(fake.courseId), "--json"]);
  expect(r.code, r.stdout + r.stderr).toBe(0);
  return { fake, dir };
}

const read = (dir: string, rel: string) => readFileSync(join(dir, rel), "utf8");
const edit = (dir: string, rel: string, from: string, to: string) => {
  const text = read(dir, rel);
  expect(text, `${rel} should contain ${from}`).toContain(from);
  writeFileSync(join(dir, rel), text.replace(from, to));
};
const base = (dir: string) => JSON.parse(read(dir, ".ulams/base.json")) as Out;
const proposal = (dir: string) => JSON.parse(read(dir, ".ulams/proposal.json")) as Out;
const learnerText = (fake: Fake) => JSON.stringify(fake.db.live);
const EDIT_FROM = "kubectl is the command-line tool for Kubernetes clusters.";
const EDIT_TO = "kubectl is the CLI for Kubernetes clusters.";

describe("course init --from-course", () => {
  it("writes the course as a folder that validates, records the base and binds ulams.yaml", async () => {
    const { fake, dir } = await initialised();
    const names = readdirSync(dir).sort();
    expect(names).toEqual([".ulams", "course.yaml", "final-test.quiz.yaml", "modules", "ulams.yaml"]);
    expect(read(dir, "ulams.yaml")).toContain(`session: ${fake.sessionId}`);
    expect(read(dir, "ulams.yaml")).toMatch(/id: 12\b/);
    const b = base(dir);
    expect(b).toMatchObject({ format: 1, sessionId: fake.sessionId, courseId: 12, baseVersionId: fake.db.currentVersionId, baseVersionNumber: 1 });
    expect(Object.keys(b.elements).length).toBeGreaterThan(20);
    expect(read(dir, ".ulams/.gitignore")).toBe("proposal.json\n");
    const folder = readFolder(dir);
    const errors = validateFiles(folder.files, folder.assets).issues.filter((i) => i.severity === "error");
    expect(errors).toEqual([]);

    const v = await cli(fake, ["course", "validate", dir, "--json"]);
    expect(v.code).toBe(0);
  });

  it("prints text for a person and the envelope for an agent", async () => {
    const fake = fakeCourseAsCode();
    const dir = join(tmp(), "text");
    const text = await cli(fake, ["course", "init", dir, "--from-course", "12"]);
    expect(text.code).toBe(0);
    expect(text.stdout).toMatch(/^Wrote \d+ files to .*text \(course 12, version 1\)\./);
    expect(text.stdout).toContain(".ulams/base.json");

    const json = await cli(fake, ["course", "init", join(tmp(), "json"), "--from-course", "12", "--json"]);
    expect(json.body()).toMatchObject({ ok: true, command: "course.init", data: { courseId: 12, sessionId: fake.sessionId, versionNumber: 1, files: expect.arrayContaining(["course.yaml", "ulams.yaml"]) } });
  });

  it("--dry-run lists the files and writes nothing", async () => {
    const fake = fakeCourseAsCode();
    const dir = join(tmp(), "dry");
    const r = await cli(fake, ["course", "init", dir, "--from-course", "12", "--dry-run", "--json"]);
    expect(r.code).toBe(0);
    expect(r.data().dryRun).toBe(true);
    expect(r.data().files.length).toBeGreaterThan(5);
    expect(existsSync(dir)).toBe(false);
  });

  it("refuses a folder that already holds something, and a missing --from-course, before any request", async () => {
    const fake = fakeCourseAsCode();
    const full = tmp();
    writeFileSync(join(full, "notes.txt"), "mine");
    writeFileSync(join(full, "README.md"), "allowed");
    const r = await cli(fake, ["course", "init", full, "--from-course", "12", "--json"]);
    expect(r.code).toBe(2);
    expect(r.error().message).toContain("is not empty");
    expect(r.requests).toHaveLength(0);

    const none = await cli(fake, ["course", "init", join(tmp(), "x"), "--json"]);
    expect(none.code).toBe(2);
    expect(none.error().message).toContain("--from-course");
    expect(none.requests).toHaveLength(0);

    const letters = await cli(fake, ["course", "init", join(tmp(), "y"), "--from-course", "abc", "--json"]);
    expect(letters.code).toBe(2);
    expect(letters.requests).toHaveLength(0);
  });

  it("allows a README and dot files (a fresh Git repository)", async () => {
    const fake = fakeCourseAsCode();
    const dir = tmp();
    mkdirSync(join(dir, ".git"));
    writeFileSync(join(dir, "README.md"), "# my course");
    const r = await cli(fake, ["course", "init", dir, "--from-course", "12", "--json"]);
    expect(r.code, r.stdout).toBe(0);
    expect(read(dir, "README.md")).toBe("# my course");
  });

  it("passes the server's refusal for a hand-made course through with the way forward", async () => {
    const fake = fakeCourseAsCode();
    const r = await cli(fake, ["course", "init", join(tmp(), "z"), "--from-course", "99", "--json"]);
    expect(r.code).toBe(5);
    const notAdopted = await runCli(["course", "init", join(tmp(), "w"), "--from-course", "7", "--json"], {
      env,
      routes: { "GET /api/admin/course-as-code/courses/7/blueprint": { status: 409, body: { success: false, message: "This course was not made with the builder, so it has no blueprint yet.", code: "NOT_ADOPTED" } } },
    });
    cleanups.push(notAdopted.cleanup);
    expect(notAdopted.code).toBe(6);
    expect(notAdopted.json()).toMatchObject({ error: { code: "CONFLICT", hint: expect.stringContaining("made with the course builder"), details: { code: "NOT_ADOPTED" } } });
  });

  it("reports drift as a warning without failing", async () => {
    const fake = fakeCourseAsCode();
    fake.db.drift = [{ element: "l1", title: "Install kubectl" }];
    const r = await cli(fake, ["course", "init", join(tmp(), "d"), "--from-course", "12", "--json"]);
    expect(r.code).toBe(0);
    expect(r.body().warnings).toEqual([expect.objectContaining({ code: "DRIFT", message: expect.stringContaining("Install kubectl") })]);
  });
});

describe("status and diff", () => {
  it("a fresh folder is clean, in sync and applied", async () => {
    const { fake, dir } = await initialised();
    const r = await cli(fake, ["course", "status", dir, "--json"]);
    expect(r.code).toBe(0);
    expect(r.data()).toMatchObject({
      course: { id: 12, session: fake.sessionId },
      base: { versionNumber: 1 },
      local: { state: "clean" },
      remote: { state: "in-sync", applied: true, drift: [] },
      proposal: null,
      conflicts: [],
      next: [],
    });
    const text = await cli(fake, ["course", "status", dir]);
    expect(text.stdout).toContain("Local:    clean");
    expect(text.stdout).toContain("Server:   in sync (version 1)");
  });

  it("diff is offline and names what was edited", async () => {
    const { fake, dir } = await initialised();
    edit(dir, INSTALL, EDIT_FROM, EDIT_TO);
    const before = fake.db.calls.length;
    const r = await cli(fake, ["course", "diff", dir, "--json"]);
    expect(r.code).toBe(0);
    expect(fake.db.calls.length).toBe(before); // no request at all
    expect(r.requests).toHaveLength(0);
    expect(r.data()).toMatchObject({ against: "base", unchanged: false, counts: { added: 0, changed: 1, removed: 0 } });
    expect(r.data().changes[0]).toMatchObject({ kind: "changed", type: "block", label: "Lesson 1.1 > block 1" });

    const text = await cli(fake, ["course", "diff", dir]);
    expect(text.stdout).toContain("Folder against its base (version 1): 1 changed, 0 added, 0 removed.");
    expect(text.stdout).toMatch(/~ Lesson 1\.1 > block 1 "kubectl is the CLI/);
  });

  it("--exit-code exits 13 only when there are differences", async () => {
    const { fake, dir } = await initialised();
    const clean = await cli(fake, ["course", "diff", dir, "--exit-code", "--json"]);
    expect(clean.code).toBe(0);
    expect(clean.data().unchanged).toBe(true);
    edit(dir, INSTALL, EDIT_FROM, EDIT_TO);
    const changed = await cli(fake, ["course", "diff", dir, "--exit-code", "--json"]);
    expect(changed.code).toBe(13);
    expect(changed.data().counts.changed).toBe(1);
    const plain = await cli(fake, ["course", "diff", dir, "--json"]);
    expect(plain.code).toBe(0);
  });

  it("detects added and removed lessons and a reorder", async () => {
    const { fake, dir } = await initialised();
    rmSync(join(dir, PODS));
    const r = await cli(fake, ["course", "diff", dir, "--json"]);
    expect(r.data().counts.removed).toBeGreaterThan(0);
    expect(r.data().changes.find((c: Out) => c.type === "lesson")).toMatchObject({ kind: "removed", label: "Lesson 1.2" });
  });

  it("diff --remote shows, field by field, what a push would change; diff --version shows a version on the server", async () => {
    const { fake, dir } = await initialised();
    edit(dir, INSTALL, "minutes: 10", "minutes: 15");
    const r = await cli(fake, ["course", "diff", dir, "--remote", "--json"]);
    expect(r.code).toBe(0);
    expect(r.data()).toMatchObject({ against: "remote", behind: false, counts: { changed: 1 } });
    expect(r.data().changes[0].fields).toEqual([{ field: "minutes", before: 10, after: 15 }]);

    const pushed = await cli(fake, ["course", "push", dir, "--json"]);
    const versionId = pushed.data().version.id as string;
    const v = await cli(fake, ["course", "diff", "--version", versionId, "--json"]);
    expect(v.code).toBe(0);
    expect(v.data().counts).toEqual({ added: 0, changed: 1, removed: 0 });
  });

  it("status explains a proposal that waits and what to do next", async () => {
    const { fake, dir } = await initialised();
    edit(dir, INSTALL, EDIT_FROM, EDIT_TO);
    const modified = await cli(fake, ["course", "status", dir, "--json"]);
    expect(modified.data().local).toMatchObject({ state: "modified", counts: { changed: 1 } });
    expect(modified.data().next).toEqual(["`ulams course push`"]);

    await cli(fake, ["course", "push", dir, "--json"]);
    const waiting = await cli(fake, ["course", "status", dir, "--json"]);
    expect(waiting.data().proposal).toMatchObject({ versionNumber: 2, state: "waiting" });
    expect(waiting.data().next[0]).toContain("course apply --approve");
    const text = await cli(fake, ["course", "status", dir]);
    expect(text.stdout).toContain("Proposal: version 2");
    expect(text.stdout).toContain("waiting for approval");
  });

  it("status says when the server moved on, and when both sides changed", async () => {
    const { fake, dir } = await initialised();
    fake.studioEdit((doc) => {
      (((doc.modules as Out[])[0] as Out).lessons as Out[])[0]!.minutes = 30;
    });
    const behind = await cli(fake, ["course", "status", dir, "--json"]);
    expect(behind.data().remote).toMatchObject({ state: "behind", counts: { changed: 1 } });
    expect(behind.data().next).toEqual(["`ulams course pull`"]);

    edit(dir, INSTALL, EDIT_FROM, EDIT_TO);
    const both = await cli(fake, ["course", "status", dir, "--json"]);
    expect(both.data().local.state).toBe("modified");
    expect(both.data().next[0]).toContain("both sides changed");
  });

  it("status reports a folder with errors instead of guessing", async () => {
    const { fake, dir } = await initialised();
    writeFileSync(join(dir, INSTALL), read(dir, INSTALL).replace(":::paragraph", ":::paragrap"));
    const r = await cli(fake, ["course", "status", dir, "--json"]);
    expect(r.code).toBe(0);
    expect(r.data().local.state).toBe("invalid");
    expect(r.data().next[0]).toContain("validate");
  });

  it("a folder that was never initialised is explained, not crashed on", async () => {
    const fake = fakeCourseAsCode();
    const dir = tmp();
    writeFileSync(join(dir, "ulams.yaml"), "format: 1\nblueprint: 3\n");
    const r = await cli(fake, ["course", "status", dir, "--json"]);
    expect(r.code).toBe(2);
    expect(r.error().message).toContain(".ulams/base.json is missing");
    expect(r.error().hint).toContain("course init");
    const notFolder = await cli(fake, ["course", "diff", tmp(), "--json"]);
    expect(notFolder.code).toBe(2);
    expect(notFolder.error().message).toContain("no ulams.yaml");
  });

  it("a damaged base file is named, not trusted", async () => {
    const { fake, dir } = await initialised();
    writeFileSync(join(dir, ".ulams/base.json"), "{not json");
    const r = await cli(fake, ["course", "diff", dir, "--json"]);
    expect(r.code).toBe(2);
    expect(r.error().message).toContain("is not valid JSON");
  });
});

describe("course push", () => {
  it("proposes the folder: nothing changes for learners, the diff comes back, the proposal is remembered", async () => {
    const { fake, dir } = await initialised();
    edit(dir, INSTALL, EDIT_FROM, EDIT_TO);
    const r = await cli(fake, ["course", "push", dir, "--message", "Shorter intro", "--json"]);
    expect(r.code, r.stdout).toBe(0);
    const sent = r.requests.find((q) => q.method === "POST" && q.path.endsWith("/imports"));
    expect(sent?.body).toMatchObject({ baseVersionId: fake.db.versions[0]?.id, message: "Shorter intro" });
    expect((sent?.body as Out).document.schemaVersion).toBe(3);
    expect(r.data()).toMatchObject({ pushed: true, approved: false, applied: false, version: { number: 2, kind: "import", status: "proposed" }, counts: { changed: 1 } });
    expect(proposal(dir)).toMatchObject({ versionNumber: 2, baseVersionId: fake.db.versions[0]?.id, message: "Shorter intro" });
    expect(base(dir).baseVersionNumber).toBe(1); // the base moves only when the version is approved
    expect(learnerText(fake)).not.toContain(EDIT_TO);
    expect(fake.current().number).toBe(1);
    expect(fake.db.calls.some((c) => c.includes("/approve") || c.includes("/apply"))).toBe(false);
  });

  it("prints the diff and the next step in text", async () => {
    const { fake, dir } = await initialised();
    edit(dir, INSTALL, EDIT_FROM, EDIT_TO);
    const r = await cli(fake, ["course", "push", dir]);
    expect(r.stdout).toContain("Proposed version 2 (from version 1): 1 changed, 0 added, 0 removed.");
    expect(r.stdout).toContain("Nothing changes for learners until the version is approved and applied.");
    expect(r.stdout).toContain("ulams course apply --approve");
  });

  it("a folder equal to its base sends nothing", async () => {
    const { fake, dir } = await initialised();
    const r = await cli(fake, ["course", "push", dir, "--json"]);
    expect(r.code).toBe(0);
    expect(r.data()).toMatchObject({ unchanged: true, pushed: false });
    expect(r.requests).toHaveLength(0);
    expect(existsSync(join(dir, ".ulams/proposal.json"))).toBe(false);
  });

  it("--dry-run validates and shows the request and the diff, and sends nothing", async () => {
    const { fake, dir } = await initialised();
    edit(dir, INSTALL, EDIT_FROM, EDIT_TO);
    const r = await cli(fake, ["course", "push", dir, "--apply", "--dry-run", "--json"]);
    expect(r.code).toBe(0);
    expect(r.requests).toHaveLength(0);
    expect(r.data()).toMatchObject({
      dryRun: true,
      request: { method: "POST", path: expect.stringContaining(`/sessions/${fake.sessionId}/imports`), body: { document: "<document>" } },
      diff: { counts: { changed: 1 } },
    });
    expect(r.data().then).toHaveLength(2);
    expect(existsSync(join(dir, ".ulams/proposal.json"))).toBe(false);

    // a dry run still refuses an invalid folder
    writeFileSync(join(dir, INSTALL), read(dir, INSTALL).replace(":::paragraph", ":::paragrap"));
    const bad = await cli(fake, ["course", "push", dir, "--dry-run", "--json"]);
    expect(bad.code).toBe(7);
  });

  it("refuses a folder with validation errors before any request, listing file and line", async () => {
    const { fake, dir } = await initialised();
    writeFileSync(join(dir, INSTALL), read(dir, INSTALL).replace(":::paragraph", ":::paragrap"));
    const r = await cli(fake, ["course", "push", dir, "--json"]);
    expect(r.code).toBe(7);
    expect(r.requests).toHaveLength(0);
    expect(r.error()).toMatchObject({ code: "VALIDATION_FAILED", details: { valid: false } });
    expect(r.error().details.issues[0]).toMatchObject({ file: expect.stringContaining("01-install.md"), line: expect.any(Number), column: expect.any(Number) });
    const text = await cli(fake, ["course", "push", dir]);
    expect(text.code).toBe(7);
    expect(text.stdout).toContain("01-install.md:");
  });

  it("BASE_OUTDATED becomes SYNC_CONFLICT (exit 6) with the conflicting elements and the way out, and changes no file", async () => {
    const { fake, dir } = await initialised();
    fake.studioEdit((doc) => {
      ((((doc.modules as Out[])[0] as Out).lessons as Out[])[0] as Out).blocks[0].markdown = "kubectl, said the studio.";
    });
    edit(dir, INSTALL, EDIT_FROM, EDIT_TO);
    const baseBefore = read(dir, ".ulams/base.json");
    const r = await cli(fake, ["course", "push", dir, "--json"]);
    expect(r.code).toBe(6);
    expect(r.error()).toMatchObject({ code: "SYNC_CONFLICT", details: { code: "BASE_OUTDATED", currentVersionNumber: 2 } });
    expect(r.error().details.remote[0]).toMatchObject({ kind: "changed", type: "block" });
    expect(r.error().details.conflicts[0]).toMatchObject({ kind: "edit-edit", type: "block", label: "Lesson 1.1 > block 1", field: "markdown" });
    expect(r.error().message).toContain("Lesson 1.1 > block 1");
    expect(r.error().hint).toContain("ulams course pull");
    expect(read(dir, ".ulams/base.json")).toBe(baseBefore);
    expect(existsSync(join(dir, ".ulams/proposal.json"))).toBe(false);
    expect(existsSync(join(dir, ".ulams/conflicts"))).toBe(false);
    expect(read(dir, INSTALL)).toContain(EDIT_TO);
  });

  it("a push that is behind the server but touches other elements is merged by the server into one proposal", async () => {
    const { fake, dir } = await initialised();
    fake.studioEdit((doc) => {
      ((((doc.modules as Out[])[0] as Out).lessons as Out[])[0] as Out).minutes = 30;
    });
    edit(dir, INSTALL, EDIT_FROM, EDIT_TO);
    const r = await cli(fake, ["course", "push", dir, "--json"]);
    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(r.data()).toMatchObject({ pushed: true, version: { kind: "import", status: "proposed", parentId: fake.current().id }, merged: { baseVersionNumber: 1, currentVersionNumber: 2 } });
    expect(r.data().merged.fromServer).toEqual([expect.objectContaining({ kind: "changed" })]);
    expect(r.data().counts).toEqual({ added: 0, changed: 1, removed: 0 });
    const doc = fake.db.versions.at(-1)?.document as Out;
    expect(doc.modules[0].lessons[0]).toMatchObject({ minutes: 30 });
    expect(JSON.stringify(doc)).toContain(EDIT_TO);
    const text = await cli(fake, ["course", "status", dir]);
    expect(text.stdout).toContain("waiting for approval");
  });

  it("an invalid blueprint on the server is VALIDATION_FAILED with the server's errors", async () => {
    const { fake, dir } = await initialised({ rejectImport: ["lesson l1: a quiz needs an answer", "block b2: cites a fragment that does not exist"] });
    edit(dir, INSTALL, EDIT_FROM, EDIT_TO);
    const r = await cli(fake, ["course", "push", dir, "--json"]);
    expect(r.code).toBe(7);
    expect(r.error()).toMatchObject({ code: "VALIDATION_FAILED", message: expect.stringContaining("a quiz needs an answer"), details: { code: "INVALID_BLUEPRINT", errors: expect.arrayContaining(["block b2: cites a fragment that does not exist"]) } });
  });

  it("--approve approves the proposal and moves the base; learners still see the old text until it is applied", async () => {
    const { fake, dir } = await initialised();
    edit(dir, INSTALL, EDIT_FROM, EDIT_TO);
    const r = await cli(fake, ["course", "push", dir, "--approve", "--json"]);
    expect(r.code, r.stdout).toBe(0);
    expect(r.data()).toMatchObject({ approved: true, applied: false, baseMoved: true, approval: { number: 2, status: "approved" } });
    expect(base(dir)).toMatchObject({ baseVersionNumber: 2, baseVersionId: fake.current().id });
    expect(existsSync(join(dir, ".ulams/proposal.json"))).toBe(false);
    expect(learnerText(fake)).not.toContain(EDIT_TO);
    const status = await cli(fake, ["course", "status", dir, "--json"]);
    expect(status.data()).toMatchObject({ local: { state: "clean" }, remote: { state: "in-sync", applied: false }, next: ["`ulams course apply`"] });
  });

  it("--apply approves, applies and waits: the learner sees the change", async () => {
    const { fake, dir } = await initialised({ applyTicks: 3 });
    edit(dir, INSTALL, EDIT_FROM, EDIT_TO);
    const r = await cli(fake, ["course", "push", dir, "--apply", "--json"]);
    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(r.data()).toMatchObject({ approved: true, applied: true, apply: { versionNumber: 2 } });
    expect(learnerText(fake)).toContain(EDIT_TO);
    expect(fake.db.appliedVersionId).toBe(fake.current().id);
    expect(r.stderr).toContain("waiting for the apply");
    expect(base(dir).baseVersionNumber).toBe(2);
    const status = await cli(fake, ["course", "status", dir, "--json"]);
    expect(status.data()).toMatchObject({ local: { state: "clean" }, remote: { state: "in-sync", applied: true }, next: [] });
  });

  it("--apply --no-wait returns after the apply started, with the run handle", async () => {
    const { fake, dir } = await initialised();
    edit(dir, INSTALL, EDIT_FROM, EDIT_TO);
    const r = await cli(fake, ["course", "push", dir, "--apply", "--no-wait", "--json"]);
    expect(r.code).toBe(0);
    expect(r.data()).toMatchObject({ approved: true, applied: false });
    expect(r.body().meta).toMatchObject({ operation: expect.stringContaining("builder-run:") });
    expect(fake.db.applying).not.toBeNull();
  });

  it("an agent token can propose but not approve: the proposal stays and the error says who can", async () => {
    const { fake, dir } = await initialised({ agentToken: true });
    edit(dir, INSTALL, EDIT_FROM, EDIT_TO);
    const r = await cli(fake, ["course", "push", dir, "--apply", "--json"]);
    expect(r.code).toBe(4);
    expect(r.error()).toMatchObject({ code: "FORBIDDEN", details: { code: "APPROVAL_REQUIRES_HUMAN", proposed: { versionNumber: 2 } } });
    expect(r.error().hint).toContain("Agent tokens and MCP clients can only propose");
    expect(proposal(dir).versionNumber).toBe(2);
    expect(fake.current().number).toBe(1);
    expect(fake.db.calls.some((c) => c.includes("/apply"))).toBe(false);
  });
});

describe("course apply", () => {
  it("refuses an unapproved proposal (exit 11) unless --approve, and applies once approved", async () => {
    const { fake, dir } = await initialised();
    edit(dir, INSTALL, EDIT_FROM, EDIT_TO);
    await cli(fake, ["course", "push", dir, "--json"]);

    const refused = await cli(fake, ["course", "apply", "--dir", dir, "--json"]);
    expect(refused.code).toBe(11);
    expect(refused.error()).toMatchObject({ code: "CONFIRMATION_REQUIRED", details: { status: "proposed" } });
    expect(refused.error().hint).toContain("--approve");
    expect(fake.db.calls.some((c) => c.includes("/apply"))).toBe(false);

    const ok = await cli(fake, ["course", "apply", "--dir", dir, "--approve", "--json"]);
    expect(ok.code, ok.stdout + ok.stderr).toBe(0);
    expect(ok.data()).toMatchObject({ approved: true, applied: true, sessionId: fake.sessionId });
    expect(learnerText(fake)).toContain(EDIT_TO);
    expect(base(dir).baseVersionNumber).toBe(2);
    expect(existsSync(join(dir, ".ulams/proposal.json"))).toBe(false);
  });

  it("applies when the proposal was approved in the studio", async () => {
    const { fake, dir } = await initialised();
    edit(dir, INSTALL, EDIT_FROM, EDIT_TO);
    const pushed = await cli(fake, ["course", "push", dir, "--json"]);
    const v = fake.db.versions.find((x) => x.id === pushed.data().version.id);
    if (!v) throw new Error("missing version");
    v.status = "approved";
    fake.db.currentVersionId = v.id;
    const r = await cli(fake, ["course", "apply", "--dir", dir, "--json"]);
    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(r.data()).toMatchObject({ approved: false, applied: true });
    expect(existsSync(join(dir, ".ulams/proposal.json"))).toBe(false);
  });

  it("a proposal made stale by a studio edit cannot be approved (SYNC_CONFLICT)", async () => {
    const { fake, dir } = await initialised();
    edit(dir, INSTALL, EDIT_FROM, EDIT_TO);
    await cli(fake, ["course", "push", dir, "--json"]);
    fake.studioEdit((doc) => {
      (((doc.modules as Out[])[0] as Out).lessons as Out[])[0]!.minutes = 30;
    });
    const r = await cli(fake, ["course", "apply", "--dir", dir, "--approve", "--json"]);
    expect(r.code).toBe(6);
    expect(r.error()).toMatchObject({ code: "SYNC_CONFLICT", details: { code: "STALE_PROPOSAL" } });
  });

  it("works with a session id and no folder, and --dry-run plans without sending", async () => {
    const fake = fakeCourseAsCode();
    const dry = await cli(fake, ["course", "apply", fake.sessionId, "--dry-run", "--json"]);
    expect(dry.code).toBe(0);
    expect(dry.requests).toHaveLength(0);
    expect(dry.data()).toMatchObject({ dryRun: true, request: { method: "POST", path: expect.stringContaining(`/sessions/${fake.sessionId}/apply`) } });
    const r = await cli(fake, ["course", "apply", fake.sessionId, "--json"]);
    expect(r.code).toBe(0);
    expect(r.data()).toMatchObject({ sessionId: fake.sessionId, applied: false, runId: expect.any(String) });
    expect(r.body().meta).toMatchObject({ operation: expect.stringContaining("builder-run:") });
  });

  it("asks for a session when there is neither a folder nor an argument", async () => {
    const fake = fakeCourseAsCode();
    const r = await cli(fake, ["course", "apply", "--dir", tmp(), "--json"]);
    expect(r.code).toBe(2);
    expect(r.requests).toHaveLength(0);
  });
});

describe("course publish", () => {
  it("publishes an applied course", async () => {
    const { fake, dir } = await initialised();
    const r = await cli(fake, ["course", "publish", "--dir", dir, "--json"]);
    expect(r.code, r.stdout).toBe(0);
    expect(r.data()).toMatchObject({ courseId: 12, published: true });
    expect(fake.db.published).toBe(true);
    const text = await cli(fake, ["course", "publish", "--dir", dir]);
    expect(text.stdout).toContain("Published course 12");
  });

  it("refuses a course that was never applied and says what to run", async () => {
    const fake = fakeCourseAsCode();
    fake.db.appliedVersionId = null;
    const r = await cli(fake, ["course", "publish", fake.sessionId, "--json"]);
    expect(r.code).toBe(6);
    expect(r.error().message).toContain("Apply the course");
    expect(r.error().hint).toContain("course apply");
    expect(fake.db.published).toBe(false);
  });

  it("warnings need --acknowledge-warnings (exit 11); blocking items never publish (exit 6)", async () => {
    const warned = fakeCourseAsCode({ publishWarnings: ["3 blocks have no sources, so Living Course cannot keep them up to date."] });
    const stop = await cli(warned, ["course", "publish", warned.sessionId, "--json"]);
    expect(stop.code).toBe(11);
    expect(stop.error()).toMatchObject({ code: "CONFIRMATION_REQUIRED", details: { warnings: [{ message: expect.stringContaining("3 blocks have no sources") }] } });
    expect(stop.error().hint).toContain("--acknowledge-warnings");
    const go = await cli(warned, ["course", "publish", warned.sessionId, "--acknowledge-warnings", "--json"]);
    expect(go.code).toBe(0);
    expect(warned.db.published).toBe(true);

    const blocked = fakeCourseAsCode({ publishBlocking: ["The landing page has no title."] });
    const no = await cli(blocked, ["course", "publish", blocked.sessionId, "--acknowledge-warnings", "--json"]);
    expect(no.code).toBe(6);
    expect(no.error()).toMatchObject({ code: "CONFLICT", details: { blocking: [{ message: "The landing page has no title." }] } });
    expect(blocked.db.published).toBe(false);
  });

  it("--dry-run sends nothing", async () => {
    const fake = fakeCourseAsCode();
    const r = await cli(fake, ["course", "publish", fake.sessionId, "--dry-run", "--json"]);
    expect(r.code).toBe(0);
    expect(r.requests).toHaveLength(0);
    expect(fake.db.published).toBe(false);
  });
});

describe("course pull", () => {
  it("fast-forwards a clean folder to what the studio changed, and moves the base", async () => {
    const { fake, dir } = await initialised();
    fake.studioEdit((doc) => {
      const lessons = ((doc.modules as Out[])[0] as Out).lessons as Out[];
      (lessons[0] as Out).title = "Install the command line tool";
      (lessons[0] as Out).minutes = 30;
      lessons.pop();
    });
    const r = await cli(fake, ["course", "pull", dir, "--json"]);
    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(r.data()).toMatchObject({ state: "updated", version: { number: 2 } });
    expect(r.data().files.removed).toEqual([PODS]);
    expect(read(dir, INSTALL)).toContain("title: Install the command line tool");
    expect(read(dir, INSTALL)).toContain("minutes: 30");
    expect(existsSync(join(dir, PODS))).toBe(false);
    expect(base(dir).baseVersionNumber).toBe(2);
    const status = await cli(fake, ["course", "status", dir, "--json"]);
    expect(status.data()).toMatchObject({ local: { state: "clean" }, remote: { state: "in-sync" } });
    const validate = await cli(fake, ["course", "validate", dir, "--json"]);
    expect(validate.code).toBe(0);
  });

  it("keeps the file names the author chose and leaves their own files alone", async () => {
    const { fake, dir } = await initialised();
    const renamed = "modules/01-basics/01-install-kubectl.md";
    writeFileSync(join(dir, renamed), read(dir, INSTALL));
    writeFileSync(join(dir, "modules/01-basics/01-install-kubectl.quiz.yaml"), read(dir, "modules/01-basics/01-install.quiz.yaml"));
    rmSync(join(dir, INSTALL));
    rmSync(join(dir, "modules/01-basics/01-install.quiz.yaml"));
    // the rename is a no-op for the model (same ids), so the folder is still clean
    mkdirSync(join(dir, "assets"), { recursive: true });
    writeFileSync(join(dir, "assets/mine.txt"), "keep me");
    writeFileSync(join(dir, "NOTES.md"), "keep me too");
    fake.studioEdit((doc) => {
      ((((doc.modules as Out[])[0] as Out).lessons as Out[])[0] as Out).minutes = 45;
    });
    const r = await cli(fake, ["course", "pull", dir, "--json"]);
    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(read(dir, renamed)).toContain("minutes: 45");
    expect(existsSync(join(dir, INSTALL))).toBe(false);
    expect(read(dir, "assets/mine.txt")).toBe("keep me");
    expect(read(dir, "NOTES.md")).toBe("keep me too");
  });

  it("does nothing when the server did not move, and keeps local edits", async () => {
    const { fake, dir } = await initialised();
    edit(dir, INSTALL, EDIT_FROM, EDIT_TO);
    const r = await cli(fake, ["course", "pull", dir, "--json"]);
    expect(r.code).toBe(0);
    expect(r.data()).toMatchObject({ state: "up-to-date", localChanges: true });
    expect(read(dir, INSTALL)).toContain(EDIT_TO);
  });

  it("merges when both sides changed different elements: the server's edit lands in the folder, yours stays", async () => {
    const { fake, dir } = await initialised();
    fake.studioEdit((doc) => {
      ((((doc.modules as Out[])[0] as Out).lessons as Out[])[0] as Out).minutes = 45;
    });
    edit(dir, INSTALL, EDIT_FROM, EDIT_TO);
    const r = await cli(fake, ["course", "pull", dir, "--json"]);
    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(r.data()).toMatchObject({ state: "merged", version: { number: 2 }, conflicts: [], fromServer: [expect.objectContaining({ kind: "changed" })] });
    expect(r.data().fromFolder).toHaveLength(1);
    expect(read(dir, INSTALL)).toContain("minutes: 45");
    expect(read(dir, INSTALL)).toContain(EDIT_TO);
    expect(base(dir).baseVersionNumber).toBe(2);
    expect(existsSync(join(dir, ".ulams/conflicts"))).toBe(false);
    // the folder now differs from the new base by exactly your edit
    const diff = await cli(fake, ["course", "diff", dir, "--json"]);
    expect(diff.data().counts).toEqual({ added: 0, changed: 1, removed: 0 });
    const push = await cli(fake, ["course", "push", dir, "--json"]);
    expect(push.data()).toMatchObject({ pushed: true, version: { parentId: fake.current().id } });
    expect(push.data().merged).toBeNull();
  });

  it("moves only the base when the folder already equals the server (the pushed version was approved in the studio)", async () => {
    const { fake, dir } = await initialised();
    edit(dir, INSTALL, EDIT_FROM, EDIT_TO);
    const pushed = await cli(fake, ["course", "push", dir, "--json"]);
    const v = fake.db.versions.find((x) => x.id === pushed.data().version.id);
    if (!v) throw new Error("missing version");
    v.status = "approved";
    fake.db.currentVersionId = v.id;

    const status = await cli(fake, ["course", "status", dir, "--json"]);
    expect(status.data().remote).toMatchObject({ state: "behind", sameAsFolder: true });
    expect(status.data().proposal.state).toBe("approved");

    const before = read(dir, INSTALL);
    const r = await cli(fake, ["course", "pull", dir, "--json"]);
    expect(r.code).toBe(0);
    expect(r.data().state).toBe("base-moved");
    expect(read(dir, INSTALL)).toBe(before);
    expect(base(dir).baseVersionNumber).toBe(2);
    expect(existsSync(join(dir, ".ulams/proposal.json"))).toBe(false);
  });

  it("--dry-run lists the files and changes nothing", async () => {
    const { fake, dir } = await initialised();
    fake.studioEdit((doc) => {
      ((((doc.modules as Out[])[0] as Out).lessons as Out[])[0] as Out).minutes = 45;
    });
    const before = read(dir, INSTALL);
    const baseBefore = read(dir, ".ulams/base.json");
    const r = await cli(fake, ["course", "pull", dir, "--dry-run", "--json"]);
    expect(r.code).toBe(0);
    expect(r.data()).toMatchObject({ state: "would-update", dryRun: true, files: { updated: [INSTALL] } });
    expect(read(dir, INSTALL)).toBe(before);
    expect(read(dir, ".ulams/base.json")).toBe(baseBefore);
  });

  it("adds a lesson created in the studio and clears an outdated proposal", async () => {
    const { fake, dir } = await initialised();
    edit(dir, INSTALL, EDIT_FROM, EDIT_TO);
    await cli(fake, ["course", "push", dir, "--json"]);
    edit(dir, INSTALL, EDIT_TO, EDIT_FROM); // the author backs the edit out
    fake.studioEdit((doc) => {
      ((doc.modules as Out[])[1] as Out).lessons.push({
        id: "01z00000000000000000000077",
        key: "rollouts",
        title: "Rollouts",
        minutes: 5,
        objectives: [{ id: "01z00000000000000000000078", text: "Roll out a change" }],
        blocks: [{ id: "01z00000000000000000000079", kind: "paragraph", markdown: "A rollout replaces pods gradually.", origin: "author" }],
        quiz: null,
      });
    });
    const r = await cli(fake, ["course", "pull", dir, "--json"]);
    expect(r.code, r.stdout + r.stderr).toBe(0);
    expect(r.data().files.written).toEqual(["modules/02-workloads/02-rollouts.md"]);
    expect(read(dir, "modules/02-workloads/02-rollouts.md")).toContain("A rollout replaces pods gradually.");
    expect(existsSync(join(dir, ".ulams/proposal.json"))).toBe(false);
  });
});

describe("the whole loop", () => {
  it("export -> edit file -> push -> diff -> approve -> apply -> publish -> pull is a no-op", async () => {
    const { fake, dir } = await initialised({ applyTicks: 2 });
    expect(learnerText(fake)).toContain(EDIT_FROM);

    edit(dir, INSTALL, EDIT_FROM, EDIT_TO);
    const diff = await cli(fake, ["course", "diff", dir, "--json"]);
    expect(diff.data().counts.changed).toBe(1);

    const pushed = await cli(fake, ["course", "push", dir, "--message", "Shorter intro", "--json"]);
    expect(pushed.data().version.status).toBe("proposed");
    expect(learnerText(fake)).toContain(EDIT_FROM);

    const review = await cli(fake, ["course", "diff", "--version", pushed.data().version.id, "--json"]);
    expect(review.data().counts.changed).toBe(1);

    const applied = await cli(fake, ["course", "apply", "--dir", dir, "--approve", "--json"]);
    expect(applied.code, applied.stdout + applied.stderr).toBe(0);
    expect(learnerText(fake)).toContain(EDIT_TO);

    const published = await cli(fake, ["course", "publish", "--dir", dir, "--json"]);
    expect(published.data().published).toBe(true);
    expect(fake.db.published).toBe(true);

    const pull = await cli(fake, ["course", "pull", dir, "--json"]);
    expect(pull.data().state).toBe("up-to-date");
    const status = await cli(fake, ["course", "status", dir, "--json"]);
    expect(status.data()).toMatchObject({ local: { state: "clean" }, remote: { state: "in-sync", applied: true }, proposal: null, next: [] });
  });

  it("two authors: the second one is told to pull, pulls, edits again and pushes", async () => {
    const { fake, dir } = await initialised();
    const other = join(tmp(), "second");
    expect((await cli(fake, ["course", "init", other, "--from-course", "12", "--json"])).code).toBe(0);

    edit(dir, INSTALL, EDIT_FROM, EDIT_TO);
    expect((await cli(fake, ["course", "push", dir, "--apply", "--json"])).code).toBe(0);

    edit(other, PODS, "A pod is", "A pod is really");
    // the two authors touched different elements: the server merges the second push on top of the first
    const merged = await cli(fake, ["course", "push", other, "--apply", "--json"]);
    expect(merged.code, merged.stdout + merged.stderr).toBe(0);
    expect(merged.data()).toMatchObject({ approved: true, applied: true, merged: { baseVersionNumber: 1, currentVersionNumber: 2 } });
    expect(learnerText(fake)).toContain(EDIT_TO);
    expect(learnerText(fake)).toContain("A pod is really");
    // the approved version holds the other author's edit too, so it is not what the second folder contains: the base stays put
    expect(base(other).baseVersionNumber).toBe(1);
    const follow = await cli(fake, ["course", "pull", other, "--json"]);
    expect(follow.data()).toMatchObject({ state: "merged", conflicts: [] });
    expect(read(other, INSTALL)).toContain(EDIT_TO);
    expect(read(other, PODS)).toContain("A pod is really");
    expect((await cli(fake, ["course", "status", other, "--json"])).data()).toMatchObject({ local: { state: "clean" }, remote: { state: "in-sync" } });

    // and the first author's folder takes both edits on the next pull
    const pull = await cli(fake, ["course", "pull", dir, "--json"]);
    expect(pull.code).toBe(0);
    expect(pull.data().state).toBe("updated");
    expect(read(dir, PODS)).toContain("A pod is really");
  });
});

describe("errors and secrets", () => {
  it("an unreachable server is a network error (exit 9) and leaves the folder alone", async () => {
    const { dir } = await initialised();
    edit(dir, INSTALL, EDIT_FROM, EDIT_TO);
    const before = read(dir, ".ulams/base.json");
    const r = await runCli(["course", "push", dir, "--json"], {
      env,
      routes: { "*": () => ({ status: 0 }) },
    });
    cleanups.push(r.cleanup);
    expect(r.code).toBe(9);
    expect(read(dir, ".ulams/base.json")).toBe(before);
    expect(existsSync(join(dir, ".ulams/proposal.json"))).toBe(false);
  });

  it("a server error (500) and a rejected token (401) are mapped, and pull/status/init fail cleanly", async () => {
    const { dir } = await initialised();
    const broken = fakeCourseAsCode({ failWith: 500 });
    const r = await cli(broken, ["course", "status", dir, "--json"]);
    expect(r.code).toBe(9);
    expect(r.error().code).toBe("SERVER_ERROR");
    const unauthorised = fakeCourseAsCode({ failWith: 401 });
    const u = await cli(unauthorised, ["course", "pull", dir, "--json"]);
    expect(u.code).toBe(3);
    const i = await cli(unauthorised, ["course", "init", join(tmp(), "q"), "--from-course", "12", "--json"]);
    expect(i.code).toBe(3);
    const forbidden = fakeCourseAsCode({ failWith: 403 });
    const f = await cli(forbidden, ["course", "push", dir, "--json"]);
    expect([f.code, f.requests.length]).toEqual([0, 0]); // nothing to push: the folder equals its base
  });

  it("commands that need the server say so when no login is configured; local diff does not", async () => {
    const { dir } = await initialised();
    const noLogin = await runCli(["course", "status", dir, "--json"], { env: {}, routes: {} });
    cleanups.push(noLogin.cleanup);
    expect(noLogin.code).toBe(3);
    const offline = await runCli(["course", "diff", dir, "--json"], { env: {}, routes: {} });
    cleanups.push(offline.cleanup);
    expect(offline.code).toBe(0);
    const remote = await runCli(["course", "diff", dir, "--remote", "--json"], { env: {}, routes: {} });
    cleanups.push(remote.cleanup);
    expect(remote.code).toBe(3);
  });

  it("the token never reaches output, the base or the proposal", async () => {
    const { fake, dir } = await initialised();
    edit(dir, INSTALL, EDIT_FROM, EDIT_TO);
    const outputs: string[] = [];
    for (const args of [
      ["course", "push", dir, "--apply", "--debug"],
      ["course", "status", dir, "--debug"],
      ["course", "pull", dir, "--debug"],
      ["course", "publish", "--dir", dir, "--debug"],
    ]) {
      const r = await cli(fake, args);
      outputs.push(r.stdout, r.stderr);
    }
    const bad = await cli(fakeCourseAsCode({ failWith: 500 }), ["course", "status", dir, "--json", "--debug"]);
    outputs.push(bad.stdout, bad.stderr);
    for (const text of outputs) expect(text).not.toContain(TOKEN);
    for (const file of readdirSync(join(dir, ".ulams"))) expect(read(dir, `.ulams/${file}`)).not.toContain(TOKEN);
    expect(read(dir, "ulams.yaml")).not.toContain(TOKEN);
  });
});
