import { describe, expect, it, vi } from "vitest";
import { JSDOM } from "jsdom";
import { mount, renderDoc, setupDom } from "./astro-render.ts";
import { validateDocument, type UiNode } from "../src/render-core.ts";

const showcase = {
  src: "https://content.test/interactive/k/v1/index.html",
  title: "Solar system",
  steps: [{ id: "solar-system", title: "The solar system", text: "Eight planets orbit the Sun." }],
  showcase: { steps: ["solar-system"], poster: "https://content.test/interactive/k/v1/posters/showcase.webp" },
  href: "/learn/7",
  tryLabel: "Try it",
  licence: "MIT",
};
const lessons = [
  { title: "Welcome", kicker: "Module 1", summary: "A short start.", minutes: 5, topics: [{ title: "Hello", format: "reading" }] },
  { title: "Orbits", summary: "Why planets stay put.", minutes: 12, topics: [{ title: "Kepler", format: "interactive" }] },
];
const header = (mark: string): UiNode => ({ component: "SiteHeader", props: { brand: "Demo", mark, links: [{ label: "Syllabus", href: "#syllabus" }] } });

describe("the cosmos, atlas and notebook hero", () => {
  it.each([["cosmos"], ["atlas"], ["notebook"]])("%s draws its own picture when there is no live package", async (variant) => {
    const doc: UiNode = { component: "Hero", props: { variant, title: "Fall into it", primaryCta: { label: "Start", href: "/" } } };
    expect(validateDocument(doc)).toEqual([]);
    const { document } = new JSDOM(await renderDoc(doc)).window;
    expect(document.querySelector(`svg.u-stage--${variant}`)?.getAttribute("aria-hidden")).toBe("true");
    expect(document.querySelector("iframe")).toBeNull();
  });

  it("the notebook picture plots the primes of an Ulam spiral", async () => {
    const { document } = new JSDOM(await renderDoc({ component: "Hero", props: { variant: "notebook", title: "Primes" } })).window;
    // 23 x 23 spiral: 529 integers; 99 primes up to 529 (excluding 1) are drawn, plus the centre marker
    expect(document.querySelectorAll(".u-stage--notebook circle:not(.u-stage__one)")).toHaveLength(99);
  });

  it("plays the package as a clean loop: a still, a sandboxed frame nobody can reach, a text alternative and one link", async () => {
    const doc: UiNode = { component: "Hero", props: { variant: "cosmos", title: "Fall into it", showcase } };
    expect(validateDocument(doc)).toEqual([]);
    const { document } = new JSDOM(await renderDoc(doc)).window;
    expect(document.querySelector("svg.u-stage")).toBeNull();
    const frame = document.querySelector(".u-hero__stage iframe")!;
    expect(frame.getAttribute("sandbox")).not.toContain("allow-same-origin");
    expect(frame.hasAttribute("src")).toBe(false); // starts after first paint, never at load
    expect(frame.hasAttribute("inert")).toBe(true);
    expect(frame.getAttribute("tabindex")).toBe("-1");
    const stage = document.querySelector(".u-sc__stage")!;
    expect(stage.getAttribute("aria-hidden")).toBe("true");
    expect(stage.querySelector("img.u-sc__still")?.getAttribute("src")).toBe(showcase.showcase.poster);
    expect(stage.querySelector("img")?.getAttribute("width")).toBe("1280"); // explicit size: no layout shift
    expect(document.querySelector("ulams-showcase")?.getAttribute("steps")).toBe("solar-system");
    expect(document.querySelector(".u-hero__stage .u-visually-hidden")?.textContent).toContain("The solar system");
    // none of the lesson player's chrome
    expect(document.querySelector(".u-hero__stage details, .u-hero__stage [data-prev], .u-hero__stage [data-next], .u-hero__stage [data-counter]")).toBeNull();
    expect(document.querySelector(".u-sc__try")?.getAttribute("href")).toBe("/learn/7");
  });

  it("leaves out the link when there is no lesson to go to", async () => {
    const { href: _href, ...withoutHref } = showcase;
    const { document } = new JSDOM(await renderDoc({ component: "Hero", props: { variant: "atlas", title: "X", showcase: withoutHref } })).window;
    expect(document.querySelector(".u-sc__try")).toBeNull();
  });

  it("rejects a showcase without steps", () => {
    const doc: UiNode = { component: "Hero", props: { variant: "atlas", title: "X", showcase: { src: showcase.src, title: "t", steps: [], showcase: { steps: ["a"] } } } };
    expect(validateDocument(doc)).not.toEqual([]);
  });
});

describe("the orbits and atlas syllabus", () => {
  it.each([["orbits"], ["atlas"]])("%s keeps one list item per lesson with its topics", async (variant) => {
    const doc: UiNode = { component: "Syllabus", props: { variant, title: "Plan", lessons } };
    expect(validateDocument(doc)).toEqual([]);
    const { document } = new JSDOM(await renderDoc(doc)).window;
    expect(document.querySelectorAll(`ol.u-${variant} > li`)).toHaveLength(2);
    expect(document.body.textContent).toContain("Kepler");
  });

  it("numbers atlas chapters", async () => {
    const { document } = new JSDOM(await renderDoc({ component: "Syllabus", props: { variant: "atlas", lessons } })).window;
    expect([...document.querySelectorAll(".u-atlas__num")].map((el) => el.textContent?.trim())).toEqual(["01", "02"]);
  });
});

describe("site header marks", () => {
  it.each([["orbit"], ["compass"], ["sigma"]])("%s renders a decorative mark", async (mark) => {
    expect(validateDocument(header(mark))).toEqual([]);
    const { document } = new JSDOM(await renderDoc(header(mark))).window;
    expect(document.querySelector(`.u-header--${mark} .u-brand__mark svg`)).not.toBeNull();
  });
});

describe("accessibility of the new variants (axe)", () => {
  it.each([["cosmos"], ["atlas"], ["notebook"]])("hero %s renders without WCAG violations", async (variant) => {
    vi.useRealTimers();
    setupDom();
    mount(await renderDoc({ component: "Hero", props: { variant, title: "Fall into it", subtitle: "A subtitle", primaryCta: { label: "Start", href: "/" }, facts: [{ label: "Price", value: "Free" }] } }));
    const { default: axe } = await import("axe-core");
    const result = await axe.run(document.body.querySelector("main")!, { rules: { "color-contrast": { enabled: false }, region: { enabled: false } } });
    expect(result.violations.map((v) => `${v.id}: ${v.nodes.length}`)).toEqual([]);
  });

  it.each([["orbits"], ["atlas"]])("syllabus %s renders without WCAG violations", async (variant) => {
    vi.useRealTimers();
    setupDom();
    mount(await renderDoc({ component: "Syllabus", props: { variant, title: "Plan", lessons } }));
    const { default: axe } = await import("axe-core");
    const result = await axe.run(document.body.querySelector("main")!, { rules: { "color-contrast": { enabled: false }, region: { enabled: false } } });
    expect(result.violations.map((v) => `${v.id}: ${v.nodes.length}`)).toEqual([]);
  });
});
