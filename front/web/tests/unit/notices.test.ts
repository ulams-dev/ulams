import { describe, expect, it } from "vitest";
import type { LearnerNotice } from "@ulams/sdk";
import { validateDocument } from "@ulams/ui/render-core";
import { courseUpdatesDoc, NO_NOTICES, topicNoticeDocs, type LearnerNotices } from "../../src/lib/notices.ts";

const notice = (over: Partial<LearnerNotice>): LearnerNotice => ({
  id: 1,
  kind: "topic_updated",
  topicId: 10,
  topicTitle: "Grinding",
  giftQuestionId: null,
  message: "The ratio is now 1:16.",
  createdAt: "2026-10-09T10:00:00+00:00",
  ...over,
});

const titles = new Map([
  [10, "Grinding"],
  [11, "Brewing"],
  [12, "Cupping"],
]);

describe("topic notices", () => {
  it("shows nothing when there is nothing to say", () => {
    expect(topicNoticeDocs(NO_NOTICES, 10, true)).toEqual([]);
  });

  it("shows the update notice of this topic only, with the note and a dismiss button id", () => {
    const data: LearnerNotices = { notices: [notice({ id: 4 }), notice({ id: 5, topicId: 11 })], freshness: [] };
    const docs = topicNoticeDocs(data, 10, true);
    expect(docs).toHaveLength(1);
    expect(docs[0]).toEqual({
      component: "UpdateNotice",
      props: { noticeId: 4, started: false, date: "2026-10-09T10:00:00+00:00", message: "The ratio is now 1:16." },
    });
  });

  it("words it for a learner who started but did not complete the topic, and keeps the newest of several", () => {
    const data: LearnerNotices = {
      notices: [notice({ id: 4, createdAt: "2026-09-01T00:00:00+00:00" }), notice({ id: 9, createdAt: "2026-10-09T00:00:00+00:00", message: null })],
      freshness: [],
    };
    const docs = topicNoticeDocs(data, 10, false);
    expect(docs).toHaveLength(1);
    expect(docs[0]!.props).toMatchObject({ noticeId: 9, started: true });
    expect(docs[0]!.props).not.toHaveProperty("message", expect.anything());
  });

  it("adds the re-attempt notice linking to the quiz, and the opt-in marker", () => {
    const data: LearnerNotices = {
      notices: [notice({ id: 6, kind: "question_reattempt", giftQuestionId: 3, message: null })],
      freshness: [{ topicId: 10, since: "2026-10-01T00:00:00+00:00" }, { topicId: 11, since: null }],
    };
    const docs = topicNoticeDocs(data, 10, true);
    expect(docs.map((d) => d.component)).toEqual(["ReattemptNotice", "PendingUpdateNotice"]);
    expect(docs[0]!.props).toEqual({ quizHref: "#quiz" });
    expect(docs[1]!.props).toEqual({ since: "2026-10-01T00:00:00+00:00" });
  });

  it("produces documents the catalogue accepts", () => {
    const data: LearnerNotices = {
      notices: [notice({}), notice({ id: 2, kind: "question_reattempt" })],
      freshness: [{ topicId: 10, since: "2026-10-01T00:00:00+00:00" }],
    };
    for (const doc of topicNoticeDocs(data, 10, true)) expect(validateDocument(doc)).toEqual([]);
  });
});

describe("course page summary", () => {
  it("is null when nothing changed", () => {
    expect(courseUpdatesDoc(NO_NOTICES, 5, titles)).toBeNull();
    expect(courseUpdatesDoc({ notices: [notice({ kind: "question_reattempt" })], freshness: [] }, 5, titles)).toBeNull();
  });

  it("lists updated, new, retired and under-review lessons with links to the topics that still exist", () => {
    const data: LearnerNotices = {
      notices: [
        notice({ id: 1 }),
        notice({ id: 2, kind: "course_extended", topicId: 12, topicTitle: "Cupping", message: null }),
        notice({ id: 3, kind: "topic_retired", topicId: 99, topicTitle: "Old roast chart", message: null }),
      ],
      freshness: [{ topicId: 11, since: "2026-10-02T00:00:00+00:00" }],
    };
    const doc = courseUpdatesDoc(data, 5, titles)!;
    expect(doc.component).toBe("CourseUpdates");
    expect(doc.props).toEqual({
      updated: [{ title: "Grinding", href: "/learn/5/10", date: "2026-10-09T10:00:00+00:00" }],
      extended: [{ title: "Cupping", href: "/learn/5/12" }],
      retired: [{ title: "Old roast chart", date: "2026-10-09T10:00:00+00:00" }],
      pending: [{ title: "Brewing", href: "/learn/5/11", since: "2026-10-02T00:00:00+00:00" }],
    });
    expect(validateDocument(doc)).toEqual([]);
  });

  it("skips freshness and notices for topics it cannot name", () => {
    const data: LearnerNotices = { notices: [notice({ topicId: 77, topicTitle: null })], freshness: [{ topicId: 78, since: null }] };
    expect(courseUpdatesDoc(data, 5, titles)).toBeNull();
  });
});
