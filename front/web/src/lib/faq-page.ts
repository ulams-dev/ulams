/**
 * The public FAQ page in one language: the comparison page's header and footer around one disclosure
 * list per topic, and a single FAQPage for the whole page. Shared by `/faq/`, `/pl/faq/` and `/zh/faq/`.
 */
import type { UiNode } from "@ulams/ui/render-core";
import { comparisonDocFor, withLanguages } from "./docs.ts";
import { faqContent } from "./faq.ts";
import { LOCALE_META, languageLinks, pageAlternateLinks, pageCanonicalUrl, publicOrigin, publicPagePath, type Locale } from "../i18n/locales.ts";

const OVERVIEW = { en: "Overview", pl: "Przegląd", zh: "产品概览" } as const;

export function faqPage(locale: Locale, request: Request, url: URL) {
  const chrome = comparisonDocFor(locale);
  const content = faqContent(locale);
  if (!chrome || !content) return null;
  const header = chrome.children!.find((node) => node.component === "SiteHeader")!;
  const footer = chrome.children!.find((node) => node.component === "SiteFooter")!;
  const lang = LOCALE_META[locale].lang;
  const base: UiNode = {
    component: "Page",
    props: { ...(chrome.props ?? {}), title: content.title, description: content.description, lang },
    children: [
      header,
      {
        component: "Main",
        children: [
          { component: "Hero", props: { variant: "product", eyebrow: content.eyebrow, title: content.heading, subtitle: content.intro, primaryCta: { label: content.contact, href: `${publicPagePath(locale)}#contact` }, secondaryCta: { label: content.docs, href: "https://ulams-dev.github.io/ulams/" } } },
          ...content.sections.map((section): UiNode => ({ component: "Faq", props: { id: `faq-${section.id}`, title: section.title, items: section.items, jsonLd: false, collapsible: false } })),
        ],
      },
      footer,
    ],
  };
  const doc = withLanguages(base, languageLinks(locale, "faq"), LOCALE_META[locale].path);
  const origin = publicOrigin(request.headers, url);
  const canonical = pageCanonicalUrl(origin, locale, "faq");
  const jsonLd = [
    { "@context": "https://schema.org", "@type": "Organization", "@id": `${origin}/#organization`, name: "ulams", url: origin, logo: `${origin}/favicon.svg`, sameAs: ["https://github.com/ulams-dev"] },
    { "@context": "https://schema.org", "@type": "WebSite", "@id": `${origin}/#website`, url: origin, name: "ulams", inLanguage: lang, publisher: { "@id": `${origin}/#organization` } },
    {
      "@context": "https://schema.org", "@type": "FAQPage", name: content.title, description: content.description, url: canonical, inLanguage: lang,
      isPartOf: { "@id": `${origin}/#website` }, breadcrumb: { "@id": `${canonical}#breadcrumb` },
      mainEntity: content.sections.flatMap((section) => section.items.map(({ q, a }) => ({ "@type": "Question", name: q, acceptedAnswer: { "@type": "Answer", text: a } }))),
    },
    { "@context": "https://schema.org", "@type": "BreadcrumbList", "@id": `${canonical}#breadcrumb`, itemListElement: [
      { "@type": "ListItem", position: 1, name: OVERVIEW[locale], item: new URL(LOCALE_META[locale].path, origin).href },
      { "@type": "ListItem", position: 2, name: content.heading, item: canonical },
    ] },
  ];
  return {
    doc,
    data: {},
    meta: { title: content.title, description: content.description, lang },
    canonical,
    alternates: pageAlternateLinks(origin, "faq"),
    ogLocale: { en: "en_US", pl: "pl_PL", zh: "zh_CN" }[locale],
    pageType: "faq" as const,
    jsonLd,
  };
}
