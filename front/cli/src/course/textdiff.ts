/* A small unified line diff (plan section 15: no `diff` dependency). Longest common subsequence over lines with a
 * cap on the table size, so a pathological element cannot take the CLI down; past the cap the diff is one big hunk. */

const MAX_CELLS = 4_000_000;

type Op = { kind: " " | "-" | "+"; line: string };

function ops(a: string[], b: string[]): Op[] {
  if (a.length * b.length > MAX_CELLS) return [...a.map((line): Op => ({ kind: "-", line })), ...b.map((line): Op => ({ kind: "+", line }))];
  const n = a.length;
  const m = b.length;
  const lcs: number[][] = Array.from({ length: n + 1 }, () => new Array<number>(m + 1).fill(0));
  for (let i = n - 1; i >= 0; i--) {
    for (let j = m - 1; j >= 0; j--) {
      (lcs[i] as number[])[j] = a[i] === b[j] ? ((lcs[i + 1] as number[])[j + 1] as number) + 1 : Math.max((lcs[i + 1] as number[])[j] as number, (lcs[i] as number[])[j + 1] as number);
    }
  }
  const out: Op[] = [];
  let i = 0;
  let j = 0;
  while (i < n && j < m) {
    if (a[i] === b[j]) {
      out.push({ kind: " ", line: a[i] as string });
      i++;
      j++;
    } else if (((lcs[i + 1] as number[])[j] as number) >= ((lcs[i] as number[])[j + 1] as number)) out.push({ kind: "-", line: a[i++] as string });
    else out.push({ kind: "+", line: b[j++] as string });
  }
  while (i < n) out.push({ kind: "-", line: a[i++] as string });
  while (j < m) out.push({ kind: "+", line: b[j++] as string });
  return out;
}

/** Unified diff of two texts (three lines of context); an empty string when they are equal. */
export function unifiedDiff(from: string, to: string, fromLabel: string, toLabel: string, context = 3): string {
  const all = ops(from === "" ? [] : from.split("\n"), to === "" ? [] : to.split("\n"));
  if (all.every((o) => o.kind === " ")) return "";
  const keep = new Array<boolean>(all.length).fill(false);
  all.forEach((o, idx) => {
    if (o.kind === " ") return;
    for (let k = Math.max(0, idx - context); k <= Math.min(all.length - 1, idx + context); k++) keep[k] = true;
  });
  const lines = [`--- ${fromLabel}`, `+++ ${toLabel}`];
  let oldNo = 1;
  let newNo = 1;
  let idx = 0;
  while (idx < all.length) {
    if (!keep[idx]) {
      const o = all[idx] as Op;
      if (o.kind !== "+") oldNo++;
      if (o.kind !== "-") newNo++;
      idx++;
      continue;
    }
    const start = idx;
    const hunkOld = oldNo;
    const hunkNew = newNo;
    const body: string[] = [];
    let oldCount = 0;
    let newCount = 0;
    while (idx < all.length && keep[idx]) {
      const o = all[idx] as Op;
      body.push(`${o.kind}${o.line}`);
      if (o.kind !== "+") {
        oldNo++;
        oldCount++;
      }
      if (o.kind !== "-") {
        newNo++;
        newCount++;
      }
      idx++;
    }
    void start;
    lines.push(`@@ -${hunkOld},${oldCount} +${hunkNew},${newCount} @@`, ...body);
  }
  return lines.join("\n");
}
