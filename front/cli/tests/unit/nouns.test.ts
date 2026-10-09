import { describe, expect, it } from "vitest";
import { fakeLms } from "../fake-lms.ts";
import { runCli } from "../helpers.ts";

const env = { ULAMS_URL: "http://coffee.localhost", ULAMS_TOKEN: "tok-123456" };

describe("generated noun commands", () => {
  it("lists with paging flags, field projection and normalised meta", async () => {
    const r = await runCli(["courses", "list", "--page", "2", "--per-page", "5", "--title", "Kube", "--fields", "id,title", "--json"], {
      env,
      routes: { "GET /api/admin/courses": { body: { success: true, data: [{ id: 1, title: "Kubernetes", status: "draft" }], meta: { current_page: 2, per_page: 5, total: 7, last_page: 2 } } } },
    });
    expect(r.requests[0]?.query).toEqual({ page: "2", per_page: "5", title: "Kube" });
    expect(r.json()).toMatchObject({ data: [{ id: 1, title: "Kubernetes" }], meta: { page: 2, lastPage: 2, nextPage: null } });
    expect(JSON.stringify(r.json().data)).not.toContain("status");
  });

  it("--all follows the pages and --limit stops early", async () => {
    const pages: Record<string, unknown> = {
      "1": { success: true, data: [{ id: 1 }, { id: 2 }], meta: { current_page: 1, per_page: 2, total: 5, last_page: 3 } },
      "2": { success: true, data: [{ id: 3 }, { id: 4 }], meta: { current_page: 2, per_page: 2, total: 5, last_page: 3 } },
      "3": { success: true, data: [{ id: 5 }], meta: { current_page: 3, per_page: 2, total: 5, last_page: 3 } },
    };
    const routes = { "GET /api/admin/courses": (req: { query: Record<string, string> }) => ({ body: pages[req.query.page ?? "1"] }) };
    const all = await runCli(["courses", "list", "--all", "--json"], { env, routes });
    expect((all.json().data as unknown[]).length).toBe(5);
    const limited = await runCli(["courses", "list", "--all", "--limit", "3", "--json"], { env, routes });
    expect((limited.json().data as unknown[]).length).toBe(3);
    const nd = await runCli(["courses", "list", "--all", "--output", "ndjson"], { env, routes });
    const lines = nd.stdout.trim().split("\n").map((l) => JSON.parse(l));
    expect(lines.filter((l) => l.type === "item")).toHaveLength(5);
    expect(lines.at(-1)).toMatchObject({ type: "end", ok: true });
  });

  it("validates required input before any request (exit 2) and names the flag", async () => {
    const r = await runCli(["courses", "create", "--json"], { env });
    expect(r.code).toBe(2);
    expect(r.requests).toHaveLength(0);
    expect(r.json()).toMatchObject({ error: { code: "INPUT_INVALID" } });
    expect((r.json().error as { message: string }).message).toContain("title");
  });

  it("create sends the flags as the JSON body; positionals fill path parameters", async () => {
    const created = await runCli(["lessons", "create", "--title", "L1", "--order", "1", "--course-id", "4", "--json"], {
      env,
      routes: { "POST /api/admin/lessons": (req) => ({ body: { success: true, data: { id: 9, ...(req.body as object) } } }) },
    });
    expect(created.json()).toMatchObject({ data: { id: 9, title: "L1", order: 1, course_id: 4 } });
    const got = await runCli(["lessons", "get", "9", "--json"], { env, routes: { "GET /api/admin/lessons/9": { body: { success: true, data: { id: 9 } } } } });
    expect(got.code).toBe(0);
  });

  it("--dry-run shows the request and, for updates, a diff against the current resource", async () => {
    const r = await runCli(["lessons", "update", "9", "--title", "New", "--dry-run", "--json"], {
      env,
      routes: { "GET /api/admin/lessons/9": { body: { success: true, data: { id: 9, title: "Old" } } } },
    });
    expect(r.requests.map((x) => x.method)).toEqual(["GET"]);
    expect(r.json()).toMatchObject({ data: { dryRun: true, request: { method: "PUT", path: "/api/admin/lessons/9" }, changes: [{ op: "replace", path: "/title", from: "Old", to: "New" }] } });
  });

  it("destructive commands exit 11 with the current resource in the plan, --yes deletes", async () => {
    const routes = {
      "GET /api/admin/lessons/9": { body: { success: true, data: { id: 9, title: "Old" } } },
      "DELETE /api/admin/lessons/9": { body: { success: true, data: null } },
    };
    const refused = await runCli(["lessons", "delete", "9", "--json"], { env, routes });
    expect(refused.code).toBe(11);
    expect(refused.json()).toMatchObject({ error: { code: "CONFIRMATION_REQUIRED", details: { plan: { current: { id: 9 } } } } });
    expect(refused.requests.some((q) => q.method === "DELETE")).toBe(false);
    const ok = await runCli(["lessons", "delete", "9", "--yes", "--json"], { env, routes });
    expect(ok.code).toBe(0);
    expect(ok.requests.some((q) => q.method === "DELETE")).toBe(true);
  });

  it("downloads need --out and write the bytes", async () => {
    const written: Record<string, Uint8Array | string> = {};
    const fs = {
      readFile: async () => new Uint8Array(),
      readText: async () => "",
      writeFile: async (p: string, d: Uint8Array | string) => void (written[p] = d),
      exists: async () => true,
    };
    const routes = { "GET /api/admin/courses/4/export": { raw: "ZIPDATA", headers: { "content-type": "application/zip" } } };
    const no = await runCli(["courses", "export", "4", "--json"], { env, routes, fs });
    expect(no.code).toBe(2);
    const yes = await runCli(["courses", "export", "4", "--out", "/tmp/c.zip", "--json"], { env, routes, fs });
    expect(yes.json()).toMatchObject({ data: { path: "/tmp/c.zip", bytes: 7, contentType: "application/zip" } });
    expect(new TextDecoder().decode(written["/tmp/c.zip"] as Uint8Array)).toBe("ZIPDATA");
  });
});

