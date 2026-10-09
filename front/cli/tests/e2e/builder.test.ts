// Opt-in end-to-end test of the builder and Living Course commands against a running stack:
//   ULAMS_E2E=1 ULAMS_E2E_BUILDER_URL=http://clitest.localhost yarn workspace ulams test:e2e
// The tenant must run the fake AI driver (`ulams:tenant:set-env <slug> --set=AI_DRIVER=fake`) or you
// pay for real model calls (about 0.25 USD for this test). Uses the demo admin; deletes the sessions
// and courses it creates.
import { afterAll, beforeAll, describe, expect, it } from "vitest";
import { spawnSync } from "node:child_process";
import { existsSync, mkdtempSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join, resolve } from "node:path";
import { Client } from "@modelcontextprotocol/client";
import { StdioClientTransport } from "@modelcontextprotocol/client/stdio";

const URL_ = process.env.ULAMS_E2E_BUILDER_URL ?? "";
const enabled = process.env.ULAMS_E2E === "1" && URL_ !== "";
const root = resolve(__dirname, "../..");
const dist = resolve(root, "dist/ulams.mjs");
const FIXTURES = resolve(root, "../../api/packages");
const COFFEE = join(FIXTURES, "course-builder/resources/fixtures/coffee-brewing.md");
const V1 = join(FIXTURES, "living-course/resources/fixtures/coffee-brewing.v1.md");
const V2 = join(FIXTURES, "living-course/resources/fixtures/coffee-brewing.v2.md");

let dir = "";
const sessions: string[] = [];
const courses = new Set<number>();

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type Out = Record<string, any>;
function ulams(args: string[]) {
  const res = spawnSync("node", [dist, ...args, "--json"], { encoding: "utf8", env: { ...process.env, ULAMS_CONFIG_DIR: dir, ULAMS_URL: "", ULAMS_TOKEN: "" } });
  const lines = res.stdout.trim().split("\n").filter(Boolean);
  const out: Out = lines.length ? (JSON.parse(lines[lines.length - 1] as string) as Out) : {};
  return { code: res.status ?? -1, out, data: out.data as Out, stderr: res.stderr };
}

