import { formatMinutes } from "@ulams/ui/format";
import { fill, plural, playerLocale, playerStrings } from "@ulams/ui/player-strings";
import type { CourseStanding } from "@ulams/sdk";

/**
 * One line that tells a learner what comes next and how much is left, from data the API already
 * returns (program, progress, topic durations). Empty when there is nothing useful to say.
 */
export function standingNote(standing: CourseStanding, lang = "en"): string {
  if (standing.total === 0) return "";
  const locale = playerLocale(lang);
  const t = playerStrings(locale);
  if (standing.finished) return t.courseComplete;
  const time = standing.remainingMinutes > 0 ? formatMinutes(standing.remainingMinutes, locale) : "";
  if (standing.done === 0) {
    const topics = plural(locale, t.topicsCount, standing.total);
    return time ? fill(t.standingTotal, { topics, time }) : topics;
  }
  const left = time ? fill(t.timeLeft, { time }) : plural(locale, t.topicsLeft, standing.left);
  return standing.next ? fill(t.standingNext, { title: standing.next.title, left }) : left;
}
