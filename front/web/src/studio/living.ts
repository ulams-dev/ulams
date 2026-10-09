/**
 * Pure helpers of the Sources page: API rows to catalogue props, upload result messages and the
 * cosmetic-change filter. Only values the API returns are shown; nothing is invented.
 */
import { scheduleLabel } from "./connect-model.ts";
import type { ChangeCounts, FragmentChangeRow, LivingSource, SourceRevision } from "@ulams/sdk";

type Props = Record<string, unknown>;

const cut = (value: string | null | undefined, max: number): string | undefined => (value ? (value.length > max ? `${value.slice(0, max - 1)}…` : value) : undefined);

/** Drops undefined and null so the props validate against the catalogue schema. */
export function compact<T extends Props>(props: T): T {
  return Object.fromEntries(Object.entries(props).filter(([, v]) => v !== undefined && v !== null)) as T;
}

export type SyncState = "up_to_date" | "new_version" | "processing" | "failed" | "paused";

export function syncState(source: LivingSource): SyncState {
  const c = source.connection;
  if (source.status === "failed" || c?.lastError || c?.latestRevision?.status === "failed") return "failed";
  if (source.status !== "ready") return "processing";
  if (c && c.status !== "active") return "paused";
  if (c?.latestRevision && (c.syncedRevision === null || c.latestRevision.number > c.syncedRevision.number)) return "new_version";
  return "up_to_date";
}

export function cardProps(source: LivingSource): Props {
  const c = source.connection;
  return compact({
    sourceId: source.id,
    name: cut(source.title ?? source.name, 255) ?? source.name,
    kind: source.kind,
    connector: c?.connector ?? "upload",
    state: syncState(source),
    syncedRevision: c?.syncedRevision?.number,
    latestRevision: c?.latestRevision?.number,
    lastCheckedAt: c?.lastCheckedAt,
    // uploads have no schedule: a new version appears when the author uploads it
    schedule: c && c.connector !== "upload" ? scheduleLabel(c.schedule) : undefined,
    error: cut(c?.lastError ?? (source.status === "failed" ? "The source could not be read." : null), 500),
    // an upload has nothing to check; a paused connection must be resumed first
    canCheck: Boolean(c && c.connector !== "upload" && c.status === "active"),
    canUpload: source.status === "ready",
  });
}

export function timelineProps(sourceId: string, revisions: SourceRevision[], selectedId: string | null): Props {
  return compact({
    sourceId,
    selectedRevisionId: selectedId ?? undefined,
    revisions: revisions.map((r) =>
      compact({
        id: r.id,
        number: r.number,
        origin: r.origin,
        trigger: r.trigger,
        detectedAt: r.detectedAt,
        status: r.status,
        counts: r.counts ?? undefined,
        synced: r.synced ?? undefined,
        latest: r.latest ?? undefined,
        error: cut(r.error, 500),
      })
    ),
  });
}

export function changeProps(row: FragmentChangeRow): Props {
  const side = (f: FragmentChangeRow["old"]) =>
    f ? compact({ fragmentId: f.fragmentId, label: cut(f.label, 160), text: f.text }) : undefined;
  const where = (row.new ?? row.old)?.headingPath?.join(" › ") || (row.new ?? row.old)?.section;
  return compact({
    changeId: String(row.id),
    kind: row.kind,
    magnitude: row.magnitude,
    similarity: Number.isFinite(row.similarity) ? Math.min(1, Math.max(0, row.similarity)) : undefined,
    signals: row.signals?.length ? row.signals : undefined,
    section: cut(where ?? undefined, 300),
    old: side(row.old),
    new: side(row.new),
    wordDiff: row.wordDiff && row.wordDiff.length ? row.wordDiff : undefined,
  });
}

/** Cosmetic (trivial) changes are hidden until the author asks for them. */
export function splitChanges(rows: FragmentChangeRow[]): { shown: FragmentChangeRow[]; cosmetic: FragmentChangeRow[] } {
  return { shown: rows.filter((r) => r.magnitude !== "trivial"), cosmetic: rows.filter((r) => r.magnitude === "trivial") };
}

export const cosmeticToggleLabel = (count: number, open: boolean): string =>
  open ? `Hide ${count} cosmetic ${count === 1 ? "change" : "changes"}` : `Show ${count} cosmetic ${count === 1 ? "change" : "changes"}`;

/** "3 changed, 1 removed, 2 added, 1 moved" without zero parts. */
export function countsSentence(counts: ChangeCounts | null | undefined): string {
  if (!counts) return "";
  return (["changed", "removed", "added", "moved"] as const)
    .filter((k) => counts[k] > 0)
    .map((k) => `${counts[k]} ${k}`)
    .join(", ");
}

export function uploadMessage(result: { unchanged: boolean; created: boolean; revision: SourceRevision }): string {
  const n = result.revision.number;
  if (result.unchanged || !result.created) return `This file is already the latest revision (revision ${n}). Nothing new to review.`;
  const counts = countsSentence(result.revision.counts);
  return counts ? `Revision ${n} added: ${counts}. The changes are shown below.` : `Revision ${n} added. No changes to the content were found.`;
}

export const emptyChangesText = (connector: string | undefined): string =>
  connector === "upload" || !connector
    ? "No source changes yet. New versions appear here when you upload them."
    : "No source changes yet. We check daily.";

export const ACCEPTED_FILES = ".md,.markdown,.txt,.pdf,.docx,text/markdown,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document";
