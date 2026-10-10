import { describe, expect, it } from "vitest";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import SiteHeader from "../src/components/SiteHeader.astro";
import SiteFooter from "../src/components/SiteFooter.astro";
import ComparisonTable from "../src/components/ComparisonTable.astro";
import { uiStrings } from "../src/lib/i18n.ts";

const I18N = { defaultLocale: "en", locales: ["en", "pl", { path: "zh", codes: ["zh"] }], routing: { prefixDefaultLocale: false } } as never;
const languages = [
  { code: "en", label: "EN", href: "/" },
  { code: "pl", label: "PL", href: "/pl/", current: true },
  { code: "zh-Hans", label: "中文", href: "/zh/" },
];

async function render(component: unknown, props: Record<string, unknown>, path: string, i18n = true): Promise<string> {
  const container = await AstroContainer.create(i18n ? { astroConfig: { i18n: I18N } as never } : {});
  return container.renderToString(component as never, { props, request: new Request(`http://app.localhost${path}`) });
}

describe("catalogue strings follow the page language", () => {
  const header = { brand: "ulams", mark: "logo", links: [{ label: "Produkt", href: "#product" }], languages, homeHref: "/pl/" };

  it("the header names its landmarks in Polish and links the brand to the language home", async () => {
    const html = await render(SiteHeader, header, "/pl/");
    expect(html).toContain(`aria-label="${uiStrings("pl").navMain}"`);
    expect(html).toContain(`aria-label="${uiStrings("pl").menu}"`);
    expect(html).toContain('href="/pl/"');
    expect(html).toContain("ulams, strona główna");
  });

  it("the header names its landmarks in Chinese", async () => {
    const html = await render(SiteHeader, { ...header, homeHref: "/zh/" }, "/zh/");
    expect(html).toContain(`aria-label="${uiStrings("zh").navMain}"`);
    expect(html).toContain("ulams，返回首页");
  });

  it("the switcher is its own navigation, marks the current language and carries the base for the anchor script", async () => {
    const html = await render(SiteHeader, header, "/pl/");
    expect(html).toContain(`<nav class="u-lang" aria-label="${uiStrings("pl").language}"`);
    expect(html).toMatch(/<a href="\/pl\/"[^>]*aria-current="true"[^>]*>\s*PL\s*<\/a>/);
    expect(html).toContain('hreflang="zh-Hans"');
    expect(html).toContain('data-base="/zh/"');
    // not one of the navigation links
    expect(html.match(/<nav class="u-nav"[\s\S]*?<\/nav>/)![0]).not.toContain("u-lang");
  });

  it("no switcher with a single language, and English without i18n routing", async () => {
    expect(await render(SiteHeader, { ...header, languages: [languages[0]] }, "/", false)).not.toContain("u-lang");
    const html = await render(SiteHeader, { brand: "ulams", links: [{ label: "Product", href: "#product" }] }, "/", false);
    expect(html).toContain('aria-label="Main"');
    expect(html).toContain("ulams, home");
  });

  it("the footer and the comparison table use the page language", async () => {
    expect(await render(SiteFooter, { brand: "ulams", links: [{ label: "A", href: "#a" }] }, "/zh/")).toContain(`aria-label="${uiStrings("zh").navFooter}"`);
    const table = await render(
      ComparisonTable,
      {
        caption: "c",
        asOf: "październik 2026",
        columns: [{ label: "ulams", highlight: true }],
        rows: [{ label: "Funkcja", cells: [{ value: "Tak", kind: "yes" }, { value: "x", kind: "no" }] }],
      },
      "/pl/"
    );
    expect(table).toContain("Przewiń w bok");
    expect(table).toContain(", stan na październik 2026");
    expect(table).toMatch(/data-kind="yes"/);
  });
});
