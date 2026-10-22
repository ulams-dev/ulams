/**
 * View model of "Propose an improvement" (ADR 0181): turns the evidence and the API's refusals into the figures, rows
 * and sentences of the panel, the evidence card and the review screen, in the reader's language. No DOM here, so every
 * state is unit-tested. The evidence is aggregated by the API (groups of at least the minimum, wrong options below 5
 * learners merged); this code only presents it.
 */
import { apiErrorInfo, type InsightsEvidence, type ProposalStatus } from "@ulams/sdk";
import type { EvidenceCardOptions } from "@ulams/ui/builder/insights-view.ts";
import { fill, type InsightsLocale } from "./insights-i18n.ts";
import { IMPROVE_STRINGS, type ImproveStrings } from "./improve-i18n.ts";
import { formatterFor, type Formatter } from "./insights-model.ts";

export const improveStrings = (locale: InsightsLocale): ImproveStrings => IMPROVE_STRINGS[locale];

/** The cost as shown to the author: whole cents, "less than $0.01" below. */
export function costText(usd: number): string {
  return usd > 0 && usd < 0.005 ? "less than $0.01" : `$${usd.toFixed(2)}`;
}

/** The evidence card for the panel (`sent` set, before anything is sent) or for the review screen (no `sent`). */
export function evidenceCardOptions(f: Formatter, s: ImproveStrings, e: InsightsEvidence, extra: { sent?: boolean; level?: 2 | 3 | 4 } = {}): EvidenceCardOptions {
  const rate = (value: number | null) => ({ value: value === null ? s.noFigure : f.percent(value), rate: value });
  const wrong = e.wrongOptions.length === 0
    ? null
    : {
        title: s.wrongTitle,
        intro: s.wrongIntro,
        table: {
          caption: s.wrongTitle,
          scrollLabel: s.wrongScroll,
          columns: [{ label: s.wrongOption }, { label: s.wrongShare, numeric: true }],
          rows: e.wrongOptions.map((o) => ({ id: o.optionId, cells: [o.optionId === "other" || o.label === "" ? s.wrongOther : o.label, f.percent(o.share)] })),
        },
      };
  return {
    title: s.evidenceTitle,
    intro: fill(s.evidenceIntro, { learners: f.number(e.learners), days: e.periodDays }),
    figuresLabel: s.evidenceAria,
    figures: [
      { label: s.figStruggle, ...rate(e.struggleRate) },
      { label: s.figHelp, ...rate(e.remediationRate) },
      { label: s.figRecovery, ...rate(e.recoveryRate) },
      { label: s.figHelpful, ...rate(e.helpfulShare) },
      ...(e.tutorOutOfScope > 0 ? [{ label: s.figTutor, value: f.number(e.tutorOutOfScope) }] : []),
    ],
    reasonsTitle: s.reasonsTitle,
    reasons: e.topReasons.map((r) => `${s.reasons[r.rule] ?? r.rule.replace(/_/g, " ")} (${f.number(r.count)})`),
    noReasons: s.noReasons,
    wrong,
    sent: extra.sent ? { title: s.sentTitle, text: s.sentText } : null,
    lang: s.htmlLang,
    level: extra.level ?? 2,
  };
}

/** What a refusal of the API means for the author; unknown refusals get the generic text. */
export function improveErrorText(s: ImproveStrings, error: unknown, minGroup: number): string {
  const { status, code } = apiErrorInfo(error);
  switch (code) {
    case "small_group":
      return fill(s.errSmallGroup, { n: minGroup });
    case "daily_limit":
      return s.errDailyLimit;
    case "ai_disabled":
      return s.errAiOff;
    case "budget_blocked":
      return s.errBudget;
    case "not_builder_element":
      return s.errNotBuilder;
    case "element_not_improvable":
      return s.errNotImprovable;
    case "no_sources":
      return s.errNoSources;
    case "no_course":
      return s.errNoCourse;
    default:
      return status === 403 ? s.errForbidden : s.errGeneric;
  }
}

/** The state of a created proposal in the author's words. */
export function proposalStateText(s: ImproveStrings, status: ProposalStatus): string {
  return status === "ready" ? s.statusReady : status === "analysing" ? s.statusAnalysing : s.statusAwaiting;
}

/** The review screen of a proposal, in the language the author works in. */
export const reviewHref = (sessionId: string, proposalId: string, locale: InsightsLocale = "en"): string => `/studio/s/${sessionId}/updates/${proposalId}?lang=${locale}`;

export { formatterFor };
