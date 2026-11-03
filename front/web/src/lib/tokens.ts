import type { Tenant } from "@ulams/sdk";
import { accountStrings, fill, type AccountStrings } from "@ulams/ui/account-strings";
import { describeScope } from "./cli-authorize.ts";

/**
 * "My tokens" on the account page (ADR 0074): a person creates, lists and revokes their own scoped
 * personal access tokens. Plain forms posted to /account, no script. The API decides everything
 * that matters (a token can never do more than the account, nor grant what it lacks itself); this
 * module formats, validates the form and forwards with the signed-in user's own token.
 */
export interface TokenPreset {
  key: string;
  /** What the API receives: a `@preset` name it expands. */
  scope: string;
  label: string;
  description: string;
}

const PRESET_SCOPES: ReadonlyArray<{ key: keyof AccountStrings["presets"]; scope: string }> = [
  { key: "read-only", scope: "@read-only" },
  { key: "author", scope: "@author" },
  { key: "ci", scope: "@ci" },
  { key: "learner", scope: "@learner" },
  { key: "admin", scope: "@admin" },
];

/** The presets of the create form with their text in the language. */
export const presetsFor = (lang = "en"): readonly TokenPreset[] => {
  const t = accountStrings(lang);
  return PRESET_SCOPES.map((p) => ({ key: p.key, scope: p.scope, ...t.presets[p.key] }));
};

export const PRESETS: readonly TokenPreset[] = presetsFor("en");

export const EXPIRY_DAYS = [7, 30, 90, 365] as const;
export const DEFAULT_EXPIRY_DAYS = 90;

const KIND_VALUES = ["cli", "agent", "ci", "integration"] as const;

/** The kinds of the create form with their label in the language. */
export const kindsFor = (lang = "en"): ReadonlyArray<{ value: (typeof KIND_VALUES)[number]; label: string }> => {
  const t = accountStrings(lang);
  return KIND_VALUES.map((value) => ({ value, label: t.kinds[value] }));
};

export const KINDS = kindsFor("en");

export interface TokenRow {
  id: string;
  name: string | null;
  scopes: string[];
  kind: string;
  agent_name: string | null;
  created_via: string | null;
  created_at: string | null;
  expires_at: string | null;
  last_used_at: string | null;
  revoked: boolean;
}

export interface CreatedToken extends TokenRow {
  /** The secret: returned once, never stored. */
  token: string;
}

export interface CreateInput {
  name: string;
  scopes: string[];
  expires_in_days: number;
  kind: string;
}

export type FormResult = { ok: true; input: CreateInput } | { ok: false; error: string };

/** Validates the create form. Unknown values never reach the API. */
export function parseCreateForm(form: FormData, lang = "en"): FormResult {
  const t = accountStrings(lang);
  const name = String(form.get("name") ?? "").trim().replace(/\s+/g, " ");
  if (name === "") return { ok: false, error: t.errNameRequired };
  if (name.length > 100) return { ok: false, error: t.errNameLong };
  if (/[<>]/.test(name)) return { ok: false, error: t.errNameChars };
  const preset = PRESET_SCOPES.find((p) => p.key === String(form.get("access") ?? ""));
  if (!preset) return { ok: false, error: t.errAccessRequired };
  const days = Number(form.get("days"));
  const kind = KINDS.find((k) => k.value === String(form.get("kind") ?? ""));
  return {
    ok: true,
    input: {
      name,
      scopes: [preset.scope],
      expires_in_days: (EXPIRY_DAYS as readonly number[]).includes(days) ? days : DEFAULT_EXPIRY_DAYS,
      kind: kind?.value ?? "cli",
    },
  };
}

/** `@read-only` etc. are expanded by the API; show a stored scope list as short readable text. */
export function summariseScopes(scopes: string[], lang = "en"): string {
  const t = accountStrings(lang);
  if (scopes.includes("*")) return t.scopesEverything;
  const write = scopes.filter((s) => s.endsWith(":write")).map((s) => s.split(":")[0]!);
  const read = scopes.filter((s) => s.endsWith(":read") && !write.includes(s.split(":")[0]!)).map((s) => s.split(":")[0]!);
  const parts: string[] = [];
  if (write.length) parts.push(fill(t.scopesChange, { list: write.join(", ") }));
  if (read.length) parts.push(fill(t.scopesRead, { list: read.join(", ") }));
  return parts.join(" · ") || t.scopesNone;
}

