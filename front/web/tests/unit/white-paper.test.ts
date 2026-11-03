import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { JSDOM } from "jsdom";
import { beforeAll, describe, expect, it } from "vitest";
import { validateDocument } from "@ulams/ui/render-core";
import { AI_ACT_SOURCE, WP_SECTIONS, whitePaperCopy, whitePaperPage } from "../../src/lib/white-paper.ts";
import { BRANDON_HALL_SOURCE, CHAPMAN_SOURCE } from "../../src/lib/impact-pages.ts";
import { landingDocFor } from "../../src/lib/docs.ts";
import { GET as sitemap } from "../../src/pages/sitemap.xml.ts";
import { LOCALES, publicPagePath, type Locale } from "../../src/i18n/locales.ts";

const shape = (value: unknown): unknown => {
  if (Array.isArray(value)) return value.map(shape);
  if (value && typeof value === "object") return Object.fromEntries(Object.entries(value).map(([k, v]) => [k, shape(v)]));
  return typeof value;
};
const RATIO = /times faster|\d\s*(x|×)\b|\d+\s*times\b|razy szybciej|\d\s*razy\b|\d\s*倍/i;
const COMING: Record<Locale, string> = { en: "Coming", pl: "Wkrótce", zh: "即将推出" };
const pageFor = (locale: Locale) => {
  const path = publicPagePath(locale, "white-paper");
  return whitePaperPage(locale, new Request(`https://ulams.app${path}`), new URL(`https://ulams.app${path}`))!;
};

describe("white paper: copy", () => {
  it("has the same structure in every language", () => {
    const en = shape(whitePaperCopy("en"));
    for (const locale of LOCALES) expect(shape(whitePaperCopy(locale)), locale).toEqual(en);
  });

  for (const locale of LOCALES) {
    it(`${locale}: every figure has its source, and the run is labelled as one run`, () => {
      const copy = whitePaperCopy(locale);
      const sources = copy.sources.map((s) => s.href);
      expect(sources).toEqual(expect.arrayContaining([CHAPMAN_SOURCE, BRANDON_HALL_SOURCE, AI_ACT_SOURCE, publicPagePath(locale, "case-docs-repo"), publicPagePath(locale, "case-notion")]));
      const problem = copy.sections.problem.markdown;
      expect(problem).toMatch(/49/);
      expect(problem).toMatch(/716/);
      expect(problem).toContain(locale === "pl" ? "45,5%" : "45.5%");
      expect(problem).toContain(locale === "pl" ? "47,7%" : "47.7%");
      expect(problem).toContain("457");
      const example = copy.sections.example.markdown;
      expect(example).toMatch(/7\D+44/);
      expect(example).toContain(locale === "pl" ? "0,28" : "0.28");
      expect(example).toContain(locale === "pl" ? "0,1973" : "0.1973");
      expect(example).toContain("35");
      expect(JSON.stringify(copy).replace(/ROI/g, "")).not.toMatch(RATIO);
    });

    it(`${locale}: claims only what is shipped and labels the rest as coming`, () => {
      const { sections } = whitePaperCopy(locale);
      const architecture = sections.architecture.markdown;
      const row = (needle: string) => architecture.split("\n").find((line) => line.includes(needle))!;
      expect(row("SCIM 2.0")).toContain(COMING[locale]);
      expect(row("HRIS")).toContain(COMING[locale]);
      expect(row("SAML 2.0")).not.toContain(COMING[locale]);
      expect(row("LTI 1.3")).not.toContain(COMING[locale]);
      expect(sections.mechanism.markdown.split("\n").find((line) => line.startsWith("| 5a."))).toContain(COMING[locale]);
      expect(sections.trust.markdown).toContain("2024/1689");
      expect(sections.trust.markdown).toMatch(/2 December 2026|2 grudnia 2026|2026 年 12 月 2 日/);
    });
  }
});

describe("white paper: page", () => {
  for (const locale of LOCALES) {
    it(`${locale}: is a valid, canonical article with one hero, in the sitemap and linked from the home page`, async () => {
      const page = pageFor(locale);
      expect(validateDocument(page.doc, {})).toEqual([]);
      expect(page.canonical).toBe(`https://ulams.app${publicPagePath(locale, "white-paper")}`);
      expect(page.meta.title.length).toBeLessThanOrEqual(70);
      expect(page.meta.description.length).toBeLessThanOrEqual(160);
      expect(page.jsonLd.some((item) => item["@type"] === "Article")).toBe(true);
      const band = landingDocFor("platform", locale)!.children!.find((n) => n.component === "Main")!.children!.find((n) => n.props?.id === "white-paper-cta")!;
      expect((band.props!.cta as { href: string }).href).toBe(publicPagePath(locale, "white-paper"));
    });
  }

  it("lists the white paper and the style guide in the sitemap in every language", async () => {
    const response = sitemap({ request: new Request("https://ulams.app/sitemap.xml"), url: new URL("https://ulams.app/sitemap.xml"), locals: { platform: true } } as never) as Response;
    const xml = await response.text();
    for (const locale of LOCALES) {
      expect(xml).toContain(`<loc>https://ulams.app${publicPagePath(locale, "white-paper")}</loc>`);
      expect(xml).toContain(`<loc>https://ulams.app${publicPagePath(locale, "style-guide")}</loc>`);
    }
  });
});

let container: AstroContainer;
let Body: unknown;
beforeAll(async () => {
  container = await AstroContainer.create();
  // tsc has no types for .astro files (astro check covers them), so the component is imported by a plain string
  const path = "../../src/components/white-paper/WhitePaperBody.astro";
  Body = ((await import(/* @vite-ignore */ path)) as { default: unknown }).default;
});

describe("white paper: rendered markup", () => {
  for (const locale of LOCALES) {
    it(`${locale}: sections in order with a contents list, numbered sources and a print button that waits for the script`, async () => {
      const html = await container.renderToString(Body as never, { props: { shell: pageFor(locale), locale } });
      const document = new JSDOM(`<!doctype html><html><body>${html}</body></html>`).window.document;
      expect(document.querySelectorAll("main#main")).toHaveLength(1);
      expect(document.querySelectorAll("h1")).toHaveLength(1);
      const ids = [...document.querySelectorAll(".wp-section")].map((s) => s.id);
      expect(ids).toEqual([...WP_SECTIONS, "sources"]);
      expect([...document.querySelectorAll(".wp-toc a")].map((a) => a.getAttribute("href"))).toEqual(ids.map((id) => `#${id}`));
      expect(document.querySelectorAll(".wp-sources li")).toHaveLength(whitePaperCopy(locale).sources.length);
      expect(document.querySelectorAll(".wp-section table").length).toBeGreaterThanOrEqual(3);
      const button = document.querySelector<HTMLButtonElement>("[data-wp-print]")!;
      expect(button.hasAttribute("hidden")).toBe(true);
      expect(button.getAttribute("type")).toBe("button");
      expect(button.getAttribute("data-umami-event")).toBe("white_paper_print");
      expect(document.querySelector(".wp-print__note")!.textContent).toBe(whitePaperCopy(locale).printNote);
    });
  }
});
