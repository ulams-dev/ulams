/**
 * Comparison table data (src/data/comparison.json) → ComparisonTable props.
 * Every cell carries {value, note, source, checkedAt}; competitor facts come only from
 * official documentation, pricing pages and licences (see the unit test).
 */
import data from "../data/comparison.json";
import plData from "../data/i18n/comparison.pl.json";
import zhData from "../data/i18n/comparison.zh.json";
import type { Locale } from "../i18n/locales.ts";

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
  /** Row sections in display order; each row names its section. */
  sections: Array<{ key: string; label: string }>;
  rows: Array<{ key: string; label: string; help?: string; section: string; groups?: string[] }>;
  systems: Array<{ key: string; name: string; note?: string; ours?: boolean; groups: string[]; cells: Record<string, ComparisonCell> }>;
}

const inGroup = (memberOf: string[] | undefined, group: string) => !memberOf || memberOf.includes(group);

export const comparisonData = data as ComparisonData;

/**
 * Translated labels of the comparison (src/data/i18n/comparison.<locale>.json): group, section and row
 * texts, the product descriptors and the value words. Product names, cell notes, sources and dates are
 * facts and stay as they are.
 */
export interface ComparisonTranslation {
  asOf: string;
  groups: Record<string, { label: string; caption: string }>;
  sections: Record<string, string>;
  rows: Record<string, { label: string; help?: string }>;
  systems: Record<string, string>;
  values: Record<string, string>;
}
export const comparisonTranslations: Partial<Record<Locale, ComparisonTranslation>> = { pl: plData, zh: zhData };

/** The neutral kind of an English value (drives the mark and the colour of a cell). */
const KINDS: Record<string, string> = { yes: "yes", no: "no", partial: "partial", "via plugin": "plugin", "paid add-on": "paid", "not documented": "unknown", coming: "coming" };
export const valueKind = (value: string): string | undefined => KINDS[value.trim().toLowerCase()];

const INTL_LOCALE: Record<Locale, string> = { en: "en-GB", pl: "pl-PL", zh: "zh-CN" };
const formatDate = (iso: string, locale: Locale = "en") => {
  const d = new Date(`${iso}T00:00:00Z`);
  return Number.isNaN(d.getTime()) ? iso : new Intl.DateTimeFormat(INTL_LOCALE[locale], { day: "numeric", month: locale === "zh" ? "long" : "short", year: "numeric", timeZone: "UTC" }).format(d);
};

export function comparisonModel(input: ComparisonData = comparisonData, locale: Locale = "en") {
  const tr = comparisonTranslations[locale];
  const row$ = (key: string, label: string, help?: string) => ({ label: tr?.rows[key]?.label ?? label, help: tr ? (tr.rows[key]?.help ?? help) : help });
  const ordered = [...input.systems].sort((a, b) => Number(Boolean(b.ours)) - Number(Boolean(a.ours)));
  const groups = input.groups.map((g) => {
    const systems = ordered.filter((s) => inGroup(s.groups, g.key));
    const rows = input.rows.filter((r) => inGroup(r.groups, g.key));
    return {
      label: tr?.groups[g.key]?.label ?? g.label,
      caption: tr?.groups[g.key]?.caption ?? g.caption,
      columns: systems.map((s) => ({ label: s.name, note: (tr?.systems[s.key] ?? s.note) || undefined, highlight: Boolean(s.ours) })),
      sections: input.sections
        .map((section) => ({
          label: tr?.sections[section.key] ?? section.label,
          rows: rows
            .filter((row) => row.section === section.key)
            .map((row) => ({
              ...row$(row.key, row.label, row.help),
              cells: systems.map((s) => {
                const cell = s.cells[row.key];
                const value = cell?.value ?? "Not documented";
                const shown = tr?.values[value] ?? value;
                // a translated value keeps the kind of the English one (mark and colour)
                return { value: shown, ...(cell?.note ? { note: cell.note } : {}), ...(tr && valueKind(value) ? { kind: valueKind(value) } : {}) };
              }),
            })),
        }))
        .filter((section) => section.rows.length > 0),
    };
  });
  // one source entry per system and URL, listing the rows it supports (rows shown in a group the system is in)
  const sources: Array<{ label: string; href: string; checked: string }> = [];
  for (const s of ordered) {
    const byUrl = new Map<string, { rows: string[]; checked: string }>();
    for (const row of input.rows) {
      const cell = s.cells[row.key];
      if (!cell) continue;
      const rowLabel = row$(row.key, row.label).label;
      const entry = byUrl.get(cell.source) ?? { rows: [], checked: cell.checkedAt };
      entry.rows.push(rowLabel);
      if (cell.checkedAt > entry.checked) entry.checked = cell.checkedAt;
      byUrl.set(cell.source, entry);
    }
    for (const [href, entry] of byUrl) sources.push({ label: `${s.name}: ${entry.rows.join(", ")}`, href, checked: formatDate(entry.checked, locale) });
  }
  return { asOf: tr?.asOf ?? input.asOf, groups, sources };
}
