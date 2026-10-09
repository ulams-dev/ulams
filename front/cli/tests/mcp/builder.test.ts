import { describe, expect, it } from "vitest";
import { fakeBuilder } from "../fake-builder.ts";
import { connect, text } from "./helpers.ts";

describe("builder and living MCP tools", () => {
  it("the builder toolset exposes every command except the stream, with annotations", async () => {
    const { client, close } = await connect({}, { toolsets: ["builder", "living"] });
    const tools = (await client.listTools()).tools;
    const byName = Object.fromEntries(tools.map((t) => [t.name, t]));
    for (const name of ["builder_start", "builder_sessions_get", "builder_interview_show", "builder_interview_answer", "builder_outline_approve", "builder_apply", "builder_publish", "builder_chat", "builder_patches_approve", "builder_versions_list", "builder_undo", "builder_events_list", "builder_runs_get", "living_proposals_list", "living_proposals_accept", "living_proposals_apply", "living_audit_list"]) {
      expect(byName[name], name).toBeDefined();
    }
    expect(byName.builder_events).toBeUndefined();
    expect(byName.builder_sessions_get?.annotations).toMatchObject({ readOnlyHint: true });
    expect(byName.builder_sessions_delete?.annotations).toMatchObject({ destructiveHint: true });
    expect(byName.living_proposals_reject_all?.annotations).toMatchObject({ destructiveHint: true });
    // long-running tools take wait and timeout_seconds
    expect(byName.builder_outline_approve?.inputSchema.properties).toHaveProperty("wait");
    expect(byName.builder_outline_approve?.inputSchema.properties).toHaveProperty("timeout_seconds");
    expect(byName.builder_sessions_get?.inputSchema.properties).not.toHaveProperty("wait");
    await close();
  });

  it("builder_start runs the pipeline through MCP", async () => {
    const fake = fakeBuilder({ ticks: 1 });
    const { client, close } = await connect({ "*": fake.handler }, { toolsets: ["builder"] });
    const r = await client.callTool({ name: "builder_start", arguments: { session: undefined, title: "t", from: [], defaults: true } });
    // no source: a usable error, not a crash
    expect(r.isError).toBe(true);
    expect(text(r)).toMatchObject({ ok: false, error: { code: "INPUT_INVALID" } });
    await close();
  });

  it("a wait that runs out returns status running and the handle instead of an error", async () => {
    const fake = fakeBuilder({ ticks: 1000 });
    const { client, close } = await connect({ "*": fake.handler }, { toolsets: ["builder", "core"] });
    const created = text(await client.callTool({ name: "builder_sessions_create", arguments: {} }));
    const sessionId = created.data.id as string;
    const up = await client.callTool({ name: "builder_sources_add", arguments: { session: sessionId, file: ["package.json"], wait: true, timeout_seconds: 1 } });
    expect(up.isError).toBeFalsy();
    expect(text(up)).toMatchObject({ ok: true, data: { status: "running", operation: expect.stringMatching(/^builder-run:/) }, warnings: [{ code: "STILL_RUNNING" }] });
    await close();
  });

  it("builder_events_list is a bounded read", async () => {
    const fake = fakeBuilder({ ticks: 1 });
    const { client, close } = await connect({ "*": fake.handler }, { toolsets: ["builder"] });
    const created = text(await client.callTool({ name: "builder_sessions_create", arguments: {} }));
    const r = text(await client.callTool({ name: "builder_events_list", arguments: { session: created.data.id, limit: 5 } }));
    expect(r).toMatchObject({ ok: true, data: { events: [], total: 0 } });
    await close();
  });
});
