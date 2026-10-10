/**
 * The platform product landing in one language: the document (with the display status applied), its
 * data model, the meta of the page and the language alternates. Shared by `/`, `/pl/` and `/zh/`.
 */
import type { UiNode } from "@ulams/ui/render-core";
import { landingDocFor, pageMeta, withLanguages } from "./docs.ts";
import { platformModel } from "./platform.ts";
import { applyLandingStatus } from "./landing-status.ts";
import { config } from "./config.ts";
import { LOCALE_META, alternateLinks, canonicalUrl, languageLinks, publicOrigin, type Locale } from "../i18n/locales.ts";

export async function platformPage(locale: Locale, request: Request, url: URL) {
  const base = landingDocFor("platform", locale);
  if (!base) return null;
  const doc: UiNode = withLanguages(applyLandingStatus(base, config.landingStatus), languageLinks(locale), LOCALE_META[locale].path);
  const data = await platformModel(url, config.landingStatus, locale);
  const origin = publicOrigin(request.headers, url);
  return {
    doc,
    data,
    meta: { ...pageMeta(doc, data), lang: LOCALE_META[locale].lang },
    canonical: canonicalUrl(origin, locale),
    alternates: alternateLinks(origin),
    ogLocale: { en: "en_US", pl: "pl_PL", zh: "zh_CN" }[locale],
  };
}
