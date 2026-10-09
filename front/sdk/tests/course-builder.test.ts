import { describe, expect, it, vi } from "vitest";
import { ApiError } from "../src/client.ts";
import { createCourseBuilderClient } from "../src/course-builder.ts";

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

describe("course builder client", () => {
  it("calls the API prefix with the token and unwraps data", async () => {
    const { fn, calls } = fakeFetch([{ body: { success: true, data: [{ id: "s1", status: "draft" }] } }]);
    const cb = createCourseBuilderClient({ baseUrl: "http://coffee.localhost/", token: "tok", fetch: fn });
    const list = await cb.sessions.list();
    expect(list[0]?.id).toBe("s1");
    expect(calls[0]!.url).toBe("http://coffee.localhost/api/admin/course-builder/sessions");
    expect((calls[0]!.init.headers as Record<string, string>).Authorization).toBe("Bearer tok");
  });

  it("works behind the studio BFF with an empty prefix and no token", async () => {
    const { fn, calls } = fakeFetch([{ status: 202, body: { data: { runId: "r1", accepted: true, message: null } } }]);
    const cb = createCourseBuilderClient({ baseUrl: "/studio/api", prefix: "", fetch: fn });
    await cb.runs.action("s1", { name: "answer", surfaceId: "interview", context: { key: "tone", value: "friendly" } });
    expect(calls[0]!.url).toBe("/studio/api/sessions/s1/runs");
    expect((calls[0]!.init.headers as Record<string, string>).Authorization).toBeUndefined();
    const body = JSON.parse(String(calls[0]!.init.body));
    expect(body.threadId).toBe("s1");
    expect(body.messages).toEqual([]);
    expect(body.forwardedProps.action).toEqual({ name: "answer", surfaceId: "interview", context: { key: "tone", value: "friendly" } });
  });

  it("sends typed text scoped to the selected element", async () => {
    const { fn, calls } = fakeFetch([{ status: 202, body: { data: { runId: "r2", accepted: true, message: null } } }]);
    const cb = createCourseBuilderClient({ baseUrl: "/studio/api", prefix: "", fetch: fn });
    await cb.runs.message("s1", "Make the distractors less obvious", "01q");
    const body = JSON.parse(String(calls[0]!.init.body));
    expect(body.messages[0]).toMatchObject({ role: "user", content: "Make the distractors less obvious" });
    expect(body.forwardedProps).toEqual({ selection: { elementId: "01q" } });
  });

  it("uploads multipart without a JSON content type", async () => {
    const { fn, calls } = fakeFetch([{ status: 202, body: { data: { source: { id: "src" }, runId: "r" } } }]);
    const cb = createCourseBuilderClient({ baseUrl: "/studio/api", prefix: "", fetch: fn });
    await cb.sources.upload("s1", new Blob(["# Hi"], { type: "text/markdown" }), "hi.md");
    expect(calls[0]!.init.body).toBeInstanceOf(FormData);
    expect((calls[0]!.init.headers as Record<string, string>)["Content-Type"]).toBeUndefined();
    expect((calls[0]!.init.body as FormData).get("file")).toBeInstanceOf(Blob);
  });

  it("raises ApiError with the server message", async () => {
    const { fn } = fakeFetch([{ status: 503, body: { success: false, message: "AI disabled", code: "ai_disabled" } }]);
    const cb = createCourseBuilderClient({ baseUrl: "/studio/api", prefix: "", fetch: fn });
    await expect(cb.sessions.create()).rejects.toMatchObject({ status: 503, message: "AI disabled" });
    await expect(cb.sessions.create()).rejects.toBeInstanceOf(ApiError);
  });

  it("encodes ids in paths", async () => {
    const { fn, calls } = fakeFetch([{ body: { data: {} } }]);
    const cb = createCourseBuilderClient({ baseUrl: "/b", prefix: "", fetch: fn });
    await cb.versions.diff("v 1", "v/2");
    expect(calls[0]!.url).toBe("/b/versions/v%201/diff?against=v%2F2");
  });
});
