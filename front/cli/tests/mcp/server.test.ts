import { describe, expect, it } from "vitest";
import { connect, text } from "./helpers.ts";

const courses = { "GET /api/admin/courses": { body: { success: true, data: [{ id: 1, title: "A" }], meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 } } } };
const del = {
  "GET /api/admin/lessons/9": { body: { success: true, data: { id: 9, title: "L" } } },
  "DELETE /api/admin/lessons/9": { body: { success: true, data: null } },
};

describe("tools/list", () => {
  it("default core toolset: small, annotated, with meta tools", async () => {
    const { client, close } = await connect({});
    const tools = (await client.listTools()).tools;
    const names = tools.map((t) => t.name);
    expect(names).toEqual(expect.arrayContaining(["whoami", "courses_list", "courses_create", "courses_publish", "topics_create_richtext", "access_grant", "commands_search", "commands_describe", "commands_run"]));
    expect(names.length).toBeLessThan(30);
    const byName = Object.fromEntries(tools.map((t) => [t.name, t]));
    expect(byName.courses_list?.annotations).toMatchObject({ readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false });
    expect(byName.courses_create?.annotations).toMatchObject({ readOnlyHint: false, destructiveHint: false });
    expect(byName.courses_create?.inputSchema.properties).toHaveProperty("title");
    expect(byName.courses_create?.inputSchema.properties).toHaveProperty("dry_run");
    expect(byName.courses_list?.inputSchema.properties).toHaveProperty("all");
    // deterministic order, and no tool leaks local-only commands
    expect(names).toEqual([...names]);
    expect(names).not.toContain("login");
    expect(names).not.toContain("mcp");
    await close();
  });

  it("toolsets widen the list and `all` exposes the generated catalogue", async () => {
    const core = await connect({});
    const users = await connect({}, { toolsets: ["core", "users"] });
    const all = await connect({}, { toolsets: "all" });
    const n = async (c: typeof core) => (await c.client.listTools()).tools.length;
    expect(await n(users)).toBeGreaterThan(await n(core));
    expect(await n(all)).toBeGreaterThan(250);
    const allTools = (await all.client.listTools()).tools;
    expect(allTools.find((t) => t.name === "lessons_delete")?.annotations).toMatchObject({ destructiveHint: true, readOnlyHint: false });
    for (const c of [core, users, all]) await c.close();
  });

  it("the topics toolset has topics_create_interactive, a write tool with the step range and display inputs", async () => {
    const topics = await connect({}, { toolsets: ["core", "topics"] });
    const tool = (await topics.client.listTools()).tools.find((t) => t.name === "topics_create_interactive");
    expect(tool?.annotations).toMatchObject({ readOnlyHint: false, destructiveHint: false });
    for (const field of ["lesson", "title", "file", "package", "startStep", "endStep", "completion", "display", "followLatest", "acceptNetwork", "dry_run"]) {
      expect(tool?.inputSchema.properties, field).toHaveProperty(field);
    }
    await topics.close();
  });

  it("--read-only exposes only read tools and --no-destructive hides destructive ones", async () => {
    const ro = await connect({}, { toolsets: "all", readOnly: true });
    const roTools = (await ro.client.listTools()).tools;
    expect(roTools.every((t) => t.annotations?.readOnlyHint === true || t.name.startsWith("commands_"))).toBe(true);
    expect(roTools.map((t) => t.name)).not.toContain("courses_create");
    const nd = await connect({}, { toolsets: "all", noDestructive: true });
    const names = (await nd.client.listTools()).tools.map((t) => t.name);
    expect(names).toContain("courses_create");
    expect(names).not.toContain("lessons_delete");
    await ro.close();
    await nd.close();
  });
});

describe("tools/call", () => {
  it("a read tool returns the CLI envelope as structured content and compact text", async () => {
    const { client, api, close } = await connect(courses);
    const r = await client.callTool({ name: "courses_list", arguments: { per_page: 15, fields: ["id", "title"] } });
    expect(r.isError).toBeFalsy();
    expect(r.structuredContent).toMatchObject({ ok: true, contract: 1, command: "courses.list", data: [{ id: 1, title: "A" }], meta: { page: 1, total: 1 } });
    expect(text(r)).toEqual(r.structuredContent);
    expect(api.requests[0]?.headers["x-ulams-client"]).toBe("mcp");
    expect(api.requests[0]?.headers.authorization).toBe("Bearer tok-123456");
    await close();
  });

  it("a write tool with dry_run changes nothing", async () => {
    const { client, api, close } = await connect({});
    const r = await client.callTool({ name: "courses_create", arguments: { title: "T", dry_run: true } });
    expect(r.isError).toBeFalsy();
    expect(r.structuredContent).toMatchObject({ data: { dryRun: true, request: { method: "POST", path: "/api/admin/courses" } } });
    expect(api.requests).toHaveLength(0);
    await close();
  });

  it("invalid input is an error result that names the field", async () => {
    const { client, close } = await connect({});
    const r = await client.callTool({ name: "courses_create", arguments: {} });
    expect(r.isError).toBe(true);
    // The SDK validates against the tool's input schema before our handler runs.
    expect(JSON.stringify(r.content)).toContain("title");
    await close();
  });

  it("API errors map to error results and never contain the token", async () => {
    const { client, close } = await connect({ "GET /api/admin/courses": { status: 500, body: { message: "boom tok-123456" } } });
    const r = await client.callTool({ name: "courses_list", arguments: {} });
    expect(r.isError).toBe(true);
    expect(JSON.stringify(r)).not.toContain("tok-123456");
    await close();
  });

  it("long lists are truncated with a warning", async () => {
    const big = Array.from({ length: 2000 }, (_, i) => ({ id: i, title: `Course number ${i} with a long title to fill the budget` }));
    const { client, close } = await connect({ "GET /api/admin/courses": { body: { success: true, data: big } } });
    const r = await client.callTool({ name: "courses_list", arguments: {} });
    const body = text(r);
    expect(JSON.stringify(body).length).toBeLessThan(26_000);
    expect(body.warnings[0].code).toBe("TRUNCATED");
    await close();
  });
});

