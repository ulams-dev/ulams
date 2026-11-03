import { describe, expect, it } from "vitest";
import { connect, text } from "./helpers.ts";

/** The authoring metric over MCP (ADR 0194, AM-6): read-only tools in the analytics toolset that say what the numbers are. */
describe("authoring metric MCP tools", () => {
  it("are read tools of the analytics toolset, named from the commands, available with --read-only", async () => {
    const { client, close } = await connect({}, { toolsets: ["analytics"], readOnly: true });
    const byName = Object.fromEntries((await client.listTools()).tools.map((t) => [t.name, t]));

    for (const name of ["authoring_metrics", "authoring_course", "authoring_summary", "authoring_export"]) {
      expect(byName[name], name).toBeDefined();
      expect(byName[name].annotations).toMatchObject({ readOnlyHint: true });
    }
    expect(byName.authoring_metrics.inputSchema.properties).toHaveProperty("from");
    expect(byName.authoring_metrics.inputSchema.properties).toHaveProperty("course_id[]");
    await close();
  });

  it("tell an agent that the numbers are the tenant's own and that missing is not zero", async () => {
    const { client, close } = await connect({}, { toolsets: ["analytics"] });
    const byName = Object.fromEntries((await client.listTools()).tools.map((t) => [t.name, t]));

    expect(byName.authoring_metrics.description).toContain("tenant's own measurements");
    expect(byName.authoring_metrics.description).toContain("never zero");
    expect(byName.authoring_summary.description).toContain("do not estimate it");
    expect(byName.authoring_summary.description).toContain("not_enough_courses");
    await close();
  });

  it("call the API and return its answer", async () => {
    const data = { status: "not_enough_courses", n_courses: 2, n_with_figure: 2, min_courses: 3, median_hours_per_learner_hour: null, own_baseline: null };
    const { client, close } = await connect({ "GET /api/admin/authoring-metrics/summary": { body: { success: true, data, message: "OK" } } }, { toolsets: ["analytics"] });

    const r = await client.callTool({ name: "authoring_summary", arguments: {} });

    expect(r.isError).toBeFalsy();
    expect(JSON.stringify(text(r))).toContain("not_enough_courses");
    await close();
  });
});
