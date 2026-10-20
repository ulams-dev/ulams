import { describe, expect, it } from "vitest";
import { validateDocument } from "@ulams/ui/render-core";
import type { UiNode } from "@ulams/ui/render-core";
import { CASE_IDS, COURSE_LINKS, SHOTS, SOURCE_LINKS, generationCopy } from "../../src/lib/course-generation.ts";
import { caseStudyPage, generationPage } from "../../src/lib/course-generation-page.ts";
import { LOCALES, publicPagePath } from "../../src/i18n/locales.ts";

const ofType = (node: UiNode, name: string): UiNode[] => [
  ...(node.component === name ? [node] : []),
  ...(node.children ?? []).flatMap((child) => ofType(child, name)),
];

const requestFor = (path: string) => new Request(`https://ulams.app${path}`);

describe("course generation pages", () => {
  for (const locale of LOCALES) {
    it(`${locale}: the overview and both case studies are valid and linked`, () => {
      const overview = generationPage(locale, requestFor(publicPagePath(locale, "generation")), new URL(`https://ulams.app${publicPagePath(locale, "generation")}`));
      expect(overview).not.toBeNull();
      expect(validateDocument(overview!.doc, overview!.data)).toEqual([]);
      expect(overview!.canonical).toBe(`https://ulams.app${publicPagePath(locale, "generation")}`);
      const main = overview!.doc.children!.find((node) => node.component === "Main")!;
      const cards = main.children!.find((node) => node.component === "Showcase")!;
      const hrefs = (cards.props!.items as Array<{ primary: { href: string } }>).map((item) => item.primary.href);
      expect(hrefs).toEqual([publicPagePath(locale, "case-microdosing"), publicPagePath(locale, "case-stablecoins")]);
      for (const caseId of CASE_IDS) {
        const pageName = caseId === "microdosing-study" ? "case-microdosing" : "case-stablecoins";
        const page = caseStudyPage(locale, caseId, requestFor(publicPagePath(locale, pageName)), new URL(`https://ulams.app${publicPagePath(locale, pageName)}`));
        expect(page).not.toBeNull();
        expect(validateDocument(page!.doc, page!.data)).toEqual([]);
        const main = page!.doc.children!.find((node) => node.component === "Main")!;
        const slider = ofType(main, "FrameSlider")[0]!;
        expect((slider.props!.frames as unknown[]).length).toBe(SHOTS[caseId].frames.length);
        expect(ofType(main, "Figure")).toHaveLength(0);
        expect(ofType(main, "VideoPlayer")).toHaveLength(2);
        const hero = ofType(main, "Hero")[0]!;
        expect((hero.props!.primaryCta as { href: string }).href).toBe(COURSE_LINKS[caseId]);
        expect((hero.props!.secondaryCta as { href: string }).href).toBe(SOURCE_LINKS[caseId]);
        expect(
          ofType(main, "CtaBand").some(
            (band) => (band.props!.cta as { href: string }).href === COURSE_LINKS[caseId] && (band.props!.secondary as { href: string }).href === SOURCE_LINKS[caseId],
          ),
        ).toBe(true);
      }
    });
  }

  it("points at the public source PDFs", () => {
    expect(SOURCE_LINKS["microdosing-study"]).toBe("https://www.nature.com/articles/s41598-021-01811-4.pdf");
    expect(SOURCE_LINKS["stablecoin-report"]).toBe("https://home.treasury.gov/system/files/136/StableCoinReport_Nov1_508.pdf");
  });

  it("keeps the same frames and the same number of captions in every language", () => {
    const english = generationCopy("en");
    for (const locale of LOCALES) {
      const copy = generationCopy(locale);
      expect(copy.overview.steps).toHaveLength(english.overview.steps.length);
      expect(copy.overview.cards).toHaveLength(2);
      for (const caseId of CASE_IDS) {
        expect(copy.cases[caseId].captions).toHaveLength(SHOTS[caseId].frames.length);
        expect(copy.cases[caseId].steps).toHaveLength(english.cases[caseId].steps.length);
        expect(copy.cases[caseId].captions.every((caption) => caption.trim().length > 0)).toBe(true);
        expect(copy.cases[caseId].source).toBeTruthy();
        expect(copy.cases[caseId].previous).toBeTruthy();
        expect(copy.cases[caseId].next).toBeTruthy();
      }
    }
  });
});
