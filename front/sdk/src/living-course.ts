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

const PREFIX = "/api/admin/living-course";

export type LivingCourseClient = ReturnType<typeof createLivingCourseClient>;

/**
 * `prefix` is where the API lives under `baseUrl`: the API path by default, or `/living-course`
 * when `baseUrl` points at the studio BFF.
 */
export function createLivingCourseClient(options: ClientOptions & { prefix?: string }) {
  const base = options.baseUrl.replace(/\/+$/, "") + (options.prefix ?? PREFIX);
  const doFetch = options.fetch ?? ((input: RequestInfo | URL, init?: RequestInit) => fetch(input, init));

  async function call<T>(method: string, path: string, form?: FormData): Promise<{ status: number; data: T }> {
    const headers: Record<string, string> = {
      Accept: "application/json",
      ...options.headers,
      ...(options.token ? { Authorization: `Bearer ${options.token}` } : {}),
    };
    let response: Response;
    try {
      response = await doFetch(`${base}${path}`, { method, headers, body: form, signal: AbortSignal.timeout(options.timeoutMs ?? 60_000) });
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
