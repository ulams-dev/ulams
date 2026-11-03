/**
 * The page shell of the white paper and the style guide on the public product site: the site header and footer of
 * the language, a product hero, canonical and language alternates, and the JSON-LD of the page. The bodies are
 * Astro components (WhitePaperBody, StyleGuideBody) that render this shell around their own sections.
 */
import type { UiNode } from "@ulams/ui/render-core";
import { comparisonDocFor, withLanguages } from "./docs.ts";
import { LOCALE_META, languageLinks, pageAlternateLinks, pageCanonicalUrl, publicOrigin, type Locale } from "../i18n/locales.ts";

export type ProductPage = "white-paper" | "style-guide" | "learning-elements";

const OVERVIEW_NAME: Record<Locale, string> = { en: "Overview", pl: "Przegląd", zh: "产品概览" };

export interface ProductHero {
  title: string;
  description: string;
  eyebrow: string;
  heading: string;
  subtitle: string;
  body: string;
  primary: { label: string; href: string };
  secondary: { label: string; href: string };
  facts?: Array<{ label: string; value: string; note?: string }>;
}

export interface ProductShell {
  header: UiNode;
  hero: UiNode;
  footer: UiNode;
  /** The whole document (header, main with the hero, footer), for validation and metadata. */
  doc: UiNode;
  meta: { title: string; description: string; lang: string };
  canonical: string;
  alternates: Array<{ hreflang: string; href: string }>;
  ogLocale: string;
  jsonLd: Array<Record<string, unknown>>;
}

export function productShell(page: ProductPage, locale: Locale, hero: ProductHero, request: Request, url: URL, type: "Article" | "WebPage" = "WebPage"): ProductShell | null {
  const chrome = comparisonDocFor(locale);
  const header = chrome?.children?.find((node) => node.component === "SiteHeader");
  const footer = chrome?.children?.find((node) => node.component === "SiteFooter");
  if (!header || !footer) return null;
  const lang = LOCALE_META[locale].lang;
  const heroNode: UiNode = {
    component: "Hero",
    props: { variant: "product", eyebrow: hero.eyebrow, title: hero.heading, subtitle: hero.subtitle, body: hero.body, primaryCta: hero.primary, secondaryCta: hero.secondary, ...(hero.facts ? { facts: hero.facts } : {}) },
  };
  const base: UiNode = {
    component: "Page",
    props: { theme: "platform", title: hero.title, description: hero.description, lang },
    children: [header, { component: "Main", children: [heroNode] }, footer],
  };
  const doc = withLanguages(base, languageLinks(locale, page), LOCALE_META[locale].path);
  const origin = publicOrigin(request.headers, url);
  const canonical = pageCanonicalUrl(origin, locale, page);
  return {
    header: doc.children![0]!,
    hero: heroNode,
    footer: doc.children![2]!,
    doc,
    meta: { title: hero.title, description: hero.description, lang },
    canonical,
    alternates: pageAlternateLinks(origin, page),
    ogLocale: { en: "en_US", pl: "pl_PL", zh: "zh_CN" }[locale],
    jsonLd: [
      { "@context": "https://schema.org", "@type": "Organization", "@id": `${origin}/#organization`, name: "ulams", url: origin, logo: `${origin}/favicon.svg`, sameAs: ["https://github.com/ulams-dev"] },
      { "@context": "https://schema.org", "@type": "WebSite", "@id": `${origin}/#website`, url: origin, name: "ulams", inLanguage: lang, publisher: { "@id": `${origin}/#organization` } },
      {
        "@context": "https://schema.org",
        "@type": type,
        ...(type === "Article" ? { headline: hero.heading, author: { "@id": `${origin}/#organization` }, publisher: { "@id": `${origin}/#organization` } } : { name: hero.title }),
        description: hero.description,
        url: canonical,
        inLanguage: lang,
        isPartOf: { "@id": `${origin}/#website` },
        breadcrumb: { "@id": `${canonical}#breadcrumb` },
      },
      {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "@id": `${canonical}#breadcrumb`,
        itemListElement: [
          { "@type": "ListItem", position: 1, name: OVERVIEW_NAME[locale], item: new URL(LOCALE_META[locale].path, origin).href },
          { "@type": "ListItem", position: 2, name: hero.heading, item: canonical },
        ],
      },
    ],
  };
}
