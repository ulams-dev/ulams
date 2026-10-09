import { z } from "zod";
import { CliError } from "../../errors.ts";
import { defineCommand } from "../define.ts";
import operations from "../../generated/operations.json" with { type: "json" };
import { getRegistry } from "../../registry/index.ts";
import { fillPath } from "../../registry/request.ts";
import type { Kind } from "../../registry/types.ts";

const METHODS = ["GET", "POST", "PUT", "PATCH", "DELETE"] as const;

function pairs(items: string[] | undefined, flag: string): Record<string, string> {
  const out: Record<string, string> = {};
  for (const item of items ?? []) {
    const eq = item.indexOf("=");
    if (eq < 1) throw new CliError("INPUT_INVALID", `--${flag} expects key=value, got "${item}".`);
    out[item.slice(0, eq)] = item.slice(eq + 1);
  }
  return out;
}

const input = z.object({
  method: z.string().optional().describe("HTTP method: GET, POST, PUT, PATCH or DELETE."),
  path: z.string().optional().describe("API path, literal (/api/admin/courses/12) or a spec template (/api/admin/courses/{id} with --param id=12)."),
  param: z.array(z.string()).optional().describe("Path parameter key=value (repeatable)."),
  query: z.array(z.string()).optional().describe("Query parameter key=value (repeatable)."),
  body: z.unknown().optional().describe("JSON body: inline, @file.json, @file.yaml or - for stdin."),
  raw: z.boolean().optional().describe("Print the whole response body instead of the envelope data."),
  list: z.boolean().optional().describe("List the operations of the bundled API spec instead of calling the API."),
  filter: z.string().optional().describe("With --list: only operations whose path or summary contains this text."),
});

export const api = defineCommand({
  id: "api",
  summary: "Call any API endpoint, or list the endpoints of the bundled spec",
  description:
    "The escape hatch over the whole API. Prefer a noun command when one exists (`ulams schema` lists them); use `ulams api --list --filter <text>` to find endpoints. GET is read, DELETE is destructive and needs --yes, other methods are writes and support --dry-run. Output data is the API's `data`; --raw prints the full body.",
  kind: "write",
  idempotent: false,
  mcp: { expose: false },
  audience: ["any"],
  positionals: ["method", "path"],
  input,
  output: z.unknown(),
  examples: [
    { title: "List courses", argv: "api GET /api/admin/courses --query per_page=5 --json" },
    { title: "Update a course", argv: "api PUT /api/admin/courses/{id} --param id=12 --body '{\"title\":\"New\"}'" },
    { title: "Find endpoints", argv: "api --list --filter quiz" },
  ],
  kindFor(i): Kind {
    const method = String(i.method ?? "GET").toUpperCase();
    if (i.list) return "read";
    return method === "GET" ? "read" : method === "DELETE" ? "destructive" : "write";
  },
  async plan(_ctx, i) {
    const req = prepare(i);
    return { request: { method: req.method, path: req.path, ...(req.query ? { query: req.query } : {}), ...(req.body !== undefined ? { body: req.body } : {}) } };
  },
  async run(ctx, i) {
    if (i.list) {
      const needle = i.filter?.toLowerCase();
      const covered = new Map<string, string>();
      for (const cmd of getRegistry()) for (const e of cmd.endpoints) if (!covered.has(e)) covered.set(e, cmd.id);
      const rows = operations
        .filter((o) => !needle || o.path.toLowerCase().includes(needle) || o.summary.toLowerCase().includes(needle))
        .map((o) => ({ method: o.method, path: o.path, summary: o.summary, command: covered.get(`${o.method} ${o.path}`) ?? null }));
      return { data: rows, meta: { total: rows.length } };
    }
    const req = prepare(i);
    const res = await ctx.client.call(req.method, req.path, {
      query: req.query,
      body: req.body,
      idempotent: req.method !== "POST",
      ...(ctx.flags.idempotencyKey ? { idempotencyKey: ctx.flags.idempotencyKey } : {}),
      signal: ctx.signal,
    });
    return { data: i.raw ? res.body : res.data, ...(res.meta && !i.raw ? { meta: res.meta } : {}) };
  },
});

function prepare(i: z.infer<typeof input>) {
  const method = String(i.method ?? "").toUpperCase();
  if (!(METHODS as readonly string[]).includes(method)) {
    throw new CliError("INPUT_INVALID", `api needs a method (${METHODS.join(", ")}) and a path.`, {
      hint: "Example: ulams api GET /api/admin/courses",
    });
  }
  if (!i.path || !i.path.startsWith("/")) {
    throw new CliError("INPUT_INVALID", "api needs a path starting with /.", { hint: "Example: ulams api GET /api/admin/courses" });
  }
  const params = pairs(i.param, "param");
  const path = fillPath(i.path, params);
  const missing = path.match(/\{(\w+)\}/);
  if (missing) throw new CliError("INPUT_INVALID", `Missing path parameter "${missing[1]}".`, { hint: `Pass --param ${missing[1]}=<value>.` });
  const query = pairs(i.query, "query");
  return {
    method: method as (typeof METHODS)[number],
    path,
    query: Object.keys(query).length ? query : undefined,
    body: i.body,
  };
}
