import { describe, expect, it } from "vitest";
import { runCli, type Handler } from "../helpers.ts";

const env = { ULAMS_URL: "http://coffee.localhost", ULAMS_TOKEN: "tok-123456", ULAMS_POLL_MS: "1" };
const ok = (data: unknown, status = 200, extra: Record<string, unknown> = {}) => ({ status, body: { success: true, data, message: "OK", ...extra } });
const R = "/api/admin/reporting";
const ID = "01J9Z3K8M2X4Q7R5T6V8W0Y1AB";
const definition = { dataset: "assignments", columns: ["course_title", "status"] };
const saved = { id: ID, name: "Overdue", dataset: "assignments", visibility: "private", shared_roles: [], definition, can_edit: true, runnable: true, needs_repair: false, feed_enabled: false };

function routes(extra: Record<string, Handler> = {}): Record<string, Handler> {
  return {
    [`GET ${R}/datasets`]: ok([{ key: "assignments", label: "Training assignments", columns: [{ name: "course_title", type: "string" }] }]),
    [`POST ${R}/query`]: ok({ columns: [{ name: "course_title", type: "string", label: "Course" }], rows: [{ course_title: "Safety", status: "overdue" }] }, 200, { meta: { mode: "rows", total_rows: 1, withheld_groups: 0 } }),
    [`GET ${R}/saved`]: ok([saved]),
    [`GET ${R}/saved/${ID}`]: ok(saved),
    [`POST ${R}/saved`]: (req) => ok({ ...saved, ...(req.body as object) }, 201),
    [`PUT ${R}/saved/${ID}`]: (req) => ok({ ...saved, ...(req.body as object) }),
    [`DELETE ${R}/saved/${ID}`]: ok(null),
    ...extra,
  };
}

const data = (r: { json(): Record<string, unknown> }) => r.json().data as Record<string, any>; // eslint-disable-line @typescript-eslint/no-explicit-any

