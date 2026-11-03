import { describe, expect, it, vi } from "vitest";
import { ApiError } from "../src/client.ts";
import { createInsightsAnalyticsClient, createInsightsModuleClient } from "../src/insights-admin.ts";

function fakeFetch(responses: Array<{ status?: number; body: unknown }>) {
  const calls: Array<{ url: string; init: RequestInit }> = [];
  const fn = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
    calls.push({ url: String(url), init: init ?? {} });
    const next = responses.shift() ?? { status: 500, body: { message: "no more" } };
    return new Response(JSON.stringify(next.body), { status: next.status ?? 200 });
  });
  return { fn: fn as unknown as typeof fetch, calls };
}

describe("learner insights analytics client", () => {
  it("reads the summary and the elements of a course for a period through the studio BFF", async () => {
    const summary = { courseId: 5, periodDays: 7, minGroupSize: 10, enrolled: 4, smallGroup: true };
    const { fn, calls } = fakeFetch([{ body: { data: summary } }, { body: { data: { courseId: 5, periodDays: 90, minGroupSize: 10, elements: [] } } }]);
    const client = createInsightsAnalyticsClient({ baseUrl: "/studio/api/", prefix: "/learner-insights", fetch: fn });

    expect(await client.summary(5, 7)).toEqual(summary);
    expect((await client.elements(5, 90, "recovery")).elements).toEqual([]);
    expect(calls[0]!.url).toBe("/studio/api/learner-insights/courses/5/summary?period=7");
    expect(calls[1]!.url).toBe("/studio/api/learner-insights/courses/5/elements?period=90&sort=recovery");
    expect(calls.every((c) => c.init.method === "GET")).toBe(true);
  });

  it("defaults to the admin API path, sends the token and reads the individual endpoints", async () => {
    const { fn, calls } = fakeFetch([{ body: { data: [] } }, { body: { data: [] } }]);
    const client = createInsightsAnalyticsClient({ baseUrl: "http://coffee.localhost", token: "tok", fetch: fn });

    await client.learners(3, "struggling");
    await client.timeline(8, 3);
    expect(calls[0]!.url).toBe("http://coffee.localhost/api/admin/learner-insights/courses/3/learners?status=struggling");
    expect(calls[1]!.url).toBe("http://coffee.localhost/api/admin/learner-insights/learners/8/courses/3/timeline");
    expect((calls[0]!.init.headers as Record<string, string>).Authorization).toBe("Bearer tok");
  });

  it("turns a refusal into an ApiError with its status and message", async () => {
    const { fn } = fakeFetch([{ status: 403, body: { message: "This action is unauthorized." } }]);
    const client = createInsightsAnalyticsClient({ baseUrl: "/studio/api", prefix: "/learner-insights", fetch: fn });

    await expect(client.learners(3)).rejects.toMatchObject({ status: 403, message: "This action is unauthorized." });
  });

  it("reports an unreachable API as status 0", async () => {
    const fn = (async () => {
      throw new TypeError("fetch failed");
    }) as unknown as typeof fetch;
    const client = createInsightsAnalyticsClient({ baseUrl: "/studio/api", prefix: "/learner-insights", fetch: fn });

    const error = await client.summary(1).catch((e: unknown) => e);
    expect(error).toBeInstanceOf(ApiError);
    expect((error as ApiError).status).toBe(0);
  });
});

describe("propose an improvement", () => {
  const evidence = { periodDays: 30, learners: 42, minGroupSize: 10, struggleRate: 0.38, remediationRate: 0.2, recoveryRate: null, helpfulShare: null, topReasons: [], wrongOptions: [], tutorOutOfScope: 0 };

  it("only estimates without confirmEstimate and creates the proposal with it, encoding the element key", async () => {
    const estimate = { state: "estimate", estimateMicroUsd: 120000, estimateUsd: 0.12, label: "Lesson 2.1 › Q1", elements: 1, evidence };
    const started = { state: "started", runId: "r1", estimateMicroUsd: 120000, message: null, proposal: { id: "p1", kind: "insights" } };
    const { fn, calls } = fakeFetch([{ body: { data: estimate } }, { status: 201, body: { data: started } }]);
    const client = createInsightsAnalyticsClient({ baseUrl: "/studio/api", prefix: "/learner-insights", fetch: fn });

    expect(await client.improve(3, "question:7", { period: 7 })).toEqual(estimate);
    expect(await client.improve(3, "01arz3ndektsv4rrffq69g5fav", { confirmEstimate: true })).toEqual(started);

    expect(calls.map((c) => [c.init.method, c.url])).toEqual([
      ["POST", "/studio/api/learner-insights/courses/3/elements/question%3A7/improve"],
      ["POST", "/studio/api/learner-insights/courses/3/elements/01arz3ndektsv4rrffq69g5fav/improve"],
    ]);
    expect(JSON.parse(String(calls[0]!.init.body))).toEqual({ period: 7 });
    expect(JSON.parse(String(calls[1]!.init.body))).toEqual({ confirmEstimate: true });
    expect((calls[0]!.init.headers as Record<string, string>)["Content-Type"]).toBe("application/json");
  });

  it("keeps the refusal code of the API on the error", async () => {
    const { fn } = fakeFetch([{ status: 429, body: { success: false, code: "daily_limit", message: "Three improvement proposals today." } }]);
    const client = createInsightsAnalyticsClient({ baseUrl: "/studio/api", prefix: "/learner-insights", fetch: fn });

    const error = (await client.improve(3, "01arz3ndektsv4rrffq69g5fav", { confirmEstimate: true }).catch((e: unknown) => e)) as ApiError;
    expect(error).toBeInstanceOf(ApiError);
    expect(error.status).toBe(429);
    expect((error.body as { code: string }).code).toBe("daily_limit");
  });
});

describe("learner insights module client", () => {
  it("tells whether the module is on for the academy", async () => {
    const { fn, calls } = fakeFetch([{ body: { data: { enabled: false } } }, { body: { data: { enabled: true } } }]);
    const client = createInsightsModuleClient({ baseUrl: "/studio/api", prefix: "/learner-insights", fetch: fn });

    expect(await client.enabled()).toBe(false);
    expect(await client.enabled()).toBe(true);
    expect(calls[0]!.url).toBe("/studio/api/learner-insights/me");
  });

  it("throws on an error status so the page does not mistake it for 'off'", async () => {
    const { fn } = fakeFetch([{ status: 500, body: {} }]);
    await expect(createInsightsModuleClient({ baseUrl: "/x", fetch: fn }).enabled()).rejects.toMatchObject({ status: 500 });
  });
});
