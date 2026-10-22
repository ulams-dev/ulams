import { afterEach, describe, expect, it } from "vitest";
import { imageConfig, imageSrc, rawImageConfig } from "../src/lib/images.ts";
import { renderMarkdown } from "../src/lib/markdown.ts";

const STORAGE = "https://staging-storage.ulams.app";
const keys = ["ULAMS_IMAGE_SERVICE", "ULAMS_IMAGE_SOURCES", "ULAMS_STORAGE_ORIGINS"] as const;
const previous = Object.fromEntries(keys.map((k) => [k, process.env[k]]));
afterEach(() => {
  for (const k of keys) {
    if (previous[k] === undefined) delete process.env[k];
    else process.env[k] = previous[k];
  }
});

describe("imageConfig", () => {
  it("defaults to astro with the default storage origin", () => {
    for (const k of keys) delete process.env[k];
    expect(imageConfig()).toEqual({ service: "astro", allowedSources: ["http://storage.localhost"] });
  });

  it("reads the mode at call time, and the sources from ULAMS_IMAGE_SOURCES before ULAMS_STORAGE_ORIGINS", () => {
    process.env.ULAMS_STORAGE_ORIGINS = "http://a.localhost";
    process.env.ULAMS_IMAGE_SERVICE = "cloudflare";
    expect(imageConfig()).toEqual({ service: "cloudflare", allowedSources: ["http://a.localhost"] });
    process.env.ULAMS_IMAGE_SOURCES = ` ${STORAGE} , https://*.ulams.app `;
    process.env.ULAMS_IMAGE_SERVICE = "noop";
    expect(imageConfig()).toEqual({ service: "noop", allowedSources: [STORAGE, "https://*.ulams.app"] });
  });

  it("treats php and unknown values as unsupported here", () => {
    process.env.ULAMS_IMAGE_SERVICE = "php";
    expect(imageConfig().service).toBe("noop");
    process.env.ULAMS_IMAGE_SERVICE = "nonsense";
    expect(imageConfig().service).toBe("astro");
  });

  it("serves originals to plain <img> sites in astro mode, as before", () => {
    delete process.env.ULAMS_IMAGE_SERVICE;
    expect(rawImageConfig().service).toBe("noop");
    process.env.ULAMS_IMAGE_SERVICE = "cloudflare";
    expect(rawImageConfig().service).toBe("cloudflare");
  });
});

describe("imageSrc for plain <img> sites", () => {
  it("is the original in astro and noop modes", () => {
    delete process.env.ULAMS_IMAGE_SERVICE;
    expect(imageSrc("/landing/a.png", "content", 1280)).toBe("/landing/a.png");
    process.env.ULAMS_IMAGE_SERVICE = "noop";
    expect(imageSrc("/landing/a.png", "logo", 320)).toBe("/landing/a.png");
  });

  it("goes through the logo and content presets in cloudflare mode", () => {
    process.env.ULAMS_IMAGE_SERVICE = "cloudflare";
    process.env.ULAMS_IMAGE_SOURCES = STORAGE;
    expect(imageSrc(`${STORAGE}/theme/logo.png`, "logo", 320)).toBe(
      `/cdn-cgi/image/width=320,quality=85,format=auto,fit=scale-down,onerror=redirect/${STORAGE}/theme/logo.png`,
    );
    expect(imageSrc("/landing/poster.png", "content", 1280)).toContain("width=1280,quality=78,");
    expect(imageSrc(`${STORAGE}/theme/logo.svg`, "logo", 320)).toBe(`${STORAGE}/theme/logo.svg`);
  });
});

describe("rich text images", () => {
  it("keep the original in astro mode", () => {
    delete process.env.ULAMS_IMAGE_SERVICE;
    expect(renderMarkdown(`![Cup](${STORAGE}/c/cup.png)`)).toContain(`src="${STORAGE}/c/cup.png"`);
  });

  it("use the richText preset in cloudflare mode: one src, no srcset", () => {
    process.env.ULAMS_IMAGE_SERVICE = "cloudflare";
    process.env.ULAMS_IMAGE_SOURCES = STORAGE;
    const html = renderMarkdown(`![Cup & saucer](${STORAGE}/c/cup.png) ![Foreign](https://x.example/p.png)`);
    expect(html).toContain(`src="/cdn-cgi/image/width=1280,quality=78,format=auto,fit=scale-down,onerror=redirect/${STORAGE}/c/cup.png"`);
    expect(html).toContain('alt="Cup &amp; saucer"');
    expect(html).toContain('src="https://x.example/p.png"');
    expect(html).not.toContain("srcset");
    expect(html).toContain('loading="lazy"');
  });

  it("keep refusing unsafe hrefs", () => {
    process.env.ULAMS_IMAGE_SERVICE = "cloudflare";
    expect(renderMarkdown("![x](javascript:alert(1))")).not.toContain("javascript:");
  });
});
