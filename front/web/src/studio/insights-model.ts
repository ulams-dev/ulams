/**
 * View model of the studio Insights page: turns the analytics API's answers into the figures, table rows and
 * sentences the page shows, in the reader's language. No DOM here, so every state is unit-tested. The API
 * already withheld every group below its minimum size; this code only presents what arrived and never tries to
 * work out what was left out.
 */
import type { CourseElements, CourseInsightsFull, ElementInsights, InsightsPeriod, StrugglingLearner, TimelineEntry } from "@ulams/sdk";
import { fill, INSIGHTS_STRINGS, type InsightsLocale, type InsightsStrings } from "./insights-i18n.ts";

export interface Formatter {
  t: InsightsStrings;
  percent: (rate: number) => string;
  number: (n: number) => string;
  date: (iso: string) => string;
  dateTime: (iso: string) => string;
}

export function formatterFor(locale: InsightsLocale): Formatter {
  const t = INSIGHTS_STRINGS[locale];
  const tag = t.htmlLang;
  const percent = new Intl.NumberFormat(tag, { style: "percent", maximumFractionDigits: 0 });
  const number = new Intl.NumberFormat(tag);
  const date = new Intl.DateTimeFormat(tag, { dateStyle: "medium" });
  const dateTime = new Intl.DateTimeFormat(tag, { dateStyle: "medium", timeStyle: "short" });
  const valid = (iso: string): Date | null => {
    const d = new Date(iso);
    return Number.isNaN(d.getTime()) ? null : d;
  };
  return {
    t,
    percent: (rate) => percent.format(rate),
    number: (n) => number.format(n),
    date: (iso) => {
      const d = valid(iso);
      return d ? date.format(d) : "–";
    },
    dateTime: (iso) => {
      const d = valid(iso);
      return d ? dateTime.format(d) : "–";
    },
  };
}

export const periodLabel = (t: InsightsStrings, period: InsightsPeriod): string => (period === 7 ? t.period7 : period === 90 ? t.period90 : t.period30);

/** A rate as text, or the reason there is no figure. */
export const rateText = (f: Formatter, rate: number | null): string => (rate === null ? f.t.noFigure : f.percent(rate));

export interface FigureData {
  label: string;
  value: string;
  note?: string;
}

export function progressFigures(f: Formatter, s: CourseInsightsFull): FigureData[] {
  return [
    { label: f.t.enrolled, value: f.number(s.learners.enrolled) },
    { label: f.t.active, value: f.number(s.learners.active) },
    { label: f.t.completed, value: f.number(s.learners.completed) },
    { label: f.t.completionRate, value: f.percent(s.learners.completionRate) },
  ];
}

export function quizFigures(f: Formatter, s: CourseInsightsFull): FigureData[] {
  return [
    { label: f.t.quizAttempts, value: f.number(s.quiz.attempts), note: f.t.quizNote },
    { label: f.t.quizPassRate, value: rateText(f, s.quiz.passRate) },
    { label: f.t.quizAverage, value: rateText(f, s.quiz.averageScore) },
  ];
}

export function supportFigures(f: Formatter, s: CourseInsightsFull): FigureData[] {
  return [
    { label: f.t.onTrack, value: f.number(s.statuses.on_track) },
    { label: f.t.struggling, value: f.number(s.statuses.struggling) },
    { label: f.t.atRisk, value: f.number(s.statuses.at_risk) },
  ];
}

export function helpFigures(f: Formatter, s: CourseInsightsFull): FigureData[] {
  const recovery = (rate: number | null): FigureData["note"] => (rate === null ? fill(f.t.recoveryNote, { n: s.minGroupSize }) : undefined);
  return [
    { label: f.t.helpShown, value: f.number(s.remediation.shown) },
    { label: f.t.helpHelpful, value: f.number(s.remediation.helpful) },
    { label: f.t.helpNotHelpful, value: f.number(s.remediation.notHelpful) },
    { label: f.t.helpRecovery, value: rateText(f, s.remediation.recoveryRate), note: recovery(s.remediation.recoveryRate) },
    { label: f.t.nudgesSent, value: f.number(s.nudges.sent) },
    { label: f.t.nudgesOpened, value: f.number(s.nudges.opened) },
    { label: f.t.nudgesHeld, value: f.number(s.nudges.suppressed) },
    { label: f.t.nudgesRecovery, value: rateText(f, s.nudges.recoveryRate), note: recovery(s.nudges.recoveryRate) },
  ];
}

/** The name of a rule in the author's words; an unknown rule keeps its key so nothing is silently dropped. */
export const reasonName = (t: InsightsStrings, rule: string): string => t.reasons[rule] ?? rule;

/** One row of the "sections that confuse people" table; rates are text plus a number for the bar. */
export interface ElementRow {
  key: string;
  label: string;
  withheld: boolean;
  /** Present for rows that are not withheld. */
  active?: string;
  struggle?: { rate: number; text: string };
  helped?: { rate: number; text: string };
  recovered?: { rate: number | null; text: string };
  reasons?: string;
}

export function elementRows(f: Formatter, data: CourseElements): ElementRow[] {
  return data.elements.map((e: ElementInsights): ElementRow => {
    if (e.hidden) return { key: e.elementKey, label: e.label, withheld: true };
    return {
      key: e.elementKey,
      label: e.label,
      withheld: false,
      active: f.number(e.activeLearners),
      struggle: { rate: e.struggleRate, text: f.percent(e.struggleRate) },
      helped: { rate: e.remediationRate, text: f.percent(e.remediationRate) },
      recovered: { rate: e.recoveryRate, text: rateText(f, e.recoveryRate) },
      reasons: e.topReasons.length ? e.topReasons.map((r) => `${reasonName(f.t, r.rule)} (${f.number(r.count)})`).join("; ") : f.t.noReasons,
    };
  });
}

export const statusName = (t: InsightsStrings, status: StrugglingLearner["status"]): string => (status === "at_risk" ? t.atRisk : status === "struggling" ? t.struggling : t.onTrack);

export interface LearnerRow {
  userId: number;
  name: string;
  status: string;
  reasons: string;
  since: string;
}

export function learnerRows(f: Formatter, learners: StrugglingLearner[]): LearnerRow[] {
  return learners.map((l) => ({
    userId: l.userId,
    name: l.name || `#${l.userId}`,
    status: statusName(f.t, l.status),
    reasons: l.reasons.length ? l.reasons.join("; ") : "–",
    since: f.date(l.since),
  }));
}

export interface TimelineRow {
  key: string;
  when: string;
  what: string;
  where: string;
}

export function timelineRows(f: Formatter, entries: TimelineEntry[]): TimelineRow[] {
  return entries.map((e, i) => ({ key: `${i}`, when: f.dateTime(e.at), what: f.t.signals[e.type] ?? e.type, where: e.element ?? "–" }));
}
