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

const doc: UiNode = {
  component: "FeatureIndex",
  props: {
    id: "features",
    title: "Everything it does",
    docsBase: "https://docs.example.test/",
    groups: [
      {
        id: "media",
        title: "Media pipeline",
        summary: "Upload once.",
        items: [
          { title: "Video to HLS", text: "A queued ffmpeg job.", packages: ["video"] },
          { title: "Adapt courses", text: "Off by default.", packages: ["adapt"], status: "preview" },
        ],
      },
      { id: "h5p", title: "H5P content types", tags: ["Accordion", "Crossword"] },
    ],
  },
};

async function render(): Promise<{ html: string; root: HTMLElement }> {
  const container = await AstroContainer.create();
  const html = await container.renderToString(Render as never, { props: { doc } });
  document.body.innerHTML = `<main>${html}</main>`;
  return { html, root: document.body.querySelector("main")! };
}

describe("FeatureIndex", () => {
  it("renders every group collapsed, with the features in the HTML and package links to the docs", async () => {
    const { root } = await render();
    const groups = [...root.querySelectorAll("details.u-fx__group")];
    expect(groups.map((g) => g.querySelector("h3")?.textContent)).toEqual(["Media pipeline", "H5P content types"]);
    expect(groups.every((g) => !g.hasAttribute("open"))).toBe(true);
    expect(groups[0]!.textContent).toContain("A queued ffmpeg job.");
    expect(groups[0]!.querySelector("a.u-fx__pkg")?.getAttribute("href")).toBe("https://docs.example.test/reference/packages/video/");
    expect([...groups[1]!.querySelectorAll(".u-fx__tags li")].map((li) => li.textContent)).toEqual(["Accordion", "Crossword"]);
    expect(groups[0]!.querySelector(".u-status--preview")).not.toBeNull();
  });

  it("emits a schema.org ItemList with a nested list per group", async () => {
    const { root } = await render();
    const ld = JSON.parse(root.querySelector('script[type="application/ld+json"]')!.textContent!);
    expect(ld["@type"]).toBe("ItemList");
    expect(ld.itemListElement).toHaveLength(2);
    expect(ld.itemListElement[0].item.itemListElement.map((i: { name: string }) => i.name)).toEqual(["Video to HLS", "Adapt courses"]);
    expect(ld.itemListElement[1].item.itemListElement.map((i: { name: string }) => i.name)).toEqual(["Accordion", "Crossword"]);
  });

  it("has no WCAG violations (axe)", async () => {
    const { root } = await render();
    const result = await axe.run(root, { rules: { "color-contrast": { enabled: false }, region: { enabled: false } } });
    expect(result.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.html).join(" | ")}`)).toEqual([]);
  });
});
