import { describe, expect, it } from "vitest";
import { consoleLog, courseModel, eventModels, formatMoney, plainText, planModels, siteModel, splitLessonTitle } from "../../src/lib/view-model.ts";
import { FIXTURES, raw } from "./fixtures.ts";

describe("view model", () => {
  it("splits the lesson title conventions of the three demo courses", () => {
    expect(splitLessonTitle("I. Origins")).toEqual({ kicker: "I", title: "Origins" });
    expect(splitLessonTitle("0. Briefing")).toEqual({ kicker: "0", title: "Briefing" });
    expect(splitLessonTitle("Mission 1 · Lift-off")).toEqual({ kicker: "Mission 1", title: "Lift-off" });
    expect(splitLessonTitle("Plain title")).toEqual({ title: "Plain title" });
  });

  it("builds the coffee course model only from API data", () => {
    const c = courseModel(FIXTURES.coffee.course, "EUR");
    expect(c.lessonCount).toBe(6);
    expect(c.topicCount).toBe(18);
    expect(c.lessons[0]).toMatchObject({ kicker: "I", title: "Origins", formats: ["video", "image", "reading"] });
    expect(c.previewHref).toBe("/learn/1/1");
    expect(c.price).toBe("€89");
    expect(c.testimonials.length).toBeGreaterThan(0);
    expect(c.tutors.map((t) => t.name)).toEqual(["Inés Duarte", "Tomasz Wierzba"]);
  });

  it("maps landing string lists and badges (nightsky)", () => {
    const c = courseModel(FIXTURES.nightsky.course);
    expect(c.badges.map((b) => b.name)).toContain("Junior Astronomer");
    expect(c.lists.for_parents?.[0]?.title).toMatch(/^[A-Z]/);
  });

  it("formats prices and subscription periods", () => {
    expect(formatMoney(8900)).toBe("€89");
    expect(formatMoney(1250, "EUR")).toBe("€12.50");
    expect(formatMoney(null)).toBeUndefined();
    const plans = planModels(FIXTURES.oncall.products, "EUR", "/learn/1/1");
    expect(plans.find((p) => p.name.includes("Teams"))?.period).toBe("per year");
    expect(plans.every((p) => p.cta.href === "/learn/1/1")).toBe(true);
  });

  it("keeps upcoming events, sorted, with naive times as UTC wall clock", () => {
    const events = eventModels(FIXTURES.coffee.webinars, FIXTURES.coffee.events, Date.parse("2026-10-01"));
    expect(events.map((e) => e.kind)).toEqual(["webinar", "in-person"]);
    expect(events[1]?.date).toBe("2026-11-28T10:00:00Z");
    expect(eventModels(FIXTURES.coffee.webinars, [], Date.parse("2027-06-01"))).toEqual([]);
  });

  it("builds the On-Call console log from modules and the next session only", () => {
    const course = courseModel(FIXTURES.oncall.course);
    const log = consoleLog(course, eventModels(FIXTURES.oncall.webinars, FIXTURES.oncall.events, Date.parse("2026-10-01")));
    expect(log.lines.slice(0, 5).map((l) => l.time)).toEqual(["mod 0", "mod 1", "mod 2", "mod 3", "mod 4"]);
    expect(log.lines[0]?.text).toContain("Briefing — Course kickoff");
    expect(log.lines[5]).toMatchObject({ tag: "LIVE", text: FIXTURES.oncall.webinars[0]!.name });
    expect(log.values).toEqual(course.lessons.map((l) => l.minutes));
    expect(consoleLog(undefined, [])).toEqual({ lines: [], values: [] });
  });

  it("strips HTML from API rich text", () => {
    expect(plainText("<p>Cup &amp; spoon</p>")).toBe("Cup & spoon");
  });

  it("builds the site model for every demo tenant", () => {
    for (const slug of ["coffee", "oncall", "nightsky"] as const) {
      const site = siteModel(raw(slug), { slug, adminUrl: `http://${slug}.admin.localhost` });
      expect(site.course?.lessons.length, slug).toBeGreaterThan(0);
      expect(site.tenant.name, slug).not.toBe(slug);
    }
  });
});
