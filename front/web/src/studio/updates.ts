/**
 * Pure helpers of the update review: API rows to catalogue props (UpdateItem, ImpactSummary),
 * field-level before/after of a blueprint element, counts for the apply bar, analysis progress
 * and the texts of every proposal state. Only values the API returns are shown.
 */
import type { AnalysisStep, ElementNode, ProposalDetail, ProposalGroup, ProposalItem, ProposalStatus, ProposalSummary } from "@ulams/sdk";
import { compact } from "./living.ts";

type Props = Record<string, unknown>;

const cut = (value: string | null | undefined, max: number): string | undefined => (value ? (value.length > max ? `${value.slice(0, max - 1)}…` : value) : undefined);

export const MAX_REGENERATIONS = 3;
/** Kinds that change the course when accepted; the others are acknowledged only. */
export const APPLIED_KINDS = ["update", "remove", "citation_remap"] as const;
/** Kinds the author has to decide on (remaps start accepted, uncovered is informational). */
export const DECISION_KINDS = ["update", "no_change", "remove", "manual"] as const;

export const usdText = (micro: number | null | undefined): string => {
  const value = Number(micro ?? 0) / 1_000_000;
  return value > 0 && value < 0.005 ? "less than $0.01" : `$${value.toFixed(2)}`;
};

/* ------------------------------------------------------------------ element fields */

export interface Field {
  path: string;
  label: string;
  before?: string;
  after?: string;
}

const str = (value: unknown): string | undefined => (typeof value === "string" ? value : undefined);
type Option = { id?: string; text?: string; correct?: boolean };
const optionsOf = (node: ElementNode | null | undefined): Option[] => (Array.isArray(node?.options) ? (node!.options as Option[]) : []);
const letter = (index: number): string => String.fromCharCode(65 + (index % 26));

/** The text fields of an element that the author can read, in order. */
function textFields(type: string, node: ElementNode): Array<[string, string, string | undefined]> {
  switch (type) {
    case "block":
      return [["markdown", "Text", str(node.markdown)]];
    case "question":
      return [["stem", "Question", str(node.stem)], ["explanation", "Explanation", str(node.explanation)]];
    case "objective":
      return [["text", "Learning objective", str(node.text)]];
    case "lesson":
      return [["title", "Lesson title", str(node.title)]];
    case "course":
      return [["title", "Course title", str(node.title)]];
    default:
      return [];
  }
}

/**
 * Before/after text per field. Updates list only what differs; removals list what goes away.
 * Question options are paired by id (new options by position) and marked "(correct)".
 */
export function fieldsFor(item: Pick<ProposalItem, "type" | "kind" | "before" | "after">): Field[] {
  const { type, kind, before, after } = item;
  const out: Field[] = [];
  if (kind === "remove" && before) {
    for (const [path, label, text] of textFields(type, before)) if (text) out.push({ path, label, before: text });
    optionsOf(before).forEach((o, i) => out.push({ path: `options.${i}`, label: `Option ${letter(i)}${o.correct ? " (correct)" : ""}`, before: str(o.text) ?? "" }));
    return out;
  }
  if (kind !== "update" || !before || !after) return out;
  const afterByPath = new Map(textFields(type, after).map(([path, , text]) => [path, text]));
  for (const [path, label, was] of textFields(type, before)) {
    const now = afterByPath.get(path);
    if (was !== now && (was !== undefined || now !== undefined)) out.push(compact({ path, label, before: was, after: now }) as Field);
  }
  if (type === "question") {
    const was = optionsOf(before);
    const now = optionsOf(after);
    const used = new Set<number>();
    const paired = now.map((option, index) => {
      let match = option.id ? was.findIndex((w, i) => w.id === option.id && !used.has(i)) : -1;
      if (match < 0 && !option.id && index < was.length && !used.has(index)) match = index;
      if (match >= 0) used.add(match);
      return { option, index, previous: match >= 0 ? was[match] : undefined };
    });
    for (const { option, index, previous } of paired) {
      const label = `Option ${letter(index)}${option.correct ? " (correct)" : ""}`;
      if (!previous) out.push({ path: `options.${index}`, label, after: str(option.text) ?? "" });
      else if (previous.text !== option.text || Boolean(previous.correct) !== Boolean(option.correct)) {
        const note = previous.correct !== option.correct ? (option.correct ? " (now correct)" : " (no longer correct)") : "";
        out.push({ path: `options.${index}`, label: `${label.replace(" (correct)", "")}${note || (option.correct ? " (correct)" : "")}`, before: str(previous.text) ?? "", after: str(option.text) ?? "" });
      }
    }
    was.forEach((w, i) => {
      if (!used.has(i)) out.push({ path: `options.removed.${i}`, label: `Option ${letter(i)}${w.correct ? " (correct)" : ""} removed`, before: str(w.text) ?? "" });
    });
    if (before.type !== after.type) out.push({ path: "type", label: "Question type", before: str(before.type) ?? "", after: str(after.type) ?? "" });
  }
  return out;
}

