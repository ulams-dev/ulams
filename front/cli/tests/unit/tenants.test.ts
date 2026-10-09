import { describe, expect, it } from "vitest";
import { runCli, type Handler } from "../helpers.ts";

const env = { ULAMS_URL: "http://api.localhost", ULAMS_TOKEN: "tok-123456", ULAMS_POLL_MS: "1" };
const ok = (data: unknown, status = 200) => ({ status, body: { success: true, data, message: "OK" } });
const P = "/api/platform";

const steps = (status: string) => ["database", "bucket", "env"].map((name) => ({ name, status, startedAt: null, finishedAt: null, error: null }));
const operation = (status: string, kind = "create", extra: Record<string, unknown> = {}) => ({ id: "01j9z3k8m2x4q7r5t6v8w0y1ab", kind, status, tenant: "acme", steps: steps(status === "succeeded" ? "succeeded" : "pending"), error: null, ...extra });
const tenant = { slug: "acme", name: "Acme", status: "active", urls: { api: "http://acme.localhost", front: "http://acme.app.localhost", admin: "http://acme.admin.localhost" }, env_override_keys: [] };

function routes(extra: Record<string, Handler> = {}): Record<string, Handler> {
  let polls = 0;
  return {
    [`GET ${P}/tenants`]: ok([tenant]),
    [`GET ${P}/tenants/acme`]: ok(tenant),
    [`POST ${P}/tenants`]: (req) => ok({ operation: operation("queued"), tenant: { ...tenant, status: "provisioning", name: (req.body as { name?: string }).name ?? "Acme" } }, 202),
    [`GET ${P}/operations/01j9z3k8m2x4q7r5t6v8w0y1ab`]: () => ok(operation(++polls < 3 ? "running" : "succeeded")),
    ...extra,
  };
}

const data = (r: { json(): Record<string, unknown> }) => r.json().data as Record<string, any>; // eslint-disable-line @typescript-eslint/no-explicit-any

