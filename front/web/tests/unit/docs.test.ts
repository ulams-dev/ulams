import { describe, expect, it } from "vitest";
import { validateDocument } from "@ulams/ui/render-core";
import { THEMES } from "@ulams/ui/registry";
import { chromeFor, landingDocs, pageMeta } from "../../src/lib/docs.ts";
import { siteModel } from "../../src/lib/view-model.ts";
import { completionMode, topicDoc } from "../../src/lib/page-docs.ts";
import { flattenTopics } from "@ulams/sdk";
import { COFFEE_PROGRAM, raw } from "./fixtures.ts";
import { comparisonModel } from "../../src/lib/comparison.ts";

describe("platform landing", () => {
  const demo = (title: string, theme: string) => ({
    title,
    text: "A demo academy.",
    theme,
    facts: [{ label: "Lessons", value: "6" }],
    primary: { label: "Open as learner", href: "http://coffee.app.localhost:4321/learn/1" },
    secondary: { label: "Open as admin", href: "http://coffee.admin.localhost" },
  });
  it("is valid against the catalogue with three demo cards", () => {
    const data = {
      demos: [demo("The Coffee Atlas", "coffee"), demo("On-Call", "oncall"), demo("Night Sky Explorers", "nightsky")],
      comparison: comparisonModel(),
    };
    expect(validateDocument(landingDocs.platform!, data)).toEqual([]);
  });
  it("labels roadmap items as coming and invents no numbers", () => {
    // the hero's update-proposal card is an illustration of an SLO lesson (its numbers are lesson content)
    const json = JSON.stringify(landingDocs.platform, (key, value) => (key === "diff" ? undefined : value));
    expect(json).toContain('"status":"coming"');
    expect(json).not.toMatch(/\b\d{2,}[,.]?\d*\s*(%|\+|customers|learners|users)/i);
  });
});

describe("landing documents", () => {
  for (const theme of THEMES.filter((t) => t !== "platform")) {
    it(`${theme}: every node is valid against the catalogue with real API data`, () => {
      const doc = landingDocs[theme];
      expect(doc).toBeDefined();
      const data = siteModel(raw(theme), { slug: theme, adminUrl: "http://admin" });
      expect(validateDocument(doc!, data)).toEqual([]);
      expect(pageMeta(doc!, data).title.length).toBeGreaterThan(5);
    });

    it(`${theme}: still valid when the API returned nothing`, () => {
      const empty = siteModel(
        { settings: null, courses: [], course: null, tutors: [], webinars: [], events: [], products: [] },
        { slug: theme, adminUrl: "http://admin" }
      );
      const problems = validateDocument(landingDocs[theme]!, empty);
      // sections whose only content is API data fall back to text instead of breaking the page
      for (const p of problems) expect(["Syllabus", "Quotes", "Events", "Pricing", "Badges", "FeatureList", "Hero"]).toContain(p.component);
    });

    it(`${theme}: header links point back to the landing from other pages`, () => {
      const { header } = chromeFor(theme);
      const links = (header?.props?.links ?? []) as Array<{ href: string }>;
      expect(links.every((l) => !l.href.startsWith("#"))).toBe(true);
    });
  }
});

