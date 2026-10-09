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
  /** Where a source host posts push events; null for uploads. */
  webhookUrl?: string | null;
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

/**
 * How many learners each notice rule reaches (distinct learners per kind), and the text learners
 * see under "Updated since you completed it": the author's note, else the reasons of the major changes.
 */
export interface LearnerImpact {
  learners: Record<"topic_updated" | "question_reattempt" | "topic_retired" | "course_extended", number>;
  note: string;
}

export interface LearnerNoteResult {
  /** What the author saved; null when the suggested note is in use. */
  learnerNote: string | null;
  effectiveNote: string;
}

/** What the author may change on a source connection from the studio. */
export interface ConnectionPatch {
  schedule?: string;
  autoAnalyse?: boolean;
  status?: "active" | "paused";
  settings?: { show_pending_to_learners?: boolean; notify_learners_of_updates?: boolean };
  /** Replaces the connector settings (validated against the host again). */
  config?: Record<string, unknown>;
  /** Write-only values such as a token; a field left out keeps its stored value. */
  secrets?: Record<string, string>;
}

/** A source connector this installation offers (`git`, `url`, plugins). */
export interface ConnectorInfo {
  key: string;
  label: string;
  configSchema: Record<string, unknown>;
  secretFields: string[];
  webhooks: boolean;
}

export type GitHost = "github" | "gitlab" | "gitea";

export interface GitConfig {
  host: GitHost;
  /** Gitea, Forgejo and self-hosted GitLab. */
  base_url?: string;
  /** `owner/name` (GitLab allows groups: `group/sub/name`). */
  repository: string;
  branch?: string;
  paths?: string[];
  extensions?: string[];
}

export interface UrlConfig {
  /** One to 20 https addresses of one site. */
  urls: string[];
  /** CSS selector of the main content. */
  selector?: string;
}

export interface ConnectInput {
  connector: "git" | "url" | string;
  config: GitConfig | UrlConfig | Record<string, unknown>;
  /** For Git: `{ token }`, optional for public repositories. */
  secrets?: Record<string, string>;
  schedule?: "hourly" | "daily" | "weekly" | "manual";
}

export interface ConnectResult {
  connection: SourceConnection;
  source: LivingSource;
  /** The signing secret for the source host's webhook. Returned once: null for connectors without webhooks. */
  webhookSecret: string | null;
}

export interface ProposalDetail extends ProposalSummary {
  /** Absent on an API older than the learner notices. */
  learnerImpact?: LearnerImpact;
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

export type AuditActorType = "user" | "system" | "agent";

export interface AuditEntry {
  id: number;
  at: string | null;
  /** For example `proposal.applied` or `item.accepted`. */
  action: string;
  actor: { type: AuditActorType | string; id: number | null; name: string | null; onBehalfOf: number | null };
  subject: { type: string | null; id: string | null };
  sourceId: string | null;
  revisionId: string | null;
  originRef: string | null;
  /** Course version numbers the entry moved between (the API sends numbers). */
  versionFrom: number | string | null;
  versionTo: number | string | null;
  aiCallIds: Array<number | string>;
  /** Reason, fingerprints and counts the action recorded; the shape depends on the action. */
  data: Record<string, unknown>;
  hash: string;
  prevHash: string | null;
}

export interface AuditPage {
  entries: AuditEntry[];
  total: number;
  page: number;
  perPage: number;
}

export interface AuditFilters {
  /** Action prefix, for example `proposal.` or `item.accepted`. */
  action?: string;
  actorType?: string;
  /** Date-time strings the API compares with the entry time. */
  from?: string;
  to?: string;
  source?: string;
  page?: number;
  perPage?: number;
}

/** The hash chain of the audit trail (the whole academy's chain, not one session's). */
export interface AuditVerdict {
  ok: boolean;
  checked: number;
  brokenId: number | null;
  reason: string | null;
}

/** Query string of the audit endpoints; empty filters are left out. */
export function auditQuery(filters: AuditFilters, format?: "csv" | "json"): string {
  const search = new URLSearchParams();
  if (format) search.set("format", format);
  for (const [key, value] of Object.entries(filters)) {
    if (value === undefined || value === null || value === "") continue;
    search.set(key, String(value));
  }
  const text = search.toString();
  return text ? `?${text}` : "";
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
    let json: { data?: T; message?: string } | null;
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
      /** Saves the note learners see (plain text, up to 500 characters); an empty note brings back the suggested one. Only while the proposal is open. */
      setLearnerNote: async (proposalId: string, note: string) =>
        (await call<LearnerNoteResult>("PUT", `/proposals/${id(proposalId)}/learner-note`, undefined, { note })).data,
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
    connectors: {
      /** The kinds of source this installation offers. */
      list: async () => (await call<ConnectorInfo[]>("GET", "/connectors")).data,
    },
    connections: {
      /** Asks for a check now (202, queued). Throws 409 for an upload source or a paused connection. */
      check: async (connectionId: string) => (await call<{ queued: boolean; connectionId: string }>("POST", `/connections/${id(connectionId)}/check`)).data,
      /** A new webhook secret, returned once; the old one stops working. */
      rotateSecret: async (connectionId: string) => (await call<{ webhookSecret: string }>("POST", `/connections/${id(connectionId)}/webhook-secret`)).data,
      /** Stops checking the source and forgets its secrets; revisions and the audit trail stay. */
      disconnect: async (connectionId: string) => (await call<SourceConnection>("DELETE", `/connections/${id(connectionId)}`)).data,
      /** Changes a connection: schedule, automatic analysis, paused, and the two learner notice settings. */
      update: async (connectionId: string, patch: ConnectionPatch) => (await call<SourceConnection>("PUT", `/connections/${id(connectionId)}`, undefined, patch)).data,
    },
    audit: {
      /** Newest first. */
      list: async (sessionId: string, filters: AuditFilters = {}) =>
        (await call<AuditPage>("GET", `/sessions/${id(sessionId)}/audit${auditQuery(filters)}`)).data,
      /** Recomputes the hash chain. */
      verify: async (sessionId: string) => (await call<AuditVerdict>("GET", `/sessions/${id(sessionId)}/audit/verify`)).data,
      /** Link for a plain download; the filters apply, paging does not. The browser sends the author's cookie to the BFF. */
      exportUrl: (sessionId: string, format: "csv" | "json", filters: AuditFilters = {}) =>
        `${base}/sessions/${id(sessionId)}/audit/export${auditQuery({ ...filters, page: undefined, perPage: undefined }, format)}`,
    },
    sources: {
      /** Sources of a builder session with connection and sync state. */
      list: async (sessionId: string) => (await call<LivingSource[]>("GET", `/sessions/${id(sessionId)}/sources`)).data,
      /**
       * Adds a Git repository or web pages as a source: the API checks the settings, fetches
       * revision 1 and starts the interview. Throws 422 with a readable message when the host,
       * token or paths are refused.
       */
      connect: async (sessionId: string, input: ConnectInput) => (await call<ConnectResult>("POST", `/sessions/${id(sessionId)}/sources/connect`, undefined, input)).data,
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
