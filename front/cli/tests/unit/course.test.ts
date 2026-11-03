import { describe, expect, it } from "vitest";
import { runCli, type Handler } from "../helpers.ts";

const env = { ULAMS_URL: "http://coffee.localhost", ULAMS_TOKEN: "tok-123456" };
const P = "/api/admin/course-as-code";
const SESSION = "01j9z3k8m2x4q7r5t6v8w0y1ab";
const VERSION = "01j9z3k8m2x4q7r5t6v8w0y1ac";

const payload = {
  courseId: 12,
  sessionId: SESSION,
  versionId: VERSION,
  versionNumber: 4,
  appliedVersionId: VERSION,
  schemaVersion: 3,
  drift: [],
  document: { schemaVersion: 3, sources: [], course: { id: "c1", title: "Coffee", language: "en", objectives: [] }, modules: [], finalTest: null, pages: { landing: null, header: null } },
};
const ok = (data: unknown, status = 200): Handler => ({ status, body: { success: true, data, message: "OK" } });

describe("course export", () => {
  it("prints the blueprint payload", async () => {
    const r = await runCli(["course", "export", "12", "--json"], { env, routes: { [`GET ${P}/courses/12/blueprint`]: ok(payload) } });
    expect(r.code).toBe(0);
    expect((r.json().data as typeof payload).versionId).toBe(VERSION);
    expect(r.requests[0]?.headers.authorization).toBe("Bearer tok-123456");
    r.cleanup();
  });

  it("--out writes the whole payload to a file and prints a summary without the document", async () => {
    const written: Record<string, string> = {};
    const { nodeFs } = await import("../../src/cli/run.ts");
    const r = await runCli(["course", "export", "12", "--out", "bp.json", "--json"], {
      env,
      routes: { [`GET ${P}/courses/12/blueprint`]: ok(payload) },
      fs: { ...nodeFs, writeFile: async (p, d) => void (written[p] = String(d)) },
    });
    expect(r.code).toBe(0);
    expect(JSON.parse(written["bp.json"] ?? "{}").document.schemaVersion).toBe(3);
    const data = r.json().data as Record<string, unknown>;
    expect(data).toMatchObject({ versionId: VERSION, file: "bp.json" });
    expect(data).not.toHaveProperty("document");
    r.cleanup();
  });

  it("rejects a course id that is not a number before calling the API", async () => {
    const r = await runCli(["course", "export", "abc", "--json"], { env, routes: {} });
    expect(r.code).not.toBe(0);
    expect(r.requests).toHaveLength(0);
    r.cleanup();
  });

  it("passes the API's NOT_ADOPTED refusal through as an error", async () => {
    const r = await runCli(["course", "export", "12", "--json"], { env, routes: { [`GET ${P}/courses/12/blueprint`]: { status: 409, body: { success: false, message: "This course was not made with the builder, so it has no blueprint yet.", code: "NOT_ADOPTED" } } } });
    expect(r.code).not.toBe(0);
    expect(r.stdout + r.stderr).toContain("no blueprint yet");
    r.cleanup();
  });
});

