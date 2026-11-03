/**
 * The blog of the platform landing: posts in `src/blog/<slug>/` (`meta.json`, `en.md`, `pl.md`),
 * read at build time, rendered with the `BlogIndex` and `BlogPost` catalogue components. English at
 * `/blog/`, Polish at `/pl/blog/`; there is no Chinese blog (the Chinese header links to the English one).
 * Media sits in `public/blog/<slug>/`.
 */
import type { UiNode } from "@ulams/ui/render-core";
import { chromeFor, pageMeta, withLanguages } from "./docs.ts";
import { LOCALE_META, publicOrigin, type Locale } from "../i18n/locales.ts";

export const BLOG_LOCALES = ["en", "pl"] as const;
export type BlogLocale = (typeof BLOG_LOCALES)[number];
export const AUTHOR = "Mateusz Wojczal";

interface LocaleMeta {
  title: string;
  description: string;
  tags: string[];
  video?: { file: string; poster: string; width: number; height: number; caption: string };
  imagesHeading?: string;
  imagesIntro?: string;
  images?: Array<{ name: string; width: number; height: number; alt: string; caption?: string }>;
  footer?: string;
}
export interface BlogMeta {
  slug: string;
  /** Publication date, YYYY-MM-DD. */
  date: string;
  /** The post belongs to the ten-day series (a closing sentence links back to the blog). */
  series: boolean;
  en: LocaleMeta;
  pl: LocaleMeta;
}
export interface BlogPost extends LocaleMeta {
  slug: string;
  date: string;
  locale: BlogLocale;
  /** Body in Markdown, with the closing sentence of the series when the post has one. */
  markdown: string;
  href: string;
  /** Every file under `public/` the post uses. */
  media: string[];
}

const metas = import.meta.glob<{ default: BlogMeta }>("../blog/*/meta.json", { eager: true });
const bodies = import.meta.glob<string>("../blog/*/*.md", { eager: true, query: "?raw", import: "default" });

const SERIES: Record<BlogLocale, string> = {
  en: "This post is part of a ten-day series that follows one example, from runbook to course to update. All posts are on the [blog](/blog/).",
  pl: "Ten wpis należy do dziesięciodniowej serii, która śledzi jeden przykład: od runbooka, przez kurs, do aktualizacji. Wszystkie wpisy są na [blogu](/pl/blog/).",
};

export const blogBase = (locale: BlogLocale): string => (locale === "en" ? "/blog/" : "/pl/blog/");
export const blogPath = (locale: BlogLocale, slug?: string): string => (slug ? `${blogBase(locale)}${slug}/` : blogBase(locale));
export const isBlogLocale = (locale: Locale): locale is BlogLocale => (BLOG_LOCALES as readonly string[]).includes(locale);

/** The raw post sources: used by the pages (`getStaticPaths`-free routes), the sitemap and the tests. */
export const blogSources: BlogMeta[] = Object.values(metas)
  .map((mod) => mod.default)
  .sort((a, b) => b.date.localeCompare(a.date) || a.slug.localeCompare(b.slug));

export const blogBody = (slug: string, locale: BlogLocale): string | undefined => bodies[`../blog/${slug}/${locale}.md`]?.trim();

/** The posts in a language, newest first. */
export function blogPosts(locale: BlogLocale): BlogPost[] {
  return blogSources.map((meta) => {
    const text = meta[locale];
    const body = blogBody(meta.slug, locale) ?? "";
    const base = `/blog/${meta.slug}/`;
    return {
      ...text,
      slug: meta.slug,
      date: meta.date,
      locale,
      markdown: meta.series ? `${body}\n\n${SERIES[locale]}` : body,
      href: blogPath(locale, meta.slug),
      media: [...(text.video ? [text.video.file, text.video.poster] : []), ...(text.images ?? []).map((image) => `${image.name}.webp`)].map((file) => `${base}${file}`),
    };
  });
}

export const blogPost = (locale: BlogLocale, slug: string): BlogPost | undefined => blogPosts(locale).find((post) => post.slug === slug);

