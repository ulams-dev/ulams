/**
 * Public pages for how a course is generated, plus one page per recorded case study.
 * Shared by the English, Polish and Chinese routes.
 */
import type { UiNode } from "@ulams/ui/render-core";
import { comparisonDocFor, withLanguages } from "./docs.ts";
import { CASE_IDS, COURSE_LINKS, SHOTS, SOURCE_LINKS, generationCopy, type CaseId } from "./course-generation.ts";
import { LOCALE_META, languageLinks, pageAlternateLinks, pageCanonicalUrl, publicOrigin, publicPagePath, type Locale, type PublicPage } from "../i18n/locales.ts";

const OVERVIEW_NAME = { en: "Overview", pl: "Przegląd", zh: "产品概览" } as const;
const GUIDE = "https://ulams-dev.github.io/ulams/creators/course-builder/";

function chrome(locale: Locale) {
  const doc = comparisonDocFor(locale);
  if (!doc?.children) return null;
  const header = doc.children.find((node) => node.component === "SiteHeader");
  const footer = doc.children.find((node) => node.component === "SiteFooter");
  if (!header || !footer) return null;
  return { header, footer };
}

const CASE_PAGE: Record<CaseId, PublicPage> = {
  "microdosing-study": "case-microdosing",
  "stablecoin-report": "case-stablecoins",
  "docs-repo": "case-docs-repo",
};

/** Screencasts of each case, in the order they are shown. */
const CASE_VIDEOS: Record<CaseId, { build: string; publish: string; sync?: string }> = {
  "microdosing-study": { build: "/course-generation/microdosing-build.mp4", publish: "/course-generation/microdosing-publish.mp4" },
  "stablecoin-report": { build: "/course-generation/stablecoin-build.mp4", publish: "/course-generation/stablecoin-publish.mp4" },
  "docs-repo": { build: "/course-generation/pigeon-build.mp4", publish: "/course-generation/pigeon-publish.mp4", sync: "/course-generation/pigeon-living.mp4" },
};

function video(src: string, poster: string, title: string): UiNode {
  return { component: "VideoPlayer", props: { src, poster, width: 960, height: 540, title } };
}

function frames(caseId: CaseId, copy: ReturnType<typeof generationCopy>["cases"][CaseId]): UiNode {
  const shots = SHOTS[caseId];
  return {
    component: "FrameSlider",
    props: {
      id: "screens",
      title: copy.framesTitle,
      intro: copy.framesIntro,
      previous: copy.previous,
      next: copy.next,
      frames: shots.frames.map((frame, index) => ({
        image: {
          src: `/course-generation/${shots.folder}/${frame.file}`,
          alt: copy.captions[index] ?? "",
          width: frame.width,
          height: frame.height,
        },
        caption: copy.captions[index] ?? "",
      })),
    },
  };
}

function contained(children: UiNode[]): UiNode {
  return { component: "Stack", props: { gap: "lg" }, children };
}

function pageShell(locale: Locale, page: PublicPage, title: string, description: string, main: UiNode[]): UiNode | null {
  const parts = chrome(locale);
  if (!parts) return null;
  const base: UiNode = {
    component: "Page",
    props: { theme: "platform", title, description, lang: LOCALE_META[locale].lang },
    children: [parts.header, { component: "Main", children: main }, parts.footer],
  };
  return withLanguages(base, languageLinks(locale, page), LOCALE_META[locale].path);
}

function crumbs(origin: string, canonical: string, items: Array<{ name: string; href: string }>) {
  return {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "@id": `${canonical}#breadcrumb`,
    itemListElement: items.map((item, index) => ({ "@type": "ListItem", position: index + 1, name: item.name, item: new URL(item.href, origin).href })),
  };
}

function envelope(locale: Locale, request: Request, url: URL, page: PublicPage, doc: UiNode, description: string) {
  const origin = publicOrigin(request.headers, url);
  const canonical = pageCanonicalUrl(origin, locale, page);
  const home = new URL(LOCALE_META[locale].path, origin).href;
  const jsonLd = [
    { "@context": "https://schema.org", "@type": "Organization", "@id": `${origin}/#organization`, name: "ulams", url: origin, logo: `${origin}/favicon.svg`, sameAs: ["https://github.com/ulams-dev"] },
    { "@context": "https://schema.org", "@type": "WebSite", "@id": `${origin}/#website`, url: origin, name: "ulams", inLanguage: LOCALE_META[locale].lang, publisher: { "@id": `${origin}/#organization` } },
    { "@context": "https://schema.org", "@type": "WebPage", name: doc.props?.title, description, url: canonical, inLanguage: LOCALE_META[locale].lang, isPartOf: { "@id": `${origin}/#website` }, breadcrumb: { "@id": `${canonical}#breadcrumb` } },
    crumbs(origin, canonical, [
      { name: OVERVIEW_NAME[locale], href: home },
      { name: String(doc.props?.title ?? ""), href: canonical },
    ]),
  ];
  return {
    doc,
    data: {},
    meta: { title: String(doc.props?.title ?? ""), description, lang: LOCALE_META[locale].lang },
    canonical,
    alternates: pageAlternateLinks(origin, page),
    ogLocale: { en: "en_US", pl: "pl_PL", zh: "zh_CN" }[locale],
    jsonLd,
  };
}

