import { describe, expect, it, vi } from "vitest";
import { ApiError } from "../src/client.ts";
import { apiErrorInfo, createLivingCourseClient } from "../src/living-course.ts";

type Call = { url: string; init: RequestInit };

function fakeFetch(responses: Array<{ status?: number; body: unknown }>) {
  const calls: Call[] = [];
  const fn = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
    calls.push({ url: String(url), init: init ?? {} });
    const next = responses.shift() ?? { status: 500, body: { message: "no more" } };
    return new Response(JSON.stringify(next.body), { status: next.status ?? 200 });
  });
  return { fn: fn as unknown as typeof fetch, calls };
}

describe("living course client", () => {
  it("lists the sources of a session on the API prefix with the token", async () => {
    const { fn, calls } = fakeFetch([{ body: { success: true, data: [{ id: "src1", name: "handbook.md", connection: null, revisionCount: 0 }] } }]);
    const lc = createLivingCourseClient({ baseUrl: "http://coffee.localhost/", token: "tok", fetch: fn });
    const sources = await lc.sources.list("sess1");
    expect(sources[0]?.name).toBe("handbook.md");
    expect(calls[0]!.url).toBe("http://coffee.localhost/api/admin/living-course/sessions/sess1/sources");
    expect((calls[0]!.init.headers as Record<string, string>).Authorization).toBe("Bearer tok");
  });

  it("works behind the studio BFF", async () => {
    const { fn, calls } = fakeFetch([{ body: { data: [] } }]);
    const lc = createLivingCourseClient({ baseUrl: "/studio/api", prefix: "/living-course", fetch: fn });
    await lc.revisions.list("src 1");
    expect(calls[0]!.url).toBe("/studio/api/living-course/sources/src%201/revisions");
    expect((calls[0]!.init.headers as Record<string, string>).Authorization).toBeUndefined();
  });

  it("reads changes, optionally against another revision", async () => {
    const payload = { from: null, to: { id: "r2" }, counts: { total: 1 }, changes: [{ id: 1, kind: "changed", wordDiff: [["=", "a"]] }] };
    const { fn, calls } = fakeFetch([{ body: { data: payload } }, { body: { data: payload } }]);
    const lc = createLivingCourseClient({ baseUrl: "/studio/api", prefix: "/living-course", fetch: fn });
    const changes = await lc.revisions.changes("r2");
    expect(changes.changes[0]?.kind).toBe("changed");
    await lc.revisions.changes("r2", "r1");
    expect(calls[0]!.url).toBe("/studio/api/living-course/revisions/r2/changes");
    expect(calls[1]!.url).toBe("/studio/api/living-course/revisions/r2/changes?against=r1");
  });

  it("uploads multipart and tells a new revision from the same file", async () => {
    const { fn, calls } = fakeFetch([
      { status: 201, body: { data: { unchanged: false, revision: { id: "r2", number: 2 } } } },
      { status: 200, body: { data: { unchanged: true, revision: { id: "r2", number: 2 } } } },
    ]);
    const lc = createLivingCourseClient({ baseUrl: "/studio/api", prefix: "/living-course", fetch: fn });
    const created = await lc.revisions.upload("src1", new Blob(["# v2"]), "handbook.md");
    expect(created).toMatchObject({ unchanged: false, created: true });
    const again = await lc.revisions.upload("src1", new Blob(["# v2"]), "handbook.md");
    expect(again).toMatchObject({ unchanged: true, created: false });
    expect(calls[0]!.init.method).toBe("POST");
    expect(calls[0]!.url).toBe("/studio/api/living-course/sources/src1/revisions");
    expect(calls[0]!.init.body).toBeInstanceOf(FormData);
    expect((calls[0]!.init.body as FormData).get("file")).toBeInstanceOf(File);
    expect((calls[0]!.init.headers as Record<string, string>)["Content-Type"]).toBeUndefined();
  });

  it("raises ApiError with the API message for a rejected upload", async () => {
    const { fn } = fakeFetch([{ status: 422, body: { success: false, message: "That file type is not supported." } }]);
    const lc = createLivingCourseClient({ baseUrl: "/studio/api", prefix: "/living-course", fetch: fn });
    await expect(lc.revisions.upload("src1", new Blob(["x"]), "a.exe")).rejects.toMatchObject({ status: 422, message: "That file type is not supported." });
  });

  it("turns a network failure into ApiError status 0", async () => {
    const lc = createLivingCourseClient({
      baseUrl: "/studio/api",
      prefix: "/living-course",
      fetch: (async () => {
        throw new Error("offline");
      }) as unknown as typeof fetch,
    });
    await expect(lc.sources.list("s")).rejects.toBeInstanceOf(ApiError);
  });

  it("sends decisions and JSON bodies to the proposal endpoints", async () => {
    const item = { id: "i1", status: "accepted" };
    const proposal = { id: "p1", status: "ready" };
    const { fn, calls } = fakeFetch([
      { body: { data: { item, proposal } } },
      { body: { data: { item, proposal } } },
      { body: { data: { item, proposal } } },
      { body: { data: { item, proposal } } },
      { body: { data: { accepted: 3, proposal } } },
      { body: { data: proposal } },
      { status: 202, body: { data: { runId: "r1", proposal } } },
      { status: 202, body: { data: { state: "started", estimateMicroUsd: 420000, runId: "r2", message: null } } },
    ]);
    const lc = createLivingCourseClient({ baseUrl: "/studio/api", prefix: "/living-course", fetch: fn });
    expect((await lc.proposals.accept("p1", "i1")).item.id).toBe("i1");
    await lc.proposals.reject("p1", "i1");
    await lc.proposals.reset("p1", "i1");
    await lc.proposals.regenerate("p1", "i1", "Keep the example");
    expect((await lc.proposals.acceptAll("p1")).accepted).toBe(3);
    await lc.proposals.rejectAll("p1");
    expect((await lc.proposals.apply("p1", true)).runId).toBe("r1");
    expect((await lc.proposals.analyse("p1", true)).runId).toBe("r2");
    expect(calls.map((c) => `${c.init.method} ${c.url.replace("/studio/api/living-course", "")}`)).toEqual([
      "POST /proposals/p1/items/i1/accept",
      "POST /proposals/p1/items/i1/reject",
      "POST /proposals/p1/items/i1/reset",
      "POST /proposals/p1/items/i1/regenerate",
      "POST /proposals/p1/accept-all",
      "POST /proposals/p1/reject",
      "POST /proposals/p1/apply",
      "POST /proposals/p1/analyse",
    ]);
    expect(calls[0]!.init.body).toBeUndefined();
    expect(JSON.parse(String(calls[3]!.init.body))).toEqual({ comment: "Keep the example" });
    expect((calls[3]!.init.headers as Record<string, string>)["Content-Type"]).toBe("application/json");
    expect(JSON.parse(String(calls[6]!.init.body))).toEqual({ overwrite: true });
    expect(JSON.parse(String(calls[7]!.init.body))).toEqual({ confirmEstimate: true });
  });

  it("exposes the code and data of a refused apply or analysis", async () => {
    const { fn } = fakeFetch([
      { status: 409, body: { success: false, code: "conflicts", message: "You edited some elements.", data: { conflicts: [{ id: "i1" }] } } },
      { status: 409, body: { success: false, code: "confirm_estimate", message: "About $0.42.", data: { estimateMicroUsd: 420000 } } },
      { status: 500, body: { message: "boom" } },
    ]);
    const lc = createLivingCourseClient({ baseUrl: "/studio/api", prefix: "/living-course", fetch: fn });
    const conflict = await lc.proposals.apply("p1").catch((e) => e);
    expect(conflict).toBeInstanceOf(ApiError);
    expect(apiErrorInfo(conflict)).toEqual({ status: 409, code: "conflicts", data: { conflicts: [{ id: "i1" }] } });
    expect(apiErrorInfo(await lc.proposals.analyse("p1").catch((e) => e)).data).toEqual({ estimateMicroUsd: 420000 });
    expect(apiErrorInfo(await lc.proposals.apply("p1").catch((e) => e))).toEqual({ status: 500, code: null, data: null });
    expect(apiErrorInfo(new Error("x"))).toEqual({ status: 0, code: null, data: null });
  });

  it("reads staleness and proposals", async () => {
    const summary = { state: "stale", since: "2026-10-01T00:00:00+00:00", days: 3, pendingElements: 2, openProposalId: "p1", syncedRevision: 1, latestRevision: 2, lastCheckedAt: null, tracked: true };
    const { fn, calls } = fakeFetch([
      { body: { data: { summary, elements: [{ elementId: "e1", status: "pending", type: "block", label: "Lesson 1", since: null, proposalId: "p1", fragmentIds: [], answerCheck: true }] } } },
      { body: { data: [{ id: "p1", number: 1, status: "ready" }] } },
      { body: { data: { id: "p1", status: "ready", groups: [{ key: "lesson:1", label: "Lesson 1", items: [] }], items: [], steps: [] } } },
    ]);
    const lc = createLivingCourseClient({ baseUrl: "/studio/api", prefix: "/living-course", fetch: fn });
    const staleness = await lc.staleness.get("sess1");
    expect(staleness.summary.state).toBe("stale");
    expect(staleness.elements[0]?.answerCheck).toBe(true);
    expect((await lc.proposals.list("sess1"))[0]?.number).toBe(1);
    expect((await lc.proposals.get("p 1")).groups[0]?.label).toBe("Lesson 1");
    expect(calls.map((c) => c.url)).toEqual([
      "/studio/api/living-course/sessions/sess1/staleness",
      "/studio/api/living-course/sessions/sess1/proposals",
      "/studio/api/living-course/proposals/p%201",
    ]);
  });
});
