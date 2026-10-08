import { describe, expect, it } from "vitest";
import { validateDocument } from "@ulams/ui/render-core";
import { THEMES } from "@ulams/ui/registry";
import { chromeFor, landingDocs, pageMeta } from "../../src/lib/docs.ts";
import { siteModel } from "../../src/lib/view-model.ts";
import { completionMode, topicDoc } from "../../src/lib/page-docs.ts";
import { flattenTopics } from "@ulams/sdk";
import { COFFEE_PROGRAM, raw } from "./fixtures.ts";

describe("landing documents", () => {
  for (const theme of THEMES) {
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

  it("shows a locked card for topics without content", () => {
    const topic = { ...flattenTopics(COFFEE_PROGRAM)[3]!, topicable: undefined };
    const doc = topicDoc({ tenant, theme: "coffee", course: COFFEE_PROGRAM, lesson: undefined, topic, access: false, nextHref: "/" });
    expect(doc.children?.[0]).toMatchObject({ component: "ActivityCard", props: { kind: "locked" } });
  });
});
