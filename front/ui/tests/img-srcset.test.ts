import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { afterEach, describe, expect, it } from "vitest";
import Img from "../src/components/Img.astro";

const render = async () => {
  const container = await AstroContainer.create();
  return container.renderToString(Img as never, { props: { src: "/landing/shot.png", alt: "", width: 1600, height: 900, sizes: "100vw" } });
};

describe("Img", () => {
  const previous = process.env.ULAMS_IMAGE_SERVICE;
  afterEach(() => {
    if (previous === undefined) delete process.env.ULAMS_IMAGE_SERVICE;
    else process.env.ULAMS_IMAGE_SERVICE = previous;
  });

  it("sends a plain src, not a srcset repeating one URL, when the image service is noop", async () => {
    process.env.ULAMS_IMAGE_SERVICE = "noop";
    const html = await render();
    expect(html).toContain("<img");
    expect(html).not.toContain("srcset=");
    expect(html).toContain('width="1600"');
    expect(html).toContain('height="900"');
  });

  it("builds Cloudflare Image Transformations URLs with distinct widths for the cloudflare service", async () => {
    process.env.ULAMS_IMAGE_SERVICE = "cloudflare";
    const html = await render();
    expect(html).toContain("/cdn-cgi/image/width=360,format=auto,quality=78/landing/shot.png 360w");
    expect(html).toContain("/cdn-cgi/image/width=1600,format=auto,quality=78/landing/shot.png 1600w");
    expect(new Set(html.match(/\/cdn-cgi\/image\/width=\d+/g)).size).toBeGreaterThan(1);
  });

  it("keeps the responsive srcset with a real image service", async () => {
    delete process.env.ULAMS_IMAGE_SERVICE;
    const html = await render();
    expect(html).toContain("srcset=");
  });
});
