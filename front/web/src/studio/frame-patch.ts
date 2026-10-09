/**
 * Re-rendering the preview in place: when a new blueprint version arrives, the studio fetches the
 * page again and compares it element by element (`data-blueprint-id`). Elements whose markup
 * changed are swapped and highlighted; if elements appeared or disappeared the frame reloads.
 */
export interface MarkedElement {
  /** `id|part#n`: the blueprint id, the page part and the occurrence (an id can mark several parts). */
  key: string;
  html: string;
}

export const elementKey = (id: string, part: string | null | undefined, occurrence: number): string => `${id}|${part ?? ""}#${occurrence}`;

/** Reads the marked elements of a document in order, with stable keys. */
export function markedElements(root: ParentNode): MarkedElement[] {
  const seen = new Map<string, number>();
  return [...root.querySelectorAll<HTMLElement>("[data-blueprint-id]")].map((el) => {
    const base = `${el.dataset.blueprintId}|${el.dataset.blueprintPart ?? ""}`;
    const n = seen.get(base) ?? 0;
    seen.set(base, n + 1);
    return { key: `${base}#${n}`, html: el.innerHTML };
  });
}

export interface FramePatch {
  /** Elements were added or removed: reload the frame. */
  structural: boolean;
  /** Keys of the elements whose markup changed. */
  changed: string[];
}

export function planFramePatch(current: MarkedElement[], next: MarkedElement[]): FramePatch {
  const before = new Map(current.map((e) => [e.key, e.html]));
  const sameSet = current.length === next.length && next.every((e) => before.has(e.key));
  if (!sameSet) return { structural: true, changed: [] };
  return { structural: false, changed: next.filter((e) => before.get(e.key) !== e.html).map((e) => e.key) };
}