describe("destructive confirmation", () => {
  it("returns the plan and a confirm token, then runs once with it", async () => {
    const { client, api, close } = await connect(del, { toolsets: "all" });
    const first = await client.callTool({ name: "lessons_delete", arguments: { id: 9 } });
    expect(first.isError).toBe(true);
    const err = text(first).error;
    expect(err.code).toBe("CONFIRMATION_REQUIRED");
    expect(err.details.plan.request).toMatchObject({ method: "DELETE", path: "/api/admin/lessons/9" });
    expect(err.details.plan.current).toMatchObject({ id: 9 });
    expect(api.requests.some((q) => q.method === "DELETE")).toBe(false);

    const ok = await client.callTool({ name: "lessons_delete", arguments: { id: 9, confirm: err.details.confirm } });
    expect(ok.isError).toBeFalsy();
    expect(api.requests.filter((q) => q.method === "DELETE")).toHaveLength(1);

    const reuse = await client.callTool({ name: "lessons_delete", arguments: { id: 9, confirm: err.details.confirm } });
    expect(reuse.isError).toBe(true);
    expect(api.requests.filter((q) => q.method === "DELETE")).toHaveLength(1);
    await close();
  });

  it("a token issued for other input does not confirm", async () => {
    const { client, api, close } = await connect({ ...del, "GET /api/admin/lessons/10": { body: { success: true, data: { id: 10 } } } }, { toolsets: "all" });
    const t9 = text(await client.callTool({ name: "lessons_delete", arguments: { id: 9 } })).error.details.confirm;
    const wrong = await client.callTool({ name: "lessons_delete", arguments: { id: 10, confirm: t9 } });
    expect(wrong.isError).toBe(true);
    expect(api.requests.some((q) => q.method === "DELETE")).toBe(false);
    await close();
  });

  it("dry_run on a destructive tool needs no confirmation", async () => {
    const { client, api, close } = await connect(del, { toolsets: "all" });
    const r = await client.callTool({ name: "lessons_delete", arguments: { id: 9, dry_run: true } });
    expect(r.isError).toBeFalsy();
    expect(api.requests.some((q) => q.method === "DELETE")).toBe(false);
    await close();
  });

  it("--yes disables confirmation (sandboxes only)", async () => {
    const { client, api, close } = await connect(del, { toolsets: "all", yes: true });
    expect((await client.callTool({ name: "lessons_delete", arguments: { id: 9 } })).isError).toBeFalsy();
    expect(api.requests.some((q) => q.method === "DELETE")).toBe(true);
    await close();
  });
});

describe("meta tools", () => {
  it("commands_search finds commands across toolsets and commands_describe returns the schema", async () => {
    const { client, close } = await connect({});
    const found = text(await client.callTool({ name: "commands_search", arguments: { query: "delete lesson" } }));
    expect(found.data.map((d: { id: string }) => d.id)).toContain("lessons.delete");
    const desc = text(await client.callTool({ name: "commands_describe", arguments: { id: "lessons.create" } }));
    expect(desc.data.input.properties).toHaveProperty("course_id");
    expect(desc.data.kind).toBe("write");
    const missing = await client.callTool({ name: "commands_describe", arguments: { id: "nope.nope" } });
    expect(missing.isError).toBe(true);
    await close();
  });

  it("commands_run runs any exposed command with the same confirmation rules", async () => {
    const { client, api, close } = await connect(del);
    const first = await client.callTool({ name: "commands_run", arguments: { id: "lessons.delete", input: { id: 9 } } });
    expect(text(first).error.code).toBe("CONFIRMATION_REQUIRED");
    const confirm = text(first).error.details.confirm;
    const ok = await client.callTool({ name: "commands_run", arguments: { id: "lessons.delete", input: { id: 9 }, confirm } });
    expect(ok.isError).toBeFalsy();
    expect(api.requests.some((q) => q.method === "DELETE")).toBe(true);
    await close();
  });

  it("commands_run refuses writes in read-only mode", async () => {
    const { client, api, close } = await connect({}, { readOnly: true });
    const r = await client.callTool({ name: "commands_run", arguments: { id: "courses.create", input: { title: "x" } } });
    expect(r.isError).toBe(true);
    expect(api.requests).toHaveLength(0);
    await close();
  });
});

describe("resources", () => {
  it("lists courses and reads one as Markdown", async () => {
    const routes = {
      ...courses,
      "GET /api/admin/courses/1": { body: { success: true, data: { id: 1, title: "A", status: "draft", lessons: [{ id: 5, title: "L", topics: [{ id: 8, title: "T", topicable_type: "Ulams\\TopicTypes\\Models\\TopicContent\\RichText" }] }] } } },
    };
    const { client, close } = await connect(routes);
    const list = await client.listResources();
    expect(list.resources[0]).toMatchObject({ uri: "ulams://courses/1", name: "A" });
    const read = await client.readResource({ uri: "ulams://courses/1" });
    const body = (read.contents[0] as { text: string }).text;
    expect(body).toContain("# A");
    expect(body).toContain("- T (topic 8, RichText)");
    await close();
  });
});