/* ------------------------------------------------------------------ props */

export interface ItemContext {
  sessionId: string;
  canDecide: boolean;
  canRegenerate: boolean;
}

export function sourcesText(item: ProposalItem): string | undefined {
  const labels = [...new Set(item.fragments.map((f) => f.label).filter(Boolean))].slice(0, 3);
  return labels.length ? `Based on ${labels.join(", ")}` : undefined;
}

export function itemProps(item: ProposalItem, ctx: ItemContext): Props {
  const grounding = item.flags?.grounding ?? [];
  const fields = fieldsFor(item);
  return compact({
    itemId: item.id,
    elementId: item.elementId,
    elementType: item.type,
    label: cut(item.label, 200) ?? item.elementId,
    kind: item.kind,
    status: item.status,
    reason: cut(item.reason, 500),
    severity: item.severity ?? undefined,
    sources: sourcesText(item),
    fields: fields.length ? fields.slice(0, 60) : undefined,
    citations: item.fragments.length && item.kind !== "uncovered" ? item.fragments.slice(0, 20).map((f) => ({ fragmentId: f.fragmentId, label: cut(f.label, 120) ?? f.fragmentId })) : undefined,
    flags: grounding.length ? grounding.slice(0, 10).map((g) => cut(g, 300)!) : undefined,
    signals: item.flags?.signals?.length ? item.flags.signals.slice(0, 8) : undefined,
    answerCheck: Boolean(item.answerCheck || item.flags?.checkAnswer || item.answerStatus === "unsure"),
    answerChanged: item.changeClass === "answer_changed",
    regenerations: Math.min(10, item.regenerations ?? 0),
    maxRegenerations: MAX_REGENERATIONS,
    canDecide: ctx.canDecide,
    canRegenerate: ctx.canRegenerate,
    href: `/studio/s/${ctx.sessionId}/workspace`,
  });
}

export function impactProps(p: ProposalSummary): Props {
  const c = p.counts;
  return compact({
    elements: c?.elements ?? 0,
    answerChecks: c?.answerChecks ?? 0,
    uncovered: c?.uncovered ?? 0,
    remaps: c?.remaps,
    major: c?.major,
    lessons: c?.groups || undefined,
    estimatedCostMicroUsd: p.estimatedCostMicroUsd ?? undefined,
    costMicroUsd: p.costMicroUsd ?? undefined,
    // what this means for learners has its own panel with the editable note (learners.ts)
  });
}

/* ------------------------------------------------------------------ decisions and apply */

export interface ApplyModel {
  /** Accepted items that change the course (updates, removals and citation updates). */
  applicable: number;
  undecided: number;
  conflicts: number;
  rejected: number;
  canApply: boolean;
}

export function applyModel(detail: Pick<ProposalDetail, "status" | "items">): ApplyModel {
  const items = detail.items;
  const applicable = items.filter((i) => i.status === "accepted" && (APPLIED_KINDS as readonly string[]).includes(i.kind)).length;
  const undecided = items.filter((i) => i.status === "pending" && (DECISION_KINDS as readonly string[]).includes(i.kind)).length;
  const conflicts = items.filter((i) => i.status === "conflict" || i.status === "stale").length;
  const rejected = items.filter((i) => i.status === "rejected").length;
  return { applicable, undecided, conflicts, rejected, canApply: detail.status === "ready" && applicable > 0 };
}

export const applyLabel = (n: number): string => (n === 0 ? "Apply accepted changes" : `Apply ${n} accepted ${n === 1 ? "change" : "changes"}`);

export function applySummary(model: ApplyModel): string {
  const parts = [`${model.applicable} accepted`, `${model.undecided} undecided`, `${model.rejected} rejected`];
  if (model.conflicts > 0) parts.push(`${model.conflicts} in conflict`);
  return parts.join(" · ");
}

/** "2 of 3 decided" for a group; remaps and informational rows do not need a decision. */
export function groupProgress(group: ProposalGroup): string {
  const needs = group.items.filter((i) => (DECISION_KINDS as readonly string[]).includes(i.kind));
  if (needs.length === 0) return "";
  const decided = needs.filter((i) => i.status === "accepted" || i.status === "rejected").length;
  return `${decided} of ${needs.length} decided`;
}

