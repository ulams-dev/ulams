import { describe, expect, it, vi } from "vitest";
import { ApiError } from "../src/client.ts";
import { createCertificateClient } from "../src/certificates.ts";
import { createComplianceClient } from "../src/compliance.ts";

function fakeFetch(responses: Array<{ status?: number; body: unknown }>) {
  const calls: Array<{ url: string; init: RequestInit }> = [];
  const fn = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
    calls.push({ url: String(url), init: init ?? {} });
    const next = responses.shift() ?? { status: 500, body: { message: "no more" } };
    return new Response(JSON.stringify(next.body), { status: next.status ?? 200 });
  });
  return { fn: fn as unknown as typeof fetch, calls };
}

describe("compliance client", () => {
  it("reads my assignments, paths and skills through the BFF, empty on a null body", async () => {
    const assignment = { id: 1, user: null, course: { id: 5, title: "Safety" }, source: "manual", status: "overdue", assigned_at: null, due_at: "2026-10-01T00:00:00+00:00", completed_at: null, escalation_level: 1, certificate: null };
    const { fn, calls } = fakeFetch([{ body: { data: [assignment] } }, { body: { data: [{ path_id: 2, title: "Onboarding", steps: [] }] } }, { body: { data: null } }]);
    const client = createComplianceClient({ baseUrl: "/bff", fetch: fn });
    expect(await client.myAssignments()).toEqual([assignment]);
    expect((await client.myPaths())[0]!.title).toBe("Onboarding");
    expect(await client.mySkills()).toEqual([]);
    expect(calls.map((c) => c.url)).toEqual(["/bff/api/compliance/my-assignments", "/bff/api/compliance/my-paths", "/bff/api/compliance/my-skills"]);
    expect((calls[0]!.init.headers as Record<string, string>).Authorization).toBeUndefined();
  });

  it("pages the team view with a status filter and the server token", async () => {
    const { fn, calls } = fakeFetch([{ body: { data: [{ id: 3 }], meta: { total: 41, per_page: 25, current_page: 2, last_page: 2 } } }]);
    const client = createComplianceClient({ baseUrl: "http://coffee.localhost/", token: "tok", fetch: fn });
    const page = await client.teamAssignments({ status: "overdue", page: 2 });
    expect(page).toMatchObject({ total: 41, perPage: 25, currentPage: 2, lastPage: 2 });
    expect(page.items).toHaveLength(1);
    expect(calls[0]!.url).toBe("http://coffee.localhost/api/compliance/team/assignments?status=overdue&page=2");
    expect((calls[0]!.init.headers as Record<string, string>).Authorization).toBe("Bearer tok");
  });

  it("turns failures into ApiError, status 0 when unreachable", async () => {
    const { fn } = fakeFetch([{ status: 401, body: { message: "Unauthenticated." } }]);
    await expect(createComplianceClient({ baseUrl: "/bff", fetch: fn }).myAssignments()).rejects.toMatchObject({ status: 401 });
    const down = createComplianceClient({ baseUrl: "/bff", fetch: (async () => Promise.reject(new Error("offline"))) as unknown as typeof fetch });
    await expect(down.mySkills()).rejects.toBeInstanceOf(ApiError);
    await expect(down.mySkills()).rejects.toMatchObject({ status: 0 });
  });
});

describe("certificate client", () => {
  it("lists my certificates, filtered by course, and builds the download URL", async () => {
    const { fn, calls } = fakeFetch([{ body: { data: [{ id: 9, certificate_id: "abc-12345678", status: "valid" }], meta: { total: 1 } } }]);
    const client = createCertificateClient({ baseUrl: "http://coffee.localhost", token: "tok", fetch: fn });
    const page = await client.list({ assignable_type: "Ulams\\Courses\\Models\\Course", assignable_id: 5 });
    expect(page.items[0]!.certificate_id).toBe("abc-12345678");
    expect(calls[0]!.url).toBe("http://coffee.localhost/api/pdfs?per_page=100&assignable_type=Ulams%5CCourses%5CModels%5CCourse&assignable_id=5");
    expect(client.downloadUrl(9)).toBe("http://coffee.localhost/api/pdfs/generate/9");
  });

  it("verifies publicly without a token and returns null for an unknown id", async () => {
    const record = { certificate_id: "abc-12345678", status: "valid", title: "Safety", course_title: "Safety", holder_name: "Ada", issued_at: null, expires_at: null, revoked_at: null, issuer: "Acme" };
    const { fn, calls } = fakeFetch([{ body: { data: record } }, { status: 404, body: { message: "Not found" } }, { status: 500, body: {} }]);
    const client = createCertificateClient({ baseUrl: "https://acme.test", fetch: fn });
    expect(await client.verify("abc-12345678")).toEqual(record);
    expect(calls[0]!.url).toBe("https://acme.test/api/certificates/verify/abc-12345678");
    expect((calls[0]!.init.headers as Record<string, string>).Authorization).toBeUndefined();
    expect(await client.verify("zzzzzzzz")).toBeNull();
    await expect(client.verify("abc-12345678")).rejects.toMatchObject({ status: 500 });
  });
});
