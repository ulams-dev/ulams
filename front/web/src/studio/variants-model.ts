/**
 * View model of the variant parts of the update review (ADR 0192): the badge that names the course a proposal is for,
 * the entries of the "Versions of this update" navigation, and the props that switch on "Accept in all versions" and
 * the reused-change note on an item. No DOM here, so every case is unit-tested. Only what the API returns is shown;
 * a proposal that is in no group reads exactly as before.
 */
import type { ProposalItem, ProposalSibling, ProposalStatus, ProposalSummary, ProposalVariant } from "@ulams/sdk";
import { fill } from "./insights-i18n.ts";
import type { VariantStrings } from "./variants-i18n.ts";

/** Kinds whose patch can be the same in every version (the item kinds `accept-in-group` takes together). */
export const SHARED_KINDS: readonly string[] = ["update", "no_change", "remove"];

type Grouped = Pick<ProposalSummary, "variant" | "proposalGroup">;

/** The name of the course a proposal is for: the variant's label, "Base course", or null outside a group. */
export function courseName(t: VariantStrings, p: { variant?: ProposalVariant | null; proposalGroup?: string | null }): string | null {
  if (p.variant) return p.variant.label || p.variant.key || t.variantFallback;
  return p.proposalGroup ? t.baseCourse : null;
}

export interface GroupEntry {
  id: string;
  number: number;
  /** The course the proposal is for. */
  name: string;
  isCurrent: boolean;
  status: ProposalStatus;
  statusText: string;
  /** "3 decided, 1 undecided"; empty while there is nothing to decide. */
  counts: string;
  /** Set when the proposal waits for a decision, so the entry can be told apart from one that is settled. */
  open: boolean;
}

const OPEN_STATUSES: ProposalStatus[] = ["ready", "budget_blocked", "failed", "awaiting_analysis", "analysing"];

export function countsText(t: VariantStrings, decisions: Partial<Record<string, number>>): string {
  const decided = (decisions.accepted ?? 0) + (decisions.rejected ?? 0);
  const undecided = decisions.pending ?? 0;
  return decided + undecided === 0 ? "" : fill(t.entryCounts, { decided, undecided });
}

/** The proposals of the group in order of their numbers, the open one included; empty when there are no siblings. */
export function groupEntries(t: VariantStrings, current: Pick<ProposalSummary, "id" | "number" | "status" | "variant" | "proposalGroup" | "decisions"> & { siblings?: ProposalSibling[] }): GroupEntry[] {
  const siblings = current.siblings ?? [];
  if (siblings.length === 0) return [];
  const entry = (p: { id: string; number: number; status: ProposalStatus; variant?: ProposalVariant | null; decisions: Partial<Record<string, number>> }, isCurrent: boolean): GroupEntry => ({
    id: p.id,
    number: p.number,
    // inside a group a proposal without a variant is the base course
    name: courseName(t, { variant: p.variant, proposalGroup: "group" })!,
    isCurrent,
    status: p.status,
    statusText: t.statuses[p.status] ?? p.status,
    counts: countsText(t, p.decisions),
    open: OPEN_STATUSES.includes(p.status),
  });
  return [entry(current, true), ...siblings.map((s) => entry(s, false))].sort((a, b) => a.number - b.number);
}

/** Props of an item that turn on the group action and the reused note; empty for an item that is not shared. */
export function groupItemProps(t: VariantStrings | undefined, item: Pick<ProposalItem, "kind" | "flags">, hasSiblings: boolean): { acceptInGroupLabel?: string; reusedNote?: string } {
  if (!t) return {};
  const reused = Boolean(item.flags?.reusedFrom);
  return {
    ...(reused || (hasSiblings && SHARED_KINDS.includes(item.kind)) ? { acceptInGroupLabel: t.acceptInGroup } : {}),
    ...(reused ? { reusedNote: t.reusedNote } : {}),
  };
}

/** "Accepted here and in 2 other versions"; when nothing else was accepted, says so. */
export function acceptedInGroupText(t: VariantStrings, others: number): string {
  if (others <= 0) return t.acceptedHereOnly;
  const category = new Intl.PluralRules(t.htmlLang).select(others);
  return fill(t.acceptedElsewhere[category] ?? t.acceptedElsewhere.other, { n: others });
}

export const isGrouped = (p: Grouped): boolean => Boolean(p.variant) || Boolean(p.proposalGroup);
