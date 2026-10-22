/**
 * Course versions (`/api/admin/course-versions`, ADR 0192): the releases of a course, that is the versions of it that
 * learners actually got, newest first. Keyed by the LMS course id and available with AI switched off. REST calls return
 * the unwrapped `data`. In the browser the reference app points `baseUrl` at its BFF (`/studio/api`) with
 * `prefix: "/course-versions"`, which adds the author's token.
 */
import { ApiError, type ClientOptions } from "./client.ts";

/** What started a release (`trigger`): generation, author edit, chat edit, source update, insights improvement, course-as-code push, undo/restore, variant rebase. */
export type ReleaseTrigger = "generate" | "author" | "chat" | "update" | "insights" | "import" | "restore" | "rebase";

export interface ReleaseChangeCounts {
  added: number;
  changed: number;
  removed: number;
}

/** What changed since the previous release, counted from the element diff (the first release counts everything as added). */
export interface ReleaseSummary {
  first: boolean;
  counts: ReleaseChangeCounts;
  lessons: ReleaseChangeCounts;
  questions: ReleaseChangeCounts;
  /** Elements whose citations changed. */
  citationsChanged: number;
  /** Quiz questions whose correct answer changed. */
  answerKeysChanged: number;
}

export interface CourseRelease {
  id: string;
  /** Sequential per course, 1 is the first apply. */
  number: number;
  trigger: ReleaseTrigger;
  versionId: string;
  /** The number of the applied blueprint version (releases skip proposals and undone versions). */
  versionNumber: number | null;
  /** Why the version was made (the author's instruction, or "Restored version 3"). */
  reason: string | null;
  previousReleaseId: string | null;
  courseId: number;
  approvedBy: { id: number; name: string } | null;
  /** `{sourceId: revisionId}` the release was written from. */
  sourceRevisions: Record<string, string> | null;
  summary: ReleaseSummary;
  /** ISO 8601 time the apply finished. */
  releasedAt: string;
}

export interface CourseReleases {
  sessionId: string;
  courseId: number;
  releases: CourseRelease[];
  total: number;
  page: number;
  perPage: number;
}

export interface ReleaseDetail extends CourseRelease {
  /** The Blueprint v3 document of the released version. */
  document: Record<string, unknown> | null;
  fragments: Record<string, unknown>;
}

const PREFIX = "/api/admin/course-versions";

export type CourseVersionsClient = ReturnType<typeof createCourseVersionsClient>;

export function createCourseVersionsClient(options: ClientOptions & { prefix?: string }) {
  const base = options.baseUrl.replace(/\/+$/, "") + (options.prefix ?? PREFIX);
  const doFetch = options.fetch ?? ((input: RequestInfo | URL, init?: RequestInit) => fetch(input, init));

  async function get<T>(path: string): Promise<T> {
    const headers: Record<string, string> = { Accept: "application/json", ...options.headers, ...(options.token ? { Authorization: `Bearer ${options.token}` } : {}) };
    let response: Response;
    try {
      response = await doFetch(`${base}${path}`, { method: "GET", headers, signal: AbortSignal.timeout(options.timeoutMs ?? 30_000) });
    } catch (error) {
      throw new ApiError(0, path, null, `API unreachable on ${path}: ${(error as Error).message}`);
    }
    const text = await response.text();
    let json: { data?: T; message?: string } | null;
    try {
      json = text ? JSON.parse(text) : null;
    } catch {
      json = { message: text.slice(0, 200) };
    }
    if (!response.ok) throw new ApiError(response.status, path, json, json?.message ?? `API ${response.status} on ${path}`);
    return (json?.data ?? null) as T;
  }

  return {
    base,
    /** Releases of the course, newest first. 404 when the course was not made with the builder, 403 for another author's course. */
    releases: (courseId: number, page?: { page?: number; perPage?: number }) => {
      const search = new URLSearchParams();
      if (page?.page) search.set("page", String(page.page));
      if (page?.perPage) search.set("perPage", String(page.perPage));
      const text = search.toString();
      return get<CourseReleases>(`/courses/${courseId}/releases${text ? `?${text}` : ""}`);
    },
    /** One release with the blueprint document of its version. */
    release: (id: string) => get<ReleaseDetail>(`/releases/${encodeURIComponent(id)}`),
  };
}
