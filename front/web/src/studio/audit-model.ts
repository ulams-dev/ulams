/**
 * Audit trail screen (/studio/s/:id/audit): the words, filters and props the island turns into the
 * AuditTable component. Pure functions, tested without a DOM. The trail itself is written by the
 * API in the same transaction as each change and is append-only; nothing here can alter it.
 */
import type { AuditEntry, AuditFilters, AuditVerdict } from "@ulams/sdk";

/** What an action code means, in plain words. Unknown codes are spelled out from the code. */
export const ACTION_LABEL: Record<string, string> = {
  "connection.created": "Source connected",
  "connection.updated": "Connection changed",
  "connection.disconnected": "Source disconnected",
  "connection.secret_rotated": "Access secret replaced",
  "webhook.rejected": "Webhook rejected",
  "revision.detected": "New source revision",
  "revision.no_impact": "Source revision with no impact",
  "revision.failed": "Source check failed",
  "revision.promoted": "Source revision now in the course",
  "proposal.created": "Update proposal created",
  "proposal.analysed": "Update proposal analysed",
  "proposal.superseded": "Proposal replaced by a newer one",
  "proposal.rejected": "Update proposal rejected",
  "proposal.applied": "Update applied",
  "item.accepted": "Change accepted",
  "item.rejected": "Change rejected",
  "item.regenerated": "New version requested",
  "item.reset": "Decision taken back",
  "progress.rules_applied": "Learner progress rules applied",
  "notice.created": "Learner notices created",
};

export function actionLabel(action: string): string {
  const known = ACTION_LABEL[action];
  if (known) return known;
  const words = action.replace(/[._]+/g, " ").trim();
  return words ? words.charAt(0).toUpperCase() + words.slice(1) : "Unknown action";
}

/** The action filter: a group is an action prefix, which the API matches. */
export const ACTION_GROUPS: Array<{ value: string; label: string }> = [
  { value: "", label: "All actions" },
  { value: "connection.", label: "Connections" },
  { value: "webhook.", label: "Webhooks" },
  { value: "revision.", label: "Source revisions" },
  { value: "proposal.", label: "Update proposals" },
  { value: "item.", label: "Decisions on changes" },
  { value: "progress.", label: "Learner progress" },
  { value: "notice.", label: "Learner notices" },
];

export const ACTOR_TYPES: Array<{ value: string; label: string }> = [
  { value: "", label: "Anyone" },
  { value: "user", label: "People" },
  { value: "system", label: "System" },
  { value: "agent", label: "Agents" },
];

export interface AuditFormValues {
  group: string;
  actorType: string;
  /** `YYYY-MM-DD` from a date input, or empty. */
  from: string;
  to: string;
}

const DAY = /^\d{4}-\d{2}-\d{2}$/;

/**
 * Filters for the API. The dates cover whole days; they are sent in the form the API compares
 * with the entry time. An end date before the start date is a mistake the form reports.
 */
export function filtersFrom(form: AuditFormValues): AuditFilters {
  return {
    action: form.group || undefined,
    actorType: form.actorType || undefined,
    from: DAY.test(form.from) ? `${form.from} 00:00:00` : undefined,
    to: DAY.test(form.to) ? `${form.to} 23:59:59` : undefined,
  };
}

export function rangeError(form: AuditFormValues): string | null {
  if (DAY.test(form.from) && DAY.test(form.to) && form.to < form.from) return "The end date is before the start date.";
  return null;
}

export const PER_PAGE = 25;

export function pageCount(total: number, perPage: number): number {
  return Math.max(1, Math.ceil(total / Math.max(1, perPage)));
}

export function rangeText(page: number, perPage: number, total: number): string {
  if (total === 0) return "No entries.";
  const first = (page - 1) * perPage + 1;
  const last = Math.min(total, page * perPage);
  return `Showing ${first} to ${last} of ${total} ${total === 1 ? "entry" : "entries"}.`;
}

export function chainText(verdict: AuditVerdict): string {
  if (verdict.ok) {
    return `Chain verified: ${verdict.checked} ${verdict.checked === 1 ? "entry" : "entries"} checked, none has been changed.`;
  }
  const where = verdict.brokenId !== null ? `entry ${verdict.brokenId}` : "an entry";
  return `Chain broken at ${where}${verdict.reason ? `: ${verdict.reason}` : ""}. Entries after it cannot be trusted until this is investigated.`;
}

const scalar = (value: unknown): value is string | number | boolean => ["string", "number", "boolean"].includes(typeof value);
const humanKey = (key: string): string => key.replace(/[_.]+/g, " ").replace(/([a-z])([A-Z])/g, "$1 $2").toLowerCase();

