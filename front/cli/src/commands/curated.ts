import { z } from "zod";
import { CliError } from "../errors.ts";
import { defineCommand } from "./define.ts";
import type { AnyCommand, Ctx } from "../registry/types.ts";
import { fillPath } from "../registry/request.ts";

const admin = { audience: ["admin" as const] };

interface CourseData {
  id?: number;
  title?: string;
  status?: string;
}

async function getCourse(ctx: Ctx, id: number): Promise<CourseData & Record<string, unknown>> {
  return (await ctx.client.call<CourseData>("GET", "/api/admin/courses/{course}", { params: { course: id }, idempotent: true })).data as CourseData & Record<string, unknown>;
}

function setStatus(id: string, status: "published" | "draft", summary: string): AnyCommand {
  return defineCommand({
    ...admin,
    id,
    summary,
    description: status === "published" ? "Makes the course visible to learners. Use `courses get` first to check that it has lessons and topics." : "Moves the course back to draft; learners lose access to it until it is published again.",
    kind: "write",
    idempotent: true,
    scopes: ["courses:write"],
    endpoints: ["POST /api/admin/courses/{id}"],
    undocumented: ["GET /api/admin/courses/{course}"],
    positionals: ["id"],
    input: z.object({ id: z.number().int().describe("Course id.") }),
    output: z.unknown(),
    examples: [{ title: summary, argv: `${id.replace(".", " ")} 12 --json` }],
    mcp: { toolset: "courses" },
    async plan(ctx, i) {
      const course = await getCourse(ctx, i.id);
      return {
        request: { method: "POST", path: fillPath("/api/admin/courses/{id}", { id: i.id }), body: { title: course.title, status } },
        changes: course.status === status ? [] : [{ op: "replace", path: "/status", from: course.status ?? null, to: status }],
      };
    },
    async run(ctx, i) {
      const course = await getCourse(ctx, i.id);
      if (course.status === status) return { data: { id: i.id, status, changed: false } };
      const res = await ctx.client.call<CourseData>("POST", "/api/admin/courses/{id}", { params: { id: i.id }, body: { title: course.title, status }, idempotent: true, signal: ctx.signal });
      return { data: { id: i.id, title: res.data.title, status: res.data.status ?? status, changed: true } };
    },
  });
}

const accessInput = z.object({
  course: z.number().int().describe("Course id."),
  user: z.array(z.number().int()).optional().describe("User id (repeatable)."),
  group: z.array(z.number().int()).optional().describe("Group id (repeatable)."),
});

function accessCommand(id: string, endpoint: "add" | "remove" | "set", summary: string, destructive: boolean): AnyCommand {
  return defineCommand({
    ...admin,
    id,
    summary,
    description: endpoint === "set" ? "REPLACES the whole list of users and groups with access to the course; everyone not listed loses access." : "Course ids come from `courses list`, user ids from `users list`, group ids from `groups list`.",
    kind: destructive ? "destructive" : "write",
    idempotent: true,
    scopes: ["enrolments:write"],
    endpoints: [`POST /api/admin/courses/{id}/access/${endpoint}`],
    input: accessInput,
    output: z.unknown(),
    mcp: { toolset: "access" },
    examples: [{ title: summary, argv: `${id.replace(".", " ")} --course 12 --user 34 --json` }],
    async plan(_ctx, i) {
      return { request: { method: "POST", path: fillPath("/api/admin/courses/{id}/access/" + endpoint, { id: i.course }), body: { users: i.user ?? [], groups: i.group ?? [] } } };
    },
    async run(ctx, i) {
      if (!i.user?.length && !i.group?.length && endpoint !== "set") {
        throw new CliError("INPUT_INVALID", "Pass at least one --user or --group.");
      }
      const res = await ctx.client.call("POST", `/api/admin/courses/{id}/access/${endpoint}` as never, { params: { id: i.course }, body: { users: i.user ?? [], groups: i.group ?? [] }, idempotent: true, signal: ctx.signal });
      return { data: res.data };
    },
  });
}

interface Setting {
  id: number;
  key: string;
  group: string;
  value: unknown;
}

