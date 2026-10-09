import { describe, expect, it, vi } from "vitest";
import { ApiError } from "../src/client.ts";
import { createLivingLearnerClient } from "../src/living-learner.ts";

function fakeFetch(responses: Array<{ status?: number; body: unknown }>) {
  const calls: Array<{ url: string; init: RequestInit }> = [];
  const fn = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
    calls.push({ url: String(url), init: init ?? {} });
    const next = responses.shift() ?? { status: 500, body: { message: "no more" } };
    return new Response(JSON.stringify(next.body), { status: next.status ?? 200 });
  });
  return { fn: fn as unknown as typeof fetch, calls };
}

describe("living course learner client", () => {
  it("reads the open notices of a course through the BFF", async () => {
    const notice = { id: 7, kind: "topic_updated", topicId: 12, topicTitle: "Grinding", giftQuestionId: null, message: "New ratio", createdAt: "2026-10-09T10:00:00+00:00" };
    const { fn, calls } = fakeFetch([{ body: { success: true, data: [notice] } }]);
    const client = createLivingLearnerClient({ baseUrl: "/bff", fetch: fn });
    expect(await client.notices(5)).toEqual([notice]);
    expect(calls[0]!.url).toBe("/bff/api/living-course/courses/5/notices");
    expect(calls[0]!.init.method).toBe("GET");
    expect((calls[0]!.init.headers as Record<string, string>).Authorization).toBeUndefined();
  });

  it("sends the token on the server and treats an empty body as no notices", async () => {
    const { fn, calls } = fakeFetch([{ body: { data: null } }]);
    const client = createLivingLearnerClient({ baseUrl: "http://coffee.localhost/", token: "tok", fetch: fn });
    expect(await client.notices(5)).toEqual([]);
    expect(calls[0]!.url).toBe("http://coffee.localhost/api/living-course/courses/5/notices");
    expect((calls[0]!.init.headers as Record<string, string>).Authorization).toBe("Bearer tok");
  });

  it("dismisses a notice and returns its status (a re-attempt notice stays open)", async () => {
    const { fn, calls } = fakeFetch([{ body: { data: { id: 7, status: "dismissed" } } }, { body: { data: { id: 8, status: "open" } } }]);
    const client = createLivingLearnerClient({ baseUrl: "/bff", fetch: fn });
    expect(await client.dismiss(7)).toEqual({ id: 7, status: "dismissed" });
    expect((await client.dismiss(8)).status).toBe("open");
    expect(calls[0]!.url).toBe("/bff/api/living-course/notices/7/dismiss");
    expect(calls[0]!.init.method).toBe("POST");
  });

  it("returns the topics of the freshness marker, empty when the course does not show it", async () => {
    const { fn, calls } = fakeFetch([{ body: { data: { topics: [{ topicId: 3, since: "2026-10-01T00:00:00+00:00" }] } } }, { body: { data: { topics: [] } } }]);
    const client = createLivingLearnerClient({ baseUrl: "/bff", fetch: fn });
    expect(await client.freshness(5)).toEqual([{ topicId: 3, since: "2026-10-01T00:00:00+00:00" }]);
    expect(await client.freshness(5)).toEqual([]);
    expect(calls[0]!.url).toBe("/bff/api/living-course/courses/5/freshness");
  });

  it("throws ApiError with the API message and status 0 when unreachable", async () => {
    const { fn } = fakeFetch([{ status: 404, body: { message: "Notice not found." } }]);
    const client = createLivingLearnerClient({ baseUrl: "/bff", fetch: fn });
    await expect(client.dismiss(9)).rejects.toMatchObject({ status: 404, message: "Notice not found." });
    const down = createLivingLearnerClient({ baseUrl: "/bff", fetch: (async () => Promise.reject(new Error("offline"))) as unknown as typeof fetch });
    await expect(down.notices(1)).rejects.toBeInstanceOf(ApiError);
    await expect(down.notices(1)).rejects.toMatchObject({ status: 0 });
  });
});
