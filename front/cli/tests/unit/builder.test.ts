import { describe, expect, it } from "vitest";
import { fakeBuilder, type FakeBuilderOptions } from "../fake-builder.ts";
import { runCli } from "../helpers.ts";

const env = { ULAMS_URL: "http://coffee.localhost", ULAMS_TOKEN: "tok-123456", ULAMS_POLL_MS: "1", ULAMS_EVENTS_IDLE_MS: "40" };

function setup(options: FakeBuilderOptions = {}, files: Record<string, string> = {}) {
  const fake = fakeBuilder(options);
  const run = (argv: string[], extra: { env?: Record<string, string>; routes?: Record<string, never> } = {}) =>
    runCli(argv, { env: { ...env, ...extra.env }, routes: { "*": fake.handler, ...(extra.routes ?? {}) }, files: { "guide.md": "# Coffee\n\nGrind fresh.\n", ...files } });
  return { fake, run };
}

const data = (r: { json(): Record<string, unknown> }) => r.json().data as Record<string, any>; // eslint-disable-line @typescript-eslint/no-explicit-any

describe("builder start", () => {
  it("uploads the source, waits for the interview and stops at the first question (exit 0, data.pending)", async () => {
    const { fake, run } = setup();
    const r = await run(["builder", "start", "--from", "guide.md", "--title", "Coffee", "--json"]);
    expect(r.code).toBe(0);
    expect(data(r)).toMatchObject({ status: "interviewing", pending: { type: "interview" } });
    expect(data(r).pending.questions.map((q: { key: string }) => q.key)).toEqual(["audience", "level", "duration"]);
    expect(fake.db.uploads).toEqual(["guide.md"]);
    expect(r.requests.find((q) => q.path.endsWith("/sources"))?.form?.get("file")).toBeInstanceOf(File);
    expect(r.requests.every((q) => q.headers.authorization === "Bearer tok-123456")).toBe(true);
    r.cleanup();
  });

  it("--defaults --approve-outline --apply --publish runs the whole pipeline in order", async () => {
    const { fake, run } = setup();
    const r = await run(["builder", "start", "--from", "guide.md", "--defaults", "--approve-outline", "--apply", "--publish", "--json"]);
    expect(r.code).toBe(0);
    expect(data(r)).toMatchObject({ status: "applied", courseId: 77, published: true });
    expect(data(r).steps.map((s: { step: string }) => s.step)).toEqual(["create-session", "add-source", "interview", "approve-outline", "apply", "publish"]);
    expect(fake.db.published).toBe(true);
    expect(fake.db.posts.map((p) => p.path.replace(/ses\d{4}0+/, "S").replace(/ver\d{4}0+/, "V"))).toEqual([
      "POST /sessions",
      "POST /sessions/S/sources",
      "POST /sessions/S/runs",
      "POST /versions/V/approve",
      "POST /sessions/S/apply",
      "POST /sessions/S/publish",
    ]);
    r.cleanup();
  });

  it("--answers from a file answers the named questions and returns the rest as pending", async () => {
    const { fake, run } = setup({}, { "a.json": JSON.stringify({ audience: "new baristas", level: "beginner" }) });
    const r = await run(["builder", "start", "--from", "guide.md", "--answers", "@a.json", "--json"]);
    expect(r.code).toBe(0);
    expect([...fake.db.answered]).toEqual(["audience", "level"]);
    expect(data(r).pending.questions.map((q: { key: string }) => q.key)).toEqual(["duration"]);
    r.cleanup();
  });

  it("an unknown answer key exits 2 and names the questions", async () => {
    const { run } = setup();
    const r = await run(["builder", "start", "--from", "guide.md", "--answers", '{"colour":"red"}', "--json"]);
    expect(r.code).toBe(2);
    expect(r.json()).toMatchObject({ error: { code: "INPUT_INVALID", details: { choices: ["audience", "level", "duration"] } } });
    r.cleanup();
  });

  it("needs a source", async () => {
    const { run } = setup();
    const r = await run(["builder", "start", "--json"]);
    expect(r.code).toBe(2);
    r.cleanup();
  });

  it("--no-wait returns the ingest run handle, which operations wait resolves", async () => {
    const { run } = setup();
    const r = await run(["builder", "start", "--from", "guide.md", "--no-wait", "--json"]);
    expect(r.code).toBe(0);
    const handle = (r.json().meta as { operation: string }).operation;
    expect(handle).toMatch(/^builder-run:/);
    r.cleanup();
  });

  it("a failed interview run exits 9 with the run's error", async () => {
    const { run } = setup({ failInterview: true });
    const r = await run(["builder", "start", "--from", "guide.md", "--json"]);
    expect(r.code).toBe(9);
    expect(r.json()).toMatchObject({ ok: false, error: { code: "SERVER_ERROR", message: expect.stringContaining("The model refused to answer.") } });
    r.cleanup();
  });

  it("AI disabled exits 12", async () => {
    const { run } = setup({ aiDisabled: true });
    const r = await run(["builder", "start", "--from", "guide.md", "--json"]);
    expect(r.code).toBe(12);
    expect(r.json()).toMatchObject({ error: { code: "FEATURE_DISABLED" } });
    r.cleanup();
  });

  it("--from-url fetches the document and refuses web pages", async () => {
    const { fake, run } = setup();
    const ok = await run(["builder", "start", "--from-url", "https://raw.example.com/docs/guide", "--json"], {
      routes: { "GET /docs/guide": { body: undefined, raw: "# Hi\n", headers: { "content-type": "text/markdown; charset=utf-8" } } as never },
    });
    expect(ok.code).toBe(0);
    expect(fake.db.uploads).toEqual(["guide.md"]);
    const html = await run(["builder", "start", "--from-url", "https://example.com/page", "--json"], { routes: { "GET /page": { raw: "<html></html>", headers: { "content-type": "text/html" } } as never } });
    expect(html.code).toBe(2);
    expect(html.json()).toMatchObject({ error: { code: "INPUT_INVALID" } });
    ok.cleanup();
    html.cleanup();
  });
});