async function findSetting(ctx: Ctx, group: string, key: string): Promise<Setting | undefined> {
  const res = await ctx.client.call<Setting[]>("GET", "/api/admin/settings", { query: { group, per_page: 100 }, idempotent: true });
  return (Array.isArray(res.data) ? res.data : []).find((s) => s.key === key && s.group === group);
}

async function upsertSetting(ctx: Ctx, group: string, key: string, value: string): Promise<{ id: number; action: string }> {
  const existing = await findSetting(ctx, group, key);
  if (existing) {
    if (String(existing.value) === value) return { id: existing.id, action: "unchanged" };
    await ctx.client.call("PUT", "/api/admin/settings/{id}", { params: { id: existing.id }, body: { key, group, value, type: "text" }, idempotent: true, signal: ctx.signal });
    return { id: existing.id, action: "updated" };
  }
  const created = await ctx.client.call<{ id: number }>("POST", "/api/admin/settings", { body: { key, group, value, type: "text", public: group === "theme" }, signal: ctx.signal });
  return { id: created.data.id, action: "created" };
}

export const curatedCommands: AnyCommand[] = [
  defineCommand({
    ...admin,
    id: "courses.get",
    summary: "Show one course with its lessons and topics",
    description: "Course ids come from `courses list`. Includes lessons and their topics (id, title, type, order).",
    kind: "read",
    scopes: ["courses:read"],
    endpoints: [],
    undocumented: ["GET /api/admin/courses/{course}"],
    positionals: ["id"],
    input: z.object({ id: z.number().int().describe("Course id.") }),
    output: z.unknown(),
    mcp: { toolset: "courses" },
    examples: [{ title: "Show a course", argv: "courses get 12 --json" }],
    async run(ctx, i) {
      return { data: await getCourse(ctx, i.id) };
    },
  }),
  defineCommand({
    ...admin,
    id: "courses.program",
    summary: "Show the full program of a course (lessons, topics and their content)",
    kind: "read",
    scopes: ["courses:read"],
    endpoints: [],
    undocumented: ["GET /api/admin/courses/{course}/program"],
    positionals: ["id"],
    input: z.object({ id: z.number().int().describe("Course id.") }),
    output: z.unknown(),
    mcp: { toolset: "courses" },
    examples: [{ title: "Program of a course", argv: "courses program 12 --json" }],
    async run(ctx, i) {
      return { data: (await ctx.client.call("GET", "/api/admin/courses/{course}/program", { params: { course: i.id }, idempotent: true })).data };
    },
  }),
  setStatus("courses.publish", "published", "Publish a course"),
  setStatus("courses.unpublish", "draft", "Move a course back to draft"),
  defineCommand({
    ...admin,
    id: "access.list",
    summary: "List the users and groups with access to a course",
    kind: "read",
    scopes: ["enrolments:read"],
    endpoints: ["GET /api/admin/courses/{id}/access"],
    input: z.object({ course: z.number().int().describe("Course id.") }),
    output: z.unknown(),
    mcp: { toolset: "access" },
    examples: [{ title: "Who can take course 12", argv: "access list --course 12 --json" }],
    async run(ctx, i) {
      return { data: (await ctx.client.call("GET", "/api/admin/courses/{id}/access", { params: { id: i.course }, idempotent: true })).data };
    },
  }),
  accessCommand("access.grant", "add", "Give users or groups access to a course (enrol)", false),
  accessCommand("access.revoke", "remove", "Remove users or groups from a course", false),
  accessCommand("access.set", "set", "Replace the access list of a course", true),
  defineCommand({
    ...admin,
    id: "enrol",
    summary: "Enrol users or groups in a course (alias of access grant)",
    kind: "write",
    idempotent: true,
    scopes: ["enrolments:write"],
    endpoints: [],
    input: accessInput,
    output: z.unknown(),
    mcp: { expose: false },
    examples: [{ title: "Enrol a user", argv: "enrol --course 12 --user 34 --json" }],
    async run(ctx, i) {
      const res = await ctx.client.call("POST", "/api/admin/courses/{id}/access/add", { params: { id: i.course }, body: { users: i.user ?? [], groups: i.group ?? [] }, idempotent: true, signal: ctx.signal });
      return { data: res.data };
    },
  }),
  defineCommand({
    ...admin,
    id: "settings.get",
    summary: "Read one setting by group and key",
    kind: "read",
    scopes: ["settings:read"],
    endpoints: ["GET /api/admin/settings"],
    positionals: ["group", "key"],
    input: z.object({ group: z.string().describe("Settings group (see `settings groups`)."), key: z.string().describe("Setting key.") }),
    output: z.unknown(),
    mcp: { toolset: "settings" },
    examples: [{ title: "Company name", argv: "settings get global companyName --json" }],
    async run(ctx, i) {
      const found = await findSetting(ctx, i.group, i.key);
      if (!found) throw new CliError("NOT_FOUND", `No setting ${i.group}.${i.key}.`, { hint: "List them with `ulams settings list --group <group>`." });
      return { data: found };
    },
  }),
  defineCommand({
    ...admin,
    id: "settings.set",
    summary: "Create or update a text setting by group and key",
    description: "Idempotent: creates the setting when missing, updates it when the value differs, does nothing otherwise.",
    kind: "write",
    idempotent: true,
    scopes: ["settings:write"],
    endpoints: ["GET /api/admin/settings", "POST /api/admin/settings", "PUT /api/admin/settings/{id}"],
    positionals: ["group", "key", "value"],
    input: z.object({ group: z.string().describe("Settings group."), key: z.string().describe("Setting key."), value: z.string().describe("New value.") }),
    output: z.unknown(),
    mcp: { toolset: "settings" },
    examples: [{ title: "Rename the company", argv: 'settings set global companyName "Acme Academy" --json' }],
    async plan(ctx, i) {
      const existing = await findSetting(ctx, i.group, i.key);
      return {
        request: existing ? { method: "PUT", path: `/api/admin/settings/${existing.id}`, body: { value: i.value } } : { method: "POST", path: "/api/admin/settings", body: { group: i.group, key: i.key, value: i.value } },
        changes: existing ? (String(existing.value) === i.value ? [] : [{ op: "replace", path: `/${i.group}/${i.key}`, from: existing.value, to: i.value }]) : [{ op: "add", path: `/${i.group}/${i.key}`, to: i.value }],
      };
    },
    async run(ctx, i) {
      return { data: { group: i.group, key: i.key, value: i.value, ...(await upsertSetting(ctx, i.group, i.key, i.value)) } };
    },
  }),
  defineCommand({
    ...admin,
    id: "theme.get",
    summary: "Show the tenant theme (settings group theme: theme, accent)",
    kind: "read",
    scopes: ["settings:read"],
    endpoints: ["GET /api/admin/settings"],
    input: z.object({}),
    output: z.unknown(),
    mcp: { toolset: "settings" },
    examples: [{ title: "Current theme", argv: "theme get --json" }],
    async run(ctx) {
      const res = await ctx.client.call<Setting[]>("GET", "/api/admin/settings", { query: { group: "theme", per_page: 100 }, idempotent: true });
      const map = Object.fromEntries((Array.isArray(res.data) ? res.data : []).map((s) => [s.key, s.value]));
      return { data: map };
    },
  }),
  defineCommand({
    ...admin,
    id: "theme.set",
    summary: "Change the tenant theme name and accent colour",
    kind: "write",
    idempotent: true,
    scopes: ["settings:write"],
    endpoints: ["GET /api/admin/settings", "POST /api/admin/settings", "PUT /api/admin/settings/{id}"],
    input: z.object({ theme: z.string().optional().describe("Theme name."), accent: z.string().optional().describe("Accent colour, e.g. #C2552D.") }),
    output: z.unknown(),
    mcp: { toolset: "settings" },
    examples: [{ title: "Set the accent", argv: "theme set --accent '#0A7' --json" }],
    async run(ctx, i) {
      if (i.theme === undefined && i.accent === undefined) throw new CliError("INPUT_INVALID", "Pass --theme and/or --accent.");
      const out: Record<string, unknown> = {};
      if (i.theme !== undefined) out.theme = await upsertSetting(ctx, "theme", "theme", i.theme);
      if (i.accent !== undefined) out.accent = await upsertSetting(ctx, "theme", "accent", i.accent);
      return { data: out };
    },
  }),
];