describe("course import", () => {
  const response = { unchanged: false, version: { id: "v2", number: 5, kind: "import", status: "proposed", parentId: VERSION }, counts: { added: 0, changed: 1, removed: 0 }, changes: [], warnings: [], handEdited: [] };

  it("takes the session and base version from a file written by `course export --out`", async () => {
    const r = await runCli(["course", "import", "--file", "bp.json", "--message", "Fix install", "--json"], {
      env,
      files: { "bp.json": JSON.stringify(payload) },
      routes: { [`POST ${P}/sessions/${SESSION}/imports`]: ok(response, 201) },
    });
    expect(r.code).toBe(0);
    expect(r.requests[0]?.body).toEqual({ document: payload.document, baseVersionId: VERSION, message: "Fix install" });
    expect((r.json().data as typeof response).version.status).toBe("proposed");
    r.cleanup();
  });

  it("accepts a bare document with explicit session and --base, and the output of `--json` export", async () => {
    const routes = { [`POST ${P}/sessions/${SESSION}/imports`]: ok(response, 201) };
    const bare = await runCli(["course", "import", SESSION, "--file", "doc.json", "--base", VERSION, "--json"], { env, files: { "doc.json": JSON.stringify(payload.document) }, routes });
    expect(bare.code).toBe(0);
    expect((bare.requests[0]?.body as { baseVersionId: string }).baseVersionId).toBe(VERSION);
    const enveloped = await runCli(["course", "import", "--file", "e.json", "--json"], { env, files: { "e.json": JSON.stringify({ ok: true, data: payload }) }, routes });
    expect(enveloped.code).toBe(0);
    bare.cleanup();
    enveloped.cleanup();
  });

  it("explains a missing session or base instead of guessing", async () => {
    const r = await runCli(["course", "import", "--file", "doc.json", "--json"], { env, files: { "doc.json": JSON.stringify(payload.document) }, routes: {} });
    expect(r.code).not.toBe(0);
    expect(r.stdout + r.stderr).toContain("Pass the builder session");
    expect(r.requests).toHaveLength(0);
    const notJson = await runCli(["course", "import", "--file", "x.json", "--json"], { env, files: { "x.json": "{nope" }, routes: {} });
    expect(notJson.stdout + notJson.stderr).toContain("not valid JSON");
    const notBlueprint = await runCli(["course", "import", "--file", "y.json", "--json"], { env, files: { "y.json": '{"hello":1}' }, routes: {} });
    expect(notBlueprint.stdout + notBlueprint.stderr).toContain("neither a blueprint document");
    r.cleanup();
    notJson.cleanup();
    notBlueprint.cleanup();
  });

  it("--dry-run plans the request and sends nothing", async () => {
    const r = await runCli(["course", "import", "--file", "bp.json", "--dry-run", "--json"], { env, files: { "bp.json": JSON.stringify(payload) }, routes: {} });
    expect(r.code).toBe(0);
    expect(r.requests).toHaveLength(0);
    expect(JSON.stringify(r.json())).toContain(`${P}/sessions/${SESSION}/imports`);
    r.cleanup();
  });

  it("shows BASE_OUTDATED as an error", async () => {
    const r = await runCli(["course", "import", "--file", "bp.json", "--json"], {
      env,
      files: { "bp.json": JSON.stringify(payload) },
      routes: { [`POST ${P}/sessions/${SESSION}/imports`]: { status: 409, body: { success: false, message: "The course changed on the server since your base version; pull first.", code: "BASE_OUTDATED", data: { currentVersionId: "v9" } } } },
    });
    expect(r.code).not.toBe(0);
    expect(r.stdout + r.stderr).toContain("pull first");
    r.cleanup();
  });
});

describe("course diff, approve, apply, publish", () => {
  it("calls the AI-free endpoints", async () => {
    const routes = {
      [`GET ${P}/versions/v2/diff`]: ok({ against: VERSION, changes: [] }),
      [`POST ${P}/versions/v2/approve`]: ok({ versionId: "v2", status: "approved", applied: false }),
      [`POST ${P}/sessions/${SESSION}/apply`]: ok({ runId: "run1" }, 202),
      [`POST ${P}/sessions/${SESSION}/publish`]: ok({ courseId: 12, published: true }),
    };
    const diff = await runCli(["course", "diff", "--version", "v2", "--against", VERSION, "--json"], { env, routes });
    expect(diff.code).toBe(0);
    expect(diff.requests[0]?.query).toEqual({ against: VERSION });
    const approve = await runCli(["course", "approve", "v2", "--json"], { env, routes });
    expect((approve.json().data as { status: string }).status).toBe("approved");
    const apply = await runCli(["course", "apply", SESSION, "--json"], { env, routes });
    expect(apply.code).toBe(0);
    expect(JSON.stringify(apply.json())).toContain("builder-run:run1");
    const publish = await runCli(["course", "publish", SESSION, "--acknowledge-warnings", "--json"], { env, routes });
    expect(publish.requests[0]?.body).toEqual({ acknowledgedWarnings: true });
    for (const r of [diff, approve, apply, publish]) r.cleanup();
  });

  it("an approval refused for an agent token surfaces the server's reason", async () => {
    const r = await runCli(["course", "approve", "v2", "--json"], { env, routes: { [`POST ${P}/versions/v2/approve`]: { status: 403, body: { success: false, message: "An agent token can propose changes but only a person can approve them.", code: "APPROVAL_REQUIRES_HUMAN" } } } });
    expect(r.code).not.toBe(0);
    expect(r.stdout + r.stderr).toContain("only a person can approve");
    r.cleanup();
  });
});
