import { describe, expect, it } from "vitest";
import Ajv2020 from "ajv/dist/2020.js";
import { ENVELOPE_SCHEMA } from "../../src/output/envelope.ts";
import { runCli } from "../helpers.ts";

const env = { ULAMS_URL: "http://coffee.localhost", ULAMS_TOKEN: "tok-123456" };

describe("ulams api", () => {
  it("GET returns the envelope with the API data and normalised paging", async () => {
    const r = await runCli(["api", "GET", "/api/admin/courses", "--query", "per_page=2", "--json"], {
      env,
      routes: {
        "GET /api/admin/courses": {
          body: { success: true, data: [{ id: 1, title: "A" }], meta: { current_page: 1, per_page: 2, total: 5, last_page: 3 } },
        },
      },
    });
    expect(r.code).toBe(0);
    expect(r.requests[0]?.query).toEqual({ per_page: "2" });
    expect(r.json()).toEqual({
      ok: true,
      contract: 1,
      command: "api",
      data: [{ id: 1, title: "A" }],
      meta: { page: 1, perPage: 2, total: 5, lastPage: 3, nextPage: 2 },
    });
  });

  it("fills path templates from --param and sends a JSON body", async () => {
    const r = await runCli(["api", "PUT", "/api/admin/courses/{id}", "--param", "id=12", "--body", '{"title":"New"}', "--json"], {
      env,
      routes: { "PUT /api/admin/courses/12": (req) => ({ body: { success: true, data: req.body } }) },
    });
    expect(r.code).toBe(0);
    expect(r.json()).toMatchObject({ data: { title: "New" } });
  });

  it("reads the body from a file and from stdin", async () => {
    const file = await runCli(["api", "POST", "/api/x", "--body", "@b.yaml", "--json"], {
      env,
      files: { "b.yaml": "title: From file\n" },
      routes: { "POST /api/x": (req) => ({ body: { success: true, data: req.body } }) },
    });
    expect(file.json()).toMatchObject({ data: { title: "From file" } });
    const stdin = await runCli(["api", "POST", "/api/x", "--body", "-", "--json"], {
      env,
      stdin: '{"a":1}',
      routes: { "POST /api/x": (req) => ({ body: { success: true, data: req.body } }) },
    });
    expect(stdin.json()).toMatchObject({ data: { a: 1 } });
  });

  it("--raw prints the whole response body", async () => {
    const r = await runCli(["api", "GET", "/api/x", "--raw", "--json"], { env, routes: { "GET /api/x": { body: { success: true, message: "hi", data: { a: 1 } } } } });
    expect(r.json()).toMatchObject({ data: { success: true, message: "hi", data: { a: 1 } } });
  });

  it("--dry-run prints the request and sends nothing for writes", async () => {
    const r = await runCli(["api", "POST", "/api/admin/courses", "--body", '{"title":"T"}', "--dry-run", "--json"], { env });
    expect(r.code).toBe(0);
    expect(r.requests).toHaveLength(0);
    expect(r.json()).toMatchObject({ data: { dryRun: true, request: { method: "POST", path: "/api/admin/courses", body: { title: "T" } } } });
  });

  it("DELETE needs --yes: exit 11 with the plan, then runs", async () => {
    const routes = { "DELETE /api/admin/courses/9": { body: { success: true, data: null } } };
    const refused = await runCli(["api", "DELETE", "/api/admin/courses/9", "--json"], { env, routes });
    expect(refused.code).toBe(11);
    expect(refused.requests).toHaveLength(0);
    expect(refused.json()).toMatchObject({ error: { code: "CONFIRMATION_REQUIRED", details: { plan: { request: { method: "DELETE", path: "/api/admin/courses/9" } } } } });
    const ok = await runCli(["api", "DELETE", "/api/admin/courses/9", "--yes", "--json"], { env, routes });
    expect(ok.code).toBe(0);
  });

  it("missing method or path parameter is exit 2", async () => {
    expect((await runCli(["api", "--json"], { env })).code).toBe(2);
    expect((await runCli(["api", "GET", "/api/admin/courses/{id}", "--json"], { env })).code).toBe(2);
    expect((await runCli(["api", "FETCH", "/x", "--json"], { env })).code).toBe(2);
  });

  it("--list shows operations from the bundled spec", async () => {
    const r = await runCli(["api", "--list", "--filter", "admin/courses", "--json"], { env });
    const rows = r.json().data as Array<{ method: string; path: string }>;
    expect(rows.length).toBeGreaterThan(3);
    expect(rows.every((x) => x.path.includes("admin/courses"))).toBe(true);
  });
});

describe("parser", () => {
  it("unknown command suggests a near match (exit 2)", async () => {
    const r = await runCli(["whoamy", "--json"], { env });
    expect(r.code).toBe(2);
    expect((r.json().error as { hint: string }).hint).toContain("whoami");
  });

  it("unknown flag is a usage error (exit 2)", async () => {
    const r = await runCli(["whoami", "--nope", "--json"], { env });
    expect(r.code).toBe(2);
    expect(r.json()).toMatchObject({ error: { code: "USAGE" } });
  });

  it("--input merges under explicit flags", async () => {
    const r = await runCli(["config", "set", "--input", '{"key":"color","value":"true"}', "--value", "false", "--json"], { env });
    expect(r.code).toBe(0);
    expect(r.json()).toMatchObject({ data: { color: false } });
  });

  it("--set builds nested input", async () => {
    const r = await runCli(["api", "POST", "/api/x", "--set", "body.a.b=3", "--dry-run", "--json"], { env });
    expect(r.json()).toMatchObject({ data: { request: { body: { a: { b: 3 } } } } });
  });

  it("--help prints help and exits 0", async () => {
    const r = await runCli(["api", "--help"], { env });
    expect(r.code).toBe(0);
    expect(r.stdout).toContain("ulams api");
    const root = await runCli([], { env });
    expect(root.stdout).toContain("Usage: ulams");
  });
});

describe("schema and describe", () => {
  it("schema works without credentials and validates against the envelope schema", async () => {
    const r = await runCli(["schema", "--json"], {});
    expect(r.code).toBe(0);
    const out = r.json();
    const ajv = new Ajv2020({ strict: false });
    const validate = ajv.compile(ENVELOPE_SCHEMA);
    expect(validate(out)).toBe(true);
    const data = out.data as { contract: number; commands: Array<{ id: string; mcp: { tool: string } }>; exitCodes: unknown[] };
    expect(data.contract).toBe(1);
    expect(data.commands.map((c) => c.id)).toContain("login");
    expect(data.exitCodes.length).toBeGreaterThan(15);
  });

  it("every error envelope validates against the schema", async () => {
    const r = await runCli(["whoami", "--json"], {});
    const ajv = new Ajv2020({ strict: false });
    expect(ajv.compile(ENVELOPE_SCHEMA)(r.json())).toBe(true);
  });

  it("describe returns one command and exits 5 for an unknown one", async () => {
    const ok = await runCli(["describe", "api", "--json"], {});
    expect(ok.json()).toMatchObject({ data: { id: "api", flags: expect.any(Array) } });
    const bad = await runCli(["describe", "nope", "--json"], {});
    expect(bad.code).toBe(5);
  });
});
