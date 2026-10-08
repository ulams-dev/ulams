import { describe, expect, it } from "vitest";
import {
  completionPercent,
  durationToMinutes,
  flattenTopics,
  resumeTopic,
  topicKind,
  topicNeighbours,
} from "../src/topics.ts";
import { isCompletingStatement, isH5PEmbedMessage, h5pEmbedPlayUrl } from "../src/h5p.ts";
import type { Course } from "../src/types.ts";

const course: Pick<Course, "lessons"> = {
  lessons: [
    {
      id: 2,
      title: "II",
      order: 2,
      topics: [{ id: 3, title: "c", lesson_id: 2, topicable_type: "X\\GiftQuiz" }],
    },
    {
      id: 1,
      title: "I",
      order: 1,
      topics: [
        { id: 2, title: "b", lesson_id: 1, order: 2, topicable_type: "X\\RichText" },
        { id: 1, title: "a", lesson_id: 1, order: 1, topicable_type: "X\\Video" },
      ],
    },
  ],
};

describe("topics", () => {
  it("maps topicable classes to kinds", () => {
    expect(topicKind("Ulams\\TopicTypes\\Models\\TopicContent\\ScormSco")).toBe("scorm");
    expect(topicKind("Ulams\\TopicTypeGift\\Models\\GiftQuiz")).toBe("quiz");
    expect(topicKind("Something\\Else")).toBe("unknown");
    expect(topicKind(undefined)).toBe("unknown");
  });

  it("parses the duration formats the seeders use", () => {
    expect(durationToMinutes("4 min")).toBe(4);
    expect(durationToMinutes("1 h 20 min")).toBe(80);
    expect(durationToMinutes("2 h")).toBe(120);
    expect(durationToMinutes("00:12:00")).toBe(12);
    expect(durationToMinutes("12")).toBe(12);
    expect(durationToMinutes(null)).toBe(0);
  });

  it("orders topics and finds neighbours", () => {
    expect(flattenTopics(course).map((t) => t.id)).toEqual([1, 2, 3]);
    const n = topicNeighbours(course, 2);
    expect([n.previous?.id, n.next?.id, n.index, n.total]).toEqual([1, 3, 1, 3]);
  });

  it("computes completion and the topic to resume", () => {
    const progress = [
      { topic_id: 1, status: 1 as const },
      { topic_id: 2, status: 2 as const },
    ];
    expect(completionPercent(course, progress)).toBe(33);
    expect(resumeTopic(course, progress)?.id).toBe(2);
    expect(resumeTopic(course, [])?.id).toBe(1);
  });
});

describe("h5p helpers", () => {
  it("builds the embed URL", () => {
    expect(h5pEmbedPlayUrl("http://coffee.localhost/", 5, { language: "en", hideActions: true })).toBe(
      "http://coffee.localhost/h5p/embed/play/5?language=en&hideActions=1"
    );
  });
  it("recognises protocol messages and completing statements", () => {
    expect(isH5PEmbedMessage({ type: "ulams-h5p:ready" })).toBe(true);
    expect(isH5PEmbedMessage({ type: "other" })).toBe(false);
    expect(isCompletingStatement({ verb: { id: "http://adlnet.gov/expapi/verbs/completed" } })).toBe(true);
    expect(isCompletingStatement({ verb: { id: "http://adlnet.gov/expapi/verbs/interacted" } })).toBe(false);
  });
});
