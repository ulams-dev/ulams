/**
 * Policy lifecycle (`/api/admin/policies`, ADR 0140): a policy document turned into one cited course per department,
 * its versions and changes, and the owner dashboard; plus the learner's own view of a policy change
 * (`/api/policies/my-changes`). REST calls return the unwrapped `data`. In the browser the reference app points `baseUrl`
 * at its BFF (`/studio/api`) with `prefix: "/policies"`, which adds the author's token. Hand-written like the other
 * studio clients: the generated types in `generated/openapi.ts` are not touched.
 */
import { ApiError, type ClientOptions } from "./client.ts";

export type PolicyVersionStatus = "draft" | "in_review" | "applying" | "approved" | "rejected" | "superseded";
export type PolicyChangeClass = "initial" | "minor" | "major";
export type Seniority = "junior" | "mid" | "senior" | "mixed";
export type PriorExperience = "none" | "some" | "expert";
/** Where a department's generation stands, read from its Course Builder session. */
export type VariantStage = "not_started" | "waiting" | "draft" | "ingesting" | "interviewing" | "outlining" | "outline_review" | "generating" | "apply_review" | "applying" | "applied" | "failed";
export type RolloutStatus = "pending" | "no_change" | "ready" | "applying" | "applied" | "failed" | "rejected";

export interface PolicyVersionRow {
  id: string;
  number: number;
  status: PolicyVersionStatus;
  changeClass: PolicyChangeClass;
  summary: string | null;
  fileName: string;
  fileSha256: string;
  fileSize: number;
  submittedBy: number | null;
  approvedBy: number | null;
  approvedAt: string | null;
  effectiveAt: string | null;
  suggestedClass: string | null;
  classReason: string | null;
  rejectedBy: number | null;
  rejectReason: string | null;
  rolledOutAt: string | null;
  createdAt: string | null;
}

export interface PolicyVariantRow {
  id: string;
  department: string;
  groupId: number | null;
  brief: { totalMinutes?: number; seniority?: Seniority; priorExperience?: PriorExperience };
  stage: VariantStage;
  sessionId: string | null;
  courseId: number | null;
  pathId: number | null;
  ruleId: number | null;
  examCourseId: number | null;
  position: number;
}

export interface PolicySummary {
  id: string;
  title: string;
  ownerUserId: number;
  status: "active" | "archived";
  minorDueDays: number;
  majorDueDays: number;
  brief: { totalMinutes: number; lessonMinutes: number; language: string; tone: string };
  currentVersion: number | null;
  latestVersion: number | null;
  pendingReview: number | null;
  departments: number;
  createdAt: string | null;
}

export interface PolicyDetail extends PolicySummary {
  versions: PolicyVersionRow[];
  variants: PolicyVariantRow[];
  /** Present on the detail answer. */
  estimate?: { perVariant: Array<{ id: string; department: string; lessons: number; estimateMicroUsd: number }>; totalMicroUsd: number };
}

export interface NewPolicy {
  title: string;
  file: File;
  brief?: { totalMinutes?: number; lessonMinutes?: number };
  departments: Array<{ label: string; seniority?: Seniority; priorExperience?: PriorExperience; totalMinutes?: number }>;
}

export interface DepartmentFigures {
  id: string;
  department: string;
  groupId: number | null;
  courseId: number | null;
  stage: VariantStage;
  assigned: number;
  completed: number;
  pending: number;
  overdue: number;
  completionRate: number | null;
}

export interface DashboardPerson {
  assignmentId: number;
  user: { id: number; name: string; email: string | null } | null;
  department: string | null;
  variantId: string | null;
  courseId: number;
  courseVersion: number | null;
  exam: boolean;
  status: "assigned" | "overdue" | "completed" | "cancelled";
  source: string;
  assignedAt: string | null;
  dueAt: string | null;
  completedAt: string | null;
  policyVersion: number | null;
  certificate: { id: number; certificateId: string; status: "valid" | "expired" | "revoked"; expiresAt: string | null; verifyPath: string } | null;
}

export interface PolicyDashboard {
  policy: { id: string; title: string; currentVersion: number | null };
  departments: DepartmentFigures[];
  people: DashboardPerson[];
  total: number;
  perPage: number;
  currentPage: number;
  lastPage: number;
}

export interface RolloutRow {
  id: string;
  variantId: string;
  department: string | null;
  status: RolloutStatus;
  error: string | null;
  proposal: { id: string; sessionId: string; status: string; number: number } | null;
  undecided: number;
  rolledOutAt: string | null;
}

export interface PreflightRow {
  id: string;
  department: string;
  affected: boolean;
  certificatesToRevoke: number;
  peopleToReassign: number;
  assigns: "exam" | "course";
}

export interface VersionReview extends PolicyVersionRow {
  /** Only for a change (not the first version). */
  suggestion?: { class: "minor" | "major"; reasons: string[] };
  rollouts?: RolloutRow[];
  changes?: { counts: Record<string, number>; sections: Array<{ kind: string; label: string; fragmentId: string | null; magnitude: string }> };
  blockers: string[];
  preflight?: { changeClass: string; departments: PreflightRow[] } | null;
}

export interface MyPolicyChange {
  assignmentId: number;
  policy: { id: string; title: string };
  version: number;
  kind: "exam" | "course";
  summary: string | null;
  dueAt: string | null;
  status: string;
}

const PREFIX = "/api/admin/policies";

export type PoliciesClient = ReturnType<typeof createPoliciesClient>;

