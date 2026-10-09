import { describe, expect, it, vi } from "vitest";
import { ApiError, buildPath, createClient } from "../src/client.ts";
import { demoStudentSession } from "../src/session.ts";

type Call = { url: string; init: RequestInit };

function fakeFetch(responses: Array<{ status?: number; body: unknown }>) {
  const calls: Call[] = [];
  const fn = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
    calls.push({ url: String(url), init: init ?? {} });
    const next = responses.shift() ?? { status: 500, body: { message: "no more responses" } };
    return new Response(JSON.stringify(next.body), { status: next.status ?? 200 });
  });
  return { fn: fn as unknown as typeof fetch, calls };
}

describe("buildPath", () => {
  it("fills and encodes placeholders", () => {
    expect(buildPath("/api/courses/{id}/preview/{topic_id}", { id: 1, topic_id: "a b" })).toBe(
      "/api/courses/1/preview/a%20b"
    );
  });
  it("throws on a missing parameter", () => {
    expect(() => buildPath("/api/courses/{id}", {})).toThrow(/id/);
  });
});

describe("createClient", () => {
  it("unwraps the envelope and sends auth, accept and timezone headers", async () => {
    const { fn, calls } = fakeFetch([{ body: { success: true, data: { id: 1, title: "A" } } }]);
    const client = createClient({ baseUrl: "http://coffee.localhost/", token: "t0k", fetch: fn, timezone: "Europe/Warsaw" });
    const course = await client.courses.get(1);
    expect(course.title).toBe("A");
    expect(calls[0]!.url).toBe("http://coffee.localhost/api/courses/1");
    const headers = calls[0]!.init.headers as Record<string, string>;
    expect(headers.Authorization).toBe("Bearer t0k");
    expect(headers.Accept).toBe("application/json");
    expect(headers["Current-timezone"]).toBe("Europe/Warsaw");
  });

  it("serialises query and JSON bodies", async () => {
    const { fn, calls } = fakeFetch([
      { body: { success: true, data: [{ id: 1 }], meta: { total: 1, per_page: 5, current_page: 1, last_page: 1 } } },
      { body: { success: true, data: [] } },
    ]);
    const client = createClient({ baseUrl: "/bff", fetch: fn });
    const list = await client.courses.list({ per_page: 5 });
    expect(list.meta?.total).toBe(1);
    expect(calls[0]!.url).toBe("/bff/api/courses?per_page=5");
    await client.progress.complete(3, 7);
    expect(calls[1]!.init.method).toBe("PATCH");
    expect(JSON.parse(String(calls[1]!.init.body))).toEqual({ progress: [{ topic_id: 7, status: 1 }] });
  });

  it("builds quiz answer bodies", async () => {
    const { fn, calls } = fakeFetch([{ body: { success: true, data: { id: 9 } } }]);
    await createClient({ baseUrl: "http://x", fetch: fn }).quiz.answer(9, 4, { numeric: 288 });
    expect(JSON.parse(String(calls[0]!.init.body))).toEqual({
      topic_gift_quiz_attempt_id: 9,
      topic_gift_question_id: 4,
      answer: { numeric: 288 },
    });
  });

  it("throws ApiError with status and message", async () => {
    const { fn } = fakeFetch([{ status: 403, body: { message: "This action is unauthorized." } }]);
    const error = await createClient({ baseUrl: "http://x", fetch: fn }).courses.program(1).catch((e: unknown) => e);
    expect(error).toBeInstanceOf(ApiError);
    expect((error as ApiError).status).toBe(403);
    expect((error as ApiError).message).toContain("unauthorized");
  });

  it("reports an unreachable API as status 0", async () => {
    const fn = (async () => {
      throw new TypeError("fetch failed");
    }) as unknown as typeof fetch;
    const error = await createClient({ baseUrl: "http://x", fetch: fn }).settings.public().catch((e: unknown) => e);
    expect((error as ApiError).status).toBe(0);
  });

  it("withToken returns a client that sends the new token", async () => {
    const { fn, calls } = fakeFetch([{ body: { success: true, data: { id: 1 } } }]);
    await createClient({ baseUrl: "http://x", fetch: fn }).withToken("new").auth.me();
    expect((calls[0]!.init.headers as Record<string, string>).Authorization).toBe("Bearer new");
  });
});

