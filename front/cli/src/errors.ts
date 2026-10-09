import { ApiError } from "@ulams/sdk";

export type ErrorCode =
  | "INTERNAL"
  | "USAGE"
  | "INPUT_INVALID"
  | "AUTH_REQUIRED"
  | "AUTH_EXPIRED"
  | "FORBIDDEN"
  | "SCOPE_MISSING"
  | "NOT_FOUND"
  | "CONFLICT"
  | "SYNC_CONFLICT"
  | "IDEMPOTENCY_MISMATCH"
  | "VALIDATION_FAILED"
  | "RATE_LIMITED"
  | "SERVER_ERROR"
  | "NETWORK"
  | "TIMEOUT"
  | "CONFIRMATION_REQUIRED"
  | "FEATURE_DISABLED"
  | "UNSUPPORTED_SERVER"
  | "DIFF_FOUND"
  | "INSECURE_CREDENTIALS";

/** ADR 0073: fixed exit codes. */
export const EXIT_CODES: Record<ErrorCode, number> = {
  INTERNAL: 1,
  USAGE: 2,
  INPUT_INVALID: 2,
  AUTH_REQUIRED: 3,
  AUTH_EXPIRED: 3,
  INSECURE_CREDENTIALS: 3,
  FORBIDDEN: 4,
  SCOPE_MISSING: 4,
  NOT_FOUND: 5,
  CONFLICT: 6,
  SYNC_CONFLICT: 6,
  IDEMPOTENCY_MISMATCH: 6,
  VALIDATION_FAILED: 7,
  RATE_LIMITED: 8,
  SERVER_ERROR: 9,
  NETWORK: 9,
  TIMEOUT: 10,
  CONFIRMATION_REQUIRED: 11,
  FEATURE_DISABLED: 12,
  UNSUPPORTED_SERVER: 12,
  DIFF_FOUND: 13,
};

export const DEFAULT_HINTS: Record<ErrorCode, string> = {
  INTERNAL: "This is a bug in the ulams CLI. Re-run with --debug and report it.",
  USAGE: "Run `ulams --help` or `ulams schema` to see the available commands.",
  INPUT_INVALID: "Run `ulams describe <command>` to see the accepted input.",
  AUTH_REQUIRED: "Run `ulams login --url <origin>` or set ULAMS_URL and ULAMS_TOKEN.",
  AUTH_EXPIRED: "The token was rejected. Run `ulams login` again.",
  FORBIDDEN: "The user lacks the permission. Run `ulams whoami` to see roles and token scopes.",
  SCOPE_MISSING: "The token lacks a scope. Create a token with the required scope.",
  NOT_FOUND: "Check the id with the matching `list` command.",
  CONFLICT: "The resource changed or already exists. Fetch it again and retry.",
  SYNC_CONFLICT: "Resolve the conflicting elements and retry.",
  IDEMPOTENCY_MISMATCH: "The idempotency key was reused with a different body. Use a new key.",
  VALIDATION_FAILED: "The API rejected the input. See error.details.fields.",
  RATE_LIMITED: "Wait for details.retryAfter seconds and retry.",
  SERVER_ERROR: "The server failed or is unreachable. Retry later; check the instance health.",
  NETWORK: "The instance is unreachable. Check the url with `ulams whoami` and your network.",
  TIMEOUT: "The operation is still running. Resume it with `ulams operations wait <handle>`.",
  CONFIRMATION_REQUIRED: "This command is destructive. Review the plan in error.details.plan and re-run with --yes.",
  FEATURE_DISABLED: "The feature is not enabled on this instance.",
  UNSUPPORTED_SERVER: "The server does not support this command. Upgrade the server or the CLI.",
  DIFF_FOUND: "Differences were found (exit code 13 is expected with --exit-code).",
  INSECURE_CREDENTIALS: "Run `chmod 600` on the credentials file.",
};

