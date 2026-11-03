/**
 * Course versions (`/api/admin/course-versions`, ADR 0192): the releases of a course, that is the versions of it that
 * learners actually got, newest first; every version of a branch; and the semantic diff of any two of them. Keyed by the
 * LMS course id and available with AI switched off. REST calls return
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
  /** Elements that moved to another parent or place (a release written before moves were counted has none). */
  moved?: number;
  /** Lessons among them. */
  lessonsMoved?: number;
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
  /** The variant this release belongs to; null for the base course. */
  variantId?: string | null;
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

/** A blueprint version: what was proposed, approved, rejected or undone, with the release that made it live (if any). */
export type VersionStatus = "proposed" | "approved" | "rejected" | "superseded";

export interface CourseVersion {
  id: string;
  /** The version number of the session (shared by the base and its variants). */
  number: number;
  /** outline, content, patch, author, restore, update or import. */
  kind: string;
  /** ai, author or restore. */
  origin: string;
  status: VersionStatus;
  reason: string | null;
  /** The variant branch the version belongs to; null for the base. */
  variantId: string | null;
  /** The latest release that applied this version, when learners got it. */
  releaseId: string | null;
  releaseNumber: number | null;
  createdAt: string | null;
  decidedAt: string | null;
}

/** A course made for one audience (a level, a role or both) from the same sources; its own LMS course after the first apply. */
export interface CourseVariantRef {
  id: string;
  key: string;
  label: string;
  level: string | null;
  role: string | null;
  status: "draft" | "approved" | "retired";
  courseId: number | null;
}

export interface CourseVersions {
  sessionId: string;
  /** The branch the course id names: null for the base course. */
  variantId: string | null;
  courseId: number;
  baseCourseId: number | null;
  versions: CourseVersion[];
  variants: CourseVariantRef[];
  total: number;
  page: number;
  perPage: number;
}

/** `added`, `removed` and `changed` come from the element diff; `moved` and `reordered` mark an element in both. */
export type CompareChangeKind = "added" | "removed" | "changed" | "moved" | "reordered";
export type ElementType = "course" | "objective" | "module" | "lesson" | "block" | "quiz" | "question" | "option" | "interaction" | string;

export interface ChangeField {
  field: string;
  /** English name of the field (the studio translates by `field`). */
  label: string;
  before: string | number | boolean | null;
  after: string | number | boolean | null;
  /** Set when the field is over 20 000 characters: `before` and `after` are null and the changed lines come in `lineDiff`. */
  truncated?: boolean;
  lineDiff?: { removed: string[]; added: string[]; omitted: number };
}

export interface ChangeCitations {
  added: string[];
  removed: string[];
  /** A citation whose fragment became another fragment in a newer source revision. */
  repointed: Array<{ from: string; to: string }>;
}

export interface ElementRef {
  id: string;
  type: ElementType | null;
  /** English label such as "Lesson 2.1" or "Lesson 2.1 › block 3". */
  label: string;
  title: string | null;
}

export interface ElementChange {
  id: string;
  type: ElementType;
  label: string;
  /** `added`, `removed` or `changed`; moves are in `moved` and `reordered`. A pure move has kind `moved` or `reordered`. */
  kind: CompareChangeKind;
  /** Ids of the parents, outermost first. */
  ancestors: string[];
  /** The parents with their type, label and title (absent in a payload cached before this field existed). */
  path?: ElementRef[];
  title?: string | null;
  fields: ChangeField[];
  moved: { from: { id: string | null; label: string | null }; to: { id: string | null; label: string | null } } | null;
  reordered: { parentId: string | null; from: number; to: number } | null;
  citations: ChangeCitations | null;
  /** The change in one English sentence (CLI and MCP read it; the studio writes its own). */
  description: string;
}

export interface QuizChange {
  questionId: string;
  label: string;
  lessonId: string | null;
  stem: { before: string; after: string } | null;
  type: { before: string | null; after: string | null } | null;
  explanation: boolean;
  options: {
    added: Array<{ id: string; text: string; correct: boolean }>;
    removed: Array<{ id: string; text: string; correct: boolean }>;
    reworded: Array<{ id: string; before: string; after: string }>;
    keyChanged: Array<{ id: string; text: string; before: boolean; after: boolean }>;
  };
  answerKeyChanged: boolean;
  wording: boolean;
  correctBefore: string[];
  correctAfter: string[];
  /** English sentence about learners who already answered; null when the key did not change. */
  consequence: string | null;
}

export interface KindCounts {
  added: number;
  changed: number;
  removed: number;
  moved: number;
  reordered: number;
}

export interface CompareLearnerImpact {
  lessonsKeepingCompletion: number;
  lessonsWithUpdatedNotice: number;
  lessonsChangedSilently: number;
  lessonsRemoved: number;
  lessonsAdded: number;
  reattemptNotices: number;
}

export interface CompareSummary {
  first: boolean;
  counts: KindCounts;
  types: Record<string, KindCounts>;
  citations: { added: number; removed: number; repointed: number };
  quiz: { answerKeysChanged: number; reworded: number; questionsAdded: number; questionsRemoved: number };
  learnerImpact: CompareLearnerImpact;
  lines: string[];
  /** The summary in one English sentence. */
  text: string;
}

/** A passage behind a citation that changed, as it was in the source revision of its side. */
export interface CitationPassage {
  label: string;
  text: string;
  sourceRevision: { id: string; number: number | null } | null;
}

export interface CompareSide {
  versionId: string;
  versionNumber: number;
  kind: string;
  status: VersionStatus;
  variantId: string | null;
  releaseId: string | null;
  releaseNumber: number | null;
  releasedAt: string | null;
  createdAt: string | null;
}

/** The semantic diff of two versions of one session (`diffVersion` 1). */
export interface CourseCompare {
  diffVersion: number;
  courseId: number;
  sessionId: string;
  from: CompareSide;
  to: CompareSide;
  summary: CompareSummary;
  changes: ElementChange[];
  quiz: QuizChange[];
  /** Fragment id to passage, for every citation that is added, removed or repointed. */
  fragments: Record<string, CitationPassage>;
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
    /**
     * Every version of the course's branch, newest first (proposals, rejected and undone versions too). `branch: "all"` lists the
     * versions of every branch of the session, so a variant can be picked against the base. 404 when the course was not made with the builder.
     */
    versions: (courseId: number, query?: { branch?: "own" | "all"; page?: number; perPage?: number }) => {
      const search = new URLSearchParams();
      if (query?.branch === "all") search.set("branch", "all");
      if (query?.page) search.set("page", String(query.page));
      if (query?.perPage) search.set("perPage", String(query.perPage));
      const text = search.toString();
      return get<CourseVersions>(`/courses/${courseId}/versions${text ? `?${text}` : ""}`);
    },
    /**
     * The semantic diff of two versions or releases of one session (ids of either kind). The course id is the base course or a
     * variant course. 422 when a side is missing, 404 for an unknown id or a pair from two sessions.
     */
    compare: (courseId: number, from: string, to: string) => get<CourseCompare>(`/courses/${courseId}/compare?${new URLSearchParams({ from, to }).toString()}`),
    /** One release with the blueprint document of its version. */
    release: (id: string) => get<ReleaseDetail>(`/releases/${encodeURIComponent(id)}`),
  };
}