export function createPoliciesClient(options: ClientOptions & { prefix?: string }) {
  const base = options.baseUrl.replace(/\/+$/, "") + (options.prefix ?? PREFIX);
  const doFetch = options.fetch ?? ((input: RequestInfo | URL, init?: RequestInit) => fetch(input, init));

  async function call<T>(method: "GET" | "POST", path: string, body?: unknown | FormData): Promise<{ data: T; meta: Record<string, number> | null }> {
    const headers: Record<string, string> = { Accept: "application/json", ...options.headers, ...(options.token ? { Authorization: `Bearer ${options.token}` } : {}) };
    let payload: BodyInit | undefined;
    if (body instanceof FormData) payload = body;
    else if (body !== undefined) {
      headers["Content-Type"] = "application/json";
      payload = JSON.stringify(body);
    }
    let response: Response;
    try {
      response = await doFetch(`${base}${path}`, { method, headers, body: payload, signal: AbortSignal.timeout(options.timeoutMs ?? 60_000) });
    } catch (error) {
      throw new ApiError(0, path, null, `API unreachable on ${path}: ${(error as Error).message}`);
    }
    const text = await response.text();
    let json: { data?: T; meta?: Record<string, number>; message?: string } | null;
    try {
      json = text ? JSON.parse(text) : null;
    } catch {
      json = { message: text.slice(0, 200) };
    }
    if (!response.ok) throw new ApiError(response.status, path, json, json?.message ?? `API ${response.status} on ${path}`);
    return { data: (json?.data ?? null) as T, meta: json?.meta ?? null };
  }
  const get = async <T>(path: string): Promise<T> => (await call<T>("GET", path)).data;
  const post = async <T>(path: string, body?: unknown | FormData): Promise<T> => (await call<T>("POST", path, body)).data;
  const query = (q: Record<string, string | number | undefined | null>): string => {
    const search = new URLSearchParams();
    for (const [k, v] of Object.entries(q)) if (v !== undefined && v !== null && v !== "") search.set(k, String(v));
    const text = search.toString();
    return text ? `?${text}` : "";
  };

  return {
    base,
    /** The policies the author may see: all with `policy_manage`, their own with `policy_owner`. */
    list: () => get<PolicySummary[]>("/"),
    get: (id: string) => get<PolicyDetail>(`/${id}`),
    create: (input: NewPolicy) => {
      const form = new FormData();
      form.set("title", input.title);
      form.set("file", input.file);
      if (input.brief?.totalMinutes) form.set("brief[totalMinutes]", String(input.brief.totalMinutes));
      if (input.brief?.lessonMinutes) form.set("brief[lessonMinutes]", String(input.brief.lessonMinutes));
      input.departments.forEach((d, i) => {
        form.set(`departments[${i}][label]`, d.label);
        if (d.seniority) form.set(`departments[${i}][seniority]`, d.seniority);
        if (d.priorExperience) form.set(`departments[${i}][priorExperience]`, d.priorExperience);
        if (d.totalMinutes) form.set(`departments[${i}][totalMinutes]`, String(d.totalMinutes));
      });
      return post<PolicyDetail>("/", form);
    },
    generate: (id: string, variantIds?: string[]) => post<PolicyVariantRow[]>(`/${id}/variants/generate`, variantIds ? { variant_ids: variantIds } : {}),
    dashboard: async (id: string, filters: { variant?: string; status?: string; page?: number; perPage?: number } = {}): Promise<PolicyDashboard> => {
      const { data, meta } = await call<Omit<PolicyDashboard, "total" | "perPage" | "currentPage" | "lastPage">>("GET", `/${id}/dashboard${query({ variant: filters.variant, status: filters.status, page: filters.page, per_page: filters.perPage })}`);
      return { ...data, total: meta?.total ?? data.people.length, perPage: meta?.per_page ?? data.people.length, currentPage: meta?.current_page ?? 1, lastPage: meta?.last_page ?? 1 };
    },
    /** The CSV export is a download; the page links to this address. */
    exportPath: (id: string, filters: { variant?: string; status?: string } = {}) => `${base}/${id}/dashboard/export${query(filters)}`,
    version: (id: string, number: number, changeClass?: "minor" | "major") => get<VersionReview>(`/${id}/versions/${number}${query({ class: changeClass })}`),
    submitVersion: (id: string, file: File) => {
      const form = new FormData();
      form.set("file", file);
      return post<VersionReview>(`/${id}/versions`, form);
    },
    approve: (id: string, number: number, body: { change_class?: "minor" | "major"; reason?: string; overwrite?: boolean } = {}) => post<PolicyVersionRow>(`/${id}/versions/${number}/approve`, body),
    reject: (id: string, number: number, reason: string) => post<PolicyVersionRow>(`/${id}/versions/${number}/reject`, { reason }),
  };
}

/** The learner's own policy change notices (`/api/policies/my-changes`); `baseUrl` is the API (server) or the learner BFF. */
export function createMyPolicyChangesClient(options: ClientOptions) {
  const origin = options.baseUrl.replace(/\/+$/, "");
  const doFetch = options.fetch ?? ((input: RequestInfo | URL, init?: RequestInit) => fetch(input, init));
  return {
    mine: async (): Promise<MyPolicyChange[]> => {
      const headers: Record<string, string> = { Accept: "application/json", ...options.headers, ...(options.token ? { Authorization: `Bearer ${options.token}` } : {}) };
      let response: Response;
      try {
        response = await doFetch(`${origin}/api/policies/my-changes`, { method: "GET", headers, signal: AbortSignal.timeout(options.timeoutMs ?? 15_000) });
      } catch (error) {
        throw new ApiError(0, "/api/policies/my-changes", null, `API unreachable: ${(error as Error).message}`);
      }
      const json = (await response.json().catch(() => null)) as { data?: MyPolicyChange[]; message?: string } | null;
      if (!response.ok) throw new ApiError(response.status, "/api/policies/my-changes", json, json?.message ?? `API ${response.status}`);
      return json?.data ?? [];
    },
  };
}
