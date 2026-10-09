/**
 * Wording of the Living Course notices a learner sees (ADR 0033). Kept apart from the Astro
 * components so the sentences are tested once and the text fallback uses the same words.
 */

/** "9 October 2026" in UTC (the API sends ISO 8601), or null when the date is missing or invalid. */
export function formatNoticeDate(iso: string | null | undefined, locale = "en-GB"): string | null {
  if (!iso) return null;
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return null;
  return new Intl.DateTimeFormat(locale, { day: "numeric", month: "long", year: "numeric", timeZone: "UTC" }).format(date);
}

const withDate = (sentence: string, date: string | null): string => (date ? `${sentence}, ${date}.` : `${sentence}.`);

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
export function updateNoticeText(options: { started?: boolean; date?: string | null; message?: string | null }): UpdateNoticeText {
  const date = formatNoticeDate(options.date);
  const message = options.message?.trim() ?? "";
  return {
    title: options.started ? withDate("This lesson was updated after you started it", date) : withDate("Updated since you completed it", date),
    change: message ? `What changed: ${message}` : null,
  };
}

export const REATTEMPT_TITLE = "One question was corrected.";
export const REATTEMPT_TEXT = "Your previous score stays on record. Retake it to update your result.";
export const REATTEMPT_LINK = "Go to the quiz";

export const RETIRED_TEXT = "Retired lesson, no longer part of the course";

export function pendingNoticeText(since: string | null | undefined): string {
  const date = formatNoticeDate(since);
  return date ? `The source of this lesson changed on ${date}; an update is under review.` : "The source of this lesson changed; an update is under review.";
}

export const EXTENDED_LABEL = "New since you finished";
export const extendedText = (lesson: string): string => `${EXTENDED_LABEL}: ${lesson}`;

export const COURSE_UPDATES_TITLE = "What changed in this course";
