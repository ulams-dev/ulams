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
    it(`${locale}: the overview and all case studies are valid and linked`, () => {
      const overview = generationPage(locale, requestFor(publicPagePath(locale, "generation")), new URL(`https://ulams.app${publicPagePath(locale, "generation")}`));
      expect(overview).not.toBeNull();
      expect(validateDocument(overview!.doc, overview!.data)).toEqual([]);
      expect(overview!.canonical).toBe(`https://ulams.app${publicPagePath(locale, "generation")}`);
      const main = overview!.doc.children!.find((node) => node.component === "Main")!;
      const cards = main.children!.find((node) => node.component === "Showcase")!;
      const hrefs = (cards.props!.items as Array<{ primary: { href: string } }>).map((item) => item.primary.href);
      expect(hrefs).toEqual([publicPagePath(locale, "case-microdosing"), publicPagePath(locale, "case-stablecoins"), publicPagePath(locale, "case-docs-repo"), publicPagePath(locale, "case-notion")]);
      for (const caseId of CASE_IDS) {
        const pageName = ({ "microdosing-study": "case-microdosing", "stablecoin-report": "case-stablecoins", "docs-repo": "case-docs-repo", "notion-page": "case-notion" } as const)[caseId];
        const page = caseStudyPage(locale, caseId, requestFor(publicPagePath(locale, pageName)), new URL(`https://ulams.app${publicPagePath(locale, pageName)}`));
        expect(page).not.toBeNull();
        expect(validateDocument(page!.doc, page!.data)).toEqual([]);
        const main = page!.doc.children!.find((node) => node.component === "Main")!;
        const slider = ofType(main, "FrameSlider")[0]!;
        expect((slider.props!.frames as unknown[]).length).toBe(SHOTS[caseId].frames.length);
        expect(ofType(main, "Figure")).toHaveLength(0);
        expect(ofType(main, "VideoPlayer")).toHaveLength(caseId === "docs-repo" ? 3 : 2);
        const hero = ofType(main, "Hero")[0]!;
        expect((hero.props!.primaryCta as { href: string }).href).toBe(COURSE_LINKS[caseId] ?? SOURCE_LINKS[caseId]);
        expect((hero.props!.secondaryCta as { href: string }).href).toBe(SOURCE_LINKS[caseId]);
        expect(
          ofType(main, "CtaBand").some(
            (band) => (band.props!.cta as { href: string }).href === (COURSE_LINKS[caseId] ?? SOURCE_LINKS[caseId]) && (band.props!.secondary as { href: string }).href === SOURCE_LINKS[caseId],
          ),
        ).toBe(true);
      }
    });
  }

  it("shows the measured runs as one run each, in every language", () => {
    for (const locale of LOCALES) {
      const copy = generationCopy(locale);
      const text = (value: unknown) => JSON.stringify(value);
      expect(text(copy.cases["docs-repo"].facts)).toMatch(/7\D+44/);
      expect(text(copy.cases["docs-repo"].facts)).toContain("0.28".replace(".", locale === "pl" ? "," : "."));
      expect(text(copy.cases["notion-page"].facts)).toMatch(/4\D+58/);
      expect(text(copy.cases["notion-page"].facts)).toContain(locale === "pl" ? "0,1973" : "0.1973");
      expect(text(copy.overview.cards[2])).toMatch(/7\D+44/);
      expect(text(copy.overview.cards[3])).toContain(locale === "pl" ? "0,1973" : "0.1973");
      expect(copy.overview.note.length).toBeGreaterThan(60);
    }
  });

  it("points at the public sources", () => {
    expect(SOURCE_LINKS["microdosing-study"]).toBe("https://www.nature.com/articles/s41598-021-01811-4.pdf");
    expect(SOURCE_LINKS["stablecoin-report"]).toBe("https://home.treasury.gov/system/files/136/StableCoinReport_Nov1_508.pdf");
    expect(SOURCE_LINKS["docs-repo"]).toBe("https://github.com/ulams-dev/demo-docs");
    expect(SOURCE_LINKS["notion-page"]).toBe("https://ulams-dev.github.io/ulams/creators/course-builder/");
  });

  it("keeps the same frames and the same number of captions in every language", () => {
    const english = generationCopy("en");
    for (const locale of LOCALES) {
      const copy = generationCopy(locale);
      expect(copy.overview.steps).toHaveLength(english.overview.steps.length);
      expect(copy.overview.cards).toHaveLength(CASE_IDS.length);
      for (const caseId of CASE_IDS) {
        expect(copy.cases[caseId].captions).toHaveLength(SHOTS[caseId].frames.length);
        expect(copy.cases[caseId].steps).toHaveLength(english.cases[caseId].steps.length);
        expect(copy.cases[caseId].captions.every((caption) => caption.trim().length > 0)).toBe(true);
        expect(copy.cases[caseId].source).toBeTruthy();
        expect(copy.cases[caseId].previous).toBeTruthy();
        expect(copy.cases[caseId].next).toBeTruthy();
        expect(Boolean(copy.cases[caseId].syncVideo), caseId).toBe(Boolean(english.cases[caseId].syncVideo));
      }
    }
  });
});

describe("case study media", () => {
  it("references only files that exist under public/ (posters, frames, videos)", async () => {
    const { existsSync } = await import("node:fs");
    const { fileURLToPath } = await import("node:url");
    const publicDir = fileURLToPath(new URL("../../public", import.meta.url));
    const pageNames = { "microdosing-study": "case-microdosing", "stablecoin-report": "case-stablecoins", "docs-repo": "case-docs-repo", "notion-page": "case-notion" } as const;
    for (const caseId of CASE_IDS) {
      const path = publicPagePath("en", pageNames[caseId]);
      const page = caseStudyPage("en", caseId, requestFor(path), new URL(`https://ulams.app${path}`));
      const refs = new Set(JSON.stringify(page!.doc).match(/\/course-generation\/[\w./-]+\.(?:png|jpe?g|webp|mp4|webm)/g) ?? []);
      expect(refs.size).toBeGreaterThan(0);
      for (const ref of refs) expect(existsSync(`${publicDir}${ref}`), `${caseId}: ${ref}`).toBe(true);
    }
  });
});
