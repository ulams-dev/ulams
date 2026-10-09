import { ApiError, createClient, type Course, type Profile, type Tenant } from "@ulams/sdk";
import type { CourseLinks } from "./view-model.ts";

/**
 * Author preview of a course that learners cannot open yet (a draft from the Course Builder).
 *
 * The preview pages (`/preview/courses/:id` and `/preview/courses/:id/:topicId`) fetch the course
 * with the studio author's token, never with the learner (demo student) session, and only for an
 * author who may edit the course. Nothing here goes through the public in-memory cache, and
 * progress is never tracked.
 */
export const PREVIEW_PREFIX = "/preview";

export const previewCourseHref = (courseId: number): string => `${PREVIEW_PREFIX}/courses/${courseId}`;
export const previewTopicHref = (courseId: number, topicId: number): string => `${previewCourseHref(courseId)}/${topicId}`;

export const PREVIEW_LINKS: CourseLinks = { learn: previewCourseHref, topic: previewTopicHref };

export const isPreviewPath = (pathname: string): boolean => pathname === PREVIEW_PREFIX || pathname.startsWith(`${PREVIEW_PREFIX}/`);

/** Headers of every preview response: private, never stored, never indexed. */
export const PREVIEW_HEADERS: Readonly<Record<string, string>> = {
  "Cache-Control": "private, no-store",
  "X-Robots-Tag": "noindex, nofollow, noarchive",
};

/** Where the "back to the studio" link of the banner goes: the studio success page of the session. */
export const studioHref = (sessionId?: string | null): string => (sessionId && /^[0-9a-z]{26}$/.test(sessionId) ? `/studio/s/${sessionId}/done` : "/studio");

type Editor = Pick<Profile, "id" | "roles" | "permissions">;
type Authored = Pick<Course, "authors"> & { author_id?: number | null };

/**
 * Mirrors the API's course `update` policy (api/packages/courses CoursesPolicy): an admin, a user
 * with `course_update`, or an author of the course with `course_update_authored`. The API checks
 * it again when it answers the program request; this keeps the preview from showing a course to
 * somebody who may only read it.
 */
export function canEditCourse(user: Editor, course: Authored): boolean {
  if (user.roles?.includes("admin")) return true;
  const permissions = user.permissions ?? [];
  if (permissions.includes("course_update")) return true;
  if (!permissions.includes("course_update_authored")) return false;
  return course.author_id === user.id || (course.authors ?? []).some((a) => a.id === user.id);
}

export const isPublished = (course: Pick<Course, "status">): boolean => course.status === "published";

export interface PreviewAccess {
  course: Course;
  user: Profile;
}

type PreviewApi = Pick<ReturnType<typeof createClient>, "auth" | "courses">;

/**
 * The course and the author for the preview, or null when the visitor may not see it: no token, an
 * expired token, a token of another tenant (the tenant API rejects it), a course that does not
 * exist there, or an author without the right to edit it. All of these answer as a plain 404.
 */
export async function loadPreview(api: PreviewApi, courseId: number): Promise<PreviewAccess | null> {
  try {
    const user = await api.auth.me();
    const course = await api.courses.program(courseId);
    return course.id === courseId && canEditCourse(user, course) ? { course, user } : null;
  } catch (error) {
    if (error instanceof ApiError && [401, 403, 404].includes(error.status)) return null;
    throw error;
  }
}

/**
 * `loadPreview` against the tenant API with the author's token: a fresh request every time. It does
 * not use the public stale-while-revalidate cache (`lib/cache.ts`), whose entries are shared by all
 * visitors; a preview must never be served to, or taken from, anybody else.
 */
export function previewFor(tenant: Tenant, token: string | null | undefined, courseId: number): Promise<PreviewAccess | null> {
  if (!token) return Promise.resolve(null);
  return loadPreview(createClient({ baseUrl: tenant.apiUrl, token, timeoutMs: 10_000, timezone: "UTC" }), courseId);
}

/** The studio session that built the course, for the banner's link back; null when it cannot be found. */
export async function sessionForCourse(list: () => Promise<Array<{ id: string; courseId: number | null }>>, courseId: number): Promise<string | null> {
  try {
    return (await list()).find((s) => s.courseId === courseId)?.id ?? null;
  } catch {
    return null;
  }
}
