import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { JSDOM } from "jsdom";
import { describe, expect, it } from "vitest";
import Render from "../src/Render.astro";
import type { UiNode } from "../src/render-core.ts";

const dom = new JSDOM("<!doctype html><html lang='en'><body></body></html>", { pretendToBeVisual: true });
for (const key of ["window", "document", "Node", "Element", "HTMLElement", "NodeList", "getComputedStyle"] as const) {
  const value = key === "getComputedStyle" ? dom.window.getComputedStyle.bind(dom.window) : (dom.window as unknown as Record<string, unknown>)[key];
  Object.defineProperty(globalThis, key, { value, configurable: true, writable: true });
}
const { default: axe } = await import("axe-core");

const index: UiNode = {
  component: "BlogIndex",
  props: {
    title: "Notes",
    intro: "From the build.",
    posts: [
      { title: "Older", description: "First.", href: "/blog/older/", date: "2026-10-08", tags: ["History"] },
      { title: "Newer", href: "/blog/newer/", date: "2026-10-16", tags: ["Living Course", "CLI"], image: { src: "/blog/newer/poster.jpg", width: 720, height: 900 } },
    ],
  },
};
const post: UiNode = {
  component: "BlogPost",
  props: {
    title: "Newer",
    lead: "A lead.",
    date: "2026-10-16",
    author: "Mateusz Wojczal",
    tags: ["Living Course"],
    backHref: "/blog/",
    markdown: "First paragraph.\n\n- one\n- two",
    video: { src: "/blog/newer/clip.mp4", poster: "/blog/newer/poster.jpg", width: 720, height: 900, caption: "In the clip: the diff." },
    imagesHeading: "In pictures",
    images: [
      { src: "/blog/newer/a.webp", alt: "A screen.", width: 1080, height: 1350, caption: "A screen." },
      { src: "/blog/newer/b.webp", alt: "A wide screen.", width: 1200, height: 800 },
    ],
    footer: "## Related\n\n- [Other](/blog/other/)",
  },
};

async function render(doc: UiNode, pathname = "/blog/"): Promise<{ html: string; root: HTMLElement }> {
  const container = await AstroContainer.create();
  const html = await container.renderToString(Render as never, { props: { doc }, request: new Request(`http://localhost${pathname}`) });
  document.body.innerHTML = `<main>${html}</main>`;
  return { html, root: document.body.querySelector("main")! };
}
const axeOptions = { rules: { "color-contrast": { enabled: false }, region: { enabled: false } } };

describe("BlogIndex", () => {
  it("lists the posts newest first, each card with one link and a dated, machine-readable time", async () => {
    const { root } = await render(index);
    expect(root.querySelector("h1")?.textContent).toBe("Notes");
    const cards = [...root.querySelectorAll("article")];
    expect(cards.map((c) => c.querySelector("h2 a")?.textContent)).toEqual(["Newer", "Older"]);
    expect(cards.every((c) => c.querySelectorAll("a").length === 1)).toBe(true);
    expect(cards[0]!.querySelector("time")?.getAttribute("datetime")).toBe("2026-10-16");
    expect(cards[0]!.querySelector("time")?.textContent).toBe("16 October 2026");
    expect(cards[0]!.querySelector("img")?.getAttribute("alt")).toBe("");
    expect(cards[1]!.querySelector("img")).toBeNull();
  });

  it("formats the date in the language of the page", async () => {
    const { root } = await render(index, "/pl/blog/");
    expect(root.querySelector("time")?.textContent).toBe("16 października 2026");
  });

  it("has no WCAG violations (axe)", async () => {
    const { root } = await render(index);
    const result = await axe.run(root, axeOptions);
    expect(result.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.html).join(" | ")}`)).toEqual([]);
  });
});

describe("BlogPost", () => {
  it("renders the byline, the clip with native controls and a caption, the body and the gallery", async () => {
    const { root } = await render(post, "/blog/newer/");
    expect(root.querySelector("h1")?.textContent).toBe("Newer");
    expect(root.querySelector("a.u-blogp__back")?.getAttribute("href")).toBe("/blog/");
    expect(root.querySelector("time")?.getAttribute("datetime")).toBe("2026-10-16");
    expect(root.textContent).toContain("By Mateusz Wojczal");
    const video = root.querySelector("video")!;
    expect(video.hasAttribute("controls")).toBe(true);
    expect(video.getAttribute("preload")).toBe("metadata");
    expect(video.getAttribute("poster")).toBe("/blog/newer/poster.jpg");
    expect(video.querySelector("source")?.getAttribute("src")).toBe("/blog/newer/clip.mp4");
    expect(root.querySelector(`#${video.getAttribute("aria-describedby")}`)?.textContent).toBe("In the clip: the diff.");
    const proses = root.querySelectorAll(".u-prose");
    expect(proses).toHaveLength(2);
    expect(proses[0]!.querySelectorAll("li")).toHaveLength(2);
    expect(proses[1]!.querySelector("a")?.getAttribute("href")).toBe("/blog/other/");
    const figures = [...root.querySelectorAll(".u-blogp__images figure")];
    expect(figures.map((f) => f.querySelector("img")?.getAttribute("alt"))).toEqual(["A screen.", "A wide screen."]);
    expect(figures[0]!.classList.contains("u-blogp__figure--portrait")).toBe(true);
    expect(figures[1]!.classList.contains("u-blogp__figure--portrait")).toBe(false);
    expect(root.querySelector(".u-blogp__images h2")?.textContent).toBe("In pictures");
  });

  it("uses the strings of the page language", async () => {
    const { root } = await render(post, "/pl/blog/newer/");
    expect(root.querySelector("a.u-blogp__back")?.textContent).toContain("Wszystkie wpisy");
    expect(root.textContent).toContain("Autor: Mateusz Wojczal");
    expect(root.querySelector("time")?.textContent).toBe("16 października 2026");
  });

  it("renders without a clip or a gallery", async () => {
    const bare: UiNode = { component: "BlogPost", props: { title: "Bare", date: "2026-10-08", backHref: "/blog/", markdown: "Text." } };
    const { root } = await render(bare);
    expect(root.querySelector("video")).toBeNull();
    expect(root.querySelector(".u-blogp__images")).toBeNull();
  });

  it("has no WCAG violations (axe)", async () => {
    const { root } = await render(post, "/blog/newer/");
    const result = await axe.run(root, axeOptions);
    expect(result.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.html).join(" | ")}`)).toEqual([]);
  });
});