export function generationPage(locale: Locale, request: Request, url: URL) {
  const copy = generationCopy(locale).overview;
  const doc = pageShell(locale, "generation", copy.title, copy.description, [
    {
      component: "Hero",
      props: {
        variant: "product",
        eyebrow: copy.eyebrow,
        title: copy.heading,
        subtitle: copy.subtitle,
        body: copy.body,
        primaryCta: { label: copy.primary, href: publicPagePath(locale, "case-microdosing") },
        secondaryCta: { label: copy.secondary, href: GUIDE },
      },
    },
    { component: "Steps", props: { eyebrow: copy.eyebrow, title: copy.stepsTitle, intro: copy.stepsIntro, items: copy.steps } },
    {
      component: "Showcase",
      props: {
        eyebrow: copy.eyebrow,
        title: copy.casesTitle,
        intro: copy.casesIntro,
        note: copy.note,
        items: copy.cards.map((card, index) => ({
          title: card.title,
          text: card.text,
          theme: ["gravity", "nightsky", "oncall"][index] ?? "platform",
          facts: card.facts,
          primary: { label: card.cta, href: publicPagePath(locale, CASE_PAGE[CASE_IDS[index]!]) },
        })),
      },
    },
    contained([{ component: "Callout", props: { tone: "key", title: copy.boundaryTitle, text: copy.boundary } }]),
    {
      component: "CtaBand",
      props: {
        title: copy.ctaTitle,
        text: copy.ctaText,
        cta: { label: copy.cta, href: GUIDE },
        secondary: { label: copy.docs, href: "https://ulams-dev.github.io/ulams/" },
      },
    },
  ]);
  if (!doc) return null;
  return envelope(locale, request, url, "generation", doc, copy.description);
}

export function caseStudyPage(locale: Locale, caseId: CaseId, request: Request, url: URL) {
  const copy = generationCopy(locale).cases[caseId];
  const next = CASE_IDS[(CASE_IDS.indexOf(caseId) + 1) % CASE_IDS.length]!;
  const poster = `/course-generation/${SHOTS[caseId].folder}/02-studio.png`;
  const videos = CASE_VIDEOS[caseId];
  const course = COURSE_LINKS[caseId];
  const source = SOURCE_LINKS[caseId];
  const doc = pageShell(locale, CASE_PAGE[caseId], copy.title, copy.description, [
    {
      component: "Hero",
      props: {
        variant: "product",
        eyebrow: copy.eyebrow,
        title: copy.heading,
        subtitle: copy.subtitle,
        body: copy.body,
        facts: copy.facts,
        primaryCta: { label: copy.preview, href: course ?? source },
        secondaryCta: { label: copy.source, href: source },
      },
    },
    {
      component: "CtaBand",
      props: {
        id: "open-course",
        title: copy.sourceTitle,
        text: copy.sourceText,
        cta: { label: copy.preview, href: course ?? source },
        secondary: { label: copy.source, href: source },
      },
    },
    contained([{ component: "Callout", props: { tone: "warning", title: copy.boundaryTitle, text: copy.boundary } }]),
    { component: "Steps", props: { title: copy.stepsTitle, intro: copy.stepsIntro, items: copy.steps } },
    frames(caseId, copy),
    contained([
      { component: "Prose", props: { markdown: `## ${copy.buildHeading}\n\n${copy.buildText}` } },
      video(videos.build, poster, copy.buildVideo),
      { component: "Prose", props: { markdown: `## ${copy.publishHeading}\n\n${copy.publishText}` } },
      video(videos.publish, poster, copy.publishVideo),
      ...(videos.sync && copy.syncHeading && copy.syncText && copy.syncVideo
        ? [{ component: "Prose", props: { markdown: `## ${copy.syncHeading}\n\n${copy.syncText}` } } as UiNode, video(videos.sync, poster, copy.syncVideo)]
        : []),
    ]),
    {
      component: "CtaBand",
      props: {
        id: "next-case",
        title: copy.other,
        text: copy.subtitle,
        cta: { label: copy.other, href: publicPagePath(locale, CASE_PAGE[next]) },
        secondary: { label: copy.back, href: publicPagePath(locale, "generation") },
      },
    },
  ]);
  if (!doc) return null;
  return envelope(locale, request, url, CASE_PAGE[caseId], doc, copy.description);
}