export class CliError extends Error {
  readonly code: ErrorCode;
  readonly hint: string;
  readonly status: number | null;
  readonly retryable: boolean;
  readonly requestId: string | null;
  readonly details: Record<string, unknown>;

  constructor(
    code: ErrorCode,
    message: string,
    opts: {
      hint?: string;
      status?: number | null;
      retryable?: boolean;
      requestId?: string | null;
      details?: Record<string, unknown>;
    } = {}
  ) {
    super(message);
    this.name = "CliError";
    this.code = code;
    this.hint = opts.hint ?? DEFAULT_HINTS[code];
    this.status = opts.status ?? null;
    this.retryable = opts.retryable ?? (code === "NETWORK" || code === "SERVER_ERROR" || code === "RATE_LIMITED");
    this.requestId = opts.requestId ?? null;
    this.details = opts.details ?? {};
  }

  get exitCode(): number {
    return EXIT_CODES[this.code];
  }
}

export interface ErrorObject {
  code: ErrorCode;
  message: string;
  hint: string;
  status: number | null;
  retryable: boolean;
  requestId: string | null;
  details: Record<string, unknown>;
}

export function toErrorObject(error: CliError): ErrorObject {
  return {
    code: error.code,
    message: error.message,
    hint: error.hint,
    status: error.status,
    retryable: error.retryable,
    requestId: error.requestId,
    details: error.details,
  };
}

interface ApiBody {
  message?: string;
  error?: string;
  required?: string[];
  errors?: Record<string, string[]>;
}

/** Maps an SDK ApiError (or anything thrown) to a CliError following ADR 0073. */
export function fromApiError(error: unknown, requestId: string | null = null, retryAfter?: number): CliError {
  if (error instanceof CliError) return error;
  if (error instanceof ApiError) {
    const body = (error.body ?? {}) as ApiBody;
    const apiMessage = typeof body.message === "string" && body.message ? body.message : null;
    const status = error.status;
    const base = { status, requestId };
    if (status === 0) return new CliError("NETWORK", error.message, { ...base, retryable: true });
    if (status === 401) return new CliError("AUTH_EXPIRED", apiMessage ?? "Unauthenticated.", base);
    if (status === 403) {
      if (body.error === "scope_missing") {
        return new CliError("SCOPE_MISSING", apiMessage ?? "The token lacks a scope.", {
          ...base,
          details: { required: body.required ?? [] },
        });
      }
      return new CliError("FORBIDDEN", apiMessage ?? "Forbidden.", base);
    }
    if (status === 404) return new CliError("NOT_FOUND", apiMessage ?? `Not found: ${error.path}`, base);
    if (status === 409) {
      return new CliError("CONFLICT", apiMessage ?? "Conflict.", { ...base, details: { body: error.body as never } });
    }
    if (status === 422) {
      if (body.error === "idempotency_mismatch") {
        return new CliError("IDEMPOTENCY_MISMATCH", apiMessage ?? "Idempotency key reused.", base);
      }
      return new CliError("VALIDATION_FAILED", apiMessage ?? "Validation failed.", {
        ...base,
        details: { fields: body.errors ?? {} },
      });
    }
    if (status === 429) {
      return new CliError("RATE_LIMITED", apiMessage ?? "Too many requests.", {
        ...base,
        retryable: true,
        details: { retryAfter: retryAfter ?? null },
      });
    }
    if (status === 503 && /ai|disabled/i.test(apiMessage ?? "")) {
      return new CliError("FEATURE_DISABLED", apiMessage ?? "Feature disabled.", base);
    }
    if (status >= 500) return new CliError("SERVER_ERROR", apiMessage ?? `Server error ${status}.`, { ...base, retryable: true });
    return new CliError("SERVER_ERROR", apiMessage ?? `Unexpected status ${status}.`, base);
  }
  const message = error instanceof Error ? error.message : String(error);
  return new CliError("INTERNAL", message);
}
