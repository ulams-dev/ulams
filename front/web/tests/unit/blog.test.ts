import { existsSync, readdirSync, statSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";
import { validateDocument } from "@ulams/ui/render-core";
import { GET as sitemap } from "../../src/pages/sitemap.xml.ts";
import { BLOG_LOCALES, blogBody, blogIndexPage, blogPostPage, blogPosts, blogSources } from "../../src/lib/blog.ts";
import { chromeFor, comparisonDocFor, landingDocFor } from "../../src/lib/docs.ts";

const publicDir = fileURLToPath(new URL("../../public", import.meta.url));
const request = new Request("https://ulams.app/blog/", { headers: { host: "ulams.app" } });
const url = new URL("https://ulams.app/blog/");

describe("blog sources", () => {
  it("has posts, with unique kebab-case slugs and valid dates that are not in the future", () => {
    expect(blogSources.length).toBeGreaterThanOrEqual(12);
    const slugs = blogSources.map((post) => post.slug);
    expect(new Set(slugs).size).toBe(slugs.length);
    for (const post of blogSources) {
      expect(post.slug, post.slug).toMatch(/^[a-z0-9]+(?:-[a-z0-9]+)*$/);
      expect(post.date, post.slug).toMatch(/^\d{4}-\d{2}-\d{2}$/);
      const date = new Date(`${post.date}T00:00:00Z`);
      expect(Number.isNaN(date.getTime()), post.slug).toBe(false);
      expect(date.toISOString().slice(0, 10), post.slug).toBe(post.date);
      expect(date.getTime(), post.slug).toBeLessThanOrEqual(Date.now());
    }
  });

  it("has English and Polish text for every post, and the same media in both", () => {
    for (const post of blogSources) {
      for (const locale of BLOG_LOCALES) {
        const text = post[locale];
        expect(text.title?.trim(), `${post.slug} ${locale} title`).toBeTruthy();
        expect(text.description?.trim().length, `${post.slug} ${locale} description`).toBeGreaterThan(40);
        expect(text.description.length, `${post.slug} ${locale} description`).toBeLessThanOrEqual(300);
        expect(text.tags.length, `${post.slug} ${locale} tags`).toBeGreaterThan(0);
        expect(blogBody(post.slug, locale)?.length ?? 0, `${post.slug} ${locale} body`).toBeGreaterThan(200);
        for (const image of text.images ?? []) {
          expect(image.alt.trim(), `${post.slug} ${locale} ${image.name} alt`).toBeTruthy();
          expect(image.width).toBeGreaterThan(0);
          expect(image.height).toBeGreaterThan(0);
        }
        if (text.video) expect(text.video.caption.trim(), `${post.slug} ${locale} video caption`).toBeTruthy();
      }
      expect((post.pl.images ?? []).map((i) => i.name), post.slug).toEqual((post.en.images ?? []).map((i) => i.name));
      expect(Boolean(post.pl.video), post.slug).toBe(Boolean(post.en.video));
      expect(post.pl.title, `${post.slug} must be translated`).not.toBe(post.en.title);
    }
  });

  it("keeps the English post in English and the Polish one in Polish (no tab leftovers)", () => {
    for (const post of blogSources) {
      for (const locale of BLOG_LOCALES) {
        const body = blogBody(post.slug, locale)!;
        expect(body, `${post.slug} ${locale}`).not.toMatch(/<\/?(TabItem|Tabs|BlogVideo|BlogImage)/);
      }
    }
  });

  it("points every media reference at a file that exists, under 2 MB, and leaves no unused file", () => {
    const referenced = new Set<string>();
    for (const locale of BLOG_LOCALES) {
      for (const post of blogPosts(locale)) {
        for (const media of post.media) {
          referenced.add(media);
          const file = `${publicDir}${media}`;
          expect(existsSync(file), media).toBe(true);
          expect(statSync(file).size, media).toBeLessThan(2 * 1024 * 1024);
        }
      }
    }
    const onDisk = readdirSync(`${publicDir}/blog`).flatMap((slug) => readdirSync(`${publicDir}/blog/${slug}`).map((file) => `/blog/${slug}/${file}`));
    expect(onDisk.filter((file) => !referenced.has(file))).toEqual([]);
  });

  it("links only to posts that exist, within the language of the post", () => {
    const slugs = new Set(blogSources.map((post) => post.slug));
    for (const locale of BLOG_LOCALES) {
      for (const post of blogPosts(locale)) {
        for (const text of [post.markdown, post.footer ?? ""]) {
          for (const [, path] of text.matchAll(/\]\((\/(?:pl\/)?blog\/[^)]*)\)/g)) {
            const match = /^(?:\/pl)?\/blog\/(?:([a-z0-9-]+)\/)?$/.exec(path!);
            expect(match, `${post.slug} ${locale}: ${path}`).not.toBeNull();
            if (match![1]) expect(slugs.has(match![1]), `${post.slug} ${locale}: ${path}`).toBe(true);
            expect(path!.startsWith("/pl/"), `${post.slug} ${locale}: ${path}`).toBe(locale === "pl");
          }
        }
      }
    }
  });
});