describe("demoStudentSession", () => {
  it("uses demo login when the tenant has demo mode", async () => {
    const { fn, calls } = fakeFetch([{ body: { success: true, data: { token: "demo" } } }]);
    const session = await demoStudentSession(createClient({ baseUrl: "http://x", fetch: fn }), {
      fallback: { email: "s@x", password: "p" },
    });
    expect(session).toMatchObject({ token: "demo", via: "demo" });
    expect(calls).toHaveLength(1);
    expect(JSON.parse(String(calls[0]!.init.body))).toEqual({ role: "student" });
  });

  it("falls back to password login when demo mode is off (404)", async () => {
    const { fn, calls } = fakeFetch([
      { status: 404, body: { message: "Not found" } },
      { body: { success: true, data: { token: "pw" } } },
    ]);
    const session = await demoStudentSession(createClient({ baseUrl: "http://x", fetch: fn }), {
      fallback: { email: "s@x", password: "p" },
    });
    expect(session).toMatchObject({ token: "pw", via: "password" });
    expect(calls[1]!.url).toBe("http://x/api/auth/login");
  });

  it("rethrows other errors and when there is no fallback", async () => {
    const { fn } = fakeFetch([{ status: 404, body: {} }]);
    await expect(demoStudentSession(createClient({ baseUrl: "http://x", fetch: fn }))).rejects.toBeInstanceOf(ApiError);
    const { fn: fn2 } = fakeFetch([{ status: 500, body: {} }]);
    await expect(
      demoStudentSession(createClient({ baseUrl: "http://x", fetch: fn2 }), { fallback: { email: "a", password: "b" } })
    ).rejects.toMatchObject({ status: 500 });
  });
});

describe("multipart and downloads", () => {
  it("sends a FormData body without a JSON content type", async () => {
    const { fn, calls } = fakeFetch([{ body: { success: true, data: { id: 5 } } }]);
    const api = createClient({ baseUrl: "http://t.test", token: "tok", fetch: fn });
    const form = new FormData();
    form.set("title", "A");
    form.set("file", new Blob(["x"]), "a.txt");
    const out = await api.request<{ id: number }>("POST", "/api/admin/file/upload", { form });
    expect(out.id).toBe(5);
    expect(calls[0]?.init.body).toBe(form);
    expect((calls[0]?.init.headers as Record<string, string>)["Content-Type"]).toBeUndefined();
  });

  it("downloads bytes with content type and file name", async () => {
    const fn = (async () =>
      new Response(new Uint8Array([1, 2, 3]), {
        headers: { "content-type": "application/zip", "content-disposition": 'attachment; filename="course-1.zip"' },
      })) as unknown as typeof fetch;
    const api = createClient({ baseUrl: "http://t.test", token: "tok", fetch: fn });
    const file = await api.download("GET", "/api/admin/courses/{id}/export", { params: { id: 1 } });
    expect(Array.from(file.data)).toEqual([1, 2, 3]);
    expect(file.contentType).toBe("application/zip");
    expect(file.filename).toBe("course-1.zip");
  });

  it("download throws ApiError on a failure", async () => {
    const { fn } = fakeFetch([{ status: 403, body: { message: "nope" } }]);
    const api = createClient({ baseUrl: "http://t.test", token: "tok", fetch: fn });
    await expect(api.download("GET", "/api/admin/courses/{id}/export", { params: { id: 1 } })).rejects.toMatchObject({ status: 403 });
  });
});