describe("tenants", () => {
  it("lists and shows tenants", async () => {
    const list = await runCli(["tenants", "list", "--json"], { env, routes: routes() });
    expect(data(list)).toEqual([tenant]);
    const get = await runCli(["tenants", "get", "acme", "--json"], { env, routes: routes() });
    expect(data(get)).toMatchObject({ slug: "acme", urls: { front: "http://acme.app.localhost" } });
  });

  it("create sends the options, waits for the operation and returns the finished tenant", async () => {
    const r = await runCli(["tenants", "create", "acme", "--name", "Acme Academy", "--theme", "coffee", "--accent", "#C2552D", "--users", "3", "--demo", "--json"], { env, routes: routes() });
    expect(r.code).toBe(0);
    expect(r.requests.find((q) => q.method === "POST")?.body).toEqual({ slug: "acme", name: "Acme Academy", theme: "coffee", accent: "#C2552D", users: 3, demo: true });
    expect(data(r)).toMatchObject({ operation: { status: "succeeded", kind: "create" }, tenant: { slug: "acme", status: "active" } });
    expect(r.stderr).toContain("waiting for tenant-op:");
  });

  it("create --no-wait returns the handle that operations wait understands", async () => {
    const r = await runCli(["tenants", "create", "acme", "--no-wait", "--json"], { env, routes: routes() });
    expect((r.json().meta as { operation: string }).operation).toBe("tenant-op:01j9z3k8m2x4q7r5t6v8w0y1ab");
    const waited = await runCli(["operations", "wait", "tenant-op:01j9z3k8m2x4q7r5t6v8w0y1ab", "--json"], { env, routes: routes() });
    expect(waited.code).toBe(0);
  });

  it("a failed operation exits 9 with the operation in the details", async () => {
    const failed = routes({ [`GET ${P}/operations/01j9z3k8m2x4q7r5t6v8w0y1ab`]: ok(operation("failed", "create", { error: "[migrate] boom" })) });
    const r = await runCli(["tenants", "create", "acme", "--json"], { env, routes: failed });
    expect(r.code).toBe(9);
    expect(r.json()).toMatchObject({ error: { message: expect.stringContaining("[migrate] boom"), details: { operation: "tenant-op:01j9z3k8m2x4q7r5t6v8w0y1ab" } } });
  });

  it("an existing tenant is a conflict (exit 6) and bad input a validation error (exit 7)", async () => {
    const conflict = await runCli(["tenants", "create", "acme", "--json"], { env, routes: routes({ [`POST ${P}/tenants`]: { status: 409, body: { success: false, message: "Tenant 'acme' already exists." } } }) });
    expect(conflict.code).toBe(6);
    const invalid = await runCli(["tenants", "create", "Bad_Slug", "--json"], { env, routes: routes({ [`POST ${P}/tenants`]: { status: 422, body: { success: false, message: "The slug is invalid.", errors: { slug: ["Invalid tenant slug"] } } } }) });
    expect(invalid.code).toBe(7);
    expect(invalid.json()).toMatchObject({ error: { details: { fields: { slug: ["Invalid tenant slug"] } } } });
  });

  it("explains a 404 as the platform API being off or a tenant host", async () => {
    for (const message of ["Not found.", "The route api/platform/tenants could not be found."]) {
      const r = await runCli(["tenants", "list", "--json"], { env, routes: routes({ [`GET ${P}/tenants`]: { status: 404, body: { success: false, message } } }) });
      expect(r.code).toBe(12);
      expect(r.json()).toMatchObject({ error: { code: "FEATURE_DISABLED", hint: expect.stringContaining("TENANCY_PLATFORM_API=true") } });
    }
    // an unknown tenant stays a plain not-found
    const missing = await runCli(["tenants", "get", "nope", "--json"], { env, routes: routes({ [`GET ${P}/tenants/nope`]: { status: 404, body: { success: false, message: "Tenant not found." } } }) });
    expect(missing.code).toBe(5);
  });

  it("delete is destructive: no --yes exits 11 with the plan; --yes sends the slug as the confirmation and waits", async () => {
    const del = routes({ [`DELETE ${P}/tenants/acme`]: ok({ operation: operation("queued", "delete") }, 202) });
    const refused = await runCli(["tenants", "delete", "acme", "--json"], { env, routes: del });
    expect(refused.code).toBe(11);
    expect(refused.requests.some((q) => q.method === "DELETE")).toBe(false);
    expect(refused.json()).toMatchObject({ error: { details: { plan: { request: { method: "DELETE" }, current: { slug: "acme" } } } } });

    const done = await runCli(["tenants", "delete", "acme", "--yes", "--json"], { env, routes: { ...del, [`GET ${P}/operations/01j9z3k8m2x4q7r5t6v8w0y1ab`]: ok(operation("succeeded", "delete")) } });
    expect(done.code).toBe(0);
    expect(done.requests.find((q) => q.method === "DELETE")?.body).toEqual({ confirm: "acme" });
    expect(data(done)).toMatchObject({ operation: { kind: "delete", status: "succeeded" }, tenant: null });
  });

  it("set-env sends overrides and resets, never echoing values", async () => {
    const r = await runCli(["tenants", "set-env", "acme", "--override", "ai_driver=fake", "--reset", "AI_MODEL_LIGHT", "--json"], {
      env,
      routes: routes({ [`PATCH ${P}/tenants/acme/env`]: ok({ ...tenant, env_override_keys: ["AI_DRIVER"] }) }),
    });
    expect(r.code).toBe(0);
    expect(r.requests.find((q) => q.method === "PATCH")?.body).toEqual({ set: { AI_DRIVER: "fake" }, unset: ["AI_MODEL_LIGHT"] });
    expect(r.stdout).not.toContain("fake");
    expect((await runCli(["tenants", "set-env", "acme", "--json"], { env, routes: routes() })).code).toBe(2);
    expect((await runCli(["tenants", "set-env", "acme", "--override", "NOVALUE", "--json"], { env, routes: routes() })).code).toBe(2);
  });

  it("dry-run shows the request and sends nothing", async () => {
    const r = await runCli(["tenants", "create", "acme", "--name", "Acme", "--dry-run", "--json"], { env, routes: routes() });
    expect(data(r)).toMatchObject({ dryRun: true, request: { method: "POST", body: { slug: "acme", name: "Acme" } } });
    expect(r.requests.filter((q) => q.method === "POST")).toHaveLength(0);
  });

  it("operations get and list", async () => {
    const get = await runCli(["tenants", "operations", "get", "01j9z3k8m2x4q7r5t6v8w0y1ab", "--json"], { env, routes: routes() });
    expect(data(get)).toMatchObject({ id: "01j9z3k8m2x4q7r5t6v8w0y1ab" });
    const list = await runCli(["tenants", "operations", "list", "--json"], { env, routes: routes({ [`GET ${P}/operations`]: ok([operation("succeeded")]) }) });
    expect(data(list)).toHaveLength(1);
  });
});

describe("login on a platform host", () => {
  it("saves the profile as kind platform", async () => {
    const r = await runCli(["login", "--url", "http://api.localhost", "--token-stdin", "--json"], {
      stdin: "tok\n",
      routes: {
        "GET /api/profile/me": { body: { success: true, data: { id: 1, email: "admin@ulams.app", roles: ["admin"] } } },
        "GET /api/meta": { body: { success: true, data: { kind: "platform", features: { platformApi: true } } } },
      },
    });
    expect(r.code).toBe(0);
    const { readFileSync } = await import("node:fs");
    const { join } = await import("node:path");
    expect(JSON.parse(readFileSync(join(r.configDir, "config.json"), "utf8")).profiles.api.kind).toBe("platform");
    r.cleanup();
  });
});
