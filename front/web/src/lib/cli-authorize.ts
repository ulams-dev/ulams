import type { Tenant } from "@ulams/sdk";

/**
 * The `/cli/authorize` page (ADR 0075): a person signs in, sees which client asks for what, narrows
 * the scopes and the lifetime, and approves or denies the device login of the `ulams` CLI.
 * Everything security-relevant is decided by the API; this module only formats and forwards.
 */
export const CLI_AUTHORIZE_PATH = "/cli/authorize";

export const isCliPath = (pathname: string): boolean => pathname === "/cli" || pathname.startsWith("/cli/");

/**
 * Headers of every /cli response. The code in the URL is single use and short lived, still it must
 * not leak through Referer; the approve button must not be clickjackable; nothing is cached.
 */
export const CLI_HEADERS: Readonly<Record<string, string>> = {
  "Cache-Control": "private, no-store",
  "Referrer-Policy": "no-referrer",
  "X-Frame-Options": "DENY",
  "Content-Security-Policy": "frame-ancestors 'none'",
  "X-Robots-Tag": "noindex, nofollow, noarchive",
};

const ALPHABET = "BCDFGHJKLMNPQRSTVWXZ";

/** `bdwp hqpk` / `BDWPHQPK` / `bdwp-hqpk` -> `BDWP-HQPK`; null when it cannot be a user code. */
export function normalizeUserCode(input: string | null | undefined): string | null {
  const letters = (input ?? "").toUpperCase().replace(/[\s-]+/g, "");
  if (letters.length !== 8 || [...letters].some((c) => !ALPHABET.includes(c))) return null;
  return `${letters.slice(0, 4)}-${letters.slice(4)}`;
}

export interface DeviceRequest {
  user_code: string;
  client_name: string;
  agent_name: string | null;
  requested_scopes: string[];
  ip: string | null;
  user_agent: string | null;
  expires_in: number;
  expiry_options_days: number[];
  default_expires_in_days: number;
  max_expires_in_days: number;
}

const AREA_LABELS: Record<string, string> = {
  courses: "Courses, lessons, topics, files and quizzes",
  users: "Users, groups and roles",
  enrolments: "Who has access to which course",
  settings: "Settings, pages, templates and notifications",
  events: "Webinars, events and consultations",
  certificates: "Certificates",
  commerce: "Orders, products, vouchers and payments",
  reports: "Reports and statistics",
  lti: "LTI integrations",
  builder: "AI Course Builder",
  "living-course": "Living Course",
  learner: "Your own learning: profile, progress, notes",
  tokens: "API tokens (create and revoke tokens)",
  platform: "Platform tenants",
};

/** Areas where "change" is sensitive enough to be flagged on the approval page. */
const SENSITIVE_WRITE = new Set(["users", "settings", "commerce", "tokens", "platform", "enrolments"]);

export interface ScopeView {
  scope: string;
  label: string;
  level: "read" | "write" | "all";
  sensitive: boolean;
}

export function describeScope(scope: string): ScopeView {
  if (scope === "*") return { scope, label: "Everything your account can do", level: "all", sensitive: true };
  const [area = "", level = "read"] = scope.split(":");
  const write = level === "write";
  return {
    scope,
    label: `${AREA_LABELS[area] ?? area}: ${write ? "read and change" : "read only"}`,
    level: write ? "write" : "read",
    sensitive: write && SENSITIVE_WRITE.has(area),
  };
}

/** Scopes the person kept ticked, limited to what the client asked for (the API intersects again). */
export function parseDecision(
  form: FormData,
  request: Pick<DeviceRequest, "requested_scopes" | "expiry_options_days" | "default_expires_in_days">
): { decision: "approve" | "deny" | null; scopes: string[]; days: number } {
  const decision = form.get("decision");
  const picked = form.getAll("scope").map(String);
  const scopes = request.requested_scopes.filter((s) => picked.includes(s));
  const days = Number(form.get("days"));
  return {
    decision: decision === "approve" || decision === "deny" ? decision : null,
    scopes,
    days: request.expiry_options_days.includes(days) ? days : request.default_expires_in_days,
  };
}

export type Failure = "unauthenticated" | "unknown" | "throttled" | "forbidden" | "unavailable";

export type LookupResult = { ok: true; request: DeviceRequest } | { ok: false; reason: Failure };

const json = (token: string, init: RequestInit = {}): RequestInit => ({
  ...init,
  headers: { Accept: "application/json", "Content-Type": "application/json", Authorization: `Bearer ${token}` },
  signal: AbortSignal.timeout(15_000),
});

const reasonFor = (status: number): Failure =>
  status === 401 ? "unauthenticated" : status === 404 ? "unknown" : status === 429 ? "throttled" : status === 403 ? "forbidden" : "unavailable";

export async function lookupRequest(tenant: Tenant, token: string, userCode: string): Promise<LookupResult> {
  try {
    const response = await fetch(`${tenant.apiUrl}/api/auth/device/requests/${encodeURIComponent(userCode)}`, json(token));
    if (!response.ok) return { ok: false, reason: reasonFor(response.status) };
    const body = (await response.json()) as { data: DeviceRequest };
    return { ok: true, request: body.data };
  } catch {
    return { ok: false, reason: "unavailable" };
  }
}

export type AnswerResult = { ok: true } | { ok: false; reason: Failure; message?: string };

export async function answerRequest(
  tenant: Tenant,
  token: string,
  userCode: string,
  decision: "approve" | "deny",
  scopes: string[],
  days: number
): Promise<AnswerResult> {
  try {
    const response = await fetch(
      `${tenant.apiUrl}/api/auth/device/requests/${encodeURIComponent(userCode)}/${decision}`,
      json(token, { method: "POST", body: JSON.stringify(decision === "approve" ? { scopes, expires_in_days: days } : {}) })
    );
    if (response.ok) return { ok: true };
    const body = (await response.json().catch(() => ({}))) as { message?: string };
    return { ok: false, reason: response.status === 422 ? "unknown" : reasonFor(response.status), message: body.message };
  } catch {
    return { ok: false, reason: "unavailable" };
  }
}

export const REASON_TEXT: Record<Failure, string> = {
  unauthenticated: "Your session has expired. Sign in again.",
  unknown: "This code is unknown, has expired or was already answered. Run the command in your terminal again to get a new code.",
  throttled: "Too many attempts. Wait a minute and try again.",
  forbidden: "This sign-in cannot approve a CLI login. Sign in with your account.",
  unavailable: "The server could not be reached. Try again in a moment.",
};
