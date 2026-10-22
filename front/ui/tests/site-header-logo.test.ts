import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { afterEach, describe, expect, it } from "vitest";
import SiteHeader from "../src/components/SiteHeader.astro";

const render = async (locals: Record<string, unknown> | undefined, props: Record<string, unknown> = {}) => {
  const container = await AstroContainer.create();
  return container.renderToString(SiteHeader as never, { props: { brand: "The Coffee Atlas", mark: "wordmark", ...props }, locals });
};

describe("SiteHeader with a tenant logo", () => {
  it("shows the logo with its alt text instead of the mark and the name, and the link takes its name from it", async () => {
    const html = await render({ tenantLogo: { src: "https://s.example/theme/logo.png", alt: "Acme, home" } }, { mark: "terminal" });
    expect(html).toContain('<img class="u-brand__img" src="https://s.example/theme/logo.png" alt="Acme, home"');
    expect(html).not.toContain("u-brand__name");
    expect(html).not.toContain("u-brand__mark");
    expect(html).not.toMatch(/<a class="u-brand u-brand--logo"[^>]*aria-label/);
  });

  it("falls back to the brand as alt text", async () => {
    const html = await render({ tenantLogo: { src: "http://storage.localhost/a.svg", alt: "  " } });
    expect(html).toContain('alt="The Coffee Atlas"');
  });

  it("keeps the name as text and the home label without a logo", async () => {
    for (const locals of [undefined, {}, { tenantLogo: null }]) {
      const html = await render(locals);
      expect(html).toContain("u-brand__name");
      expect(html).not.toContain("<img");
      expect(html).toMatch(/aria-label="[^"]*The Coffee Atlas/);
    }
  });

  it("never renders an address that is not http(s)", async () => {
    for (const src of ["javascript:alert(1)", "data:image/png;base64,AAAA", "/relative.png", 7]) {
      const html = await render({ tenantLogo: { src, alt: "x" } });
      expect(html, String(src)).not.toContain("<img");
      expect(html).toContain("u-brand__name");
    }
  });

  it("leaves the platform's own lockup alone", async () => {
    const html = await render({ tenantLogo: { src: "https://s.example/a.png", alt: "x" } }, { mark: "logo", brand: "ulams" });
    expect(html).not.toContain("u-brand__img");
  });

  it("escapes the alt text", async () => {
    const html = await render({ tenantLogo: { src: "https://s.example/a.png", alt: '"><script>alert(1)</script>' } });
    // the quote that would end the attribute is escaped, so the text stays inside the alt value
    expect(html).toContain('alt="&quot;');
    expect(html).not.toMatch(/alt="[^"]*"[^>]*<script>alert/);
  });
});

describe("SiteHeader logo through the image service", () => {
  const previous = { service: process.env.ULAMS_IMAGE_SERVICE, sources: process.env.ULAMS_IMAGE_SOURCES };
  afterEach(() => {
    for (const [key, value] of [["ULAMS_IMAGE_SERVICE", previous.service], ["ULAMS_IMAGE_SOURCES", previous.sources]] as const) {
      if (value === undefined) delete process.env[key];
      else process.env[key] = value;
    }
  });

  it("uses the logo preset in cloudflare mode and the original otherwise", async () => {
    process.env.ULAMS_IMAGE_SOURCES = "https://s.example";
    const locals = { tenantLogo: { src: "https://s.example/theme/logo.png", alt: "Acme" } };
    delete process.env.ULAMS_IMAGE_SERVICE;
    expect(await render(locals)).toContain('src="https://s.example/theme/logo.png"');
    process.env.ULAMS_IMAGE_SERVICE = "cloudflare";
    const html = await render(locals);
    expect(html).toContain("/cdn-cgi/image/width=320,quality=85,format=auto,fit=scale-down,onerror=redirect/https://s.example/theme/logo.png");
    expect(html).toContain('height="40"');
    expect(html).toContain('alt="Acme"');
  });
});
