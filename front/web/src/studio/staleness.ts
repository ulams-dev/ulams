/**
 * Pure helpers for staleness in the studio: marker texts for the course tree, the "based on §3.2,
 * changed N days ago" note on stale blocks, the course banner and the session-list badge. State is
 * always text plus an icon, never colour alone, and only what the API returns is shown.
 */
import type { Blueprint, BlueprintLesson, StaleElement, StalenessSummary } from "@ulams/sdk";

export type MarkerKind = "answer" | "removed" | "pending";

export interface Marker {
  kind: MarkerKind;
  text: string;
}

/** Marker of one element; dismissed elements carry no marker (the author already decided). */
export function markerFor(element: StaleElement | undefined): Marker | null {
  if (!element || element.status === "dismissed") return null;
  if (element.status === "source_removed") return { kind: "removed", text: "Source removed" };
  if (element.answerCheck) return { kind: "answer", text: "Answer may be wrong" };
  return { kind: "pending", text: "Update pending" };
}

/** The most serious marker wins: a wrong answer, then a removed source, then a pending update. */
const RANK: Record<MarkerKind, number> = { answer: 0, removed: 1, pending: 2 };

export function strongest(markers: Marker[]): Marker | null {
  return [...markers].sort((a, b) => RANK[a.kind] - RANK[b.kind])[0] ?? null;
}

export const staleMap = (elements: StaleElement[]): Map<string, StaleElement> => new Map(elements.map((e) => [e.elementId, e]));

/** Ids of everything inside a lesson that can be stale (the lesson, objectives, blocks, questions). */
export function lessonElementIds(lesson: BlueprintLesson): string[] {
  return [lesson.id, ...lesson.objectives.map((o) => o.id), ...lesson.blocks.map((b) => b.id), ...(lesson.quiz?.questions ?? []).map((q) => q.id)];
}

/** Marker for a lesson row in the tree: the strongest of its elements, with how many are open. */
export function lessonMarker(lesson: BlueprintLesson, stale: Map<string, StaleElement>): (Marker & { count: number }) | null {
  const markers = lessonElementIds(lesson).map((id) => markerFor(stale.get(id))).filter((m): m is Marker => m !== null);
  const top = strongest(markers);
  return top ? { ...top, count: markers.length } : null;
}

export function lessonOf(doc: Blueprint, elementId: string): string | null {
  for (const m of doc.modules) for (const l of m.lessons) if (lessonElementIds(l).includes(elementId)) return l.id;
  return null;
}

export function daysSince(iso: string | null | undefined, now: Date = new Date()): number | null {
  const time = iso ? Date.parse(iso) : NaN;
  return Number.isNaN(time) ? null : Math.max(0, Math.floor((now.getTime() - time) / 86_400_000));
}

export function agoText(days: number | null): string {
  if (days === null) return "recently";
  if (days === 0) return "today";
  return days === 1 ? "1 day ago" : `${days} days ago`;
}

/** "based on §3.2, changed 3 days ago"; fragment labels come from the version's citation map. */
export function staleNote(element: StaleElement, labels: Record<string, string>, now: Date = new Date()): string {
  const cited = element.fragmentIds.map((id) => labels[id]).filter((x): x is string => Boolean(x));
  const sections = [...new Set(cited)].slice(0, 3).join(", ");
  const changed = element.status === "source_removed" ? "removed from the source" : `changed ${agoText(daysSince(element.since, now))}`;
  return sections ? `Based on ${sections}, ${changed}` : `Its source ${changed}`;
}

export interface FreshnessLabel {
  state: "in_sync" | "stale" | "dismissed";
  text: string;
}

/** Badge of a course; null when the course is not tracked against a source (nothing to say). */
export function freshnessLabel(summary: StalenessSummary | null | undefined): FreshnessLabel | null {
  if (!summary || !summary.tracked) return null;
  if (summary.state === "stale") {
    const d = summary.days;
    return { state: "stale", text: d === null ? "Stale" : d === 0 ? "Stale · today" : `Stale · ${d} ${d === 1 ? "day" : "days"}` };
  }
  if (summary.state === "dismissed") return { state: "dismissed", text: "Updates dismissed" };
  return { state: "in_sync", text: "In sync" };
}

export interface BannerModel {
  state: "stale" | "dismissed";
  title: string;
  detail: string;
}

/** Banner for the top of the workspace; null when the course is in sync or not tracked. */
export function bannerModel(summary: StalenessSummary | null | undefined): BannerModel | null {
  if (!summary || !summary.tracked || summary.state === "in_sync") return null;
  if (summary.state === "dismissed") {
    return { state: "dismissed", title: "You kept the earlier version", detail: `The source changed (revision ${summary.latestRevision ?? "?"}) and you chose to keep the course as it was. The next change is compared with that revision.` };
  }
  const n = summary.pendingElements;
  const revision = summary.latestRevision && summary.syncedRevision ? ` The course reflects revision ${summary.syncedRevision}; the latest is ${summary.latestRevision}.` : "";
  const when = summary.days === null ? "" : ` ${summary.days === 0 ? "Since today" : `Since ${summary.days} ${summary.days === 1 ? "day" : "days"} ago`}.`;
  return {
    state: "stale",
    title: n > 0 ? `${n} ${n === 1 ? "element is" : "elements are"} out of date with the source` : "The source changed",
    detail: `${when}${revision}`.trim(),
  };
}

/** Link of the banner: the open proposal when there is one, else the list of updates. */
export function reviewHref(sessionId: string, summary: StalenessSummary | null | undefined): string {
  return summary?.openProposalId ? `/studio/s/${sessionId}/updates/${summary.openProposalId}` : `/studio/s/${sessionId}/updates`;
}