describe("curated commands on a fake LMS", () => {
  it("publish is idempotent and --dry-run changes nothing", async () => {
    const lms = fakeLms();
    lms.db.courses.push({ id: 4, title: "T", status: "draft" });
    const dry = await runCli(["courses", "publish", "4", "--dry-run", "--json"], { env, routes: lms.routes });
    expect(dry.json()).toMatchObject({ data: { dryRun: true, changes: [{ op: "replace", path: "/status", from: "draft", to: "published" }] } });
    expect(lms.db.writes).toBe(0);
    const first = await runCli(["courses", "publish", "4", "--json"], { env, routes: lms.routes });
    expect(first.json()).toMatchObject({ data: { status: "published", changed: true } });
    const second = await runCli(["courses", "publish", "4", "--json"], { env, routes: lms.routes });
    expect(second.json()).toMatchObject({ data: { changed: false } });
    expect(lms.db.writes).toBe(1);
  });

  it("access grant, list, revoke and enrol", async () => {
    const lms = fakeLms();
    expect((await runCli(["access", "grant", "--course", "4", "--user", "3", "--json"], { env, routes: lms.routes })).code).toBe(0);
    expect((await runCli(["enrol", "--course", "4", "--user", "3", "--json"], { env, routes: lms.routes })).code).toBe(0);
    const list = await runCli(["access", "list", "--course", "4", "--json"], { env, routes: lms.routes });
    expect(list.json()).toMatchObject({ data: { users: [{ id: 3 }] } });
    expect((await runCli(["access", "grant", "--course", "4", "--json"], { env, routes: lms.routes })).code).toBe(2);
    await runCli(["access", "revoke", "--course", "4", "--user", "3", "--json"], { env, routes: lms.routes });
    expect(lms.db.access.get(4)?.users).toEqual([]);
    const set = await runCli(["access", "set", "--course", "4", "--user", "3", "--json"], { env, routes: lms.routes });
    expect(set.code).toBe(11);
  });

  it("settings set and theme set create, update and skip unchanged values", async () => {
    const lms = fakeLms();
    const a = await runCli(["theme", "set", "--accent", "#fff", "--json"], { env, routes: lms.routes });
    expect(a.json()).toMatchObject({ data: { accent: { action: "created" } } });
    const b = await runCli(["theme", "set", "--accent", "#000", "--json"], { env, routes: lms.routes });
    expect(b.json()).toMatchObject({ data: { accent: { action: "updated" } } });
    const c = await runCli(["theme", "set", "--accent", "#000", "--json"], { env, routes: lms.routes });
    expect(c.json()).toMatchObject({ data: { accent: { action: "unchanged" } } });
    const get = await runCli(["theme", "get", "--json"], { env, routes: lms.routes });
    expect(get.json()).toMatchObject({ data: { accent: "#000" } });
    const s = await runCli(["settings", "set", "global", "companyName", "Acme", "--json"], { env, routes: lms.routes });
    expect(s.json()).toMatchObject({ data: { action: "created" } });
    expect((await runCli(["settings", "get", "global", "nope", "--json"], { env, routes: lms.routes })).code).toBe(5);
  });
});
