import { z } from "zod";
import { CliError } from "../errors.ts";
import type { AnyCommand, Ctx, Plan } from "../registry/types.ts";
import { defineCommand } from "./define.ts";

/* The report builder from the command line and as MCP tools (ADR 0194, plan 6.10, toolset `analytics`). Every command is an
 * MCP tool named from its id (`reporting.run` is `reporting_run`). Reads work with `--read-only`; only `save`, `update` and
 * `delete` change anything, and `delete` is destructive (a plan and a confirm token first). */

const BASE = "/api/admin/reporting";
const common = { audience: ["admin" as const], mcp: { toolset: "analytics" } };
const READ = ["reports:read"];
const WRITE = ["reports:write"];

/** What an agent must not "fix": the numbers are the tenant's own, and a withheld group is withheld on purpose. */
const HONESTY =
  "Figures are this academy's own measurements. Groups with fewer learners than the threshold (10 by default), and the smallest group next to them when that is needed to keep them private, are withheld on purpose: they come back as one row marked `_withheld` with empty figures. Do not try to recover them by narrowing filters or by subtracting totals.";

const reportId = z.string().describe("Saved report id (26 characters, from `reporting list`).");
const definitionInput = z.record(z.string(), z.unknown()).optional().describe("A report definition as an object (schema report-definition/v1: dataset, columns or groupBy and aggregates, filters, dateRange, sort, limit). Alternative to --file.");
const fileInput = z.string().optional().describe("Path of a JSON file holding the report definition. Alternative to passing the definition inline.");

/** The definition from `--file` or the inline object, exactly one of them. */
async function definitionOf(ctx: Ctx, i: { file?: string; definition?: Record<string, unknown> }, required = true): Promise<Record<string, unknown> | undefined> {
  if (i.file && i.definition) throw new CliError("INPUT_INVALID", "Pass the definition with --file or inline, not both.");
  if (!i.file && !i.definition) {
    if (required) throw new CliError("INPUT_INVALID", "Pass the report definition with --file <definition.json> (or inline).", { hint: "`ulams reporting datasets --json` lists the columns a definition may use." });
    return undefined;
  }
  if (i.definition) return i.definition;
  let text: string;
  try {
    text = new TextDecoder().decode(await ctx.fs.readFile(i.file as string));
  } catch {
    throw new CliError("INPUT_INVALID", `Cannot read ${i.file}.`);
  }
  try {
    const parsed: unknown = JSON.parse(text);
    if (parsed === null || typeof parsed !== "object" || Array.isArray(parsed)) throw new Error("not an object");
    return parsed as Record<string, unknown>;
  } catch {
    throw new CliError("INPUT_INVALID", `${i.file} is not a JSON object.`, { hint: "A report definition looks like {\"dataset\":\"assignments\",\"columns\":[\"course_title\",\"status\"]}." });
  }
}

/** The report's own meta (mode, total_rows, truncated, withheld_groups, min_group_size): the client keeps only pagination meta. */
const reportMeta = (res: { body: unknown }): { meta: unknown } | Record<string, never> => {
  const meta = (res.body as { meta?: unknown } | null)?.meta;
  return meta ? { meta } : {};
};

const planOf = (method: string, path: string, body?: unknown): Plan => ({ request: { method, path, ...(body !== undefined ? { body } : {}) } });

const withSharing = (i: { visibility?: string; role?: string[] }): Record<string, unknown> => ({
  ...(i.visibility ? { visibility: i.visibility } : {}),
  ...(i.role ? { shared_roles: i.role } : i.visibility === "private" ? { shared_roles: [] } : {}),
});

const FORMATS = ["json", "csv", "jsonl", "xlsx"] as const;

