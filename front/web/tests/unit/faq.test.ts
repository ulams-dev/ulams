import { describe, expect, it } from "vitest";
import { comparisonDocFor, landingDocFor } from "../../src/lib/docs.ts";

describe("public landing FAQ", () => {
  for (const page of ["platform", "comparison"] as const) {
    for (const locale of ["en", "pl", "zh"] as const) {
      it(`${page}/${locale} exposes a unique FAQ from its footer`, () => {
        const document = page === "platform"
          ? landingDocFor("platform", locale)!
          : comparisonDocFor(locale)!;
        const main = document.children!.find(node => node.component === "Main")!;
        const questions = main.children!.filter(node => node.component === "Faq");
        expect(questions).toHaveLength(1);
        expect(questions[0]!.props!.id).toBe("faq");
        const items = questions[0]!.props!.items as Array<{q:string;a:string}>;
        expect(items).toHaveLength(8);
        expect(new Set(items.map(item => item.q)).size).toBe(items.length);
        expect(items.every(item => item.q.trim() && item.a.trim())).toBe(true);
        const footer = document.children!.find(node => node.component === "SiteFooter")!;
        const footerLinks = footer.props!.links as Array<{ label: string; href: string }>;
        expect(footerLinks).toContainEqual({ label: "FAQ", href: "#faq" });
        expect(footerLinks.some(link => /contact|kontakt|联系/i.test(link.label))).toBe(true);
        if (page === "platform") {
          const contact = main.children!.find(node => node.component === "ContactForm");
          expect(contact?.props?.endpoint).toBe("https://api.ulams.app");
          expect(contact?.props?.successMessage).toBeTruthy();
        }
      });
    }
  }
});