describe("blog pages", () => {
  it("lists the posts newest first in both languages", () => {
    for (const locale of BLOG_LOCALES) {
      const page = blogIndexPage(locale, request, url);
      const index = page.doc.children!.find((n) => n.component === "Main")!.children![0]!;
      const dates = (index.props!.posts as Array<{ date: string }>).map((p) => p.date);
      expect(dates).toEqual([...dates].sort().reverse());
      expect(dates).toHaveLength(blogSources.length);
      expect(page.canonical).toBe(locale === "en" ? "https://ulams.app/blog/" : "https://ulams.app/pl/blog/");
      expect(page.meta.lang).toBe(locale);
      expect(page.jsonLd.some((n) => n["@type"] === "Blog")).toBe(true);
    }
  });

  it("builds valid documents with the platform header and footer, and only EN and PL in the switcher", () => {
    for (const locale of BLOG_LOCALES) {
      const page = blogPostPage(locale, "living-course", request, url)!;
      expect(validateDocument(page.doc, {})).toEqual([]);
      const header = page.doc.children![0]!;
      expect(header.component).toBe("SiteHeader");
      expect((header.props!.languages as Array<{ href: string; current: boolean }>).map((l) => [l.href, l.current])).toEqual([
        ["/blog/living-course/", locale === "en"],
        ["/pl/blog/living-course/", locale === "pl"],
      ]);
      expect(page.doc.children!.at(-1)!.component).toBe("SiteFooter");
    }
  });

  it("gives a post a canonical URL, hreflang alternates and BlogPosting data", () => {
    const page = blogPostPage("pl", "living-course", request, url)!;
    expect(page.canonical).toBe("https://ulams.app/pl/blog/living-course/");
    expect(page.alternates).toEqual([
      { hreflang: "en", href: "https://ulams.app/blog/living-course/" },
      { hreflang: "pl", href: "https://ulams.app/pl/blog/living-course/" },
      { hreflang: "x-default", href: "https://ulams.app/blog/living-course/" },
    ]);
    const posting = page.jsonLd.find((n) => n["@type"] === "BlogPosting")!;
    expect(posting).toMatchObject({
      datePublished: "2026-10-16",
      inLanguage: "pl",
      author: { "@type": "Person", name: "Mateusz Wojczal" },
      publisher: { "@id": "https://ulams.app/#organization" },
    });
    expect(page.jsonLd.some((n) => n["@type"] === "Organization" && n.name === "ulams")).toBe(true);
    expect(page.meta.title).toContain("Living Course");
  });

  it("knows no post it does not have", () => {
    expect(blogPostPage("en", "no-such-post", request, url)).toBeNull();
  });

  it("keeps the English clip and the Polish clip of the narrated post apart", () => {
    const en = blogPostPage("en", "generative-ui-in-ulams", request, url)!;
    const pl = blogPostPage("pl", "generative-ui-in-ulams", request, url)!;
    const clip = (page: typeof en) => (page.doc.children!.find((n) => n.component === "Main")!.children![0]!.props!.video as { src: string }).src;
    expect(clip(en)).toBe("/blog/generative-ui-in-ulams/clip-en.mp4");
    expect(clip(pl)).toBe("/blog/generative-ui-in-ulams/clip.mp4");
  });
});

describe("blog navigation and sitemap", () => {
  it("links the blog from every platform and comparison header, in the language of the page", () => {
    const expected = { en: "/blog/", pl: "/pl/blog/", zh: "/blog/" } as const;
    for (const locale of ["en", "pl", "zh"] as const) {
      for (const doc of [landingDocFor("platform", locale)!, comparisonDocFor(locale)!]) {
        const header = (function find(node: { component: string; children?: unknown[] }): { props?: { links: Array<{ href: string }> } } | null {
          if (node.component === "SiteHeader") return node as never;
          for (const child of node.children ?? []) {
            const found = find(child as never);
            if (found) return found;
          }
          return null;
        })(doc);
        expect(header!.props!.links.map((l) => l.href), locale).toContain(expected[locale]);
      }
      expect(chromeFor("platform", locale).header, locale).not.toBeNull();
    }
  });

  it("lists the blog pages with their alternates in the sitemap, and nothing on a tenant host", async () => {
    const response = sitemap({ request, url: new URL("https://ulams.app/sitemap.xml"), locals: { platform: true } } as never) as Response;
    const xml = await response.text();
    for (const post of blogSources) {
      expect(xml).toContain(`<loc>https://ulams.app/blog/${post.slug}/</loc>`);
      expect(xml).toContain(`<loc>https://ulams.app/pl/blog/${post.slug}/</loc>`);
    }
    expect(xml).toContain("<loc>https://ulams.app/blog/</loc>");
    expect(xml).toContain("<loc>https://ulams.app/pl/blog/</loc>");
    expect(xml).toContain("<loc>https://ulams.app/comparison/</loc>");
    expect(xml).not.toContain("/zh/blog");
    const tenant = sitemap({ request, url: new URL("https://coffee.ulams.app/sitemap.xml"), locals: { platform: false } } as never) as Response;
    expect(tenant.status).toBe(404);
  });
});
