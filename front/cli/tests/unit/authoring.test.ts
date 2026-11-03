import { describe, expect, it } from "vitest";
import { getRegistry } from "../../src/registry/index.ts";
import { runCli } from "../helpers.ts";

const env = { ULAMS_URL: "http://coffee.localhost", ULAMS_TOKEN: "tok-123456" };

/** `ulams authoring ...` (ADR 0194, AM-6): generated from the OpenAPI spec, named by spec/overrides.yaml. */
describe("authoring commands", () => {
  const byId = (id: string) => getRegistry().find((c) => c.id === id)!;

  it("exist with the builder scope, read-only, and cover the four endpoints", () => {
    const expected: Record<string, string> = {
      "authoring.metrics": "GET /api/admin/authoring-metrics/courses",
      "authoring.course": "GET /api/admin/authoring-metrics/courses/{course}",
      "authoring.export": "GET /api/admin/authoring-metrics/courses/export",
      "authoring.summary": "GET /api/admin/authoring-metrics/summary",
    };
    for (const [id, endpoint] of Object.entries(expected)) {
      const cmd = byId(id);
      expect(cmd, id).toBeDefined();
      expect(cmd.kind).toBe("read");
      expect(cmd.scopes).toEqual(["builder:read"]);
      expect(cmd.endpoints).toEqual([endpoint]);
      expect(cmd.mcp?.toolset).toBe("analytics");
    }
  });

  it("does not offer review time to scripts: reporting and deleting it are browser-only", () => {
    const covered = getRegistry().flatMap((c) => c.endpoints);
    expect(covered.some((e) => e.includes("authoring-metrics/activity"))).toBe(false);
  });

  it("runs `authoring summary` against the API", async () => {
    const data = { status: "ok", n_courses: 4, n_with_figure: 4, min_courses: 3, median_hours_per_learner_hour: 5.2, own_baseline: { hours_per_learner_hour: 40, label: "own_estimate" } };
    const r = await runCli(["authoring", "summary", "--json"], { env, routes: { "GET /api/admin/authoring-metrics/summary": { body: { success: true, data, message: "OK" } } } });

    expect(r.code).toBe(0);
    expect(r.json().data ?? r.json()).toMatchObject({ n_with_figure: 4, own_baseline: { label: "own_estimate" } });
  });

  it("passes the filters of `authoring metrics` to the API", async () => {
    const r = await runCli(["authoring", "metrics", "--from", "2026-10-01", "--to", "2026-10-31", "--json"], { env, routes: { "GET /api/admin/authoring-metrics/courses": { body: { success: true, data: [], message: "OK" } } } });

    expect(r.code).toBe(0);
    expect(r.requests[0].query).toMatchObject({ from: "2026-10-01", to: "2026-10-31" });
  });
});
