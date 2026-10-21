import type { InsightsCourse, InsightsMe, InsightsReason, InsightsSettings, SettingsChange, Tenant } from "@ulams/sdk";

/**
 * "Personalisation & privacy" (Learner Insights, ADR 0061): the learner sees their own status with the reasons,
 * switches personal help and reminders off, opts out of everything, downloads what is stored about them and
 * erases it. Plain forms posted to /account/personalisation, no script. The API scopes every call to the caller
 * and decides what is recorded; this module validates the forms and forwards with the learner's own token.
 */
export type { InsightsCourse, InsightsMe, InsightsReason, InsightsSettings, SettingsChange };

export type Failure = "unauthenticated" | "unavailable";

export const FAILURE_TEXT: Record<Failure, string> = {
  unauthenticated: "Your session has expired. Sign in again.",
  unavailable: "The server could not be reached. Try again in a moment.",
};

/** The status in words: never colour alone. */
export const STATUS_TEXT: Record<InsightsCourse["status"], { label: string; help: string }> = {
  on_track: { label: "On track", help: "Nothing to flag." },
  struggling: { label: "Needs a closer look", help: "Some parts of this course have been hard. Extra help may be offered." },
  at_risk: { label: "Taking a break", help: "You have been away from this course for a while. We may send one reminder." },
};

export const statusText = (status: string): { label: string; help: string } => STATUS_TEXT[status as InsightsCourse["status"]] ?? { label: status, help: "" };

const init = (token: string, extra: RequestInit = {}): RequestInit => ({
  ...extra,
  headers: { Accept: "application/json", "Content-Type": "application/json", Authorization: `Bearer ${token}` },
  signal: AbortSignal.timeout(15_000),
});

const failure = (status: number): Failure => (status === 401 ? "unauthenticated" : "unavailable");

export type LoadResult = { ok: true; me: InsightsMe } | { ok: false; reason: Failure };

export async function getInsights(tenant: Pick<Tenant, "apiUrl">, token: string): Promise<LoadResult> {
  try {
    const response = await fetch(`${tenant.apiUrl}/api/learner-insights/me`, init(token));
    if (!response.ok) return { ok: false, reason: failure(response.status) };
    const body = (await response.json()) as { data?: InsightsMe };
    return body.data ? { ok: true, me: body.data } : { ok: false, reason: "unavailable" };
  } catch {
    return { ok: false, reason: "unavailable" };
  }
}

/** An unchecked checkbox is absent from a form post, so the form carries a marker and every switch is read by presence. */
export function parseSettingsForm(form: FormData, requiresConsent: boolean): SettingsChange {
  const on = (name: string) => form.get(name) === "on";
  const change: SettingsChange = { enabled: on("enabled"), help: on("help"), nudges: on("nudges") };
  if (requiresConsent) change.consent = on("consent");
  if (!change.enabled && on("erase_existing")) change.eraseExisting = true;
  return change;
}

export type ActionResult = { ok: true } | { ok: false; reason: Failure };

export async function saveSettings(tenant: Pick<Tenant, "apiUrl">, token: string, change: SettingsChange): Promise<ActionResult> {
  try {
    const response = await fetch(`${tenant.apiUrl}/api/learner-insights/me/settings`, init(token, { method: "PUT", body: JSON.stringify(change) }));
    return response.ok ? { ok: true } : { ok: false, reason: failure(response.status) };
  } catch {
    return { ok: false, reason: "unavailable" };
  }
}

export async function eraseInsights(tenant: Pick<Tenant, "apiUrl">, token: string): Promise<ActionResult> {
  try {
    const response = await fetch(`${tenant.apiUrl}/api/learner-insights/me`, init(token, { method: "DELETE" }));
    return response.ok ? { ok: true } : { ok: false, reason: failure(response.status) };
  } catch {
    return { ok: false, reason: "unavailable" };
  }
}

/** The JSON file of everything stored about the learner, as the API sends it. */
export async function exportInsights(tenant: Pick<Tenant, "apiUrl">, token: string): Promise<{ ok: true; body: string } | { ok: false; reason: Failure }> {
  try {
    const response = await fetch(`${tenant.apiUrl}/api/learner-insights/me/export`, init(token));
    return response.ok ? { ok: true, body: await response.text() } : { ok: false, reason: failure(response.status) };
  } catch {
    return { ok: false, reason: "unavailable" };
  }
}
