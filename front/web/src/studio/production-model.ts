/**
 * View model of the studio Production page: turns the authoring-metrics API's answer into the figures, table rows and
 * files the page shows, in the reader's language. No DOM here, so every state is unit-tested. Nothing is estimated:
 * a figure the API did not record (author time) is a sentence saying so, never a zero.
 */
import { fill, PRODUCTION_STRINGS, type ProductionLocale, type ProductionStrings } from "./production-i18n.ts";

export interface StageRow {
  run_id: string;
  run_kind: string;
  stage: string;
  started_at: string;
  finished_at: string | null;
  seconds: number | null;
  quality: string;
}

export interface CourseMetricData {
  course_id: number;
  session_id: string;
  method_version: number;
  computed_at: string;
  time: { generation_seconds: number; ai_edit_seconds: number; interview_seconds: number; model_seconds: number; author_minutes: number | null };
  cost: { build_micro_usd: number; maintenance_micro_usd: number; llm_calls: number; llm_calls_failed: number };
  counts: { lessons: number; questions: number; blocks: number; cited_blocks: number; learner_minutes_estimate: number };
  elements: { ai_elements: number; accepted_unchanged: number; revised_on_request: number; edited_by_author: number; unknown: number; author_written: number };
  stages?: StageRow[];
}

export interface ProductionFormatter {
  t: ProductionStrings;
  number: (n: number) => string;
  percent: (rate: number) => string;
  usd: (micro: number) => string;
  date: (iso: string) => string;
  /** 417 s → "6 min 57 s", 3725 s → "1 h 2 min", 24 s → "24 s". */
  duration: (seconds: number) => string;
}

export function productionFormatterFor(locale: ProductionLocale): ProductionFormatter {
  const t = PRODUCTION_STRINGS[locale];
  const number = new Intl.NumberFormat(t.htmlLang);
  const percent = new Intl.NumberFormat(t.htmlLang, { style: "percent", maximumFractionDigits: 0 });
  const date = new Intl.DateTimeFormat(t.htmlLang, { dateStyle: "medium", timeStyle: "short" });
  const small = new Intl.NumberFormat(t.htmlLang, { style: "currency", currency: "USD", minimumFractionDigits: 4, maximumFractionDigits: 4 });
  const big = new Intl.NumberFormat(t.htmlLang, { style: "currency", currency: "USD", minimumFractionDigits: 2, maximumFractionDigits: 2 });
  return {
    t,
    number: (n) => number.format(n),
    percent: (rate) => percent.format(rate),
    usd: (micro) => (micro < 1_000_000 ? small : big).format(micro / 1_000_000),
    date: (iso) => {
      const d = new Date(iso);
      return Number.isNaN(d.getTime()) ? "–" : date.format(d);
    },
    duration: (seconds) => {
      const total = Math.max(0, Math.round(seconds));
      if (total < 60) return fill(t.durationSeconds, { s: number.format(total) });
      if (total < 3600) return fill(t.durationMinutes, { m: number.format(Math.floor(total / 60)), s: number.format(total % 60) });
      return fill(t.durationHours, { h: number.format(Math.floor(total / 3600)), m: number.format(Math.floor((total % 3600) / 60)) });
    },
  };
}

export const stageName = (t: ProductionStrings, stage: string): string => t.stages[stage] ?? (stage.startsWith("variant:") ? stage.slice(8) : stage);

/** Rows of the stage table, in the order the stages ran; a stage that never finished has no time and says why. */
export function stageRows(f: ProductionFormatter, stages: StageRow[]): Array<{ id: string; cells: string[] }> {
  return stages.map((s, i) => ({
    id: `${s.run_id}:${s.stage}:${i}`,
    cells: [stageName(f.t, s.stage), f.t.runs[s.run_kind] ?? s.run_kind, s.seconds === null ? "–" : f.duration(s.seconds), f.t.quality[s.quality] ?? s.quality],
  }));
}

export interface Total {
  key: string;
  label: string;
  value: string;
  note?: string;
}

export function totals(f: ProductionFormatter, m: CourseMetricData): Total[] {
  const t = f.t;
  return [
    { key: "generation", label: t.generation, value: f.duration(m.time.generation_seconds), note: t.generationNote },
    { key: "ai-edit", label: t.aiEdit, value: f.duration(m.time.ai_edit_seconds) },
    { key: "interview", label: t.interview, value: f.duration(m.time.interview_seconds), note: t.interviewNote },
    { key: "model", label: t.model, value: f.duration(m.time.model_seconds), note: t.modelNote },
    {
      key: "author",
      label: t.authorTime,
      value: m.time.author_minutes === null ? t.authorTimeNotRecorded : fill(t.authorTimeValue, { time: f.duration(m.time.author_minutes * 60) }),
    },
  ];
}

