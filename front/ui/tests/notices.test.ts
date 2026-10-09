import { describe, expect, it } from "vitest";
import { catalogueJson } from "../src/registry.ts";
import { fallbackText, prepare, validateDocument } from "../src/render-core.ts";
import { formatNoticeDate, pendingNoticeText, updateNoticeText } from "../src/lib/notices.ts";

describe("notice wording", () => {
  it("formats the date in UTC, or says nothing for a missing or invalid one", () => {
    expect(formatNoticeDate("2026-10-09T23:30:00+00:00")).toBe("9 October 2026");
    expect(formatNoticeDate(null)).toBeNull();
    expect(formatNoticeDate("soon")).toBeNull();
  });

  it("tells a learner who completed the lesson what changed", () => {
    expect(updateNoticeText({ date: "2026-10-09T10:00:00Z", message: "New ratio" })).toEqual({
      title: "Updated since you completed it, 9 October 2026.",
      change: "What changed: New ratio",
    });
  });

  it("uses other words for a learner who started the lesson and leaves out an empty note", () => {
    expect(updateNoticeText({ started: true, date: "2026-10-09T10:00:00Z", message: "  " })).toEqual({
      title: "This lesson was updated after you started it, 9 October 2026.",
      change: null,
    });
    expect(updateNoticeText({}).title).toBe("Updated since you completed it.");
  });

  it("words the opt-in marker", () => {
    expect(pendingNoticeText("2026-10-01T00:00:00Z")).toBe("The source of this lesson changed on 1 October 2026; an update is under review.");
    expect(pendingNoticeText(null)).toBe("The source of this lesson changed; an update is under review.");
  });
});

describe("notice components in the catalogue", () => {
  const names = ["UpdateNotice", "ReattemptNotice", "PendingUpdateNotice", "CourseUpdates"];

  it("describes each one and tells the model that the app fills it", () => {
    const json = catalogueJson();
    for (const name of names) {
      expect(json[name], name).toBeDefined();
      expect(json[name]!.description, name).toMatch(/authors do not place it/i);
    }
  });

  it("renders valid props and falls back to the same words as text", () => {
    const update = prepare({ component: "UpdateNotice", props: { noticeId: 3, date: "2026-10-09T10:00:00Z", message: "New ratio" } });
    expect(update).toMatchObject({ kind: "component", props: { noticeId: 3, started: false } });
    expect(fallbackText({ component: "UpdateNotice", props: { started: true, date: "2026-10-09T10:00:00Z", message: "New ratio" } })).toBe(
      "This lesson was updated after you started it, 9 October 2026.\nWhat changed: New ratio"
    );
    expect(fallbackText({ component: "ReattemptNotice" })).toBe("One question was corrected.\nYour previous score stays on record. Retake it to update your result.");
    expect(fallbackText({ component: "PendingUpdateNotice", props: { since: "2026-10-01T00:00:00Z" } })).toContain("an update is under review");
    expect(
      fallbackText({
        component: "CourseUpdates",
        props: {
          updated: [{ title: "Grinding", href: "/learn/5/10", date: "2026-10-09T10:00:00Z" }],
          extended: [{ title: "Cupping", href: "/learn/5/12" }],
          retired: [{ title: "Old chart" }],
        },
      })
    ).toBe(
      ["Updated since you completed it, 9 October 2026: Grinding", "New since you finished: Cupping", "Retired lesson, no longer part of the course: Old chart"].join("\n")
    );
  });

  it("refuses unsafe links and over-long notes, rendering the text instead", () => {
    expect(validateDocument({ component: "ReattemptNotice", props: { quizHref: "javascript:alert(1)" } })).toHaveLength(1);
    expect(validateDocument({ component: "UpdateNotice", props: { message: "x".repeat(501) } })).toHaveLength(1);
    expect(validateDocument({ component: "CourseUpdates", props: { updated: [{ title: "A", href: "data:text/html,x" }] } })).toHaveLength(1);
    expect(prepare({ component: "UpdateNotice", props: { message: "x".repeat(501) } }).kind).toBe("fallback");
  });
});
