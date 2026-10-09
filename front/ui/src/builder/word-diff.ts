/**
 * Word-level text diff for the DiffView (jsdiff `diffWords`). Removed and added runs are marked
 * with <del>/<ins> plus visually hidden "removed"/"added" labels, so the state never relies on
 * colour alone (WCAG 1.4.1).
 */
import { diffWords } from "diff";
import { h, sr } from "./dom.ts";

export function wordDiff(before: string, after: string): HTMLElement {
  const out = h("span", { class: "cb-worddiff" });
  for (const part of diffWords(before, after)) {
    if (part.added) out.append(h("ins", {}, sr("added: "), part.value));
    else if (part.removed) out.append(h("del", {}, sr("removed: "), part.value));
    else out.append(part.value);
  }
  return out;
}
