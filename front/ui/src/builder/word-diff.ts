/**
 * Word-level text diff for the DiffView (jsdiff `diffWords`). Removed and added runs are marked
 * with <del>/<ins> plus visually hidden "removed"/"added" labels, so the state never relies on
 * colour alone (WCAG 1.4.1). `labels` replaces the English hidden labels for another language.
 */
import { diffWords } from "diff";
import { h, sr } from "./dom.ts";

export interface WordDiffLabels {
  /** Hidden text before an added run, e.g. "added: ". */
  added: string;
  /** Hidden text before a removed run, e.g. "removed: ". */
  removed: string;
}

const ENGLISH: WordDiffLabels = { added: "added: ", removed: "removed: " };

export function wordDiff(before: string, after: string, labels: WordDiffLabels = ENGLISH): HTMLElement {
  const out = h("span", { class: "cb-worddiff" });
  for (const part of diffWords(before, after)) {
    if (part.added) out.append(h("ins", {}, sr(labels.added), part.value));
    else if (part.removed) out.append(h("del", {}, sr(labels.removed), part.value));
    else out.append(part.value);
  }
  return out;
}

/**
 * The same diff for a side-by-side view: the `before` column keeps the unchanged words and marks the removed ones, the
 * `after` column keeps the unchanged words and marks the added ones.
 */
export function wordDiffSides(before: string, after: string, labels: WordDiffLabels = ENGLISH): { before: HTMLElement; after: HTMLElement } {
  const left = h("span", { class: "cb-worddiff" });
  const right = h("span", { class: "cb-worddiff" });
  for (const part of diffWords(before, after)) {
    if (part.added) right.append(h("ins", {}, sr(labels.added), part.value));
    else if (part.removed) left.append(h("del", {}, sr(labels.removed), part.value));
    else {
      left.append(part.value);
      right.append(part.value);
    }
  }
  return { before: left, after: right };
}
