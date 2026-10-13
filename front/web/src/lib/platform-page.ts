/**
 * The platform product landing in one language: the document (with the display status applied), its
 * data model, the meta of the page and the language alternates. Shared by `/`, `/pl/` and `/zh/`.
 */
import type { UiNode } from "@ulams/ui/render-core";
import { comparisonDocFor, landingDocFor, pageMeta, withLanguages } from "./docs.ts";
import { platformModel } from "./platform.ts";
import { comparisonData, comparisonModel } from "./comparison.ts";
import { applyLandingStatus } from "./landing-status.ts";
import { LOCALE_META, alternateLinks, canonicalUrl, languageLinks, pageAlternateLinks, pageCanonicalUrl, publicOrigin, type Locale } from "../i18n/locales.ts";

function structuredData(locale: Locale, canonical: string, page: "overview" | "comparison", title: string, description?: string) {
  const origin = new URL(canonical).origin;
  const organization = { "@type": "Organization", "@id": `${origin}/#organization`, name: "ulams", url: origin, logo: `${origin}/favicon.svg`, sameAs: ["https://github.com/ulams-dev"] };
  const website = { "@type": "WebSite", "@id": `${origin}/#website`, url: origin, name: "ulams", inLanguage: LOCALE_META[locale].lang, publisher: { "@id": `${origin}/#organization` } };
  if (page === "overview") return [
    { "@context": "https://schema.org", ...organization },
    { "@context": "https://schema.org", ...website },
    { "@context": "https://schema.org", "@type": "SoftwareApplication", name: "ulams", applicationCategory: "EducationalApplication", operatingSystem: "Web", url: canonical, description, publisher: { "@id": `${origin}/#organization` }, inLanguage: LOCALE_META[locale].lang },
  ];
  return [
    { "@context": "https://schema.org", ...organization },
    { "@context": "https://schema.org", ...website },
    { "@context": "https://schema.org", "@type": "CollectionPage", name: title, description, url: canonical, inLanguage: LOCALE_META[locale].lang, isPartOf: { "@id": `${origin}/#website` }, breadcrumb: { "@id": `${canonical}#breadcrumb` } },
    { "@context": "https://schema.org", "@type": "BreadcrumbList", "@id": `${canonical}#breadcrumb`, itemListElement: [
      { "@type": "ListItem", position: 1, name: locale === "pl" ? "Przegląd" : locale === "zh" ? "产品概览" : "Overview", item: new URL(LOCALE_META[locale].path, origin).href },
      { "@type": "ListItem", position: 2, name: title, item: canonical },
    ] },
  ];
}

export async function platformPage(locale: Locale, request: Request, url: URL) {
  const base = landingDocFor("platform", locale);
  if (!base) return null;
  // Public claims must match shipped status even if a local preview asks the legacy switch to hide roadmap labels.
  const doc: UiNode = withLanguages(applyLandingStatus(base, "actual"), languageLinks(locale), LOCALE_META[locale].path);
  const data = await platformModel(url, "actual", locale);
  const origin = publicOrigin(request.headers, url);
  return {
    doc,
    data,
    meta: { ...pageMeta(doc, data), lang: LOCALE_META[locale].lang },
    canonical: canonicalUrl(origin, locale),
    alternates: alternateLinks(origin),
    ogLocale: { en: "en_US", pl: "pl_PL", zh: "zh_CN" }[locale],
    pageType: "overview" as const,
    jsonLd: structuredData(locale, canonicalUrl(origin, locale), "overview", pageMeta(doc, data).title, pageMeta(doc, data).description),
  };
}

export function comparisonPage(locale: Locale, request: Request, url: URL) {
  const base = comparisonDocFor(locale);
  if (!base) return null;
  const doc = withLanguages(base, languageLinks(locale, "comparison"), LOCALE_META[locale].path);
  const data = { comparison: comparisonModel(comparisonData, locale) };
  const origin = publicOrigin(request.headers, url);
  return {
    doc,
    data,
    meta: { ...pageMeta(doc, data), lang: LOCALE_META[locale].lang },
    canonical: pageCanonicalUrl(origin, locale, "comparison"),
    alternates: pageAlternateLinks(origin, "comparison"),
    ogLocale: { en: "en_US", pl: "pl_PL", zh: "zh_CN" }[locale],
    pageType: "comparison" as const,
    jsonLd: structuredData(locale, pageCanonicalUrl(origin, locale, "comparison"), "comparison", pageMeta(doc, data).title, pageMeta(doc, data).description),
  };
}
