import { describe, expect, it } from "vitest";
import { validateDocument } from "@ulams/ui/render-core";
import { comparisonDocFor, landingDocFor } from "../../src/lib/docs.ts";
import { faqContent } from "../../src/lib/faq.ts";
import { faqPage } from "../../src/lib/faq-page.ts";
import { publicPagePath } from "../../src/i18n/locales.ts";

describe("public landing FAQ", () => {
  for (const page of ["platform", "comparison"] as const) {
    for (const locale of ["en", "pl", "zh"] as const) {
      it(`${page}/${locale} keeps a short FAQ and links the FAQ page from its footer`, () => {
        const document = page === "platform"
          ? landingDocFor("platform", locale)!
          : comparisonDocFor(locale)!;
        const main = document.children!.find(node => node.component === "Main")!;
        const questions = main.children!.filter(node => node.component === "Faq");
        expect(questions).toHaveLength(1);
        expect(questions[0]!.props!.id).toBe("faq");
        expect((questions[0]!.props!.more as { href: string }).href).toBe(publicPagePath(locale, "faq"));
        const items = questions[0]!.props!.items as Array<{q:string;a:string}>;
        expect(items).toHaveLength(8);
        expect(new Set(items.map(item => item.q)).size).toBe(items.length);
        expect(items.every(item => item.q.trim() && item.a.trim())).toBe(true);
        const footer = document.children!.find(node => node.component === "SiteFooter")!;
        const footerLinks = footer.props!.links as Array<{ label: string; href: string }>;
        expect(footerLinks).toContainEqual({ label: "FAQ", href: publicPagePath(locale, "faq") });
        expect(footerLinks.some(link => /contact|kontakt|联系/i.test(link.label))).toBe(true);
        if (page === "comparison") expect(footerLinks.filter(link => link.href.startsWith("#"))).toEqual([]);
        if (page === "platform") {
          const contact = main.children!.find(node => node.component === "ContactForm");
          expect(contact?.props?.endpoint).toBe("https://api.ulams.app");
          expect(contact?.props?.successMessage).toBeTruthy();
        }
      });
    }
  }
});

describe("FAQ page", () => {
  const en = faqContent("en")!;

  it("keeps the same sections and question count in every language", () => {
    for (const locale of ["pl", "zh"] as const) {
      const other = faqContent(locale)!;
      expect(other.sections.map(s => s.id)).toEqual(en.sections.map(s => s.id));
      expect(other.sections.map(s => s.items.length)).toEqual(en.sections.map(s => s.items.length));
    }
  });

  for (const locale of ["en", "pl", "zh"] as const) {
    it(`${locale}: unique questions, a valid document and one FAQPage with every question`, () => {
      const content = faqContent(locale)!;
      const all = content.sections.flatMap(s => s.items);
      expect(all).toHaveLength(32);
      expect(new Set(all.map(item => item.q)).size).toBe(all.length);
      const request = new Request(`https://ulams.app${publicPagePath(locale, "faq")}`);
      const page = faqPage(locale, request, new URL(request.url))!;
      expect(validateDocument(page.doc, page.data)).toEqual([]);
      const main = page.doc.children!.find(node => node.component === "Main")!;
      const sections = main.children!.filter(node => node.component === "Faq");
      expect(sections.every(node => node.props!.jsonLd === false && node.props!.collapsible === false)).toBe(true);
      const faq = (page.jsonLd as Array<Record<string, unknown>>).filter(node => node["@type"] === "FAQPage");
      expect(faq).toHaveLength(1);
      expect((faq[0]!.mainEntity as unknown[]).length).toBe(all.length);
      expect(page.canonical).toBe(`https://ulams.app${publicPagePath(locale, "faq")}`);
    });
  }
});
