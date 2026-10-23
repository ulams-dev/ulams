import { describe, expect, it } from "vitest";
import { connect, text } from "./helpers.ts";

const R = "/api/admin/reporting";
const ID = "01J9Z3K8M2X4Q7R5T6V8W0Y1AB";
const result = { columns: [{ name: "course_title", type: "string", label: "Course" }], rows: [{ course_title: "Safety" }, { course_title: "withheld (fewer than 10 learners)", _withheld: true }] };
const routes = {
  [`GET ${R}/datasets`]: { body: { success: true, data: [{ key: "assignments" }] } },
  [`POST ${R}/query`]: { body: { success: true, data: result, meta: { mode: "grouped", total_rows: 1, withheld_groups: 2, min_group_size: 10 } } },
  [`GET ${R}/saved`]: { body: { success: true, data: [{ id: ID, name: "Overdue" }] } },
  [`GET ${R}/saved/${ID}`]: { body: { success: true, data: { id: ID, name: "Overdue" } } },
  [`DELETE ${R}/saved/${ID}`]: { body: { success: true, data: null } },
};

describe("report builder tools", () => {
  it("the analytics toolset exposes the eight reporting tools, annotated, and they are not in core", async () => {
    const core = await connect({});
    const analytics = await connect({}, { toolsets: ["analytics"] });
    expect((await core.client.listTools()).tools.map((t) => t.name).filter((n) => n.startsWith("reporting_"))).toEqual([]);
    const tools = (await analytics.client.listTools()).tools;
    const reporting = tools.filter((t) => t.name.startsWith("reporting_"));
    expect(reporting.map((t) => t.name).sort()).toEqual(["reporting_datasets", "reporting_delete", "reporting_list", "reporting_query", "reporting_run", "reporting_save", "reporting_show", "reporting_update"]);
    const byName = Object.fromEntries(reporting.map((t) => [t.name, t]));
    expect(byName.reporting_query?.annotations).toMatchObject({ readOnlyHint: true, destructiveHint: false });
    expect(byName.reporting_run?.annotations).toMatchObject({ readOnlyHint: true });
    expect(byName.reporting_save?.annotations).toMatchObject({ readOnlyHint: false, destructiveHint: false });
    expect(byName.reporting_delete?.annotations).toMatchObject({ readOnlyHint: false, destructiveHint: true });
    expect(byName.reporting_query?.inputSchema.properties).toHaveProperty("definition");
    await core.close();
    await analytics.close();
  });

  it("the descriptions say the numbers are the tenant's own and that withheld groups are withheld on purpose", async () => {
    const { client, close } = await connect({}, { toolsets: ["analytics"] });
    const tools = Object.fromEntries((await client.listTools()).tools.map((t) => [t.name, t]));

    for (const name of ["reporting_query", "reporting_run", "reporting_datasets"]) {
      expect(tools[name]?.description, name).toContain("this academy's own measurements");
      expect(tools[name]?.description, name).toContain("withheld on purpose");
      expect(tools[name]?.description, name).toContain("Do not try to recover them");
    }
    await close();
  });

  it("read-only keeps the reads and drops save, update and delete", async () => {
    const { client, close } = await connect({}, { toolsets: ["analytics"], readOnly: true });
    const names = (await client.listTools()).tools.map((t) => t.name).filter((n) => n.startsWith("reporting_"));

    expect(names.sort()).toEqual(["reporting_datasets", "reporting_list", "reporting_query", "reporting_run", "reporting_show"]);
    await close();
  });

  it("an agent previews a definition and sees the withheld row and the meta that explains it", async () => {
    const { client, api, close } = await connect(routes, { toolsets: ["analytics"] });

    const r = await client.callTool({ name: "reporting_query", arguments: { definition: { dataset: "assignments", groupBy: ["course_title"], aggregates: [{ fn: "count", as: "n" }] } } });

    expect(r.isError).toBeFalsy();
    const body = text(r);
    expect(body.data.rows[1]._withheld).toBe(true);
    expect(body.meta).toMatchObject({ withheld_groups: 2, min_group_size: 10 });
    expect(api.requests.at(-1)?.body).toMatchObject({ dataset: "assignments" });
    await close();
  });

  it("delete returns a plan and a confirm token first, then deletes once", async () => {
    const { client, api, close } = await connect(routes, { toolsets: ["analytics"] });

    const first = await client.callTool({ name: "reporting_delete", arguments: { id: ID } });
    expect(first.isError).toBe(true);
    const err = text(first).error;
    expect(err.code).toBe("CONFIRMATION_REQUIRED");
    expect(err.details.plan.request).toMatchObject({ method: "DELETE", path: `${R}/saved/${ID}` });
    expect(api.requests.some((q) => q.method === "DELETE")).toBe(false);

    const done = await client.callTool({ name: "reporting_delete", arguments: { id: ID, confirm: err.details.confirm } });
    expect(done.isError).toBeFalsy();
    expect(api.requests.filter((q) => q.method === "DELETE")).toHaveLength(1);
    await close();
  });
});