describe("reporting commands", () => {
  it("datasets and list are plain reads", async () => {
    const datasets = await runCli(["reporting", "datasets", "--json"], { env, routes: routes() });
    expect(datasets.code).toBe(0);
    expect(data(datasets)[0]).toMatchObject({ key: "assignments" });
    const list = await runCli(["reporting", "list", "--json"], { env, routes: routes() });
    expect(data(list)[0]).toMatchObject({ id: ID, name: "Overdue", runnable: true });
    const show = await runCli(["reporting", "show", ID, "--json"], { env, routes: routes() });
    expect(data(show)).toMatchObject({ id: ID, definition });
  });

  it("query reads the definition from --file and sends it as the body", async () => {
    const r = await runCli(["reporting", "query", "--file", "def.json", "--json"], { env, files: { "def.json": JSON.stringify(definition) }, routes: routes() });
    expect(r.code).toBe(0);
    expect(r.requests.at(-1)?.body).toEqual(definition);
    expect(data(r).rows).toEqual([{ course_title: "Safety", status: "overdue" }]);
    expect(r.json().meta).toMatchObject({ mode: "rows", total_rows: 1, withheld_groups: 0 });
  });

  it("query tells what is wrong with the definition file", async () => {
    const missing = await runCli(["reporting", "query", "--json"], { env, routes: routes() });
    expect(missing.code).toBe(2);
    expect(missing.json()).toMatchObject({ error: { code: "INPUT_INVALID", message: expect.stringContaining("--file") } });
    const notJson = await runCli(["reporting", "query", "--file", "bad.json", "--json"], { env, files: { "bad.json": "{nope" }, routes: routes() });
    expect(notJson.json()).toMatchObject({ error: { message: expect.stringContaining("not a JSON object") } });
    const array = await runCli(["reporting", "query", "--file", "list.json", "--json"], { env, files: { "list.json": "[1]" }, routes: routes() });
    expect(array.code).toBe(2);
    const both = await runCli(["reporting", "query", "--file", "def.json", "--definition", JSON.stringify(definition), "--json"], { env, files: { "def.json": "{}" }, routes: routes() });
    expect(both.json()).toMatchObject({ error: { message: expect.stringContaining("not both") } });
  });

  it("a server refusal of the definition is a validation error with the path", async () => {
    const r = await runCli(["reporting", "query", "--file", "def.json", "--json"], {
      env,
      files: { "def.json": JSON.stringify({ dataset: "assignments", columns: ["nope"] }) },
      routes: routes({ [`POST ${R}/query`]: { status: 422, body: { success: false, message: "The given data was invalid.", errors: { "columns.0": ['Unknown column "nope".'] } } } }),
    });
    expect(r.code).toBe(7);
    expect(r.json()).toMatchObject({ error: { details: { fields: { "columns.0": ['Unknown column "nope".'] } } } });
  });

  it("run returns json by default and streams csv and jsonl to stdout or --out", async () => {
    const json = await runCli(["reporting", "run", ID, "--json"], { env, routes: routes({ [`GET ${R}/saved/${ID}/run`]: ok({ columns: [], rows: [{ a: 1 }] }, 200, { meta: { total_rows: 1 } }) }) });
    expect(data(json).rows).toEqual([{ a: 1 }]);
    expect(json.json().meta).toEqual({ total_rows: 1 });
    expect(json.requests.at(-1)?.query).toEqual({ format: "json" });

    const csv = await runCli(["reporting", "run", ID, "--format", "csv", "--json"], { env, routes: routes({ [`GET ${R}/saved/${ID}/run`]: { raw: "course_title\nSafety\n", headers: { "content-type": "text/csv" } } }) });
    expect(csv.json().data).toBe("course_title\nSafety\n");
    expect(csv.requests.at(-1)?.query).toEqual({ format: "csv" });

    const written: Record<string, Uint8Array> = {};
    const file = await runCli(["reporting", "run", ID, "--format", "jsonl", "--out", "/tmp/r.jsonl", "--json"], {
      env,
      fs: { readFile: async () => new Uint8Array(), readText: async () => "", writeFile: async (p, b) => void (written[p] = b), exists: async () => true },
      routes: routes({ [`GET ${R}/saved/${ID}/run`]: { raw: '{"a":1}\n', headers: { "content-type": "application/x-ndjson" } } }),
    });
    expect(data(file)).toMatchObject({ path: "/tmp/r.jsonl", bytes: 8 });
    expect(new TextDecoder().decode(written["/tmp/r.jsonl"])).toBe('{"a":1}\n');
  });

  it("an Excel file needs --out", async () => {
    const r = await runCli(["reporting", "run", ID, "--format", "xlsx", "--json"], { env, routes: routes({ [`GET ${R}/saved/${ID}/run`]: { raw: "PK", headers: { "content-type": "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" } } }) });
    expect(r.code).toBe(2);
    expect(r.json()).toMatchObject({ error: { message: expect.stringContaining("--out") } });
  });

  it("save sends the name, the sharing and the definition", async () => {
    const r = await runCli(["reporting", "save", "--name", "Overdue", "--description", "Who is late", "--role", "tutor", "--role", "auditor", "--file", "def.json", "--json"], { env, files: { "def.json": JSON.stringify(definition) }, routes: routes() });
    expect(r.code).toBe(0);
    expect(r.requests.at(-1)?.body).toEqual({ name: "Overdue", description: "Who is late", visibility: "roles", shared_roles: ["tutor", "auditor"], definition });
    const priv = await runCli(["reporting", "save", "--name", "Mine", "--visibility", "private", "--definition", JSON.stringify(definition), "--json"], { env, routes: routes() });
    expect(priv.requests.at(-1)?.body).toEqual({ name: "Mine", visibility: "private", shared_roles: [], definition });
  });

  it("save --dry-run shows the request and sends nothing", async () => {
    const r = await runCli(["reporting", "save", "--name", "Overdue", "--file", "def.json", "--dry-run", "--json"], { env, files: { "def.json": JSON.stringify(definition) }, routes: routes() });
    expect(r.code).toBe(0);
    expect(r.requests.filter((q) => q.method === "POST")).toHaveLength(0);
    expect(JSON.stringify(r.json())).toContain("/api/admin/reporting/saved");
  });

  it("update changes only what is passed, the feed included", async () => {
    const feed = await runCli(["reporting", "update", ID, "--feed", "on", "--json"], { env, routes: routes() });
    expect(feed.requests.at(-1)?.method).toBe("PUT");
    expect(feed.requests.at(-1)?.body).toEqual({ feed_enabled: true });
    const rename = await runCli(["reporting", "update", ID, "--name", "Renamed", "--role", "tutor", "--json"], { env, routes: routes() });
    expect(rename.requests.at(-1)?.body).toEqual({ name: "Renamed", visibility: "roles", shared_roles: ["tutor"] });
    const nothing = await runCli(["reporting", "update", ID, "--json"], { env, routes: routes() });
    expect(nothing.code).toBe(2);
  });

  it("delete is destructive: it needs --yes and then sends the DELETE", async () => {
    const refused = await runCli(["reporting", "delete", ID, "--json"], { env, routes: routes() });
    expect(refused.code).toBe(11);
    expect(refused.requests.filter((q) => q.method === "DELETE")).toHaveLength(0);
    const done = await runCli(["reporting", "delete", ID, "--yes", "--json"], { env, routes: routes() });
    expect(done.code).toBe(0);
    expect(data(done)).toEqual({ id: ID, deleted: true });
  });

  it("every command covers the routes of the report builder, none is left to the generator", async () => {
    const schema = await runCli(["schema", "--json"], { env });
    const commands = (data(schema).commands as Array<{ id: string; kind: string; endpoints: string[]; mcp: { toolset: string }; scopes: string[] }>).filter((c) => c.id.startsWith("reporting."));
    expect(commands.map((c) => c.id).sort()).toEqual(['reporting.datasets', 'reporting.delete', 'reporting.list', 'reporting.query', 'reporting.run', 'reporting.save', 'reporting.show', 'reporting.update']);
    expect(new Set(commands.map((c) => c.mcp.toolset))).toEqual(new Set(["analytics"]));
    expect(commands.filter((c) => c.kind === "read").map((c) => c.id).sort()).toEqual(['reporting.datasets', 'reporting.list', 'reporting.query', 'reporting.run', 'reporting.show']);
    expect(commands.find((c) => c.id === "reporting.delete")?.kind).toBe("destructive");
    expect(commands.find((c) => c.id === "reporting.query")?.scopes).toEqual(["reports:read"]);
    expect(commands.flatMap((c) => c.endpoints).sort()).toEqual([
      `DELETE ${R}/saved/{id}`, `GET ${R}/datasets`, `GET ${R}/saved`, `GET ${R}/saved/{id}`, `GET ${R}/saved/{id}/run`, `POST ${R}/query`, `POST ${R}/saved`, `PUT ${R}/saved/{id}`,
    ]);
  });
});
