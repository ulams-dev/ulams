import type { z } from "zod";
import type { HttpClient } from "../http/client.ts";
import type { ResolvedProfile } from "../config/profiles.ts";

export type Kind = "read" | "write" | "destructive" | "stream" | "local";
export type Audience = "admin" | "author" | "learner" | "platform" | "any";

export interface Example {
  title: string;
  argv: string;
  output?: unknown;
}

export interface FsPort {
  readFile(path: string): Promise<Uint8Array>;
  readText(path: string): Promise<string>;
  writeFile(path: string, data: Uint8Array | string): Promise<void>;
  exists(path: string): Promise<boolean>;
}

export interface GlobalFlags {
  profile?: string;
  url?: string;
  json: boolean;
  output: "auto" | "json" | "ndjson" | "yaml" | "table" | "text";
  fields?: string[];
  quiet: boolean;
  noColor: boolean;
  dryRun: boolean;
  yes: boolean;
  wait: boolean;
  timeout: number;
  idempotencyKey?: string;
  page?: number;
  perPage?: number;
  all: boolean;
  limit?: number;
  debug: boolean;
  interactive: boolean;
  tokenStdin: boolean;
  out?: string;
}

export interface PageMeta {
  page: number;
  perPage: number;
  total: number;
  lastPage: number;
  nextPage: number | null;
}

export interface Warning {
  code: string;
  message: string;
  hint?: string;
}

export interface Result<T = unknown> {
  data: T;
  meta?: PageMeta | Record<string, unknown>;
  warnings?: Warning[];
}

export interface Plan {
  request?: { method: string; path: string; query?: unknown; body?: unknown };
  changes?: Array<{ op: "add" | "replace" | "remove"; path: string; from?: unknown; to?: unknown }>;
  note?: string;
  [key: string]: unknown;
}

export interface Ctx {
  client: HttpClient;
  profile: ResolvedProfile;
  fs: FsPort;
  io: { stderr(line: string): void; isTTY: boolean; interactive: boolean };
  signal: AbortSignal;
  emit(event: unknown): void;
  flags: GlobalFlags;
  env: NodeJS.ProcessEnv;
  readStdin(): Promise<string>;
  prompt(question: string, opts?: { secret?: boolean }): Promise<string>;
  version: string;
  /** Local config store; only the local commands (login, profiles, config) use it. */
  store: import("../config/profiles.ts").ConfigStore;
}

export interface LongRunning {
  /** Operation kind handled by `ulams operations`. */
  kind: string;
  note?: string;
}

/** Declarative request description, used by generated commands and dry-run. */
export interface RequestSpec {
  method: "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
  path: string;
  pathParams: string[];
  queryParams: string[];
  /** "json" sends the body fields as JSON, "multipart" as form fields, "none" has no body. */
  bodyMode: "json" | "multipart" | "none";
  bodyFields: string[];
  /** A single `body` input field carries the whole body (unresolvable schemas). */
  wholeBody?: boolean;
  /** input key -> multipart field name for files. */
  files?: Record<string, string>;
  /** Response is a binary download written to --out. */
  download?: boolean;
}

export interface CommandDef<I extends z.ZodObject = z.ZodObject, O extends z.ZodType = z.ZodType> {
  id: string;
  summary: string;
  description?: string;
  input: I;
  positionals?: string[];
  output: O;
  kind: Kind;
  idempotent: boolean;
  scopes: string[];
  audience: Audience[];
  endpoints: string[];
  /** Routes that exist but are missing from the OpenAPI spec (fix the spec; reported by the coverage check). */
  undocumented?: string[];
  paginated?: boolean;
  longRunning?: LongRunning;
  dryRun?: "client" | "server" | "none";
  upload?: Record<string, { field: string; accept?: string[] }>;
  mcp?: { expose?: boolean; toolset?: string; title?: string };
  stability: "stable" | "beta" | "experimental";
  since: string;
  examples: Example[];
  /** Does not need credentials (schema, describe, version, login...). */
  anonymous?: boolean;
  request?: RequestSpec;
  /** Kind decided from the input (the raw `api` command: GET is read, DELETE destructive). */
  kindFor?(input: Record<string, unknown>): Kind;
  run(ctx: Ctx, input: z.infer<I>): Promise<Result>;
  plan?(ctx: Ctx, input: z.infer<I>): Promise<Plan>;
}

export type AnyCommand = CommandDef<z.ZodObject, z.ZodType>;

export const CONTRACT = 1;
