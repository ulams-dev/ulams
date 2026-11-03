import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { afterEach, describe, expect, it } from "vitest";
import Img from "../src/components/Img.astro";

const STORAGE = "https://staging-storage.ulams.app";
const OPTIONS = (w: number, q = 78) => `width=${w},quality=${q},format=auto,fit=scale-down,onerror=redirect`;

const render = async (props: Record<string, unknown> = {}) => {
  const container = await AstroContainer.create();
  return container.renderToString(Img as never, {
    props: { src: "/landing/shot.png", alt: "A shot", width: 1600, height: 900, sizes: "100vw", ...props },
  });
};

describe("Img", () => {
  const previous = { service: process.env.ULAMS_IMAGE_SERVICE, sources: process.env.ULAMS_IMAGE_SOURCES };
  const restore = (key: string, value: string | undefined) => {
    if (value === undefined) delete process.env[key];
    else process.env[key] = value;
  };
  afterEach(() => {
    restore("ULAMS_IMAGE_SERVICE", previous.service);
    restore("ULAMS_IMAGE_SOURCES", previous.sources);
  });

  it("sends a plain src, not a srcset repeating one URL, when the image service is noop", async () => {
    process.env.ULAMS_IMAGE_SERVICE = "noop";
    const html = await render();
    expect(html).toContain("<img");
    expect(html).toContain('src="/landing/shot.png"');
    expect(html).not.toContain("srcset=");
    expect(html).not.toContain("sizes=");
    expect(html).toContain('width="1600"');
    expect(html).toContain('height="900"');
    expect(html).toContain('alt="A shot"');
    expect(html).toContain('loading="lazy"');
  });

  it("builds /cdn-cgi/image URLs from the shared builder with distinct preset widths for the cloudflare service", async () => {
    process.env.ULAMS_IMAGE_SERVICE = "cloudflare";
    const html = await render({ sizes: "(min-width: 960px) 46vw, 100vw" });
    expect(html).toContain(`/cdn-cgi/image/${OPTIONS(360)}/landing/shot.png 360w`);
    expect(html).toContain(`/cdn-cgi/image/${OPTIONS(1600)}/landing/shot.png 1600w`);
    expect(html).toContain(`src="/cdn-cgi/image/${OPTIONS(1600)}/landing/shot.png"`);
    expect(html).toContain('sizes="(min-width: 960px) 46vw, 100vw"');
    expect(html).toContain('width="1600"');
    expect(html).toContain('height="900"');
    expect(html).toContain('alt="A shot"');
    expect(html).toContain('loading="lazy"');
    expect(html).toContain('decoding="async"');
    expect(new Set(html.match(/\/cdn-cgi\/image\/width=\d+/g)).size).toBeGreaterThan(1);
  });

  it("never asks for a width above the intrinsic one", async () => {
    process.env.ULAMS_IMAGE_SERVICE = "cloudflare";
    const html = await render({ width: 700, height: 400 });
    expect(html).not.toContain("width=960,");
    expect(html).toContain("640w");
  });

  it("keeps eager loading and high fetch priority for the LCP image", async () => {
    process.env.ULAMS_IMAGE_SERVICE = "cloudflare";
    const html = await render({ priority: true });
    expect(html).toContain('loading="eager"');
    expect(html).toContain('fetchpriority="high"');
    expect(html).toContain('decoding="sync"');
  });

  it("uses the preset of the prop", async () => {
    process.env.ULAMS_IMAGE_SERVICE = "cloudflare";
    const html = await render({ preset: "avatar", width: 600, height: 600 });
    expect(html).toContain(`/cdn-cgi/image/${OPTIONS(96, 80)}/landing/shot.png 96w`);
    expect(html).toContain(`${OPTIONS(600, 80)}/landing/shot.png 600w`);
  });

  it("transforms tenant storage by absolute source, and passes other origins through untouched", async () => {
    process.env.ULAMS_IMAGE_SERVICE = "cloudflare";
    process.env.ULAMS_IMAGE_SOURCES = STORAGE;
    const own = await render({ src: `${STORAGE}/coffee/hero.png` });
    expect(own).toContain(`/cdn-cgi/image/${OPTIONS(640)}/${STORAGE}/coffee/hero.png 640w`);
    const foreign = await render({ src: "https://cdn.example.com/a.png" });
    expect(foreign).toContain('src="https://cdn.example.com/a.png"');
    expect(foreign).not.toContain("cdn-cgi");
    expect(foreign).not.toContain("srcset=");
  });

  it("falls back to the original for a mode this client does not support", async () => {
    process.env.ULAMS_IMAGE_SERVICE = "php";
    const html = await render();
    expect(html).toContain('src="/landing/shot.png"');
    expect(html).not.toContain("cdn-cgi");
  });

  it("keeps the responsive srcset with a real image service", async () => {
    delete process.env.ULAMS_IMAGE_SERVICE;
    const html = await render();
    expect(html).toContain("srcset=");
    expect(html).not.toContain("cdn-cgi");
  });
});
