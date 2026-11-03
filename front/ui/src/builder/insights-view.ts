/**
 * Building blocks of the studio Insights page (client side, DOM only): a figure with its label, a rate with a
 * decorative bar, and a data table. Meaning never rests on colour or on the bar: every rate is written out
 * as a number, the bar is `aria-hidden`, and the table has a caption, column headers, a row header and a
 * keyboard-reachable scroll region on narrow screens. Text always goes in through textContent.
 */
import { h, uid } from "./dom.ts";

type Cell = Node | string | number | null;

/** A rate (0 to 1) as text with a decorative bar. `null` is "no figure": the text says why, no bar is drawn. */
export function rateBar(rate: number | null, text: string): HTMLElement {
  const fill = h("span", { class: "cb-rate-fill" });
  const clamped = rate === null || Number.isNaN(rate) ? null : Math.min(1, Math.max(0, rate));
  if (clamped !== null) fill.style.width = `${Math.round(clamped * 100)}%`;
  return h("span", { class: "cb-rate", "data-rate": clamped === null ? "none" : String(clamped) },
    h("span", { class: "cb-rate-text" }, text),
    clamped === null ? null : h("span", { class: "cb-rate-bar", "aria-hidden": "true" }, fill));
}

export interface Figure {
  label: string;
  /** The value as text, already formatted for the reader's language. */
  value: string | Node;
  /** A short explanation under the value. */
  note?: string;
}

/** Labelled figures as a description list: the label is the term, the value the description. */
export function figureList(figures: Figure[], label?: string): HTMLElement {
  return h("dl", { class: "cb-figures", "aria-label": label },
    figures.map((f) => h("div", { class: "cb-figure" },
      h("dt", {}, f.label),
      h("dd", {}, f.value, f.note ? h("span", { class: "cb-muted cb-small cb-figure-note" }, f.note) : null))));
}

export interface TableColumn {
  label: string;
  /** Right-aligned figures. */
  numeric?: boolean;
}

export interface TableRow {
  id: string;
  /** The first cell is the row header. */
  cells: Cell[];
  /** A row withheld for privacy: shown dimmed, its figures are replaced by `cells`. */
  withheld?: boolean;
}

export interface TableOptions {
  caption: string;
  columns: TableColumn[];
  rows: TableRow[];
  /** Accessible name of the scrolling region, e.g. "Sections (scrolls sideways on small screens)". */
  scrollLabel: string;
}

export function dataTable(options: TableOptions): HTMLElement {
  const id = uid("ins-table");
  const head = h("tr", {}, options.columns.map((c) => h("th", { scope: "col", class: c.numeric ? "cb-num" : "" }, c.label)));
  const body = options.rows.map((row) =>
    h("tr", { "data-row": row.id, class: row.withheld ? "cb-row-withheld" : "" },
      row.cells.map((cell, i) => {
        const numeric = options.columns[i]?.numeric ? "cb-num" : "";
        return i === 0 ? h("th", { scope: "row", class: numeric }, cell) : h("td", { class: numeric }, cell);
      })));
  return h("div", { class: "cb-ins-scroll", role: "region", "aria-label": options.scrollLabel, tabindex: "0" },
    h("table", { class: "cb-ins-table", id },
      h("caption", { class: "cb-sr" }, options.caption),
      h("thead", {}, head),
      h("tbody", {}, body)));
}

export interface EvidenceFigure {
  label: string;
  /** The value as text (`Not enough data` for a missing figure). */
  value: string;
  /** A rate (0 to 1) shown with a decorative bar next to the text; omit for counts. */
  rate?: number | null;
  note?: string;
}

export interface EvidenceCardOptions {
  title: string;
  /** What the numbers are and what they are not (groups, never a person). */
  intro: string;
  /** Accessible name of the figures. */
  figuresLabel: string;
  figures: EvidenceFigure[];
  reasonsTitle: string;
  reasons: string[];
  noReasons: string;
  /** Wrong answer options and their share of the group, for quiz questions. */
  wrong?: { title: string; intro: string; table: TableOptions } | null;
  /** What the model receives, when the card is shown before anything is sent. */
  sent?: { title: string; text: string } | null;
  /** Language of the card when it differs from the page around it. */
  lang?: string;
  /** Heading level of the card title (its sections are one level lower); 2 by default. */
  level?: 2 | 3 | 4;
}

/**
 * The "Why this was proposed" card (ADR 0181): aggregated evidence only. Every rate is written as text and the bar is
 * decorative; the card is a labelled section with its own headings so it works on the Insights page and on the review
 * screen. Text goes in through textContent.
 */
export function evidenceCard(o: EvidenceCardOptions): HTMLElement {
  const id = uid("ev-h");
  const title = `h${o.level ?? 2}` as "h2" | "h3" | "h4";
  const sub = `h${(o.level ?? 2) + 1}` as "h3" | "h4" | "h5";
  const figures = h("dl", { class: "cb-figures", "aria-label": o.figuresLabel },
    o.figures.map((f) => h("div", { class: "cb-figure" },
      h("dt", {}, f.label),
      h("dd", {}, f.rate === undefined ? f.value : rateBar(f.rate, f.value), f.note ? h("span", { class: "cb-muted cb-small cb-figure-note" }, f.note) : null))));
  const reasonsId = uid("ev-reasons");
  const reasons = o.reasons.length > 0
    ? h("ul", { class: "st-evidence-reasons", "aria-labelledby": reasonsId }, o.reasons.map((r) => h("li", {}, r)))
    : h("p", { class: "cb-muted" }, o.noReasons);
  const wrongId = uid("ev-wrong");
  return h("section", { class: "cb-card st-evidence", "aria-labelledby": id, "data-evidence": "", lang: o.lang },
    h(title, { id, class: "cb-h3" }, o.title),
    h("p", { class: "cb-muted" }, o.intro),
    figures,
    h(sub, { id: reasonsId, class: "cb-h3" }, o.reasonsTitle),
    reasons,
    o.wrong ? h(sub, { id: wrongId, class: "cb-h3" }, o.wrong.title) : null,
    o.wrong ? h("p", { class: "cb-muted cb-small" }, o.wrong.intro) : null,
    o.wrong ? dataTable(o.wrong.table) : null,
    o.sent ? h(sub, { class: "cb-h3" }, o.sent.title) : null,
    o.sent ? h("p", { class: "cb-muted" }, o.sent.text) : null);
}
