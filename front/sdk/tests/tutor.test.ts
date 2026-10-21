import { describe, expect, it, vi } from "vitest";
import { ApiError } from "../src/client.ts";
import { createTutorClient, tutorFailure } from "../src/tutor.ts";

function fakeFetch(responses: Array<{ status?: number; body?: unknown; throws?: boolean }>) {
  const calls: Array<{ url: string; init: RequestInit }> = [];
  const fn = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
    calls.push({ url: String(url), init: init ?? {} });
    const next = responses.shift() ?? { status: 500, body: { message: "no more" } };
    if (next.throws) throw new TypeError("offline");
    return new Response(next.body === undefined ? "" : JSON.stringify(next.body), { status: next.status ?? 200 });
  });
  return { fn: fn as unknown as typeof fetch, calls };
}

const status = { enabled: true, aiEnabled: true, remaining: 29, dailyLimit: 30, attemptActive: false };

describe("tutor client", () => {
  it("reads the status through the BFF", async () => {
    const { fn, calls } = fakeFetch([{ body: { success: true, data: status } }]);
    const client = createTutorClient({ baseUrl: "/bff", fetch: fn });
    expect(await client.status(5)).toEqual(status);
    expect(calls[0]!.url).toBe("/bff/api/tutor/courses/5/status");
    expect(calls[0]!.init.method).toBe("GET");
    expect((calls[0]!.init.headers as Record<string, string>).Authorization).toBeUndefined();
  });

  it("asks with a JSON body, sending the conversation and topic only when given", async () => {
    const answer = { conversationId: "c1", messageId: "m1", answer: "Because.", citations: [], refusal: "none", inScope: true, remaining: 28, label: "AI tutor" };
    const { fn, calls } = fakeFetch([{ body: { data: answer } }, { body: { data: answer } }]);
    const client = createTutorClient({ baseUrl: "http://coffee.localhost/", token: "tok", fetch: fn });
    expect((await client.ask(5, { text: "Why?" })).answer).toBe("Because.");
    await client.ask(5, { text: "And?", conversationId: "c1", topicId: 12 });
    expect(calls[0]!.url).toBe("http://coffee.localhost/api/tutor/courses/5/messages");
    expect(JSON.parse(String(calls[0]!.init.body))).toEqual({ text: "Why?" });
    expect(JSON.parse(String(calls[1]!.init.body))).toEqual({ text: "And?", conversationId: "c1", topicId: 12 });
    const headers = calls[0]!.init.headers as Record<string, string>;
    expect(headers.Authorization).toBe("Bearer tok");
    expect(headers["Content-Type"]).toBe("application/json");
  });

  it("lists, opens and deletes conversations", async () => {
    const summary = { id: "01J", lastMessageAt: "2026-10-21T10:00:00+00:00", expiresAt: "2027-01-19T10:00:00+00:00" };
    const full = { id: "01J", courseId: 5, expiresAt: summary.expiresAt, messages: [] };
    const { fn, calls } = fakeFetch([{ body: { data: [summary] } }, { body: { data: full } }, { body: { data: { deleted: true } } }, { body: { data: null } }]);
    const client = createTutorClient({ baseUrl: "/bff", fetch: fn });
    expect(await client.conversations(5)).toEqual([summary]);
    expect(await client.conversation("01J")).toEqual(full);
    await client.deleteConversation("01J");
    expect(calls.map((c) => `${c.init.method} ${c.url}`)).toEqual([
      "GET /bff/api/tutor/courses/5/conversations",
      "GET /bff/api/tutor/conversations/01J",
      "DELETE /bff/api/tutor/conversations/01J",
    ]);
    expect(await client.conversations(5)).toEqual([]);
  });

  it("turns failures into ApiError and classifies them", async () => {
    const { fn } = fakeFetch([
      { status: 429, body: { message: "You have used today's questions.", reason: "limit" } },
      { status: 503, body: { message: "off", reason: "disabled" } },
      { throws: true },
      { status: 403, body: { message: "no" } },
    ]);
    const client = createTutorClient({ baseUrl: "/bff", fetch: fn });
    const errors: unknown[] = [];
    for (let i = 0; i < 4; i++) errors.push(await client.ask(5, { text: "Why?" }).catch((e: unknown) => e));
    expect(errors[0]).toBeInstanceOf(ApiError);
    expect((errors[0] as ApiError).message).toBe("You have used today's questions.");
    expect(errors.map(tutorFailure)).toEqual(["limit", "disabled", "network", "forbidden"]);
    expect(tutorFailure(new Error("x"))).toBe("unknown");
  });
});
