import { afterEach, describe, expect, it, vi } from "vitest";
import { ApiError } from "@ulams/sdk";
import { cache } from "../../src/lib/cache.ts";
import {
  PREVIEW_HEADERS,
  canEditCourse,
  isPreviewPath,
  isPublished,
  loadPreview,
  previewCourseHref,
  previewFor,
  previewTopicHref,
  sessionForCourse,
  studioHref,
} from "../../src/lib/preview.ts";

const admin = { id: 1, roles: ["admin"], permissions: [] };
const tutor = { id: 7, roles: ["tutor"], permissions: ["course_update_authored", "course_read_authored"] };
const student = { id: 9, roles: ["student"], permissions: [] };
const course = { id: 3, title: "Draft", status: "draft", author_id: 7, authors: [{ id: 7 }] };

describe("canEditCourse", () => {
  it("lets admins and holders of course_update edit any course", () => {
    expect(canEditCourse(admin, course as never)).toBe(true);
    expect(canEditCourse({ id: 2, roles: [], permissions: ["course_update"] }, course as never)).toBe(true);
  });
  it("lets a tutor edit only the courses they author", () => {
    expect(canEditCourse(tutor, course as never)).toBe(true);
    expect(canEditCourse({ ...tutor, id: 8 }, course as never)).toBe(false);
  });
  it("refuses students and users without permissions", () => {
    expect(canEditCourse(student, course as never)).toBe(false);
    expect(canEditCourse({ id: 7, roles: ["tutor"] }, course as never)).toBe(false);
  });
});

const fakeApi = (me: unknown, program: unknown) =>
  ({
    auth: { me: vi.fn(async () => { if (me instanceof Error) throw me; return me; }) },
    courses: { program: vi.fn(async () => { if (program instanceof Error) throw program; return program; }) },
  }) as never;

describe("loadPreview", () => {
  it("returns the course for an author who may edit it", async () => {
    expect((await loadPreview(fakeApi(tutor, course), 3))?.course.id).toBe(3);
  });
  it("answers null for a reader without edit rights", async () => {
    expect(await loadPreview(fakeApi(student, course), 3)).toBeNull();
  });
  it("answers null when the API refuses or does not know the course (other tenant, expired token)", async () => {
    for (const status of [401, 403, 404]) {
      expect(await loadPreview(fakeApi(admin, new ApiError(status, "/x", null)), 3)).toBeNull();
    }
    expect(await loadPreview(fakeApi(new ApiError(401, "/x", null), course), 3)).toBeNull();
  });
  it("does not accept another course than the one asked for", async () => {
    expect(await loadPreview(fakeApi(admin, { ...course, id: 4 }), 3)).toBeNull();
  });
  it("lets server errors through", async () => {
    await expect(loadPreview(fakeApi(admin, new ApiError(500, "/x", null)), 3)).rejects.toThrow();
  });
});

describe("preview and the shared cache", () => {
  afterEach(() => vi.unstubAllGlobals());

  it("asks the API every time and never stores anything in the public cache", async () => {
    const calls: string[] = [];
    vi.stubGlobal("fetch", async (url: string, init: RequestInit) => {
      calls.push(`${url} ${(init.headers as Record<string, string>).Authorization}`);
      const data = String(url).endsWith("/api/profile/me") ? admin : course;
      return new Response(JSON.stringify({ success: true, data }), { status: 200 });
    });
    const tenant = { slug: "coffee", apiUrl: "http://coffee.localhost", adminUrl: "" };
    const before = cache.size;
    await previewFor(tenant, "author-token", 3);
    await previewFor(tenant, "author-token", 3);
    expect(calls).toHaveLength(4);
    expect(calls.every((c) => c.endsWith("Bearer author-token"))).toBe(true);
    expect(cache.size).toBe(before);
  });

  it("makes no request without a token", async () => {
    const fetchSpy = vi.fn();
    vi.stubGlobal("fetch", fetchSpy);
    expect(await previewFor({ slug: "coffee", apiUrl: "http://coffee.localhost", adminUrl: "" }, null, 3)).toBeNull();
    expect(fetchSpy).not.toHaveBeenCalled();
  });
});

describe("preview routes and headers", () => {
  it("builds the routes", () => {
    expect(previewCourseHref(3)).toBe("/preview/courses/3");
    expect(previewTopicHref(3, 35)).toBe("/preview/courses/3/35");
  });
  it("recognises preview paths only", () => {
    expect(isPreviewPath("/preview/courses/3")).toBe(true);
    expect(isPreviewPath("/preview")).toBe(true);
    expect(isPreviewPath("/previews")).toBe(false);
    expect(isPreviewPath("/courses/3")).toBe(false);
  });
  it("is private, not stored and not indexed", () => {
    expect(PREVIEW_HEADERS["Cache-Control"]).toBe("private, no-store");
    expect(PREVIEW_HEADERS["X-Robots-Tag"]).toContain("noindex");
  });
  it("tells published from unpublished", () => {
    expect(isPublished({ status: "published" })).toBe(true);
    expect(isPublished({ status: "draft" })).toBe(false);
  });
  it("links back to the studio", () => {
    expect(studioHref("01m4fj5zsgfxhzh3cantwevncw")).toBe("/studio/s/01m4fj5zsgfxhzh3cantwevncw/done");
    expect(studioHref("../x")).toBe("/studio");
    expect(studioHref(null)).toBe("/studio");
  });
  it("finds the studio session of a course", async () => {
    expect(await sessionForCourse(async () => [{ id: "a", courseId: 2 }, { id: "b", courseId: 3 }], 3)).toBe("b");
    expect(await sessionForCourse(async () => { throw new Error("x"); }, 3)).toBeNull();
  });
});