describe("builder stages", () => {
  async function toInterview(fake: ReturnType<typeof setup>["fake"], run: ReturnType<typeof setup>["run"]) {
    const r = await run(["builder", "start", "--from", "guide.md", "--json"]);
    expect(r.code).toBe(0);
    r.cleanup();
    return fake.db.session!.id;
  }

  it("interview show lists the questions and the next open one", async () => {
    const { fake, run } = setup();
    const id = await toInterview(fake, run);
    const r = await run(["builder", "interview", "show", id, "--json"]);
    expect(data(r)).toMatchObject({ open: ["audience", "level", "duration"], next: { key: "audience", options: [{ value: "new baristas" }] } });
    r.cleanup();
  });

  it("interview answer takes one key/value; the last answer starts the outline and waits for it", async () => {
    const { fake, run } = setup();
    const id = await toInterview(fake, run);
    const one = await run(["builder", "interview", "answer", id, "--key", "level", "--value", "beginner", "--json"]);
    expect(one.code).toBe(0);
    expect(data(one)).toMatchObject({ answered: ["level"], next: { key: "audience" } });
    const rest = await run(["builder", "interview", "answer", id, "--answers", '{"audience":"baristas","duration":"60|10"}', "--json"]);
    expect(data(rest)).toMatchObject({ answered: ["audience", "duration"], session: { status: "outline_review" } });
    expect(fake.db.posts.at(-1)?.body).toMatchObject({ forwardedProps: { action: { name: "answer", surfaceId: "interview", context: { key: "duration", value: "60|10" } } } });
    one.cleanup();
    rest.cleanup();
  });

  it("interview decide answers everything with the defaults", async () => {
    const { fake, run } = setup();
    const id = await toInterview(fake, run);
    const r = await run(["builder", "interview", "decide", id, "--json"]);
    expect(r.code).toBe(0);
    expect(data(r).session.status).toBe("outline_review");
    r.cleanup();
  });

  it("outline show, request-changes and approve", async () => {
    const { fake, run } = setup();
    const id = await toInterview(fake, run);
    await (await run(["builder", "interview", "decide", id, "--json"])).cleanup();
    const show = await run(["builder", "outline", "show", id, "--json"]);
    expect(data(show)).toMatchObject({ kind: "outline", status: "proposed", outline: { title: "Coffee outline", modules: [{ title: "Basics", lessons: [{ title: "Grind" }] }] } });
    const changes = await run(["builder", "outline", "request-changes", id, "--comment", "Fewer modules", "--json"]);
    expect(changes.code).toBe(0);
    expect(fake.db.posts.at(-1)).toMatchObject({ path: expect.stringContaining("/reject"), body: { comment: "Fewer modules" } });
    expect(data(changes).session.status).toBe("outline_review");
    const approve = await run(["builder", "outline", "approve", id, "--edit", "obj_1=Brew well", "--json"]);
    expect(approve.code).toBe(0);
    expect(fake.db.posts.find((p) => p.path.includes("/approve"))?.body).toEqual({ edits: [{ objectiveId: "obj_1", text: "Brew well" }] });
    expect(data(approve)).toMatchObject({ run: { kind: "generate", status: "succeeded" }, session: { status: "apply_review" } });
    for (const r of [show, changes, approve]) r.cleanup();
  });

  it("outline approve --no-wait returns the generation run handle", async () => {
    const { fake, run } = setup();
    const id = await toInterview(fake, run);
    await (await run(["builder", "interview", "decide", id, "--json"])).cleanup();
    const r = await run(["builder", "outline", "approve", id, "--no-wait", "--json"]);
    expect((r.json().meta as { operation: string }).operation).toMatch(/^builder-run:/);
    const handle = (r.json().meta as { operation: string }).operation;
    const waited = await run(["operations", "wait", handle, "--json"]);
    expect(waited.code).toBe(0);
    r.cleanup();
    waited.cleanup();
  });

  it("apply and publish", async () => {
    const { fake, run } = setup();
    const id = await toInterview(fake, run);
    await (await run(["builder", "interview", "decide", id, "--json"])).cleanup();
    await (await run(["builder", "outline", "approve", id, "--json"])).cleanup();
    const early = await run(["builder", "publish", id, "--json"]);
    expect(early.code).toBe(6);
    const apply = await run(["builder", "apply", id, "--json"]);
    expect(data(apply)).toMatchObject({ run: { kind: "apply", status: "succeeded" }, session: { status: "applied", courseId: 77 } });
    const publish = await run(["builder", "publish", id, "--json"]);
    expect(data(publish)).toEqual({ courseId: 77, published: true });
    for (const r of [early, apply, publish]) r.cleanup();
  });

  it("element chat proposes a patch with its diff; approve re-applies, reject keeps the course", async () => {
    const { fake, run } = setup();
    const id = await toInterview(fake, run);
    for (const argv of [["interview", "decide", id], ["outline", "approve", id], ["apply", id]]) await (await run(["builder", ...argv, "--json"])).cleanup();
    const elements = await run(["builder", "elements", "list", id, "--type", "paragraph", "--json"]);
    expect(data({ json: () => elements.json() })).toEqual([{ id: "blk_1", type: "paragraph", path: "modules/1/lessons/1/blocks/1", label: "Grind just before brewing." }]);
    const chat = await run(["builder", "chat", id, "Make it shorter", "--element", "blk_1", "--json"]);
    expect(chat.code).toBe(0);
    expect(fake.db.posts.at(-1)?.body).toMatchObject({ forwardedProps: { selection: { elementId: "blk_1" } }, messages: [{ role: "user", content: "Make it shorter" }] });
    const patch = data(chat).patch as { versionId: string; changes: unknown[] };
    expect(patch.changes).toHaveLength(1);
    const approve = await run(["builder", "patches", "approve", patch.versionId, "--json"]);
    expect(data(approve)).toMatchObject({ run: { kind: "apply", status: "succeeded" } });
    const again = await run(["builder", "patches", "reject", patch.versionId, "--json"]);
    expect(again.code).toBe(6);
    for (const r of [elements, chat, approve, again]) r.cleanup();
  });

  it("sessions wait stops with the failing run's error, and builder retry-able commands are listed", async () => {
    const { fake, run } = setup({ failInterview: true });
    const created = await run(["builder", "sessions", "create", "--json"]);
    const id = data(created).id as string;
    const up = await run(["builder", "sources", "add", id, "--file", "guide.md", "--no-wait", "--json"]);
    expect(up.code).toBe(0);
    const w = await run(["builder", "sessions", "wait", id, "--json"]);
    expect(w.code).toBe(9);
    expect(fake.db.session?.status).toBe("ingesting");
    for (const r of [created, up, w]) r.cleanup();
  });

  it("dry-run shows the request and sends nothing", async () => {
    const { fake, run } = setup();
    const r = await run(["builder", "sessions", "delete", "ses1", "--dry-run", "--json"]);
    expect(r.code).toBe(0);
    expect(data(r)).toMatchObject({ dryRun: true, request: { method: "DELETE" } });
    expect(fake.db.posts).toEqual([]);
    r.cleanup();
  });
});

