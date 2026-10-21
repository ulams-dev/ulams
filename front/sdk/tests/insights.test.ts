import { describe, expect, it, vi } from "vitest";
import { ApiError } from "../src/client.ts";
import { createInsightsClient } from "../src/insights.ts";

function fakeFetch(responses: Array<{ status?: number; body: unknown; raw?: string }>) {
  const calls: Array<{ url: string; init: RequestInit }> = [];
  const fn = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
    calls.push({ url: String(url), init: init ?? {} });
    const next = responses.shift() ?? { status: 500, body: { message: "no more" } };
    return new Response(next.raw ?? JSON.stringify(next.body), { status: next.status ?? 200 });
  });
  return { fn: fn as unknown as typeof fetch, calls };
}

const headersOf = (init: RequestInit) => init.headers as Record<string, string>;
const ID = "01JBCDEFGHJKMNPQRSTVWXYZ01";

describe("learner insights client", () => {
  it("reads the support items of a lesson through the BFF without a token", async () => {
    const item = { assignmentId: ID, kind: "explanation", status: "ready", reasons: ["You answered this question wrongly 2 times"], why: "x", feedback: null, document: { summary: "s", blocks: [] }, citations: [] };
    const { fn, calls } = fakeFetch([{ body: { data: { enabled: true, items: [item] } } }]);
    const client = createInsightsClient({ baseUrl: "/bff", fetch: fn });
    expect(await client.support(5, 12)).toEqual({ enabled: true, items: [item] });
    expect(calls[0]!.url).toBe("/bff/api/learner-insights/courses/5/topics/12/support");
    expect(calls[0]!.init.method).toBe("GET");
    expect(headersOf(calls[0]!.init).Authorization).toBeUndefined();
  });

  it("sends the token on the server and treats an empty body as no help", async () => {
    const { fn, calls } = fakeFetch([{ body: { data: null } }]);
    const client = createInsightsClient({ baseUrl: "http://coffee.localhost/", token: "tok", fetch: fn, headers: { "Accept-Language": "pl" } });
    expect(await client.support(5, 12)).toEqual({ enabled: false, items: [] });
    expect(calls[0]!.url).toBe("http://coffee.localhost/api/learner-insights/courses/5/topics/12/support");
    expect(headersOf(calls[0]!.init).Authorization).toBe("Bearer tok");
    expect(headersOf(calls[0]!.init)["Accept-Language"]).toBe("pl");
  });

  it("posts feedback as helpful: true or false", async () => {
    const { fn, calls } = fakeFetch([{ body: { data: { feedback: "helpful" } } }, { body: { data: { feedback: "not_helpful" } } }]);
    const client = createInsightsClient({ baseUrl: "/bff", fetch: fn });
    expect(await client.feedback(ID, true)).toEqual({ feedback: "helpful" });
    expect(await client.feedback(ID, false)).toEqual({ feedback: "not_helpful" });
    expect(calls[0]!.url).toBe(`/bff/api/learner-insights/assignments/${ID}/feedback`);
    expect(calls[0]!.init.method).toBe("POST");
    expect(calls[0]!.init.body).toBe('{"helpful":true}');
    expect(headersOf(calls[0]!.init)["Content-Type"]).toBe("application/json");
    expect(calls[1]!.init.body).toBe('{"helpful":false}');
  });

  it("hides an item with a POST that has no body", async () => {
    const { fn, calls } = fakeFetch([{ body: { data: { hidden: true } } }]);
    const client = createInsightsClient({ baseUrl: "/bff", fetch: fn });
    expect(await client.hide(ID)).toEqual({ hidden: true });
    expect(calls[0]!.url).toBe(`/bff/api/learner-insights/assignments/${ID}/hide`);
    expect(calls[0]!.init.body).toBeUndefined();
    expect(headersOf(calls[0]!.init)["Content-Type"]).toBeUndefined();
  });

  it("returns reflection feedback and maps the daily limit and AI-off answers", async () => {
    const result = { correct: "a", missing: "b", followUp: "c", citations: [], remaining: 4 };
    const { fn, calls } = fakeFetch([{ body: { data: result } }, { status: 429, body: { message: "Daily limit reached" } }, { status: 503, body: { message: "AI is disabled" } }]);
    const client = createInsightsClient({ baseUrl: "/bff", fetch: fn });
    expect(await client.reflection(ID, "In my own words, it works because…")).toEqual(result);
    expect(calls[0]!.init.body).toBe('{"text":"In my own words, it works because…"}');
    await expect(client.reflection(ID, "x".repeat(20))).rejects.toMatchObject({ status: 429, message: "Daily limit reached" });
    await expect(client.reflection(ID, "x".repeat(20))).rejects.toMatchObject({ status: 503 });
  });

  it("reads and saves the lesson-view preferences, null removing a choice", async () => {
    const state = { adaptive: true, profile: { density: "compact", chunking: "whole", navigation: "linear", hints: 0 }, derived: {}, overrides: { density: "compact" }, why: [] };
    const { fn, calls } = fakeFetch([{ body: { data: state } }, { body: { data: state } }]);
    const client = createInsightsClient({ baseUrl: "/bff", fetch: fn });
    expect(await client.presentation(5)).toEqual(state);
    expect(await client.savePresentation(5, { density: "compact", hints: null })).toEqual(state);
    expect(calls[0]!.url).toBe("/bff/api/learner-insights/courses/5/presentation");
    expect(calls[1]!.init.method).toBe("PUT");
    expect(calls[1]!.init.body).toBe('{"density":"compact","hints":null}');
  });

  it("covers the learner's own status, settings, export and erasure", async () => {
    const { fn, calls } = fakeFetch([
      { body: { data: { enabled: true, settings: { enabled: true, help: true, nudges: true, consent: null }, requiresConsent: false, courses: [], retention: { signalsDays: 90, historyDays: 365 } } } },
      { body: { data: { enabled: false, help: false, nudges: false, consent: null } } },
      { body: null, raw: '{\n  "userId": 7\n}' },
      { body: { data: { deleted: 12 } } },
    ]);
    const client = createInsightsClient({ baseUrl: "/bff", fetch: fn });
    expect((await client.me())?.retention.signalsDays).toBe(90);
    expect((await client.saveSettings({ enabled: false, help: false, nudges: false, eraseExisting: true }))?.enabled).toBe(false);
    expect(await client.exportData()).toBe('{\n  "userId": 7\n}');
    expect(await client.erase()).toEqual({ deleted: 12 });
    expect(calls.map((c) => `${c.init.method} ${c.url}`)).toEqual([
      "GET /bff/api/learner-insights/me",
      "PUT /bff/api/learner-insights/me/settings",
      "GET /bff/api/learner-insights/me/export",
      "DELETE /bff/api/learner-insights/me",
    ]);
  });

  it("maps 401, 403 and unreachable to ApiError", async () => {
    const { fn } = fakeFetch([{ status: 401, body: { message: "Unauthenticated." } }, { status: 403, body: { message: "Forbidden" } }, { status: 500, body: null, raw: "<html>boom</html>" }]);
    const client = createInsightsClient({ baseUrl: "/bff", fetch: fn });
    await expect(client.support(1, 2)).rejects.toMatchObject({ status: 401, message: "Unauthenticated." });
    await expect(client.support(1, 2)).rejects.toMatchObject({ status: 403 });
    await expect(client.support(1, 2)).rejects.toMatchObject({ status: 500 });
    const down = createInsightsClient({ baseUrl: "/bff", fetch: (async () => Promise.reject(new Error("offline"))) as unknown as typeof fetch });
    await expect(down.hide(ID)).rejects.toBeInstanceOf(ApiError);
    await expect(down.hide(ID)).rejects.toMatchObject({ status: 0 });
  });
});
