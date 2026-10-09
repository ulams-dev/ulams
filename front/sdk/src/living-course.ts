/**
 * Living Course client (`/api/admin/living-course`): sources of a builder session with their
 * connection and sync state, revisions, and the fragment changes between revisions. REST calls
 * return the unwrapped `data`. In the browser the reference app points `baseUrl` at its BFF
 * (`/studio/api`) with `prefix: "/living-course"`, which adds the author's token.
 */
import { ApiError, type ClientOptions } from "./client.ts";

export type RevisionStatus = "fetched" | "ingested" | "unchanged" | "no_impact" | "failed";
export type ConnectionStatus = string;
export type ChangeKind = "changed" | "moved" | "removed" | "added";
export type ChangeMagnitude = "trivial" | "minor" | "substantive";

export interface RevisionRef {
  id: string;
  number: number;
}

export interface ChangeCounts {
  changed: number;
  moved: number;
  removed: number;
  added: number;
  trivial: number;
  minor: number;
  substantive: number;
  total: number;
}

export interface SourceConnection {
  id: string;
  connector: string;
  schedule: string | null;
  status: ConnectionStatus;
  autoAnalyse: boolean;
  settings: Record<string, unknown>;
  config: Record<string, unknown>;
  syncedRevision: RevisionRef | null;
  latestRevision: (RevisionRef & { status: RevisionStatus }) | null;
  lastCheckedAt: string | null;
  nextCheckAt: string | null;
  lastChangeAt: string | null;
  failureCount: number;
  lastError: string | null;
  secretsSet: string[];
}

export interface LivingSource {
  id: string;
  name: string;
  status: "uploaded" | "processing" | "ready" | "failed";
  kind: "markdown" | "pdf" | "docx" | string;
  size: number;
  tokens: number;
  fragments: number;
  title: string | null;
  connection: SourceConnection | null;
  revisionCount: number;
}

export interface SourceRevision {
  id: string;
  sourceId: string;
  number: number;
  origin: string;
  originRef: string | null;
  trigger: string;
  triggeredBy: number | string | null;
  status: RevisionStatus;
  fragmentCount: number;
  tokens: number;
  title: string | null;
  name: string | null;
  files: unknown;
  counts: ChangeCounts | null;
  error: string | null;
  detectedAt: string | null;
  /** The course reflects this revision (null when the source has no connection). */
  synced: boolean | null;
  latest: boolean | null;
}

export interface ChangedFragment {
  fragmentId: string;
  label: string;
  section: string | null;
  headingPath: string[];
  file: string | null;
  text: string;
  pages: [number, number] | null;
}

/** `["=", text]`, `["-", text]` or `["+", text]` runs. */
export type WordDiffRun = [op: "=" | "-" | "+", text: string];

export interface FragmentChangeRow {
  id: number | string;
  kind: ChangeKind;
  magnitude: ChangeMagnitude;
  similarity: number;
  signals: string[];
  wordDiff: WordDiffRun[] | null;
  old: ChangedFragment | null;
  new: ChangedFragment | null;
}

export interface RevisionChanges {
  from: SourceRevision | null;
  to: SourceRevision;
  counts: ChangeCounts | null;
  changes: FragmentChangeRow[];
}

export interface UploadResult {
  /** The file is the latest revision already; nothing new was created. */
  unchanged: boolean;
  revision: SourceRevision;
}

export type Freshness = "in_sync" | "stale" | "dismissed";

/** The course summary of staleness; also `freshness` on every builder session summary. */
export interface StalenessSummary {
  state: Freshness;
  since: string | null;
  days: number | null;
  pendingElements: number;
  openProposalId: string | null;
  syncedRevision: number | null;
  latestRevision: number | null;
  lastCheckedAt: string | null;
  /** The course has a source connection; without one there is nothing to be stale against. */
  tracked: boolean;
}

export type ElementStaleness = "pending" | "source_removed" | "dismissed";

export interface StaleElement {
  elementId: string;
  status: ElementStaleness;
  type: string;
  label: string;
  since: string | null;
  proposalId: string | null;
  fragmentIds: string[];
  /** A quiz answer may be wrong because the source changed. */
  answerCheck: boolean;
}

export interface Staleness {
  summary: StalenessSummary;
  elements: StaleElement[];
}

export type ProposalStatus =
  | "analysing"
  | "ready"
  | "awaiting_analysis"
  | "budget_blocked"
  | "applying"
  | "applied"
  | "no_impact"
  | "rejected"
  | "superseded"
  | "failed";