describe("builder events", () => {
  it("prints stored events as NDJSON with their ids, filtered by type, then the envelope", async () => {
    const { fake, run } = setup();
    const r0 = await run(["builder", "start", "--from", "guide.md", "--json"]);
    r0.cleanup();
    const id = fake.db.session!.id;
    const r = await run(["builder", "events", id, "--types", "RUN_STARTED,RUN_FINISHED"]);
    expect(r.code).toBe(0);
    const lines = r.stdout.trim().split("\n").map((l) => JSON.parse(l) as Record<string, any>); // eslint-disable-line @typescript-eslint/no-explicit-any
    const events = lines.filter((l) => l.type === "event");
    expect(events.map((e) => e.data.type)).toEqual(["RUN_STARTED", "RUN_STARTED", "RUN_FINISHED", "RUN_FINISHED"]);
    expect(events.every((e) => typeof e.id === "string")).toBe(true);
    expect(lines.at(-1)).toMatchObject({ ok: true, data: { events: 4 } });
    r.cleanup();
  });

  it("--until-run stops after that run finished; a RUN_ERROR exits 9 with the event", async () => {
    const good = setup();
    const r0 = await good.run(["builder", "start", "--from", "guide.md", "--json"]);
    r0.cleanup();
    const runId = [...good.fake.db.runs.keys()][0] as string;
    const ok = await good.run(["builder", "events", good.fake.db.session!.id, "--follow", "--until-run", runId, "--types", "RUN_FINISHED"]);
    expect(ok.code).toBe(0);
    expect(ok.stdout).toContain("RUN_FINISHED");
    const bad = setup({ failInterview: true });
    const created = await bad.run(["builder", "sessions", "create", "--json"]);
    const sid = (created.json().data as { id: string }).id;
    await (await bad.run(["builder", "sources", "add", sid, "--file", "guide.md", "--no-wait", "--json"])).cleanup();
    await bad.run(["builder", "sessions", "wait", sid, "--json"]).then((x) => x.cleanup());
    const interviewRun = [...bad.fake.db.runs.values()].find((x) => x.kind === "interview")!;
    const failed = await bad.run(["builder", "events", sid, "--follow", "--until-run", interviewRun.id, "--json"]);
    expect(failed.code).toBe(9);
    expect(failed.stdout).toContain("The model refused to answer.");
    for (const r of [ok, created, failed]) r.cleanup();
  });

  it("events list is a bounded read for agents", async () => {
    const { fake, run } = setup({ failInterview: true });
    const created = await run(["builder", "sessions", "create", "--json"]);
    const sid = data(created).id as string;
    await (await run(["builder", "sources", "add", sid, "--file", "guide.md", "--no-wait", "--json"])).cleanup();
    await (await run(["builder", "sessions", "wait", sid, "--json"])).cleanup();
    void fake;
    const r = await run(["builder", "events", "list", sid, "--types", "RUN_ERROR", "--json"]);
    expect(data(r)).toMatchObject({ total: 1, events: [{ type: "RUN_ERROR", message: "The model refused to answer." }] });
    created.cleanup();
    r.cleanup();
  });
});

