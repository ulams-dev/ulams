/**
 * Wording of the Living Course notices a learner sees (ADR 0033). Kept apart from the Astro
 * components so the sentences are tested once and the text fallback uses the same words.
 */

import { fill, playerLocale, playerStrings } from "./player-strings.ts";

const DATE_LOCALE = { en: "en-GB", pl: "pl-PL", zh: "zh-CN" } as const;

/**
 * "9 October 2026" in UTC (the API sends ISO 8601), or null when the date is missing or invalid. `lang` is the
 * interface language (`en`, `pl`, `zh`, or a tag starting with one of them).
 */
export function formatNoticeDate(iso: string | null | undefined, lang = "en"): string | null {
  if (!iso) return null;
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return null;
  const locale = lang.includes("-") && !/^(en|pl|zh)(-|$)/i.test(lang) ? lang : DATE_LOCALE[playerLocale(lang)];
  return new Intl.DateTimeFormat(locale, { day: "numeric", month: "long", year: "numeric", timeZone: "UTC" }).format(date);
}

export interface UpdateNoticeText {
  /** The heading: what happened and when. */
  title: string;
  /** "What changed: …", or null when the author wrote no note. */
  change: string | null;
}

/**
 * A lesson changed after the learner completed it (`started: false`) or after they started it.
 * Completion and scores stay as they are, which the notice says only for completed lessons.
 */
export function updateNoticeText(options: { started?: boolean; date?: string | null; message?: string | null }, lang = "en"): UpdateNoticeText {
  const t = playerStrings(lang);
  const date = formatNoticeDate(options.date, lang);
  const message = options.message?.trim() ?? "";
  const dated = options.started ? t.noticeUpdatedAfterStart : t.noticeUpdatedAfterDone;
  const plain = options.started ? t.noticeUpdatedAfterStartPlain : t.noticeUpdatedAfterDonePlain;
  return {
    title: date ? fill(dated, { date }) : plain,
    change: message ? fill(t.noticeChange, { message }) : null,
  };
}

const en = playerStrings("en");
export const REATTEMPT_TITLE = en.reattemptTitle;
export const REATTEMPT_TEXT = en.reattemptText;
export const REATTEMPT_LINK = en.reattemptLink;

export const RETIRED_TEXT = en.retired;

export function pendingNoticeText(since: string | null | undefined, lang = "en"): string {
  const t = playerStrings(lang);
  const date = formatNoticeDate(since, lang);
  return date ? fill(t.pendingDate, { date }) : t.pending;
}

export const EXTENDED_LABEL = en.extended;
export const extendedText = (lesson: string): string => `${EXTENDED_LABEL}: ${lesson}`;

export const COURSE_UPDATES_TITLE = en.courseUpdatesTitle;
