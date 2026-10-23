import { existsSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { JSDOM } from "jsdom";
import { beforeAll, describe, expect, it } from "vitest";
import { validateDocument } from "@ulams/ui/render-core";
import { ELEMENTS, ELEMENT_KEYS, GROUPS, HOME_LIVE, LEARNING_ID, PRINCIPLES, learningCopy, learningPage, liveNode } from "../../src/lib/learning-elements.ts";
import { LOCALES, publicPagePath, type Locale } from "../../src/i18n/locales.ts";
import { GET as sitemap } from "../../src/pages/sitemap.xml.ts";
import { platformLlmsTxt } from "../../src/lib/llms.ts";

const shape = (value: unknown): unknown => {
  if (Array.isArray(value)) return value.map(shape);
  if (value && typeof value === "object") return Object.fromEntries(Object.entries(value).map(([k, v]) => [k, shape(v)]));
  return typeof value === "number" || typeof value === "boolean" ? value : typeof value;
};
const strings = (value: unknown): string[] =>
  typeof value === "string" ? [value] : Array.isArray(value) ? value.flatMap(strings) : value && typeof value === "object" ? Object.values(value).flatMap(strings) : [];
const publicFile = (path: string) => fileURLToPath(new URL(`../../public${path}`, import.meta.url));
const DEMO_HOSTS = /^https:\/\/(coffee|oncall|nightsky|gravity|poland|ulam)\.ulams\.app\//;
const pageFor = (locale: Locale) => {
  const path = publicPagePath(locale, "learning-elements");
  return learningPage(locale, new Request(`https://ulams.app${path}`), new URL(`https://ulams.app${path}`))!;
};

describe("learning elements: model", () => {
  it("lists every element once, in a group, with a principle", () => {
    expect(ELEMENTS.map((e) => e.key)).toEqual([...ELEMENT_KEYS]);
    for (const e of ELEMENTS) {
      expect(GROUPS).toContain(e.group);
      expect(PRINCIPLES).toContain(e.principle);
    }
    for (const group of GROUPS) expect(ELEMENTS.filter((e) => e.group === group).length, group).toBeGreaterThan(2);
  });

  it("renders the live elements from valid catalogue documents in every language, and only those", () => {
    for (const locale of LOCALES) {
      for (const e of ELEMENTS) {
        const node = liveNode(e.key, locale);
        if (e.demo === "live") {
          expect(node, e.key).not.toBeNull();
          expect(validateDocument(node!, {}), `${locale} ${e.key}`).toEqual([]);
        } else expect(node, e.key).toBeNull();
      }
    }
    for (const key of HOME_LIVE) expect(ELEMENTS.find((e) => e.key === key)!.demo).toBe("live");
  });

  it("uses real screenshots and media that exist, and links to demo academies", () => {
    for (const e of ELEMENTS) {
      if (e.demo === "shot") expect(existsSync(publicFile(e.shot!.src)), e.shot!.src).toBe(true);
      if (e.link) expect(e.link, e.key).toMatch(DEMO_HOSTS);
    }
    for (const locale of LOCALES) {
      for (const key of ["figure", "video"] as const) {
        const props = liveNode(key, locale)!.props as { image?: { src: string }; src?: string; poster?: string };
        for (const src of [props.image?.src, props.src, props.poster].filter(Boolean) as string[]) expect(existsSync(publicFile(src)), src).toBe(true);
      }
    }
  });

  it("labels what is not built: Python exercises and AI hints", () => {
    expect(ELEMENTS.filter((e) => e.coming).map((e) => e.key)).toEqual(["code"]);
    expect(learningCopy("en").elements.code.comingNote).toMatch(/Python/);
  });

  it("names only the quiz types the quiz player renders", () => {
    const quiz = learningCopy("en").elements.quiz.detail;
    for (const type of ["one or several right answers", "true/false", "short answer", "numerical", "matching", "essay"]) expect(quiz).toContain(type);
    expect(quiz).not.toMatch(/drag|ordering|cloze|hotspot/i);
  });
});

describe("learning elements: copy", () => {
  it("has the same structure in every language and no empty text", () => {
    const en = shape(learningCopy("en"));
    for (const locale of LOCALES) {
      expect(shape(learningCopy(locale)), locale).toEqual(en);
      for (const text of strings(learningCopy(locale))) expect(text.trim().length, locale).toBeGreaterThan(0);
    }
  });

  for (const locale of LOCALES) {
    it(`${locale}: the page is a valid, canonical document, in the sitemap and llms.txt`, async () => {
      const page = pageFor(locale);
      expect(validateDocument(page.doc, {})).toEqual([]);
      expect(page.canonical).toBe(`https://ulams.app${publicPagePath(locale, "learning-elements")}`);
      expect(page.meta.title.length).toBeLessThanOrEqual(70);
      expect(page.meta.description.length).toBeLessThanOrEqual(160);
      const xml = await (sitemap({ request: new Request("https://ulams.app/sitemap.xml"), url: new URL("https://ulams.app/sitemap.xml"), locals: { platform: true } } as never) as Response).text();
      expect(xml).toContain(`<loc>https://ulams.app${publicPagePath(locale, "learning-elements")}</loc>`);
    });
  }

  it("is listed in llms.txt", () => {
    expect(platformLlmsTxt("https://ulams.app")).toContain("https://ulams.app/learning-elements/");
  });
});

let container: AstroContainer;
const components: Record<string, unknown> = {};
beforeAll(async () => {
  container = await AstroContainer.create();
  for (const [name, path] of [["LearningBody", "../../src/components/learning/LearningBody.astro"], ["HomeBody", "../../src/components/diagrams/HomeBody.astro"]] as const) {
    components[name] = ((await import(/* @vite-ignore */ path)) as { default: unknown }).default;
  }
});
const dom = (html: string) => new JSDOM(`<!doctype html><html><body>${html}</body></html>`).window.document;

describe("learning elements: rendered markup", () => {
  for (const locale of LOCALES) {
    it(`${locale}: the page groups every element, with live elements, labelled screenshots and demo links`, async () => {
      const document = dom(await container.renderToString(components.LearningBody as never, { props: { shell: pageFor(locale), locale } }));
      expect(document.querySelectorAll("main#main")).toHaveLength(1);
      expect(document.querySelectorAll("h1")).toHaveLength(1);
      expect([...document.querySelectorAll("section.le--group")].map((s) => s.id)).toEqual([...GROUPS]);
      expect(document.querySelectorAll(".le-card")).toHaveLength(ELEMENTS.length);
      const copy = learningCopy(locale);
      expect(document.querySelector("#le-practice-question ulams-practice-question")).not.toBeNull();
      expect(document.querySelector("#le-flip-cards ulams-flip-cards")).not.toBeNull();
      expect(document.querySelector("#le-math math")).not.toBeNull();
      expect(document.querySelector("#le-video video")!.getAttribute("preload")).toBe("none");
      expect(document.querySelector("#le-certificate figcaption")!.textContent).toBe(copy.shotLabel);
      expect(document.querySelector("#le-code .u-status--coming")!.textContent).toBe(copy.coming);
      for (const link of document.querySelectorAll<HTMLAnchorElement>(".le-card__try a")) expect(link.getAttribute("data-umami-event")).toBe("demo_open");
      for (const card of document.querySelectorAll(".le-card")) expect(card.querySelector(".le-tag--principle")!.textContent!.length).toBeGreaterThan(3);
    });

    it(`${locale}: the home section follows the demos, with three live elements and every element linked to the page`, async () => {
      const { landingDocFor } = await import("../../src/lib/docs.ts");
      const { applyLandingStatus } = await import("../../src/lib/landing-status.ts");
      const { comparisonModel } = await import("../../src/lib/comparison.ts");
      const { workflowsModel } = await import("../../src/lib/workflows.ts");
      const doc = applyLandingStatus(landingDocFor("platform", locale)!, "actual");
      const demo = { title: "Demo", theme: "coffee", facts: [], primary: { label: "Open", href: "https://coffee.ulams.app/" }, secondary: { label: "Admin", href: "https://coffee-admin.ulams.app/" } };
      const data = { demos: [demo], demoCount: 1, comparison: comparisonModel(), workflows: workflowsModel("actual", locale) };
      const document = dom(await container.renderToString(components.HomeBody as never, { props: { doc, data, locale } }));
      const section = document.getElementById(LEARNING_ID)!;
      let previous = section.previousElementSibling;
      while (previous && previous.tagName !== "SECTION") previous = previous.previousElementSibling;
      expect(previous!.id).toBe("demos");
      expect(section.querySelectorAll(".le-live .le-card")).toHaveLength(HOME_LIVE.length);
      expect(section.querySelectorAll(".le-index li")).toHaveLength(ELEMENTS.length);
      const page = publicPagePath(locale, "learning-elements");
      for (const a of section.querySelectorAll(".le-index a")) expect(a.getAttribute("href")).toMatch(new RegExp(`^${page}#le-`));
      expect(section.querySelector(`a.u-btn[href="${page}"]`)!.getAttribute("data-umami-event")).toBe("learning_elements_open");
    });
  }
});