/** Full list for a tooltip-free, screen-reader-friendly expansion. */
export const scopeLabels = (scopes: string[]): string[] => scopes.map((s) => describeScope(s).label);

export type Failure = "unauthenticated" | "forbidden" | "invalid" | "throttled" | "unavailable";

/** The text of a failure in the language (English by default). */
export const failureText = (reason: Failure, lang = "en"): string => accountStrings(lang).failures[reason];

export const FAILURE_TEXT: Record<Failure, string> = accountStrings("en").failures;

const reasonFor = (status: number): Failure =>
  status === 401 ? "unauthenticated" : status === 403 ? "forbidden" : status === 422 ? "invalid" : status === 429 ? "throttled" : "unavailable";

const init = (token: string, extra: RequestInit = {}): RequestInit => ({
  ...extra,
  headers: { Accept: "application/json", "Content-Type": "application/json", Authorization: `Bearer ${token}` },
  signal: AbortSignal.timeout(15_000),
});

export type ListResult = { ok: true; tokens: TokenRow[] } | { ok: false; reason: Failure };
export type CreateResult = { ok: true; token: CreatedToken } | { ok: false; reason: Failure; message?: string };
export type RevokeResult = { ok: true } | { ok: false; reason: Failure };

export async function listTokens(tenant: Tenant, token: string): Promise<ListResult> {
  try {
    const response = await fetch(`${tenant.apiUrl}/api/auth/tokens`, init(token));
    if (!response.ok) return { ok: false, reason: reasonFor(response.status) };
    const body = (await response.json()) as { data?: TokenRow[] };
    return { ok: true, tokens: body.data ?? [] };
  } catch {
    return { ok: false, reason: "unavailable" };
  }
}

export async function createToken(tenant: Tenant, token: string, input: CreateInput): Promise<CreateResult> {
  try {
    const response = await fetch(`${tenant.apiUrl}/api/auth/tokens`, init(token, { method: "POST", body: JSON.stringify(input) }));
    const body = (await response.json().catch(() => ({}))) as { data?: CreatedToken; message?: string };
    if (response.ok && body.data) return { ok: true, token: body.data };
    return { ok: false, reason: reasonFor(response.status), ...(body.message ? { message: body.message } : {}) };
  } catch {
    return { ok: false, reason: "unavailable" };
  }
}

export async function revokeToken(tenant: Tenant, token: string, id: string): Promise<RevokeResult> {
  if (!/^[0-9a-zA-Z]{1,100}$/.test(id)) return { ok: false, reason: "invalid" };
  try {
    const response = await fetch(`${tenant.apiUrl}/api/auth/tokens/${encodeURIComponent(id)}`, init(token, { method: "DELETE" }));
    return response.ok ? { ok: true } : { ok: false, reason: reasonFor(response.status) };
  } catch {
    return { ok: false, reason: "unavailable" };
  }
}

const DATE_LOCALE = { en: "en", pl: "pl-PL", zh: "zh-CN" } as const;

export function formatDate(value: string | null | undefined, lang = "en"): string {
  const t = accountStrings(lang);
  if (!value) return t.never;
  const d = new Date(value);
  const locale = DATE_LOCALE[lang === "pl" || lang === "zh" ? lang : "en"];
  return Number.isNaN(d.getTime()) ? t.unknown : new Intl.DateTimeFormat(locale, { year: "numeric", month: "short", day: "numeric" }).format(d);
}

/** Active tokens first, newest first; revoked ones are not listed by the API unless asked. */
export function sortTokens(rows: TokenRow[]): TokenRow[] {
  return [...rows].sort((a, b) => Number(a.revoked) - Number(b.revoked) || String(b.created_at).localeCompare(String(a.created_at)));
}

export function isExpired(row: Pick<TokenRow, "expires_at">, now = Date.now()): boolean {
  return row.expires_at !== null && new Date(row.expires_at).getTime() < now;
}
