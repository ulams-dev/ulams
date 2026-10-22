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

  it("lists every version of the branch, or of every branch, with paging", async () => {
    const payload = { sessionId: "s1", variantId: null, courseId: 12, baseCourseId: 12, versions: [], variants: [], total: 0, page: 1, perPage: 100 };
    const { fn, calls } = fakeFetch([{ body: { data: payload } }, { body: { data: payload } }, { body: { data: payload } }]);
    const client = createCourseVersionsClient({ baseUrl: "/studio/api", prefix: "/course-versions", fetch: fn });

    expect(await client.versions(12)).toEqual(payload);
    await client.versions(12, { branch: "all", perPage: 200, page: 2 });
    await client.versions(12, { branch: "own" });
    expect(calls[0]!.url).toBe("/studio/api/course-versions/courses/12/versions");
    expect(calls[1]!.url).toBe("/studio/api/course-versions/courses/12/versions?branch=all&page=2&perPage=200");
    expect(calls[2]!.url).toBe("/studio/api/course-versions/courses/12/versions");
  });

  it("compares two versions or releases and encodes both ids in the query", async () => {
    const payload = { diffVersion: 1, courseId: 12, sessionId: "s1", changes: [], quiz: [], fragments: {} };
    const { fn, calls } = fakeFetch([{ body: { data: payload } }]);
    const client = createCourseVersionsClient({ baseUrl: "/studio/api", prefix: "/course-versions", fetch: fn });

    expect(await client.compare(12, "01aaa", "01b&b")).toEqual(payload);
    expect(calls[0]!.url).toBe("/studio/api/course-versions/courses/12/compare?from=01aaa&to=01b%26b");
    expect(calls[0]!.init.method).toBe("GET");
  });

  it("surfaces a refused pair as an ApiError", async () => {
    const { fn } = fakeFetch([{ status: 404, body: { message: "That version or release is not part of this course." } }]);
    const client = createCourseVersionsClient({ baseUrl: "/studio/api", prefix: "/course-versions", fetch: fn });

    await expect(client.compare(12, "a", "b")).rejects.toMatchObject({ status: 404, message: "That version or release is not part of this course." });
  });
});
