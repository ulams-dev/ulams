import { describe, expect, it } from "vitest";
import { courseStanding, type Course } from "@ulams/sdk";
import { standingNote } from "../../src/lib/standing.ts";

const course: Pick<Course, "lessons"> = {
  lessons: [
    {
      id: 1,
      title: "I",
      order: 1,
      topics: [
        { id: 1, title: "Origins", lesson_id: 1, order: 1, topicable_type: "X\\Video", duration: "5 min" },
        { id: 2, title: "Roasting", lesson_id: 1, order: 2, topicable_type: "X\\RichText", duration: "70 min" },
        { id: 3, title: "Tasting", lesson_id: 1, order: 3, topicable_type: "X\\RichText" },
      ],
    },
  ],
};
const done = (...ids: number[]) => ids.map((id) => ({ id, topic_id: id, status: 1, seconds: 0 }) as never);

describe("standingNote", () => {
  it("shows the size of the course before the first topic", () => {
    expect(standingNote(courseStanding(course, []))).toBe("3 topics · about 1 h 15 min in total");
  });
  it("names the next topic and the time left", () => {
    expect(standingNote(courseStanding(course, done(1)))).toBe("Next: Roasting · about 1 h 10 min left");
  });
  it("falls back to a topic count when no durations are known", () => {
    const untimed = { lessons: [{ id: 1, title: "I", topics: [{ id: 1, title: "A", lesson_id: 1, topicable_type: "X\\Video" }, { id: 2, title: "B", lesson_id: 1, topicable_type: "X\\Video" }] }] };
    expect(standingNote(courseStanding(untimed, done(1)))).toBe("Next: B · 1 topic left");
  });
  it("congratulates at the end and stays quiet for an empty course", () => {
    expect(standingNote(courseStanding(course, done(1, 2, 3)))).toMatch(/^Course complete/);
    expect(standingNote(courseStanding({ lessons: [] }, []))).toBe("");
  });
});
