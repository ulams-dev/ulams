/**
 * Languages of the platform product landing (ADR 0096): English at `/`, Polish at `/pl/`, Simplified
 * Chinese at `/zh/`. The same list is configured in astro.config.mjs (Astro i18n routing).
 */
export const LOCALES = ["en", "pl", "zh"] as const;
export type Locale = (typeof LOCALES)[number];
export const DEFAULT_LOCALE: Locale = "en";

export interface LocaleMeta {
  /** `<html lang>` and hreflang value. */
  lang: string;
  /** Short label of the switcher, in the language itself. */
  label: string;
  /** Name of the language in that language. */
  name: string;
  /** Path of the landing in that language. */
  path: string;
}

export const LOCALE_META: Record<Locale, LocaleMeta> = {
  en: { lang: "en", label: "EN", name: "English", path: "/" },
  pl: { lang: "pl", label: "PL", name: "Polski", path: "/pl/" },
  zh: { lang: "zh-Hans", label: "中文", name: "简体中文", path: "/zh/" },
};

export const PUBLIC_PAGES = ["overview", "comparison", "faq", "generation", "case-microdosing", "case-stablecoins"] as const;
export type PublicPage = (typeof PUBLIC_PAGES)[number];

/** Canonical paths for the public product pages, translated in each supported locale. */
export function publicPagePath(locale: Locale, page: PublicPage = "overview"): string {
  const prefix = locale === "en" ? "" : `/${locale}`;
  if (page === "comparison") return `${prefix}/comparison/`;
  if (page === "faq") return `${prefix}/faq/`;
  if (page === "generation") return `${prefix}/course-generation/`;
  if (page === "case-microdosing") return `${prefix}/course-generation/microdosing-study/`;
  if (page === "case-stablecoins") return `${prefix}/course-generation/stablecoin-report/`;
  return locale === "en" ? "/" : `${prefix}/`;
}

export const isLocale = (value: unknown): value is Locale => typeof value === "string" && (LOCALES as readonly string[]).includes(value);

/** The switcher of the site header: one entry per language, the current one marked. */
export function languageLinks(current: Locale, page: PublicPage = "overview"): Array<{ code: string; label: string; href: string; current: boolean }> {
  return LOCALES.map((locale) => ({ code: LOCALE_META[locale].lang, label: LOCALE_META[locale].label, href: publicPagePath(locale, page), current: locale === current }));
}

/** `<link rel="alternate" hreflang>` entries of the landing, plus x-default (English). */
export function alternateLinks(origin: string): Array<{ hreflang: string; href: string }> {
  return [
    ...LOCALES.map((locale) => ({ hreflang: LOCALE_META[locale].lang, href: new URL(LOCALE_META[locale].path, origin).href })),
    { hreflang: "x-default", href: new URL(LOCALE_META[DEFAULT_LOCALE].path, origin).href },
  ];
}

export function pageAlternateLinks(origin: string, page: PublicPage) {
  return [...LOCALES.map((locale) => ({ hreflang: LOCALE_META[locale].lang, href: new URL(publicPagePath(locale, page), origin).href })),
    { hreflang: "x-default", href: new URL(publicPagePath(DEFAULT_LOCALE, page), origin).href }];
}

export const canonicalUrl = (origin: string, locale: Locale): string => new URL(LOCALE_META[locale].path, origin).href;
export const pageCanonicalUrl = (origin: string, locale: Locale, page: PublicPage): string => new URL(publicPagePath(locale, page), origin).href;

/** The origin the visitor used (behind the proxy: the forwarded host and scheme). */
export function publicOrigin(headers: Headers, url: URL): string {
  const host = headers.get("x-forwarded-host") ?? headers.get("host") ?? url.host;
  const proto = (headers.get("x-forwarded-proto") ?? url.protocol.replace(":", "")).split(",")[0]!.trim();
  return `${proto}://${host.split(",")[0]!.trim()}`;
}
