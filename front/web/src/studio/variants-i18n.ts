/**
 * Strings of the variant parts of the studio update review (ADR 0192) in English, Polish and Simplified Chinese: the
 * badge naming the course a proposal is for, the "Versions of this update" navigation, "Accept in all versions" and
 * its result. The language is the one the page was opened in (`?lang=`, else the browser's, else English), the same
 * rule as the Insights and Versions pages; glossary in src/i18n/GLOSSARY.md. Placeholders are `{name}`. The number
 * of other versions has one text per plural category of the language.
 */
import type { ProposalStatus } from "@ulams/sdk";
import type { InsightsLocale } from "./insights-i18n.ts";

export type VariantsLocale = InsightsLocale;

export type PluralForms = Partial<Record<Intl.LDMLPluralRule, string>> & { other: string };

export interface VariantStrings {
  htmlLang: string;
  /** The badge of a proposal for the base course. */
  baseCourse: string;
  /** A variant whose name the API did not send. */
  variantFallback: string;
  navLabel: string;
  /** Read by screen readers on the entry of the proposal that is open. */
  navCurrent: string;
  /** "{decided} decided, {undecided} undecided" */
  entryCounts: string;
  statuses: Record<ProposalStatus, string>;
  acceptInGroup: string;
  acceptInGroupFailed: string;
  /** "Accepted here and in {n} other versions", one text per plural category. */
  acceptedElsewhere: PluralForms;
  acceptedHereOnly: string;
  reusedNote: string;
}

const en: VariantStrings = {
  htmlLang: "en",
  baseCourse: "Base course",
  variantFallback: "Variant",
  navLabel: "Versions of this update",
  navCurrent: "open now",
  entryCounts: "{decided} decided, {undecided} undecided",
  statuses: {
    analysing: "Analysing",
    ready: "Ready to review",
    awaiting_analysis: "Waiting to be analysed",
    budget_blocked: "Budget reached",
    applying: "Applying",
    applied: "Applied",
    no_impact: "No impact",
    rejected: "Rejected",
    superseded: "Replaced by a newer one",
    failed: "Analysis failed",
  },
  acceptInGroup: "Accept in all versions",
  acceptInGroupFailed: "The change could not be accepted in all versions.",
  acceptedElsewhere: { one: "Accepted here and in {n} other version", other: "Accepted here and in {n} other versions" },
  acceptedHereOnly: "Accepted here. No other version had the same change waiting for a decision.",
  reusedNote: "Same change as in the base course; no extra analysis was needed.",
};

const pl: VariantStrings = {
  htmlLang: "pl",
  baseCourse: "Kurs bazowy",
  variantFallback: "Wariant",
  navLabel: "Wersje tej aktualizacji",
  navCurrent: "otwarta teraz",
  entryCounts: "rozstrzygnięte: {decided}, bez decyzji: {undecided}",
  statuses: {
    analysing: "Analiza trwa",
    ready: "Gotowa do przeglądu",
    awaiting_analysis: "Czeka na analizę",
    budget_blocked: "Osiągnięto budżet",
    applying: "Stosowanie",
    applied: "Zastosowana",
    no_impact: "Bez wpływu",
    rejected: "Odrzucona",
    superseded: "Zastąpiona nowszą",
    failed: "Analiza nie powiodła się",
  },
  acceptInGroup: "Zaakceptuj we wszystkich wersjach",
  acceptInGroupFailed: "Nie udało się zaakceptować zmiany we wszystkich wersjach.",
  acceptedElsewhere: {
    one: "Zaakceptowano tutaj i w {n} innej wersji",
    few: "Zaakceptowano tutaj i w {n} innych wersjach",
    many: "Zaakceptowano tutaj i w {n} innych wersjach",
    other: "Zaakceptowano tutaj i w {n} innej wersji",
  },
  acceptedHereOnly: "Zaakceptowano tutaj. Żadna inna wersja nie miała takiej samej zmiany czekającej na decyzję.",
  reusedNote: "Ta sama zmiana co w kursie bazowym; dodatkowa analiza nie była potrzebna.",
};

const zh: VariantStrings = {
  htmlLang: "zh-Hans",
  baseCourse: "基础课程",
  variantFallback: "变体",
  navLabel: "此更新的各个版本",
  navCurrent: "当前打开",
  entryCounts: "已决定 {decided} 项，待决定 {undecided} 项",
  statuses: {
    analysing: "正在分析",
    ready: "可供审阅",
    awaiting_analysis: "等待分析",
    budget_blocked: "已达预算上限",
    applying: "正在应用",
    applied: "已应用",
    no_impact: "无影响",
    rejected: "已拒绝",
    superseded: "已被较新的取代",
    failed: "分析失败",
  },
  acceptInGroup: "在所有版本中接受",
  acceptInGroupFailed: "无法在所有版本中接受此更改。",
  acceptedElsewhere: { other: "已在此处及另外 {n} 个版本中接受" },
  acceptedHereOnly: "已在此处接受。其他版本中没有等待决定的相同更改。",
  reusedNote: "与基础课程中的更改相同，无需额外分析。",
};

export const VARIANT_STRINGS: Record<VariantsLocale, VariantStrings> = { en, pl, zh };