export type ItemKind = "update" | "citation_remap" | "remove" | "no_change" | "manual" | "uncovered";
export type ItemType = "block" | "question" | "objective" | "lesson" | "course" | "section";
export type ItemStatus = "pending" | "accepted" | "rejected" | "conflict" | "stale";
export type ChangeClass = "none" | "minor" | "major" | "answer_changed" | "removed";

export interface ProposalCounts {
  items: number;
  elements: number;
  remaps: number;
  uncovered: number;
  major: number;
  answerChecks: number;
  groups: number;
  byType?: Record<string, number>;
  source?: ChangeCounts | null;
  failedGroups?: string[];
  newerRevision?: number | null;
}

export interface ProposalSummary {
  id: string;
  number: number;
  sessionId: string;
  sourceId: string;
  status: ProposalStatus;
  trigger: string;
  counts: ProposalCounts | null;
  fromRevision: RevisionRef | null;
  toRevision: (RevisionRef & { detectedAt: string | null }) | null;
  /** Analysis run (kind `sync`); its steps are in `ProposalDetail.steps`. */
  runId?: string | null;
  baseVersionId: string | null;
  resultVersionId: string | null;
  estimatedCostMicroUsd: number | null;
  costMicroUsd: number | null;
  decisions: Partial<Record<ItemStatus, number>>;
  learnerNote: string | null;
  error: string | null;
  createdAt: string | null;
  decidedAt: string | null;
  appliedAt: string | null;
}

/** A blueprint node as the item shows it (shape depends on `ItemType`). */
export type ElementNode = Record<string, unknown> & { id?: string };

export interface ItemFlags {
  grounding?: string[];
  signals?: string[];
  checkAnswer?: boolean;
}

export interface ProposalItem {
  id: string;
  groupKey: string;
  elementId: string;
  type: ItemType;
  label: string;
  kind: ItemKind;
  reason: string | null;
  severity: "minor" | "major" | null;
  before: ElementNode | null;
  after: ElementNode | null;
  changeClass: ChangeClass | null;
  answerStatus: string | null;
  answerCheck: boolean;
  status: ItemStatus;
  flags: ItemFlags;
  regenerations: number;
  fragments: Array<{ fragmentId: string; label: string }>;
  /** Ids of the source changes (`FragmentChangeRow.id`) behind the item. */
  changeIds: Array<number | string>;
  decidedAt: string | null;
}

export interface ProposalGroup {
  key: string;
  label: string;
  items: ProposalItem[];
}

export interface AnalysisStep {
  id: string;
  groupKey: string;
  status: string;
  error: string | null;
}

export interface ProposalDetail extends ProposalSummary {
  steps?: AnalysisStep[];
  groups: ProposalGroup[];
  items: ProposalItem[];
}

export interface ItemResult {
  item: ProposalItem;
  proposal: ProposalSummary;
}

export interface AnalyseResult {
  state: string;
  estimateMicroUsd: number | null;
  runId: string | null;
  message: string | null;
}

/** The machine-readable part of an error response: `code` and `data` (for example the conflicting items). */
export function apiErrorInfo(error: unknown): { status: number; code: string | null; data: Record<string, unknown> | null } {
  if (!(error instanceof ApiError)) return { status: 0, code: null, data: null };
  const body = (error.body ?? {}) as { code?: unknown; data?: unknown };
  return {
    status: error.status,
    code: typeof body.code === "string" ? body.code : null,
    data: body.data && typeof body.data === "object" ? (body.data as Record<string, unknown>) : null,
  };
}

const PREFIX = "/api/admin/living-course";

export type LivingCourseClient = ReturnType<typeof createLivingCourseClient>;

/**
 * `prefix` is where the API lives under `baseUrl`: the API path by default, or `/living-course`
 * when `baseUrl` points at the studio BFF.
 */
