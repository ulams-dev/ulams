/**
 * Comparison table data (src/data/comparison.json) → ComparisonTable props.
 * Every cell carries {value, note, source, checkedAt}; competitor facts come only from
 * official documentation, pricing pages and licences (see the unit test).
 */
import data from "../data/comparison.json";

export interface ComparisonCell {
  value: string;
  note?: string;
  source: string;
  checkedAt: string;
}

export interface ComparisonData {
  asOf: string;
  /** Segmented groups, in display order. A row or system without `groups` belongs to every group. */
  groups: Array<{ key: string; label: string; caption: string }>;
  rows: Array<{ key: string; label: string; help?: string; groups?: string[] }>;
  systems: Array<{ key: string; name: string; note?: string; ours?: boolean; groups: string[]; cells: Record<string, ComparisonCell> }>;
}

const inGroup = (memberOf: string[] | undefined, group: string) => !memberOf || memberOf.includes(group);

export const comparisonData = data as ComparisonData;

const formatDate = (iso: string) => {
  const d = new Date(`${iso}T00:00:00Z`);
  return Number.isNaN(d.getTime()) ? iso : new Intl.DateTimeFormat("en-GB", { day: "numeric", month: "short", year: "numeric", timeZone: "UTC" }).format(d);
};

export function comparisonModel(input: ComparisonData = comparisonData) {
  const ordered = [...input.systems].sort((a, b) => Number(Boolean(b.ours)) - Number(Boolean(a.ours)));
  const groups = input.groups.map((g) => {
    const systems = ordered.filter((s) => inGroup(s.groups, g.key));
    const rows = input.rows.filter((r) => inGroup(r.groups, g.key));
    return {
      label: g.label,
      caption: g.caption,
      columns: systems.map((s) => ({ label: s.name, note: s.note, highlight: Boolean(s.ours) })),
      rows: rows.map((row) => ({
        label: row.label,
        help: row.help,
        cells: systems.map((s) => {
          const cell = s.cells[row.key];
          return cell ? { value: cell.value, note: cell.note } : { value: "Not documented" };
        }),
      })),
    };
  });
  // one source entry per system and URL, listing the rows it supports (rows shown in a group the system is in)
  const sources: Array<{ label: string; href: string; checked: string }> = [];
  for (const s of ordered) {
    const byUrl = new Map<string, { rows: string[]; checked: string }>();
    for (const row of input.rows) {
      const cell = s.cells[row.key];
      if (!cell) continue;
      const entry = byUrl.get(cell.source) ?? { rows: [], checked: cell.checkedAt };
      entry.rows.push(row.label);
      if (cell.checkedAt > entry.checked) entry.checked = cell.checkedAt;
      byUrl.set(cell.source, entry);
    }
    for (const [href, entry] of byUrl) sources.push({ label: `${s.name}: ${entry.rows.join(", ")}`, href, checked: formatDate(entry.checked) });
  }
  return { asOf: input.asOf, groups, sources };
}