/** Replaces an item in the detail (all and per group) and recomputes the decision counts. */
export function withItem(detail: ProposalDetail, item: ProposalItem, summary?: ProposalSummary): ProposalDetail {
  const swap = (list: ProposalItem[]) => list.map((i) => (i.id === item.id ? item : i));
  const items = swap(detail.items);
  const decisions: Partial<Record<ProposalItem["status"], number>> = {};
  for (const i of items) decisions[i.status] = (decisions[i.status] ?? 0) + 1;
  return { ...detail, ...(summary ?? {}), decisions: summary?.decisions ?? decisions, items, groups: detail.groups.map((g) => ({ ...g, items: swap(g.items) })) };
}

/* ------------------------------------------------------------------ analysis */

export interface StepRow {
  id: string | null;
  groupKey: string;
  label: string;
  status: "pending" | "running" | "done" | "failed";
  error: string | null;
}

const STEP_STATUS: Record<string, StepRow["status"]> = { done: "done", failed: "failed", running: "running", queued: "pending", pending: "pending" };

export function groupLabel(detail: Pick<ProposalDetail, "groups">, key: string, index = 0): string {
  return detail.groups.find((g) => g.key === key)?.label ?? (key === "course" ? "Course" : key === "final_test" ? "Final test" : `Lesson group ${index + 1}`);
}

export function stepRows(detail: Pick<ProposalDetail, "groups" | "steps" | "counts">): StepRow[] {
  const steps: AnalysisStep[] = detail.steps ?? [];
  const rows: StepRow[] = steps.map((s, i) => ({ id: s.id, groupKey: s.groupKey, label: groupLabel(detail, s.groupKey, i), status: STEP_STATUS[s.status] ?? "pending", error: s.error }));
  // a failed group the steps list does not know (no run id): shown without a retry
  for (const key of detail.counts?.failedGroups ?? []) {
    if (!rows.some((r) => r.groupKey === key)) rows.push({ id: null, groupKey: key, label: groupLabel(detail, key, rows.length), status: "failed", error: null });
  }
  return rows;
}

export interface Progress {
  total: number;
  done: number;
  failed: number;
  costMicroUsd?: number;
}

export function progressOf(detail: Pick<ProposalDetail, "groups" | "steps" | "counts" | "costMicroUsd">, live?: Progress | null): Progress {
  if (live && live.total > 0) return live;
  const rows = stepRows(detail);
  return { total: rows.length || detail.counts?.groups || 0, done: rows.filter((r) => r.status === "done").length, failed: rows.filter((r) => r.status === "failed").length, costMicroUsd: detail.costMicroUsd ?? undefined };
}

export const progressText = (p: Progress): string =>
  p.total === 0
    ? "Starting the analysis…"
    : `${p.done} of ${p.total} ${p.total === 1 ? "lesson" : "lessons"} analysed${p.failed ? ` · ${p.failed} failed` : ""}${p.costMicroUsd ? ` · ${usdText(p.costMicroUsd)} so far` : ""}`;

/* ------------------------------------------------------------------ states */

export const PROPOSAL_STATUS_LABEL: Record<ProposalStatus, string> = {
  analysing: "Analysing",
  ready: "Ready to review",
  awaiting_analysis: "Waiting to be analysed",
  budget_blocked: "Budget reached",
  applying: "Applying",
  applied: "Applied",
  no_impact: "No impact",
  rejected: "Rejected",
  superseded: "Replaced by a newer one",
  failed: "Analysis failed",
};

export const analyseLabel = (estimate: number | null | undefined): string => (estimate ? `Analyse (about ${usdText(estimate)})` : "Analyse");

export const SETTLED: ProposalStatus[] = ["applied", "rejected", "superseded", "no_impact"];

export function canDecide(status: ProposalStatus, aiEnabled: boolean): boolean {
  return status === "ready" || status === "budget_blocked" || status === "failed" || (status === "awaiting_analysis" && !aiEnabled);
}

export const needsAttention = (detail: Pick<ProposalDetail, "groups" | "steps" | "counts">): StepRow[] => stepRows(detail).filter((r) => r.status === "failed");

export const rejectAllText = (to: number | null | undefined): string =>
  `This keeps your course exactly as it is and marks source revision ${to ?? ""} as reviewed, so the same changes are not proposed again. It cannot be undone; the next source change is compared with this revision.`.replace("revision  ", "revision ");

export function conflictsText(count: number): string {
  return `${count} ${count === 1 ? "element was" : "elements were"} edited after the analysis and ${count === 1 ? "is" : "are"} marked as conflict. Ask for a new version of ${count === 1 ? "it" : "them"}, or reject ${count === 1 ? "it" : "them"}, then apply again.`;
}