describe("builder publish-check and new-site", () => {
  const base = "/api/admin/course-builder/sessions/ses1";

  it("publish-check reads the blocking items and warnings without publishing", async () => {
    const check = { blocking: [{ message: "Add a cover image." }], warnings: [], facts: { lessons: 6 } };
    const r = await runCli(["builder", "publish-check", "ses1", "--json"], { env, routes: { [`GET ${base}/publish-check`]: { body: { success: true, data: check } } } });
    expect(r.code).toBe(0);
    expect(data(r)).toEqual(check);
    expect(r.requests.map((q) => `${q.method} ${q.path}`)).toEqual([`GET ${base}/publish-check`]);
    r.cleanup();
  });

  it("new-site posts the slug and name; --dry-run sends nothing", async () => {
    const routes = { [`POST ${base}/new-site`]: { status: 202, body: { success: true, data: { status: "queued" } } } };
    const sent = await runCli(["builder", "new-site", "ses1", "--slug", "coffee-atlas", "--name", "Coffee Atlas", "--json"], { env, routes });
    expect(sent.code).toBe(0);
    expect(sent.requests.at(-1)).toMatchObject({ method: "POST", path: `${base}/new-site`, body: { slug: "coffee-atlas", name: "Coffee Atlas" } });
    expect(data(sent)).toEqual({ status: "queued" });
    const dry = await runCli(["builder", "new-site", "ses1", "--slug", "coffee-atlas", "--dry-run", "--json"], { env, routes });
    expect(dry.requests).toHaveLength(0);
    for (const r of [sent, dry]) r.cleanup();
  });
});