const COPY: Record<BlogLocale, { eyebrow: string; title: string; intro: string; metaTitle: string; metaDescription: string; home: string }> = {
  en: {
    eyebrow: "Blog",
    title: "Notes from building ulams",
    intro: "Courses from documentation, a source behind every sentence, generative UI and how to run it yourself. Most posts started as a LinkedIn post and have the same text here, with the screenshots and screencasts that did not fit in the feed.",
    metaTitle: "ulams blog: notes from building an AI-native LMS",
    metaDescription: "Notes from building ulams in the open: courses from documentation, cited generation, generative UI and how to run it yourself.",
    home: "Overview",
  },
  pl: {
    eyebrow: "Blog",
    title: "Notatki z budowy ulams",
    intro: "Kursy z dokumentacji, źródło przy każdym zdaniu, generative UI i uruchomienie systemu u siebie. Większość wpisów zaczęła się jako post na LinkedIn i ma tu ten sam tekst, razem ze zrzutami ekranu i nagraniami, które nie zmieściły się w feedzie.",
    metaTitle: "Blog ulams: notatki z budowy LMS-a z AI",
    metaDescription: "Notatki z budowy ulams na otwartej licencji: kursy z dokumentacji, cytowane generowanie, generative UI i uruchomienie u siebie.",
    home: "Przegląd",
  },
};

const languageSwitcher = (locale: BlogLocale, slug?: string) =>
  BLOG_LOCALES.map((code) => ({ code: LOCALE_META[code].lang, label: LOCALE_META[code].label, href: blogPath(code, slug), current: code === locale }));

const alternates = (origin: string, slug?: string) => [
  ...BLOG_LOCALES.map((code) => ({ hreflang: LOCALE_META[code].lang, href: new URL(blogPath(code, slug), origin).href })),
  { hreflang: "x-default", href: new URL(blogPath("en", slug), origin).href },
];

function frame(locale: BlogLocale, title: string, description: string, block: UiNode, slug?: string): UiNode {
  const { header, footer } = chromeFor("platform", locale);
  const children = [...(header ? [header] : []), { component: "Main", children: [block] }, ...(footer ? [footer] : [])];
  const doc: UiNode = { component: "Page", props: { theme: "platform", title, description, lang: LOCALE_META[locale].lang }, children };
  return withLanguages(doc, languageSwitcher(locale, slug), LOCALE_META[locale].path);
}

function jsonLd(locale: BlogLocale, origin: string, extra: Array<Record<string, unknown>>): Array<Record<string, unknown>> {
  const organization = { "@type": "Organization", "@id": `${origin}/#organization`, name: "ulams", url: origin, logo: `${origin}/favicon.svg`, sameAs: ["https://github.com/ulams-dev"] };
  const website = { "@type": "WebSite", "@id": `${origin}/#website`, url: origin, name: "ulams", inLanguage: LOCALE_META[locale].lang, publisher: { "@id": `${origin}/#organization` } };
  return [{ "@context": "https://schema.org", ...organization }, { "@context": "https://schema.org", ...website }, ...extra.map((node) => ({ "@context": "https://schema.org", ...node }))];
}

