import { conflictFileName, renderConflict, type ConflictMeta } from "./conflicts.ts";
import { canonicalJson, flatten } from "./sync.ts";
import { mergeDocuments, type Conflict, type MergeResult } from "./merge.ts";
import type { Obj } from "./types.ts";

/* Glue between the pure merge and the folder: what `course pull` writes after combining the folder with the server's
 * newer version. */

export interface VersionRef {
  versionId: string;
  versionNumber: number;
}

export interface ConflictReport {
  element: string;
  type: string;
  label: string;
  kind: Conflict["kind"];
  fields: string[];
  held: number;
  file: string;
}

export interface FolderMerge {
  /** The merged document, ready to be written as files (the server's `sources` are kept). */
  doc: Obj;
  result: MergeResult;
  reports: ConflictReport[];
  /** Conflict file name (without .md) -> text. */
  conflictFiles: Map<string, string>;
}

/** Merges the folder (`local`) with the server's newer document (`remote`) on top of what both started from (`base`). */
export function mergeFolder(base: Obj, local: Obj, remote: Obj, baseRef: VersionRef, remoteRef: VersionRef): FolderMerge {
  const result = mergeDocuments(base, local, remote);
  const labels = new Map<string, string>();
  for (const doc of [base, remote, local]) for (const [id, e] of flatten(doc)) labels.set(id, e.label);
  // `sources` is informational (written by pull): the server's list when it changed since the base, otherwise the folder's own
  const sources = canonicalJson(base.sources ?? null) !== canonicalJson(remote.sources ?? null) ? remote.sources : local.sources;
  const doc: Obj = { ...result.doc, ...(sources !== undefined ? { sources } : {}) };
  const reports: ConflictReport[] = [];
  const conflictFiles = new Map<string, string>();
  for (const c of result.conflicts) {
    const label = labels.get(c.id) ?? (c.id === "$doc" ? "Course settings" : c.id);
    const meta: ConflictMeta = { element: c.id, type: c.type, kind: c.kind, base: { versionId: baseRef.versionId, versionNumber: baseRef.versionNumber }, remote: { versionId: remoteRef.versionId, versionNumber: remoteRef.versionNumber } };
    const name = conflictFileName(c.id);
    conflictFiles.set(name, renderConflict(c, meta, label));
    reports.push({ element: c.id, type: c.type, label, kind: c.kind, fields: c.fields.map((f) => f.field), held: c.held.length, file: `.ulams/conflicts/${name}.md` });
  }
  return { doc, result, reports, conflictFiles };
}

/** The elements of a merge that came from the server, in words, for the pull report. */
export function takenLines(taken: Array<{ id: string; kind: string }>, doc: Obj, other: Obj): string[] {
  const labels = new Map<string, string>();
  for (const d of [other, doc]) for (const [id, e] of flatten(d)) labels.set(id, `${e.label}${e.summary ? ` "${e.summary}"` : ""}`);
  return taken.slice(0, 40).map((t) => `  ${t.kind === "added" ? "+" : t.kind === "removed" ? "-" : "~"} ${labels.get(t.id) ?? t.id} (${t.kind})`);
}
