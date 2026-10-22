import { describe, expect, it } from "vitest";
import { validateDocument } from "@ulams/ui/render-core";
import type { UiNode } from "@ulams/ui/render-core";
import { CHAPMAN_SOURCE, IMPACT_PAGES, impactCopy, impactPage } from "../../src/lib/impact-pages.ts";
import { applyLandingStatus } from "../../src/lib/landing-status.ts";
import { landingDocFor } from "../../src/lib/docs.ts";
import { LOCALES, publicPagePath } from "../../src/i18n/locales.ts";

const ofType = (node: UiNode, name: string): UiNode[] => [
  ...(node.component === name ? [node] : []),
  ...(node.children ?? []).flatMap((child) => ofType(child, name)),
];
const render = (page: (typeof IMPACT_PAGES)[number], locale: (typeof LOCALES)[number]) => {
  const path = publicPagePath(locale, page);
  return impactPage(page, locale, new Request(`https://ulams.app${path}`), new URL(`https://ulams.app${path}`));
};

describe("content cost and reporting pages", () => {
  it("have localized paths", () => {
    expect(publicPagePath("en", "content-cost")).toBe("/content-cost/");
    expect(publicPagePath("pl", "reporting")).toBe("/pl/reporting/");
    expect(publicPagePath("zh", "content-cost")).toBe("/zh/content-cost/");
  });

  for (const page of IMPACT_PAGES) {
    for (const locale of LOCALES) {
      it(`${page} ${locale}: is a valid, canonical document with a single h1 source`, () => {
        const result = render(page, locale)!;
        expect(result).not.toBeNull();
        expect(validateDocument(result.doc, result.data)).toEqual([]);
        expect(result.canonical).toBe(`https://ulams.app${publicPagePath(locale, page)}`);
        expect(String(result.doc.props!.title).length).toBeLessThanOrEqual(70);
        expect(String(result.doc.props!.description).length).toBeLessThanOrEqual(160);
        expect(ofType(result.doc, "Hero")).toHaveLength(1);
      });
    }

    it(`${page}: every language has the same items and statuses as English`, () => {
      const en = impactCopy(page, "en");
      for (const locale of LOCALES) {
        const copy = impactCopy(page, locale);
        expect(copy.today.map((i) => i.icon)).toEqual(en.today.map((i) => i.icon));
        expect(copy.coming.map((i) => i.icon)).toEqual(en.coming.map((i) => i.icon));
        expect(copy.ctaHref).toBe(en.ctaHref);
        for (const item of [...copy.today, ...copy.coming]) expect(item.text.trim().length).toBeGreaterThan(0);
        if (locale !== "en") expect(copy.heading).not.toBe(en.heading);
      }
    });

    it(`${page}: keeps shipped and planned items apart, planned ones with the coming status`, () => {
      const result = render(page, "en")!;
      const lists = ofType(applyLandingStatus(result.doc, "actual"), "FeatureList");
      const statuses = lists.map((l) => new Set((l.props!.items as Array<{ status: string }>).map((i) => i.status)));
      expect(statuses).toEqual([new Set(["available"]), new Set(["coming"])]);
    });
  }

  it("links the Chapman figures to elearningindustry.com and quotes no ULAMS savings", () => {
    for (const locale of LOCALES) {
      const copy = impactCopy("content-cost", locale);
      expect(copy.problem).toContain(CHAPMAN_SOURCE);
      expect(copy.problem).toContain("197");
      expect(copy.problem).toContain("49");
      expect(copy.problem).toContain("700");
    }
    expect(new URL(CHAPMAN_SOURCE).hostname).toBe("elearningindustry.com");
  });

  it("cites the satisfaction statistic as industry research, without a link", () => {
    for (const locale of LOCALES) {
      const copy = impactCopy("reporting", locale);
      expect(copy.problem).toContain("9%");
      expect(copy.problem).not.toMatch(/https?:\/\//);
    }
  });
});

describe("home call-to-action bands", () => {
  for (const locale of LOCALES) {
    it(`${locale}: two separate bands that link to the two pages`, () => {
      const doc = landingDocFor("platform", locale)!;
      const bands = ofType(doc, "CtaBand");
      const cost = bands.find((b) => b.props!.id === "cost-cta")!;
      const reporting = bands.find((b) => b.props!.id === "reporting-cta")!;
      expect((cost.props!.cta as { href: string }).href).toBe(publicPagePath(locale, "content-cost"));
      expect((reporting.props!.cta as { href: string }).href).toBe(publicPagePath(locale, "reporting"));
      const main = doc.children!.find((n) => n.component === "Main")!;
      const order = main.children!.map((n) => String(n.props?.id ?? n.component));
      expect(Math.abs(order.indexOf("cost-cta") - order.indexOf("reporting-cta"))).toBeGreaterThan(1);
    });
  }
});