/** The recorded data as short "key: value" parts; nested values are summarised by count. */
export function dataParts(data: Record<string, unknown> | null | undefined): string[] {
  return Object.entries(data ?? {}).flatMap(([key, value]) => {
    if (value === null || value === undefined || value === "") return [];
    if (scalar(value)) return [`${humanKey(key)}: ${String(value)}`];
    if (Array.isArray(value)) return [`${humanKey(key)}: ${value.length}`];
    if (typeof value === "object") {
      const inner = Object.entries(value as Record<string, unknown>).filter(([, v]) => scalar(v));
      return [`${humanKey(key)}: ${inner.length ? inner.map(([k, v]) => `${humanKey(k)} ${String(v)}`).join(", ") : "recorded"}`];
    }
    return [];
  });
}

/** One line for the table: the reason when the action recorded one, else the first recorded facts. */
export function summaryOf(entry: AuditEntry): string {
  const reason = entry.data?.reason;
  if (typeof reason === "string" && reason.trim() !== "") return reason.trim().slice(0, 300);
  const parts = dataParts(entry.data).slice(0, 4).join("; ");
  if (parts) return parts.slice(0, 300);
  return entry.subject?.type ? `${entry.subject.type}${entry.subject.id ? ` ${entry.subject.id}` : ""}` : "";
}

export function actorName(entry: AuditEntry): string {
  if (entry.actor.type === "system") return "System";
  if (entry.actor.name) return entry.actor.name;
  if (entry.actor.id !== null && entry.actor.id !== undefined) return `User #${entry.actor.id}`;
  return entry.actor.type === "agent" ? "Agent" : "Unknown";
}

const actorKind = (type: string): "user" | "system" | "agent" => (type === "system" || type === "agent" ? type : "user");

/** The full record of one entry for the Details row. Empty values are left out. */
export function detailRows(entry: AuditEntry): Array<{ label: string; value: string; mono?: boolean }> {
  const rows: Array<{ label: string; value: string; mono?: boolean }> = [];
  const add = (label: string, value: string | null | undefined, mono = false) => {
    // the component schema caps a value at 2000 characters
    if (value !== null && value !== undefined && value !== "") rows.push({ label, value: value.slice(0, 1900), ...(mono ? { mono: true } : {}) });
  };
  add("Entry", `#${entry.id}`, true);
  add("Time", entry.at ? new Date(entry.at).toISOString().replace("T", " ").replace(/\.\d+Z$/, " UTC") : null);
  add("Action", entry.action, true);
  add("Who", `${actorName(entry)} (${actorKind(entry.actor.type) === "user" ? "person" : actorKind(entry.actor.type)})`);
  if (entry.actor.onBehalfOf !== null && entry.actor.onBehalfOf !== undefined) add("On behalf of", `User #${entry.actor.onBehalfOf}`);
  add("Subject", entry.subject?.type ? `${entry.subject.type}${entry.subject.id ? ` ${entry.subject.id}` : ""}` : null, true);
  add("Source", entry.sourceId, true);
  add("Source revision", entry.revisionId, true);
  add("Origin", entry.originRef, true);
  const version = (v: number | string | null): string => (v === null || v === undefined || v === "" ? "none" : typeof v === "number" ? `v${v}` : v);
  if (entry.versionFrom || entry.versionTo) add("Course versions", `${version(entry.versionFrom)} to ${version(entry.versionTo)}`, true);
  const calls = entry.aiCallIds?.length ?? 0;
  add("AI calls", calls === 0 ? "None" : `${calls} (${entry.aiCallIds.join(", ")})`);
  const parts = dataParts(entry.data);
  if (parts.length) add("Recorded", parts.join("\n"));
  add("Hash", entry.hash, true);
  add("Previous hash", entry.prevHash ?? "none (first entry)", true);
  return rows;
}

/** Props of the AuditTable component for a page of entries. */
export function tableProps(entries: AuditEntry[], caption: string): Record<string, unknown> {
  return {
    caption,
    emptyText: "No entries match these filters.",
    entries: entries.map((entry) => ({
      id: entry.id,
      at: entry.at ?? undefined,
      action: entry.action,
      actionLabel: actionLabel(entry.action),
      actorType: actorKind(entry.actor.type),
      actor: actorName(entry),
      summary: summaryOf(entry),
      details: detailRows(entry),
    })),
  };
}

export const EMPTY_TEXT = "Nothing has been recorded for this course yet. Connecting a source, uploading a new version and deciding on an update all appear here.";
export const FILTERED_EMPTY_TEXT = "No entries match these filters. Widen the dates or choose All actions.";