describe("lesson documents", () => {
  const tenant = { slug: "coffee", apiUrl: "http://coffee.localhost", adminUrl: "http://coffee.admin.localhost" };
  it("renders every coffee topic type with valid catalogue nodes", () => {
    for (const topic of flattenTopics(COFFEE_PROGRAM)) {
      const doc = topicDoc({ tenant, theme: "coffee", course: COFFEE_PROGRAM, lesson: undefined, topic, access: true, nextHref: "/next" });
      expect(validateDocument(doc), `${topic.id} ${topic.topicable_type}`).toEqual([]);
      expect(doc.children?.length, topic.title).toBeGreaterThan(0);
      expect(["view", "manual", "media", "h5p", "quiz"]).toContain(completionMode(topic));
    }
  });

  it("plays SCORM from the tenant content origin in a sandboxed frame when the API returns a launch URL", () => {
    const topic = {
      ...flattenTopics(COFFEE_PROGRAM)[0]!,
      topicable_type: "Ulams\\TopicTypes\\Models\\TopicContent\\ScormSco",
      topicable: { id: 1, value: 7, uuid: "abc" },
    };
    const src = "http://coffee.content.localhost/scorm/_player/player.html#api=http%3A%2F%2Fcoffee.localhost&sco=abc&token=t";
    const doc = topicDoc({ tenant, theme: "coffee", course: COFFEE_PROGRAM, lesson: undefined, topic, access: true, nextHref: "/", contentOriginSrc: src });
    expect(validateDocument(doc)).toEqual([]);
    expect(doc.children?.[0]).toMatchObject({ component: "PackageFrame", props: { src, isolated: true } });

    const legacy = topicDoc({ tenant, theme: "coffee", course: COFFEE_PROGRAM, lesson: undefined, topic, access: true, nextHref: "/" });
    expect(legacy.children?.[0]).toMatchObject({ component: "PackageFrame", props: { src: "http://coffee.localhost/api/scorm/play/abc" } });
  });

  it("plays LiaScript from the content origin, or explains why it cannot", () => {
    const topic = {
      ...flattenTopics(COFFEE_PROGRAM)[0]!,
      topicable_type: "Ulams\\LiaScript\\Models\\LiaScriptTopic",
      topicable: { id: 1, value: 3 },
    };
    const url = "http://coffee.content.localhost/liascript/_player/index.html#topic=1&token=t";
    const doc = topicDoc({ tenant, theme: "coffee", course: COFFEE_PROGRAM, lesson: undefined, topic, access: true, nextHref: "/", liascript: { url, sections: 4 } });
    expect(validateDocument(doc)).toEqual([]);
    expect(doc.children?.[0]).toMatchObject({ component: "LiaScriptLesson", props: { src: url, sections: 4 } });
    expect(completionMode(topic)).toBe("manual");

    const unavailable = topicDoc({ tenant, theme: "coffee", course: COFFEE_PROGRAM, lesson: undefined, topic, access: true, nextHref: "/", liascript: { error: "No player" } });
    expect(validateDocument(unavailable)).toEqual([]);
    expect(unavailable.children?.[0]).toMatchObject({ component: "Callout", props: { text: "No player" } });
  });

  it("launches external tools (LTI) in a frame or a new window", () => {
    const topic = { ...flattenTopics(COFFEE_PROGRAM)[0]!, topicable_type: "Ulams\\Lti\\Models\\LtiLink", topicable: { id: 1, value: 1 } };
    const url = "https://tool.example.test/lti/login?login_hint=x";
    const framed = topicDoc({ tenant, theme: "coffee", course: COFFEE_PROGRAM, lesson: undefined, topic, access: true, nextHref: "/", lti: { url, presentation: "iframe", tool: "GeoGebra" } });
    expect(validateDocument(framed)).toEqual([]);
    expect(framed.children?.[0]).toMatchObject({ component: "PackageFrame", props: { src: url } });
    const windowed = topicDoc({ tenant, theme: "coffee", course: COFFEE_PROGRAM, lesson: undefined, topic, access: true, nextHref: "/", lti: { url, presentation: "window", tool: "GeoGebra" } });
    expect(validateDocument(windowed)).toEqual([]);
    expect(windowed.children?.[0]).toMatchObject({ component: "ActivityCard", props: { cta: { href: url } } });
  });

  it("shows a locked card for topics without content", () => {
    const topic = { ...flattenTopics(COFFEE_PROGRAM)[3]!, topicable: undefined };
    const doc = topicDoc({ tenant, theme: "coffee", course: COFFEE_PROGRAM, lesson: undefined, topic, access: false, nextHref: "/" });
    expect(doc.children?.[0]).toMatchObject({ component: "ActivityCard", props: { kind: "locked" } });
  });
});