export const reportingCommands: AnyCommand[] = [
  defineCommand({
    ...common,
    id: "reporting.datasets",
    summary: "The datasets you can build reports on, with every column and what it allows",
    description: `Per dataset: key, label, the permission it needs, the learner column used to protect small groups, and every column with its type, filter operators, whether it can be grouped or aggregated and whether it holds personal data. Use it to write a report definition. ${HONESTY}`,
    kind: "read",
    scopes: READ,
    endpoints: [`GET ${BASE}/datasets`],
    input: z.object({}),
    output: z.unknown(),
    examples: [{ title: "Datasets and columns", argv: "reporting datasets --json" }],
    async run(ctx) {
      return { data: (await ctx.client.call("GET", `${BASE}/datasets`, { idempotent: true, signal: ctx.signal })).data };
    },
  }),
  defineCommand({
    ...common,
    id: "reporting.query",
    summary: "Preview a report definition: up to 500 rows or groups and the total",
    description: `Runs a definition with your own permissions and changes nothing (it is a read, so it works with --read-only). Row reports list columns; grouped reports list groupBy and aggregates. ${HONESTY}`,
    kind: "read",
    scopes: READ,
    endpoints: [`POST ${BASE}/query`],
    input: z.object({ file: fileInput, definition: definitionInput }),
    output: z.unknown(),
    examples: [
      { title: "Learners per course and month", argv: "reporting query --file overdue-by-course.json --json" },
    ],
    async run(ctx, i) {
      const body = await definitionOf(ctx, i);
      const res = await ctx.client.call("POST", `${BASE}/query`, { body, idempotent: true, signal: ctx.signal });
      return { data: res.data, ...reportMeta(res) };
    },
  }),
  defineCommand({
    ...common,
    id: "reporting.list",
    summary: "Saved reports you can see: your own and those shared with your roles",
    description: "Each has can_edit, runnable (you hold the permission of its dataset) and needs_repair with the problems when its dataset or a column is gone.",
    kind: "read",
    scopes: READ,
    endpoints: [`GET ${BASE}/saved`],
    input: z.object({}),
    output: z.unknown(),
    examples: [{ title: "Saved reports", argv: "reporting list --json" }],
    async run(ctx) {
      return { data: (await ctx.client.call("GET", `${BASE}/saved`, { idempotent: true, signal: ctx.signal })).data };
    },
  }),
  defineCommand({
    ...common,
    id: "reporting.show",
    summary: "One saved report with its definition, sharing and feed",
    kind: "read",
    scopes: READ,
    endpoints: [`GET ${BASE}/saved/{id}`],
    positionals: ["id"],
    input: z.object({ id: reportId }),
    output: z.unknown(),
    examples: [{ title: "A saved report", argv: "reporting show 01J9Z3K8M2X4Q7R5T6V8W0Y1AB --json" }],
    async run(ctx, i) {
      return { data: (await ctx.client.call("GET", `${BASE}/saved/{id}`, { params: { id: i.id }, idempotent: true, signal: ctx.signal })).data };
    },
  }),
  defineCommand({
    ...common,
    id: "reporting.run",
    summary: "Run a saved report with your own permissions: json, or a csv, jsonl or xlsx file",
    description: `json (default) answers like the preview: columns, up to 500 rows and the total. csv and jsonl stream every row (up to 1 000 000) to stdout or --out; xlsx needs --out. Every file is recorded as an export. ${HONESTY}`,
    kind: "read",
    scopes: READ,
    endpoints: [`GET ${BASE}/saved/{id}/run`],
    positionals: ["id"],
    input: z.object({
      id: reportId,
      format: z.enum(FORMATS).optional().describe("json (default), csv, jsonl or xlsx."),
      out: z.string().optional().describe("Write the file to this path (- for stdout; csv and jsonl only)."),
    }),
    output: z.unknown(),
    examples: [
      { title: "As JSON lines", argv: "reporting run 01J9Z3K8M2X4Q7R5T6V8W0Y1AB --format jsonl --json" },
      { title: "An Excel file", argv: "reporting run 01J9Z3K8M2X4Q7R5T6V8W0Y1AB --format xlsx --out report.xlsx" },
    ],
    async run(ctx, i) {
      const format = i.format ?? "json";
      if (format === "json") {
        const res = await ctx.client.call("GET", `${BASE}/saved/{id}/run`, { params: { id: i.id }, query: { format }, idempotent: true, signal: ctx.signal });
        return { data: res.data, ...reportMeta(res) };
      }
      const file = await ctx.client.download("GET", `${BASE}/saved/{id}/run`, { params: { id: i.id }, query: { format }, idempotent: true, signal: ctx.signal });
      const target = i.out ?? ctx.flags.out;
      if (!target || target === "-") {
        if (format === "xlsx") throw new CliError("INPUT_INVALID", "An Excel file cannot go to stdout; pass --out <path>.", { hint: "Example: --out ./report.xlsx" });
        return { data: new TextDecoder().decode(file.data) };
      }
      await ctx.fs.writeFile(target, file.data);
      return { data: { path: target, bytes: file.data.byteLength, contentType: file.contentType, filename: file.filename } };
    },
  }),
  defineCommand({
    ...common,
    id: "reporting.save",
    summary: "Save a report definition under a name, private or shared with tenant roles",
    description: "Needs reporting_create and the permission of the definition's dataset. Sharing shares the definition, not the data: everyone runs it with their own permissions.",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: [`POST ${BASE}/saved`],
    input: z.object({
      name: z.string().min(1).max(160).describe("Name of the report."),
      description: z.string().max(2000).optional(),
      visibility: z.enum(["private", "roles"]).optional().describe("private (default) or roles."),
      role: z.array(z.string()).optional().describe("Role to share with (repeatable); implies visibility roles."),
      file: fileInput,
      definition: definitionInput,
    }),
    output: z.unknown(),
    examples: [{ title: "Save and share with tutors", argv: "reporting save --name \"Overdue by course\" --file overdue-by-course.json --role tutor --json" }],
    async plan(ctx, i) {
      return planOf("POST", `${BASE}/saved`, await body(ctx, i));
    },
    async run(ctx, i) {
      return { data: (await ctx.client.call("POST", `${BASE}/saved`, { body: await body(ctx, i), signal: ctx.signal })).data };
    },
  }),
  defineCommand({
    ...common,
    id: "reporting.update",
    summary: "Change a saved report (owner or reporting_manage); --feed on|off needs reporting_manage",
    kind: "write",
    idempotent: true,
    scopes: WRITE,
    endpoints: [`PUT ${BASE}/saved/{id}`],
    positionals: ["id"],
    input: z.object({
      id: reportId,
      name: z.string().min(1).max(160).optional(),
      description: z.string().max(2000).optional(),
      visibility: z.enum(["private", "roles"]).optional(),
      role: z.array(z.string()).optional().describe("Role to share with (repeatable)."),
      file: fileInput,
      definition: definitionInput,
      feed: z.enum(["on", "off"]).optional().describe("Turn the read-only OData feed for BI tools on or off."),
    }),
    output: z.unknown(),
    examples: [{ title: "Turn the feed on", argv: "reporting update 01J9Z3K8M2X4Q7R5T6V8W0Y1AB --feed on --json" }],
    async plan(ctx, i) {
      return planOf("PUT", `${BASE}/saved/${i.id}`, await changes(ctx, i));
    },
    async run(ctx, i) {
      const payload = await changes(ctx, i);
      if (Object.keys(payload).length === 0) throw new CliError("INPUT_INVALID", "Nothing to change: pass --name, --description, --visibility, --role, --file or --feed.");
      return { data: (await ctx.client.call("PUT", `${BASE}/saved/{id}`, { params: { id: i.id }, body: payload, idempotent: true, signal: ctx.signal })).data };
    },
  }),
  defineCommand({
    ...common,
    id: "reporting.delete",
    summary: "Delete a saved report (owner or reporting_manage)",
    description: "Schedules that send the report then skip every recipient and tell their owner; feed readers get 404. The report cannot be restored through the API.",
    kind: "destructive",
    idempotent: true,
    scopes: WRITE,
    endpoints: [`DELETE ${BASE}/saved/{id}`],
    positionals: ["id"],
    input: z.object({ id: reportId }),
    output: z.unknown(),
    examples: [{ title: "Delete", argv: "reporting delete 01J9Z3K8M2X4Q7R5T6V8W0Y1AB --yes" }],
    async plan(_ctx, i) {
      return planOf("DELETE", `${BASE}/saved/${i.id}`);
    },
    async run(ctx, i) {
      await ctx.client.call("DELETE", `${BASE}/saved/{id}`, { params: { id: i.id }, idempotent: true, signal: ctx.signal });
      return { data: { id: i.id, deleted: true } };
    },
  }),
];

async function body(ctx: Ctx, i: { name: string; description?: string; visibility?: string; role?: string[]; file?: string; definition?: Record<string, unknown> }) {
  const visibility = i.visibility ?? (i.role?.length ? "roles" : undefined);
  return { name: i.name, ...(i.description !== undefined ? { description: i.description } : {}), ...withSharing({ visibility, role: i.role }), definition: await definitionOf(ctx, i) };
}

async function changes(ctx: Ctx, i: { name?: string; description?: string; visibility?: string; role?: string[]; file?: string; definition?: Record<string, unknown>; feed?: string }) {
  const visibility = i.visibility ?? (i.role?.length ? "roles" : undefined);
  const definition = await definitionOf(ctx, i, false);

  return {
    ...(i.name !== undefined ? { name: i.name } : {}),
    ...(i.description !== undefined ? { description: i.description } : {}),
    ...withSharing({ visibility, role: i.role }),
    ...(definition ? { definition } : {}),
    ...(i.feed ? { feed_enabled: i.feed === "on" } : {}),
  };
}