describe.skipIf(!enabled)("e2e: course builder and Living Course", () => {
  beforeAll(() => {
    if (!existsSync(dist)) spawnSync("npx", ["tsup"], { cwd: root, stdio: "inherit" });
    dir = mkdtempSync(join(tmpdir(), "ulams-e2e-builder-"));
    expect(ulams(["login", "--url", URL_, "--demo", "admin"]).code).toBe(0);
  });
  afterAll(() => {
    for (const id of sessions) ulams(["builder", "sessions", "delete", id, "--yes"]);
    for (const id of courses) ulams(["courses", "delete", String(id), "--yes"]);
    rmSync(dir, { recursive: true, force: true });
  });

  it("starts from a file, answers from a file, reviews every stage, applies, chats with an element, undoes, publishes", () => {
    // 1. until the first question
    const first = ulams(["builder", "start", "--from", COFFEE, "--title", "CLI e2e stages"]);
    expect(first.code, JSON.stringify(first.out)).toBe(0);
    const id = first.data.sessionId as string;
    sessions.push(id);
    expect(first.data).toMatchObject({ status: "interviewing", pending: { type: "interview" } });
    const keys = (first.data.pending.questions as Array<{ key: string }>).map((q) => q.key);
    expect(keys).toEqual(expect.arrayContaining(["audience", "level", "duration"]));

    // 2. answers from a file, the rest by default
    const answers = join(dir, "answers.json");
    writeFileSync(answers, JSON.stringify({ audience: "home baristas", level: "beginner", duration: "30|10" }));
    const shown = ulams(["builder", "interview", "show", id]);
    expect(shown.data.open).toEqual(expect.arrayContaining(["audience"]));
    const answered = ulams(["builder", "interview", "answer", id, "--answers", `@${answers}`, "--defaults"]);
    expect(answered.code, JSON.stringify(answered.out)).toBe(0);
    expect(answered.data.session.status).toBe("outline_review");

    // 3. the outline: ask for a change once, then approve
    const outline = ulams(["builder", "outline", "show", id]);
    expect(outline.data.outline.modules.length).toBeGreaterThan(0);
    const changes = ulams(["builder", "outline", "request-changes", id, "--comment", "Fewer, shorter modules"]);
    expect(changes.data.session.status).toBe("outline_review");
    const approved = ulams(["builder", "outline", "approve", id]);
    expect(approved.code, JSON.stringify(approved.out)).toBe(0);
    expect(approved.data.session.status).toBe("apply_review");

    // 4. apply (unpublished), then chat with one element
    const applied = ulams(["builder", "apply", id]);
    expect(applied.data.session.status).toBe("applied");
    const courseId = applied.data.session.courseId as number;
    courses.add(courseId);
    const element = (ulams(["builder", "elements", "list", id, "--type", "paragraph"]).data as unknown as Array<{ id: string }>)[0] as { id: string };
    const chat = ulams(["builder", "chat", id, "Make this shorter", "--element", element.id]);
    expect(chat.code, JSON.stringify(chat.out)).toBe(0);
    const patch = chat.data.patch as { versionId: string; changes: unknown[] };
    expect(patch.versionId).toBeTruthy();
    expect(ulams(["builder", "patches", "approve", patch.versionId]).data.session.status).toBe("applied");

    // 5. undo and redo, versions, usage, events
    expect(ulams(["builder", "undo", id]).code).toBe(0);
    expect(ulams(["builder", "redo", id]).code).toBe(0);
    expect(ulams(["builder", "versions", "list", id]).data.versions.length).toBeGreaterThanOrEqual(4);
    expect(ulams(["builder", "usage", id]).data.total.calls).toBeGreaterThan(0);
    const events = spawnSync("node", [dist, "builder", "events", id, "--types", "RUN_FINISHED"], { encoding: "utf8", env: { ...process.env, ULAMS_CONFIG_DIR: dir, ULAMS_URL: "", ULAMS_TOKEN: "" } });
    const lines = events.stdout.trim().split("\n").map((l) => JSON.parse(l) as Out);
    expect(lines.filter((l) => l.type === "event").length).toBeGreaterThan(3);
    expect(lines.every((l) => l.type !== "event" || typeof l.id === "string")).toBe(true);

    // 6. publish
    expect(ulams(["builder", "publish", id]).data).toMatchObject({ courseId, published: true });
    expect(ulams(["courses", "get", String(courseId)]).data.status).toBe("published");
  }, 600_000);

  it("a whole course in one command, then a source update through Living Course", () => {
    const run = ulams(["builder", "start", "--from", V1, "--title", "CLI e2e living", "--defaults", "--approve-outline", "--apply"]);
    expect(run.code, JSON.stringify(run.out)).toBe(0);
    const id = run.data.sessionId as string;
    sessions.push(id);
    courses.add(run.data.courseId as number);
    expect(run.data.status).toBe("applied");

    const source = (ulams(["living", "sources", "list", id]).data as unknown as Array<{ id: string }>)[0] as { id: string };
    const up = ulams(["living", "revisions", "upload", source.id, V2]);
    expect(up.code, JSON.stringify(up.out)).toBe(0);
    expect(up.data.revision.number).toBe(2);
    expect(ulams(["living", "revisions", "changes", up.data.revision.id]).data.changes?.length ?? 1).toBeGreaterThan(0);

    const proposal = (ulams(["living", "proposals", "list", id]).data as unknown as Array<{ id: string }>)[0] as { id: string };
    const detail = ulams(["living", "proposals", "get", proposal.id]);
    const item = (detail.data.items as Array<{ id: string }>)[0] as { id: string };
    expect(ulams(["living", "proposals", "reject", proposal.id, "--item", item.id]).data.item.status).toBe("rejected");
    expect(ulams(["living", "proposals", "accept-all", proposal.id]).data.accepted).toBeGreaterThan(0);
    const applied = ulams(["living", "proposals", "apply", proposal.id]);
    expect(applied.code, JSON.stringify(applied.out)).toBe(0);
    expect(applied.data.run.status).toBe("succeeded");
    expect(ulams(["living", "audit", "verify", id]).data.ok).toBe(true);
    expect(ulams(["living", "audit", "list", id, "--per-page", "5"]).data.entries.length).toBeGreaterThan(0);
  }, 600_000);

  it("the same commands are MCP tools: ulams mcp over stdio drives the builder", async () => {
    const client = new Client({ name: "e2e", version: "1" });
    const token = ulams(["whoami"]);
    expect(token.code).toBe(0);
    const transport = new StdioClientTransport({ command: "node", args: [dist, "mcp", "--toolsets", "core,builder,living"], env: { ...process.env, ULAMS_CONFIG_DIR: dir, ULAMS_URL: "", ULAMS_TOKEN: "" }, stderr: "pipe" });
    await client.connect(transport);
    const names = (await client.listTools()).tools.map((t) => t.name);
    expect(names).toEqual(expect.arrayContaining(["builder_start", "builder_interview_answer", "builder_outline_approve", "living_proposals_list"]));
    const started = (await client.callTool({ name: "builder_start", arguments: { from: [COFFEE], title: "CLI e2e mcp", defaults: true, approveOutline: true, apply: true, timeout_seconds: 300 } })).structuredContent as Out;
    expect(started.ok, JSON.stringify(started)).toBe(true);
    const id = (started.data as Out).sessionId as string;
    sessions.push(id);
    if ((started.data as Out).courseId) courses.add((started.data as Out).courseId as number);
    const got = (await client.callTool({ name: "builder_sessions_get", arguments: { session: id } })).structuredContent as Out;
    expect(got.data.status).toMatch(/applied|apply_review|generating|outline_review/);
    await client.close();
  }, 600_000);
});