/** `/blog/` or `/pl/blog/`: the document, the meta and the structured data of the post list. */
export function blogIndexPage(locale: BlogLocale, request: Request, url: URL) {
  const copy = COPY[locale];
  const posts = blogPosts(locale);
  const block: UiNode = {
    component: "BlogIndex",
    props: {
      eyebrow: copy.eyebrow,
      title: copy.title,
      intro: copy.intro,
      posts: posts.map((post) => ({
        title: post.title,
        description: post.description,
        href: post.href,
        date: post.date,
        tags: post.tags,
        ...(post.video ? { image: { src: `/blog/${post.slug}/${post.video.poster}`, width: post.video.width, height: post.video.height } } : post.images?.[0] ? { image: { src: `/blog/${post.slug}/${post.images[0].name}.webp`, width: post.images[0].width, height: post.images[0].height } } : {}),
      })),
    },
  };
  const doc = frame(locale, copy.metaTitle, copy.metaDescription, block);
  const origin = publicOrigin(request.headers, url);
  const canonical = new URL(blogPath(locale), origin).href;
  return {
    doc,
    data: {},
    meta: { ...pageMeta(doc, {}), lang: LOCALE_META[locale].lang },
    canonical,
    alternates: alternates(origin),
    ogLocale: { en: "en_US", pl: "pl_PL" }[locale],
    jsonLd: jsonLd(locale, origin, [
      {
        "@type": "Blog",
        "@id": `${canonical}#blog`,
        name: copy.title,
        description: copy.metaDescription,
        url: canonical,
        inLanguage: LOCALE_META[locale].lang,
        publisher: { "@id": `${origin}/#organization` },
        isPartOf: { "@id": `${origin}/#website` },
        blogPost: posts.map((post) => ({ "@type": "BlogPosting", headline: post.title, url: new URL(post.href, origin).href, datePublished: post.date })),
      },
    ]),
  };
}

/** `/blog/<slug>/` or `/pl/blog/<slug>/`; null for an unknown slug. */
export function blogPostPage(locale: BlogLocale, slug: string, request: Request, url: URL) {
  const post = blogPost(locale, slug);
  if (!post) return null;
  const copy = COPY[locale];
  const block: UiNode = {
    component: "BlogPost",
    props: {
      title: post.title,
      lead: post.description,
      date: post.date,
      author: AUTHOR,
      tags: post.tags,
      backHref: blogPath(locale),
      markdown: post.markdown,
      ...(post.video ? { video: { src: `/blog/${slug}/${post.video.file}`, poster: `/blog/${slug}/${post.video.poster}`, width: post.video.width, height: post.video.height, caption: post.video.caption } } : {}),
      ...(post.images?.length
        ? {
            imagesHeading: post.imagesHeading,
            imagesIntro: post.imagesIntro,
            images: post.images.map((image) => ({ src: `/blog/${slug}/${image.name}.webp`, alt: image.alt, width: image.width, height: image.height, caption: image.caption })),
          }
        : {}),
      ...(post.footer ? { footer: post.footer } : {}),
    },
  };
  const doc = frame(locale, `${post.title} | ulams`, post.description, block, slug);
  const origin = publicOrigin(request.headers, url);
  const canonical = new URL(post.href, origin).href;
  const image = post.video ? `/blog/${slug}/${post.video.poster}` : post.images?.[0] ? `/blog/${slug}/${post.images[0].name}.webp` : null;
  return {
    doc,
    data: {},
    meta: { ...pageMeta(doc, {}), lang: LOCALE_META[locale].lang },
    canonical,
    alternates: alternates(origin, slug),
    ogLocale: { en: "en_US", pl: "pl_PL" }[locale],
    jsonLd: jsonLd(locale, origin, [
      {
        "@type": "BlogPosting",
        "@id": `${canonical}#post`,
        headline: post.title,
        description: post.description,
        url: canonical,
        mainEntityOfPage: canonical,
        datePublished: post.date,
        inLanguage: LOCALE_META[locale].lang,
        keywords: post.tags.join(", "),
        ...(image ? { image: new URL(image, origin).href } : {}),
        author: { "@type": "Person", name: AUTHOR },
        publisher: { "@id": `${origin}/#organization` },
        isPartOf: { "@id": `${new URL(blogPath(locale), origin).href}#blog` },
      },
      {
        "@type": "BreadcrumbList",
        "@id": `${canonical}#breadcrumb`,
        itemListElement: [
          { "@type": "ListItem", position: 1, name: copy.home, item: new URL(LOCALE_META[locale].path, origin).href },
          { "@type": "ListItem", position: 2, name: copy.eyebrow, item: new URL(blogPath(locale), origin).href },
          { "@type": "ListItem", position: 3, name: post.title, item: canonical },
        ],
      },
    ]),
  };
}
