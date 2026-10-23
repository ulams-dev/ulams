import { describe, expect, it } from "vitest";
import { runCli, type Handler } from "../helpers.ts";

const env = { ULAMS_URL: "http://coffee.localhost", ULAMS_TOKEN: "tok-123456" };
const ok = (data: unknown, extra: Record<string, unknown> = {}) => ({ status: 200, body: { success: true, data, message: "OK", ...extra } });

const policy = { id: "01pol", title: "Remote work and data handling", ownerUserId: 7, status: "active", currentVersion: 1, latestVersion: 1, pendingReview: null, departments: 2 };

describe("policies", () => {
  it("lists the policies the token may see", async () => {
    const r = await runCli(["policies", "list", "--json"], { env, routes: { "GET /api/admin/policies": ok([policy]) } });

    expect(r.code).toBe(0);
    expect((r.json().data as { id: string }[]).map((p) => p.id)).toEqual(["01pol"]);
  });

  it("sends the department and status filters of the dashboard", async () => {
    let query: Record<string, string> = {};
    const dashboard: Handler = (req) => ((query = req.query), ok({ policy: { id: "01pol", currentVersion: 1 }, departments: [], people: [] }, { meta: { total: 0 } }));

    const r = await runCli(["policies", "dashboard", "01pol", "--variant", "01var", "--status", "overdue", "--json"], { env, routes: { "GET /api/admin/policies/01pol/dashboard": dashboard } });

    expect(r.code).toBe(0);
    expect(query).toMatchObject({ variant: "01var", status: "overdue" });
  });

  it("asks for generation and approval with POST", async () => {
    const seen: string[] = [];
    const routes: Record<string, Handler> = {
      "POST /api/admin/policies/01pol/variants/generate": (req) => (seen.push(`${req.method} ${req.path}`), { status: 202, body: { success: true, data: [{ id: "01var", department: "Engineering", stage: "ingesting" }], message: "OK" } }),
      "POST /api/admin/policies/01pol/versions/1/approve": (req) => (seen.push(`${req.method} ${req.path}`), ok({ number: 1, status: "approved" })),
    };

    const generated = await runCli(["policies", "variants-generate", "01pol", "--json"], { env, routes });
    const approved = await runCli(["policies", "versions-approve", "01pol", "1", "--yes", "--json"], { env, routes });

    expect(generated.code).toBe(0);
    expect(approved.code).toBe(0);
    expect(seen).toEqual(["POST /api/admin/policies/01pol/variants/generate", "POST /api/admin/policies/01pol/versions/1/approve"]);
    expect((approved.json().data as { status: string }).status).toBe("approved");
  });

  it("reports a refused approval with the API's message", async () => {
    const r = await runCli(["policies", "versions-approve", "01pol", "1", "--yes", "--json"], {
      env,
      routes: { "POST /api/admin/policies/01pol/versions/1/approve": { status: 422, body: { success: false, message: "The policy cannot be approved yet.\nSales: publish the course in the Studio first." } } },
    });

    expect(r.code).not.toBe(0);
    expect(r.stdout + r.stderr).toContain("publish the course in the Studio first");
  });
});