export function createLivingCourseClient(options: ClientOptions & { prefix?: string }) {
  const base = options.baseUrl.replace(/\/+$/, "") + (options.prefix ?? PREFIX);
  const doFetch = options.fetch ?? ((input: RequestInfo | URL, init?: RequestInit) => fetch(input, init));

  async function call<T>(method: string, path: string, form?: FormData, json_?: unknown): Promise<{ status: number; data: T }> {
    const headers: Record<string, string> = {
      Accept: "application/json",
      ...options.headers,
      ...(options.token ? { Authorization: `Bearer ${options.token}` } : {}),
    };
    let payload: BodyInit | undefined = form;
    if (!form && json_ !== undefined) {
      headers["Content-Type"] = "application/json";
      payload = JSON.stringify(json_);
    }
    let response: Response;
    try {
      response = await doFetch(`${base}${path}`, { method, headers, body: payload, signal: AbortSignal.timeout(options.timeoutMs ?? 60_000) });
    } catch (error) {
      throw new ApiError(0, path, null, `API unreachable on ${path}: ${(error as Error).message}`);
    }
    const text = await response.text();
    let json: { data?: T; message?: string } | null = null;
    try {
      json = text ? JSON.parse(text) : null;
    } catch {
      json = { message: text.slice(0, 200) };
    }
    if (!response.ok) throw new ApiError(response.status, path, json, json?.message ?? `API ${response.status} on ${path}`);
    return { status: response.status, data: (json?.data ?? null) as T };
  }

  const id = (value: string) => encodeURIComponent(value);

  return {
    base,
    staleness: {
      /** Per-element staleness and the course summary. Works with AI disabled. */
      get: async (sessionId: string) => (await call<Staleness>("GET", `/sessions/${id(sessionId)}/staleness`)).data,
    },
    proposals: {
      /** Newest first. */
      list: async (sessionId: string) => (await call<ProposalSummary[]>("GET", `/sessions/${id(sessionId)}/proposals`)).data,
      /** Summary, items grouped by lesson, and the analysis steps. */
      get: async (proposalId: string) => (await call<ProposalDetail>("GET", `/proposals/${id(proposalId)}`)).data,
      /**
       * Starts or resumes the AI analysis. Throws `ApiError` 409 with code `confirm_estimate` (the
       * estimate is above the automatic limit: call again with `confirmEstimate`), 422 `budget_blocked`
       * or 503 `ai_disabled`; read them with `apiErrorInfo`.
       */
      analyse: async (proposalId: string, confirmEstimate = false) =>
        (await call<AnalyseResult>("POST", `/proposals/${id(proposalId)}/analyse`, undefined, { confirmEstimate })).data,
      accept: async (proposalId: string, itemId: string) => (await call<ItemResult>("POST", `/proposals/${id(proposalId)}/items/${id(itemId)}/accept`)).data,
      reject: async (proposalId: string, itemId: string) => (await call<ItemResult>("POST", `/proposals/${id(proposalId)}/items/${id(itemId)}/reject`)).data,
      reset: async (proposalId: string, itemId: string) => (await call<ItemResult>("POST", `/proposals/${id(proposalId)}/items/${id(itemId)}/reset`)).data,
      /** One new call for one element; at most three per item. */
      regenerate: async (proposalId: string, itemId: string, comment: string) =>
        (await call<ItemResult>("POST", `/proposals/${id(proposalId)}/items/${id(itemId)}/regenerate`, undefined, { comment })).data,
      acceptAll: async (proposalId: string) => (await call<{ accepted: number; proposal: ProposalSummary }>("POST", `/proposals/${id(proposalId)}/accept-all`)).data,
      /** Rejects the whole proposal and acknowledges the source revision. */
      rejectAll: async (proposalId: string) => (await call<ProposalSummary>("POST", `/proposals/${id(proposalId)}/reject`)).data,
      /**
       * Applies the accepted items as a new course version (202). Throws 409 with code `conflicts`
       * (`data.conflicts`: items) or `admin_edits` (`data.drift`: element labels; repeat with `overwrite`).
       */
      apply: async (proposalId: string, overwrite = false) =>
        (await call<{ runId: string; proposal: ProposalSummary }>("POST", `/proposals/${id(proposalId)}/apply`, undefined, { overwrite })).data,
    },
    sources: {
      /** Sources of a builder session with connection and sync state. */
      list: async (sessionId: string) => (await call<LivingSource[]>("GET", `/sessions/${id(sessionId)}/sources`)).data,
    },
    revisions: {
      /** Newest first. */
      list: async (sourceId: string) => (await call<SourceRevision[]>("GET", `/sources/${id(sourceId)}/revisions`)).data,
      get: async (revisionId: string) => (await call<SourceRevision>("GET", `/revisions/${id(revisionId)}`)).data,
      /** Changes against the revision it was compared with, or against `against`. */
      changes: async (revisionId: string, against?: string) =>
        (await call<RevisionChanges>("GET", `/revisions/${id(revisionId)}/changes${against ? `?against=${id(against)}` : ""}`)).data,
      /** Uploads a new version of a source. `created` is false when the file is the latest revision already. */
      upload: async (sourceId: string, file: Blob, filename: string) => {
        const form = new FormData();
        form.append("file", file, filename);
        const result = await call<UploadResult>("POST", `/sources/${id(sourceId)}/revisions`, form);
        return { ...result.data, created: result.status === 201 };
      },
    },
  };
}
