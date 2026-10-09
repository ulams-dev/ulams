import { describe, expect, it } from "vitest";
import { runCli, type Handler } from "../helpers.ts";

const env = { ULAMS_URL: "http://coffee.localhost", ULAMS_TOKEN: "tok-123456", ULAMS_POLL_MS: "1" };
const ok = (data: unknown, status = 200) => ({ status, body: { success: true, data, message: "OK" } });
const LC = "/api/admin/living-course";

function routes(extra: Record<string, Handler> = {}): Record<string, Handler> {
  return {
    [`GET ${LC}/sessions/ses1/sources`]: ok([{ id: "src1", name: "handbook.md", connection: { connector: "upload" } }]),
    [`GET ${LC}/sources/src1/revisions`]: ok([{ id: "rev2", number: 2 }, { id: "rev1", number: 1 }]),
    [`GET ${LC}/revisions/rev2/changes`]: (req) => ok({ against: req.query.against ?? "rev1", changes: [{ kind: "changed", fragmentId: "frg_a" }] }),
    [`GET ${LC}/sessions/ses1/proposals`]: ok([{ id: "prop1", number: 1, status: "ready" }]),
    [`GET ${LC}/proposals/prop1`]: ok({ id: "prop1", status: "ready", items: [{ id: "item1", status: "pending" }], groups: [] }),
    [`POST ${LC}/proposals/prop1/items/item1/accept`]: ok({ item: { id: "item1", status: "accepted" }, proposal: { id: "prop1" } }),
    [`POST ${LC}/proposals/prop1/items/item1/reject`]: ok({ item: { id: "item1", status: "rejected" }, proposal: { id: "prop1" } }),
    ...extra,
  };
}

const data = (r: { json(): Record<string, unknown> }) => r.json().data as Record<string, any>; // eslint-disable-line @typescript-eslint/no-explicit-any

describe("living course commands", () => {
  it("lists sources, revisions and the changes of a revision", async () => {
    const sources = await runCli(["living", "sources", "list", "ses1", "--json"], { env, routes: routes() });
    expect(data(sources)).toEqual([{ id: "src1", name: "handbook.md", connection: { connector: "upload" } }]);
    const revisions = await runCli(["living", "revisions", "list", "src1", "--json"], { env, routes: routes() });
    expect(data(revisions)).toHaveLength(2);
    const changes = await runCli(["living", "revisions", "changes", "rev2", "--against", "rev0", "--json"], { env, routes: routes() });
    expect(data(changes)).toMatchObject({ against: "rev0", changes: [{ kind: "changed" }] });
  });

  it("proposals list and get", async () => {
    const list = await runCli(["living", "proposals", "list", "ses1", "--json"], { env, routes: routes() });
    expect(data(list)[0]).toMatchObject({ id: "prop1", status: "ready" });
    const get = await runCli(["living", "proposals", "get", "prop1", "--json"], { env, routes: routes() });
    expect(data(get).items).toHaveLength(1);
  });

  it("accepts and rejects one item", async () => {
    const accept = await runCli(["living", "proposals", "accept", "prop1", "--item", "item1", "--json"], { env, routes: routes() });
    expect(data(accept).item.status).toBe("accepted");
    const reject = await runCli(["living", "proposals", "reject", "prop1", "--item", "item1", "--json"], { env, routes: routes() });
    expect(data(reject).item.status).toBe("rejected");
    const missing = await runCli(["living", "proposals", "accept", "prop1", "--json"], { env, routes: routes() });
    expect(missing.code).toBe(2);
  });

  it("apply waits for the run; --no-wait returns its handle", async () => {
    let polls = 0;
    const apply = routes({
      [`POST ${LC}/proposals/prop1/apply`]: (req) => ok({ runId: "run1", proposal: { id: "prop1" }, overwrite: (req.body as { overwrite: boolean }).overwrite }, 202),
      "GET /api/admin/course-builder/runs/run1": () => ok({ id: "run1", sessionId: "ses1", kind: "apply", status: ++polls < 3 ? "running" : "succeeded", stage: null, needsAttention: false, steps: [{ id: "s", name: "apply", status: "succeeded", error: null }], error: null }),
    });
    const waited = await runCli(["living", "proposals", "apply", "prop1", "--json"], { env, routes: apply });
    expect(waited.code).toBe(0);
    expect(data(waited)).toMatchObject({ runId: "run1", run: { kind: "apply", status: "succeeded" } });
    const later = await runCli(["living", "proposals", "apply", "prop1", "--no-wait", "--json"], { env, routes: apply });
    expect((later.json().meta as { operation: string }).operation).toBe("builder-run:run1");
  });

  it("apply explains 409 conflicts and admin edits", async () => {
    const conflict = routes({ [`POST ${LC}/proposals/prop1/apply`]: { status: 409, body: { success: false, message: "An admin edited this course.", code: "admin_edits", data: { drift: ["Lesson 1"] } } } });
    const r = await runCli(["living", "proposals", "apply", "prop1", "--json"], { env, routes: conflict });
    expect(r.code).toBe(6);
    expect(r.json()).toMatchObject({ error: { code: "CONFLICT", hint: expect.stringContaining("--overwrite") } });
  });

  it("analyse asks for the cost estimate to be confirmed", async () => {
    const r = await runCli(["living", "proposals", "analyse", "prop1", "--json"], {
      env,
      routes: routes({ [`POST ${LC}/proposals/prop1/analyse`]: { status: 409, body: { success: false, message: "Above the automatic limit.", code: "confirm_estimate", data: { estimateMicroUsd: 2_500_000 } } } }),
    });
    expect(r.code).toBe(6);
    expect(r.json()).toMatchObject({ error: { hint: expect.stringContaining("2.50 USD") } });
  });

  it("reject-all is destructive and needs --yes", async () => {
    const r = await runCli(["living", "proposals", "reject-all", "prop1", "--json"], { env, routes: routes() });
    expect(r.code).toBe(11);
  });

  it("revisions upload sends the file as multipart", async () => {
    const r = await runCli(["living", "revisions", "upload", "src1", "handbook-v2.md", "--json"], {
      env,
      files: { "handbook-v2.md": "# v2" },
      routes: routes({ [`POST ${LC}/sources/src1/revisions`]: ok({ revision: { id: "rev3" }, created: true }, 201) }),
    });
    expect(r.code).toBe(0);
    expect((r.requests.at(-1)?.form?.get("file") as File).name).toBe("handbook-v2.md");
  });

  it("audit export writes a file with --out", async () => {
    const r = await runCli(["living", "audit", "export", "ses1", "--format", "json", "--out", "/tmp/ulams-audit-test.json", "--json"], {
      env,
      fs: { readFile: async () => new Uint8Array(), readText: async () => "", writeFile: async () => undefined, exists: async () => true },
      routes: routes({ [`GET ${LC}/sessions/ses1/audit/export`]: { raw: '[{"id":1}]', headers: { "content-type": "application/json" } } }),
    });
    expect(r.code).toBe(0);
    expect(r.requests.at(-1)?.query).toMatchObject({ format: "json" });
  });
});
