/**
 * Living Course notices as catalogue documents (ADR 0033). The learner pages fetch the caller's
 * notices and the freshness marker, and these functions decide what each page shows. Nothing here
 * touches progress: a notice only informs.
 */
import type { LearnerNotice, TopicFreshness } from "@ulams/sdk";
import type { UiNode } from "@ulams/ui/render-core";
import { topicHref } from "./view-model.ts";

export interface LearnerNotices {
  notices: LearnerNotice[];
  freshness: TopicFreshness[];
}

export const NO_NOTICES: LearnerNotices = { notices: [], freshness: [] };

const newest = (notices: LearnerNotice[]): LearnerNotice[] =>
  [...notices].sort((a, b) => (Date.parse(b.createdAt ?? "") || 0) - (Date.parse(a.createdAt ?? "") || 0));

/**
 * Notices on a lesson page: "updated since you completed it" (or "after you started it"), the quiz
 * re-attempt notice, and the opt-in "an update is under review" marker.
 *
 * @param done the learner completed this topic (otherwise they started it)
 */
export function topicNoticeDocs(data: LearnerNotices, topicId: number, done: boolean, quizHref = "#quiz"): UiNode[] {
  const docs: UiNode[] = [];
  const updated = newest(data.notices.filter((n) => n.kind === "topic_updated" && n.topicId === topicId))[0];
  if (updated) {
    docs.push({
      component: "UpdateNotice",
      props: { noticeId: updated.id, started: !done, date: updated.createdAt ?? undefined, message: updated.message ?? undefined },
    });
  }
  if (data.notices.some((n) => n.kind === "question_reattempt" && n.topicId === topicId)) {
    docs.push({ component: "ReattemptNotice", props: { quizHref } });
  }
  const pending = data.freshness.find((f) => f.topicId === topicId);
  if (pending) docs.push({ component: "PendingUpdateNotice", props: { since: pending.since ?? undefined } });
  return docs;
}

/** What the course page can name: topic titles of the learner's program, by id. */
export type TopicTitles = ReadonlyMap<number, string>;

/** The course-page summary, or null when nothing changed for this learner. */
export function courseUpdatesDoc(data: LearnerNotices, courseId: number, titles: TopicTitles): UiNode | null {
  const title = (topicId: number | null, fallback: string | null): string | null => (topicId !== null ? titles.get(topicId) : undefined) ?? fallback;
  const rows = (kind: LearnerNotice["kind"]) => newest(data.notices.filter((n) => n.kind === kind));

  const updated = rows("topic_updated").flatMap((n) => {
    const name = title(n.topicId, n.topicTitle);
    return name && n.topicId !== null ? [{ title: name, href: topicHref(courseId, n.topicId), date: n.createdAt ?? undefined }] : [];
  });
  const extended = rows("course_extended").flatMap((n) => {
    const name = title(n.topicId, n.topicTitle);
    return name && n.topicId !== null ? [{ title: name, href: topicHref(courseId, n.topicId) }] : [];
  });
  const retired = rows("topic_retired").flatMap((n) => {
    const name = n.topicTitle ?? title(n.topicId, null);
    return name ? [{ title: name, date: n.createdAt ?? undefined }] : [];
  });
  const pending = data.freshness.flatMap((f) => {
    const name = titles.get(f.topicId);
    return name ? [{ title: name, href: topicHref(courseId, f.topicId), since: f.since ?? undefined }] : [];
  });

  if (updated.length + extended.length + retired.length + pending.length === 0) return null;
  return { component: "CourseUpdates", props: { updated, retired, extended, pending } };
}
