import { ApiError, createClient, type HttpMethod } from "@ulams/sdk";
import { CliError, fromApiError } from "../errors.ts";
import { redact, redactString } from "../config/redact.ts";
import type { PageMeta } from "../registry/types.ts";
import { ulid } from "./ulid.ts";

export interface HttpClientOptions {
  baseUrl: string;
  token: string | null;
  fetch?: typeof fetch;
  userAgent: string;
  /** "cli" or "mcp": sent as X-Ulams-Client (audit log, S1). */
  client: "cli" | "mcp";
  agent?: string | undefined;
  timeoutMs?: number;
  /** Debug sink (already redacted by the client). */
  debug?: ((line: string) => void) | undefined;
  sleep?: (ms: number) => Promise<void>;
  maxRetries?: number;
}

export interface CallOptions {
  params?: Record<string, string | number>;
  query?: Record<string, string | number | boolean | undefined | null>;
  body?: unknown;
  /** Multipart body. Rebuilt for every retry attempt by the caller-provided factory when given. */
  form?: FormData;
  signal?: AbortSignal | undefined;
  idempotencyKey?: string | undefined;
  /** Safe to retry even without an idempotency key. */
  idempotent?: boolean;
}

export interface CallResult<T = unknown> {
  data: T;
  /** Normalised paginator meta, when the API sent one. */
  meta?: PageMeta;
  /** The full response body (the Laravel envelope). */
  body: unknown;
  requestId: string;
}

export interface LaravelMeta {
  current_page?: number;
  per_page?: number | string;
  total?: number;
  last_page?: number;
}

export function normaliseMeta(meta: unknown): PageMeta | undefined {
  if (!meta || typeof meta !== "object") return undefined;
  const m = meta as LaravelMeta;
  if (m.current_page === undefined && m.last_page === undefined) return undefined;
  const page = Number(m.current_page ?? 1);
  const lastPage = Number(m.last_page ?? page);
  return {
    page,
    perPage: Number(m.per_page ?? 0),
    total: Number(m.total ?? 0),
    lastPage,
    nextPage: page < lastPage ? page + 1 : null,
  };
}

const RETRY_STATUS = new Set([429, 502, 503, 504]);

export class HttpClient {
  readonly baseUrl: string;
  private readonly opts: HttpClientOptions;

  constructor(opts: HttpClientOptions) {
    this.opts = opts;
    this.baseUrl = opts.baseUrl.replace(/\/+$/, "");
  }

  get fetchImpl(): typeof fetch | undefined {
    return this.opts.fetch;
  }

  get userAgent(): string {
    return this.opts.userAgent;
  }

  get token(): string | null {
    return this.opts.token;
  }

  /** Headers for a request made outside `call` (the SSE stream). Never log these. */
  streamHeaders(): Record<string, string> {
    const headers: Record<string, string> = {
      Accept: "text/event-stream",
      "User-Agent": this.opts.userAgent,
      "X-Ulams-Client": this.opts.client,
      "X-Request-Id": ulid(),
    };
    if (this.opts.token) headers.Authorization = `Bearer ${this.opts.token}`;
    if (this.opts.agent) headers["X-Ulams-Agent"] = this.opts.agent;
    return headers;
  }

  withToken(token: string | null): HttpClient {
    return new HttpClient({ ...this.opts, token });
  }

  async call<T = unknown>(method: HttpMethod, path: string, options: CallOptions = {}): Promise<CallResult<T>> {
    return this.withRetries<CallResult<T>>(method, path, options, async (sdk, requestId) => {
      const envelope = await sdk.raw<T>(method, path as never, {
        params: options.params,
        query: options.query,
        body: options.body,
        ...(options.form ? { form: options.form } : {}),
        signal: options.signal,
      });
      return unwrap<T>(envelope, requestId);
    });
  }

  async download(
    method: HttpMethod,
    path: string,
    options: CallOptions = {}
  ): Promise<{ data: Uint8Array; contentType: string | null; filename: string | null }> {
    return this.withRetries(method, path, options, (sdk) =>
      sdk.download(method, path as never, { params: options.params, query: options.query, body: options.body, signal: options.signal })
    );
  }

  private async withRetries<R>(
    method: HttpMethod,
    path: string,
    options: CallOptions,
    attemptFn: (sdk: ReturnType<typeof createClient>, requestId: string) => Promise<R>
  ): Promise<R> {
    const requestId = ulid();
    const secrets = this.opts.token ? [this.opts.token] : [];
    const canRetry = Boolean(options.idempotent || options.idempotencyKey || method === "GET");
    const maxRetries = canRetry ? (this.opts.maxRetries ?? 3) : 0;
    const sleep = this.opts.sleep ?? ((ms: number) => new Promise<void>((r) => setTimeout(r, ms)));

    for (let attempt = 0; ; attempt++) {
      const seen: { retryAfter?: number; status?: number } = {};
      const baseFetch = this.opts.fetch ?? ((input: RequestInfo | URL, init?: RequestInit) => fetch(input, init));
      const wrapped: typeof fetch = async (input, init) => {
        const response = await baseFetch(input, init);
        seen.status = response.status;
        const ra = response.headers.get("retry-after");
        if (ra && Number.isFinite(Number(ra))) seen.retryAfter = Number(ra);
        return response;
      };
      const headers: Record<string, string> = {
        "User-Agent": this.opts.userAgent,
        "X-Ulams-Client": this.opts.client,
        "X-Request-Id": requestId,
      };
      if (this.opts.agent) headers["X-Ulams-Agent"] = this.opts.agent;
      if (options.idempotencyKey) headers["Idempotency-Key"] = options.idempotencyKey;
      const sdk = createClient({
        baseUrl: this.baseUrl,
        token: this.opts.token,
        fetch: wrapped,
        headers,
        timeoutMs: this.opts.timeoutMs ?? 60_000,
      });
      this.opts.debug?.(
        redactString(`> ${method} ${this.baseUrl}${path} ${JSON.stringify(redact(options.body ?? null, secrets)).slice(0, 2000)}`, secrets)
      );
      try {
        const result = await attemptFn(sdk, requestId);
        this.opts.debug?.(`< ${seen.status ?? 200} ${method} ${path}`);
        return result;
      } catch (error) {
        const mapped = error instanceof ApiError ? fromApiError(error, requestId, seen.retryAfter) : error;
        this.opts.debug?.(redactString(`< ${(error as Error).message}`, secrets));
        const retryable =
          error instanceof ApiError && (error.status === 0 || RETRY_STATUS.has(error.status)) && attempt < maxRetries;
        if (!retryable || options.signal?.aborted) throw mapped instanceof CliError ? mapped : fromApiError(mapped, requestId);
        const base = seen.retryAfter !== undefined ? seen.retryAfter * 1000 : Math.min(500 * 2 ** attempt, 5000);
        await sleep(base + Math.floor(Math.random() * 100));
      }
    }
  }
}

function unwrap<T>(envelope: unknown, requestId: string): CallResult<T> {
  if (envelope && typeof envelope === "object" && "data" in envelope && ("success" in envelope || "meta" in envelope)) {
    const e = envelope as { data: T; meta?: unknown };
    const meta = normaliseMeta(e.meta);
    return { data: e.data, ...(meta ? { meta } : {}), body: envelope, requestId };
  }
  return { data: envelope as T, body: envelope, requestId };
}
