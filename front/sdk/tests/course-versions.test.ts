import { describe, expect, it, vi } from "vitest";
import { ApiError } from "../src/client.ts";
import { createCourseVersionsClient } from "../src/course-versions.ts";

function fakeFetch(responses: Array<{ status?: number; body: unknown }>) {
  const calls: Array<{ url: string; init: RequestInit }> = [];
  const fn = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
    calls.push({ url: String(url), init: init ?? {} });
    const next = responses.shift() ?? { status: 500, body: { message: "no more" } };
    return new Response(JSON.stringify(next.body), { status: next.status ?? 200 });
  });
  return { fn: fn as unknown as typeof fetch, calls };
}

describe("course versions client", () => {
  it("lists the releases of a course through the studio BFF, with paging", async () => {
    const payload = { sessionId: "s1", courseId: 12, releases: [], total: 0, page: 2, perPage: 10 };
    const { fn, calls } = fakeFetch([{ body: { data: payload } }, { body: { data: payload } }]);
    const client = createCourseVersionsClient({ baseUrl: "/studio/api/", prefix: "/course-versions", fetch: fn });

    expect(await client.releases(12, { page: 2, perPage: 10 })).toEqual(payload);
    await client.releases(12);
    expect(calls[0]!.url).toBe("/studio/api/course-versions/courses/12/releases?page=2&perPage=10");
    expect(calls[1]!.url).toBe("/studio/api/course-versions/courses/12/releases");
    expect(calls.every((c) => c.init.method === "GET")).toBe(true);
  });

  it("defaults to the admin API path, sends the token and reads one release", async () => {
    const { fn, calls } = fakeFetch([{ body: { data: { id: "r1", number: 1, document: { modules: [] } } } }]);
    const client = createCourseVersionsClient({ baseUrl: "http://coffee.localhost", token: "tok", fetch: fn });

    expect((await client.release("r1")).document).toEqual({ modules: [] });
    expect(calls[0]!.url).toBe("http://coffee.localhost/api/admin/course-versions/releases/r1");
    expect((calls[0]!.init.headers as Record<string, string>).Authorization).toBe("Bearer tok");
  });

  it("turns a refusal into an ApiError with its status and message", async () => {
    const { fn } = fakeFetch([{ status: 404, body: { message: "Adopt the course." } }]);
    const client = createCourseVersionsClient({ baseUrl: "/studio/api", prefix: "/course-versions", fetch: fn });

    await expect(client.releases(3)).rejects.toMatchObject({ status: 404, message: "Adopt the course." });
    await expect(client.releases(3)).rejects.toBeInstanceOf(ApiError);
  });

  it("reports an unreachable API as status 0", async () => {
    const fn = vi.fn(async () => {
      throw new Error("offline");
    }) as unknown as typeof fetch;
    const client = createCourseVersionsClient({ baseUrl: "/studio/api", fetch: fn });

    await expect(client.releases(3)).rejects.toMatchObject({ status: 0 });
  });
});