export function costFigures(f: ProductionFormatter, m: CourseMetricData): Total[] {
  const t = f.t;
  return [
    { key: "build", label: t.costBuild, value: f.usd(m.cost.build_micro_usd), note: t.costBuildNote },
    { key: "maintenance", label: t.costMaintenance, value: f.usd(m.cost.maintenance_micro_usd), note: t.costMaintenanceNote },
    { key: "calls", label: t.calls, value: f.number(m.cost.llm_calls) },
    { key: "failed", label: t.callsFailed, value: f.number(m.cost.llm_calls_failed) },
  ];
}

export function countFigures(f: ProductionFormatter, m: CourseMetricData): Total[] {
  const t = f.t;
  return [
    { key: "lessons", label: t.lessons, value: f.number(m.counts.lessons) },
    { key: "questions", label: t.questions, value: f.number(m.counts.questions) },
    { key: "blocks", label: t.blocks, value: f.number(m.counts.blocks) },
    { key: "cited", label: t.citedBlocks, value: `${f.number(m.counts.cited_blocks)} / ${f.number(m.counts.blocks)}` },
    { key: "minutes", label: t.learnerMinutes, value: f.number(m.counts.learner_minutes_estimate) },
  ];
}

export interface ElementClassRow {
  key: "accepted" | "revised" | "edited" | "unknown";
  label: string;
  count: number;
  share: number;
  text: string;
}

/** The AI elements by what became of them; shares are of the AI elements only (author-written ones are listed apart). */
export function elementClasses(f: ProductionFormatter, m: CourseMetricData): ElementClassRow[] {
  const t = f.t;
  const e = m.elements;
  const total = e.ai_elements;
  const rows: Array<[ElementClassRow["key"], string, number]> = [
    ["accepted", t.classAccepted, e.accepted_unchanged],
    ["revised", t.classRevised, e.revised_on_request],
    ["edited", t.classEdited, e.edited_by_author],
    ["unknown", t.classUnknown, e.unknown],
  ];

  return rows.map(([key, label, count]) => {
    const share = total > 0 ? count / total : 0;
    return { key, label, count, share, text: `${f.number(count)} (${f.percent(share)})` };
  });
}

/** The text that stands in for the bar of the element split, for the accessible name. */
export const barText = (f: ProductionFormatter, rows: ElementClassRow[]): string => fill(f.t.barLabel, { parts: rows.filter((r) => r.count > 0).map((r) => `${r.label} ${r.text}`).join("; ") });

const FLAT = [
  ["course_id", (m: CourseMetricData) => m.course_id], ["session_id", (m: CourseMetricData) => m.session_id], ["method_version", (m: CourseMetricData) => m.method_version], ["computed_at", (m: CourseMetricData) => m.computed_at],
  ["generation_seconds", (m: CourseMetricData) => m.time.generation_seconds], ["ai_edit_seconds", (m: CourseMetricData) => m.time.ai_edit_seconds], ["interview_seconds", (m: CourseMetricData) => m.time.interview_seconds],
  ["model_seconds", (m: CourseMetricData) => m.time.model_seconds], ["author_minutes", (m: CourseMetricData) => m.time.author_minutes],
  ["cost_build_micro_usd", (m: CourseMetricData) => m.cost.build_micro_usd], ["cost_maintenance_micro_usd", (m: CourseMetricData) => m.cost.maintenance_micro_usd],
  ["llm_calls", (m: CourseMetricData) => m.cost.llm_calls], ["llm_calls_failed", (m: CourseMetricData) => m.cost.llm_calls_failed],
  ["lessons", (m: CourseMetricData) => m.counts.lessons], ["questions", (m: CourseMetricData) => m.counts.questions], ["blocks", (m: CourseMetricData) => m.counts.blocks], ["cited_blocks", (m: CourseMetricData) => m.counts.cited_blocks],
  ["learner_minutes_estimate", (m: CourseMetricData) => m.counts.learner_minutes_estimate],
  ["ai_elements", (m: CourseMetricData) => m.elements.ai_elements], ["ai_accepted_unchanged", (m: CourseMetricData) => m.elements.accepted_unchanged], ["ai_revised_on_request", (m: CourseMetricData) => m.elements.revised_on_request],
  ["ai_edited_by_author", (m: CourseMetricData) => m.elements.edited_by_author], ["ai_unknown", (m: CourseMetricData) => m.elements.unknown], ["author_elements", (m: CourseMetricData) => m.elements.author_written],
] as const;

/** The same columns, in the same order, as the API's export, so a file from the studio and one from the admin read alike. */
export function flatRow(m: CourseMetricData): Record<string, string | number | null> {
  return Object.fromEntries(FLAT.map(([name, get]) => [name, get(m)]));
}

/** Numbers, identifiers and dates only, so no cell can start with = + - @ and be read as a formula. Not recorded is an empty cell. */
export function toCsv(m: CourseMetricData): string {
  const row = flatRow(m);
  return `${Object.keys(row).join(",")}\n${Object.values(row).map((v) => (v === null ? "" : String(v))).join(",")}\n`;
}

export const toJson = (m: CourseMetricData): string => `${JSON.stringify(flatRow(m), null, 2)}\n`;
