import { formatMinutes } from "@ulams/ui/format";
import type { CourseStanding } from "@ulams/sdk";

const topics = (n: number) => `${n} ${n === 1 ? "topic" : "topics"}`;

/**
 * One line that tells a learner what comes next and how much is left, from data the API already
 * returns (program, progress, topic durations). Empty when there is nothing useful to say.
 */
export function standingNote(standing: CourseStanding): string {
  if (standing.total === 0) return "";
  if (standing.finished) return "Course complete. Review any lesson, or check your certificate.";
  const time = standing.remainingMinutes > 0 ? formatMinutes(standing.remainingMinutes) : "";
  if (standing.done === 0) return time ? `${topics(standing.total)} · about ${time} in total` : topics(standing.total);
  const left = time ? `about ${time} left` : `${topics(standing.left)} left`;
  return standing.next ? `Next: ${standing.next.title} · ${left}` : left;
}
