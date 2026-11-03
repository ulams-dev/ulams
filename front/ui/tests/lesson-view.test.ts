import { beforeAll, describe, expect, it } from "vitest";
import { renderDoc, setupDom } from "./astro-render.ts";
import { registry } from "../src/registry.ts";
import { fallbackText, validateDocument, type UiNode } from "../src/render-core.ts";
import { splitMarkdownSections } from "../src/lib/markdown.ts";
import { validate } from "../src/schema.ts";
import { uiStrings } from "../src/lib/i18n.ts";

setupDom();
const { default: axe } = await import("axe-core");
beforeAll(async () => {
  await import("../src/elements/parts.ts");
  await import("../src/elements/practice-activity.ts");
});

describe("splitMarkdownSections", () => {
  it("splits at H2 and keeps the text before the first heading as the introduction", () => {
    const out = splitMarkdownSections("Intro line.\n\n## One\n\nA\n\n### Detail\n\nB\n\n## Two\n\nC\n");
    expect(out.intro).toBe("Intro line.");
    expect(out.sections.map((s) => [s.heading, s.level])).toEqual([["One", 2], ["Two", 2]]);
    expect(out.sections[0]!.body).toContain("### Detail");
    expect(out.sections[1]!.body).toBe("C");
  });

  it("falls back to H3 when there are fewer than two H2s", () => {
    const out = splitMarkdownSections("## Only\n\n### A\n\nx\n\n### B\n\ny\n");
    expect(out.sections.map((s) => s.heading)).toEqual(["A", "B"]);
    expect(out.intro).toBe("## Only");
  });

  it("does not split on headings inside fenced code, lists, quotes or indented code", () => {
    const md = ["## One", "", "```md", "## not a heading", "```", "", "~~~", "## nor this", "~~~", "", "- item", "  ## inside a list", "", "> ## quoted", "", "    ## indented code", "", "## Two", "", "text"].join("\n");
    const out = splitMarkdownSections(md);
    expect(out.sections.map((s) => s.heading)).toEqual(["One", "Two"]);
    expect(out.sections[0]!.body).toContain("## not a heading");
    expect(out.sections[0]!.body).toContain("## inside a list");
  });

  it("closes a fence only with the same character and at least the same length", () => {
    const md = "## One\n\n````\n```\n## still code\n````\n\n## Two\n\nx";
    expect(splitMarkdownSections(md).sections.map((s) => s.heading)).toEqual(["One", "Two"]);
  });

  it("returns no sections for fewer than two parts, and reduces marks in headings to plain text", () => {
    expect(splitMarkdownSections("## Alone\n\ntext").sections).toEqual([]);
    expect(splitMarkdownSections("").sections).toEqual([]);
    expect(splitMarkdownSections(null).sections).toEqual([]);
    const out = splitMarkdownSections("## The **ratio** of `water` ##\n\na\n\n## [Grind](https://x.example) size\n\nb");
    expect(out.sections.map((s) => s.heading)).toEqual(["The ratio of water", "Grind size"]);
  });
});

const steps = (props: Record<string, unknown> = {}): UiNode => ({
  component: "ProseSteps",
  props: {
    intro: "Before we start.",
    sections: [
      { heading: "Grind", level: 2, markdown: "Use a **medium** grind." },
      { heading: "Water", level: 2, markdown: "Heat to 94 degrees." },
      { heading: "Pour", level: 2, markdown: "Pour slowly." },
    ],
    ...props,
  },
});

async function mount(doc: UiNode): Promise<HTMLElement> {
  document.body.innerHTML = `<main><h1>Brewing</h1>${await renderDoc(doc)}<div data-end-of-content></div></main>`;
  return document.querySelector("main")!;
}
const parts = () => [...document.querySelectorAll<HTMLElement>("[data-part]")];
const click = (selector: string) => document.querySelector<HTMLButtonElement>(selector)!.click();

describe("ProseSteps", () => {
  it("validates, has a text fallback with every part, and rejects a single part", () => {
    expect(validateDocument(steps())).toEqual([]);
    expect(validate(registry.ProseSteps.props, { sections: [{ heading: "a", markdown: "b" }] }).valid).toBe(false);
    expect(validate(registry.ProseSteps.props, { sections: steps().props!.sections, onclick: "x" }).valid).toBe(false);
    const text = fallbackText(steps());
    for (const word of ["Before we start.", "Grind", "Water", "Pour slowly."]) expect(text).toContain(word);
  });

  it("renders every part in the HTML (no script needed) with the navigation hidden", async () => {
    const html = await renderDoc(steps());
    expect(html.match(/data-part/g)).toHaveLength(3);
    expect(html).toMatch(/<nav[^>]*data-nav[^>]*hidden/);
    expect(html).toContain("Use a <strong>medium</strong> grind.");
  });

  it("shows one part at a time, announces the position and moves focus to the heading", async () => {
    await mount(steps());
    expect(parts().map((p) => p.hidden)).toEqual([false, true, true]);
    expect(document.querySelector("[data-nav]")!.hasAttribute("hidden")).toBe(false);
    const status = document.querySelector("[data-status]")!;
    expect(status.getAttribute("role")).toBe("status");
    expect(status.textContent).toBe("Part 1 of 3");
    expect(document.querySelector<HTMLButtonElement>("[data-prev]")!.disabled).toBe(true);
    expect(document.querySelector<HTMLElement>("[data-end-of-content]")!.hidden).toBe(true);
    click("[data-next]");
    expect(parts().map((p) => p.hidden)).toEqual([true, false, true]);
    expect(status.textContent).toBe("Part 2 of 3");
    expect(document.activeElement?.textContent?.trim()).toBe("Water");
    click("[data-next]");
    expect(document.querySelector<HTMLButtonElement>("[data-next]")!.disabled).toBe(true);
    expect(document.querySelector<HTMLElement>("[data-end-of-content]")!.hidden).toBe(false);
    click("[data-prev]");
    expect(status.textContent).toBe("Part 2 of 3");
    expect(document.querySelector<HTMLElement>("[data-end-of-content]")!.hidden).toBe(true);
  });

  it("uses the interface language of the card", async () => {
    await mount(steps({ locale: "pl" }));
    expect(document.querySelector("[data-status]")!.textContent).toBe("Część 1 z 3");
    expect(document.querySelector("[data-next]")!.textContent).toContain(uiStrings("pl").partsNext);
    expect(document.querySelector("section.u-parts")!.getAttribute("lang")).toBe("pl");
  });

  it("has no axe violations in either state", async () => {
    const root = await mount(steps());
    const AXE = { rules: { "color-contrast": { enabled: false }, region: { enabled: false } } };
    expect((await axe.run(root, AXE)).violations.map((v) => v.id)).toEqual([]);
    click("[data-next]");
    expect((await axe.run(root, AXE)).violations.map((v) => v.id)).toEqual([]);
  });
});

const practice = (extra: Record<string, unknown> = {}): UiNode => ({
  component: "PracticeActivity",
  props: {
    intro: "Practise ratios.",
    toolbox: [{ label: "Calculator" }],
    challenges: [
      {
        id: "c1",
        level: 1,
        prompt: "How much coffee for 480 ml?",
        hints: [
          { tier: "nudge", text: "Think about the ratio." },
          { tier: "pointer", text: "1:16 means 1 part coffee." },
          { tier: "near_solution", text: "Divide 480 by 16." },
        ],
        workedSolution: "30 g.",
      },
    ],
    ...extra,
  },
});

describe("PracticeActivity initial hints", () => {
  it("accepts 0 to 3 and nothing else", () => {
    for (const n of [0, 1, 2, 3]) expect(validateDocument(practice({ initialHints: n }))).toEqual([]);
    for (const n of [-1, 4, 1.5, "2"]) expect(validateDocument(practice({ initialHints: n })).length).toBeGreaterThan(0);
  });

  async function open(initial?: number): Promise<HTMLElement> {
    document.body.innerHTML = `<main>${await renderDoc(practice(initial === undefined ? {} : { initialHints: initial }))}</main>`;
    return document.querySelector("main")!;
  }
  const shown = () => [...document.querySelectorAll(".u-practice__hint")].map((p) => p.textContent);

  it("shows no hint by default and the first N tiers in order when set", async () => {
    await open();
    expect(shown()).toEqual([]);
    await open(2);
    expect(shown()).toEqual(["Nudge: Think about the ratio.", "Pointer: 1:16 means 1 part coffee."]);
    expect(document.querySelector(".u-practice__hint-btn")!.textContent).toContain("Near the solution");
    expect(document.activeElement).toBe(document.body);
  });

  it("keeps asking one tier at a time and removes the button after the last one", async () => {
    await open(1);
    document.querySelector<HTMLButtonElement>(".u-practice__hint-btn")!.click();
    expect(shown()).toHaveLength(2);
    document.querySelector<HTMLButtonElement>(".u-practice__hint-btn")!.click();
    expect(shown()).toHaveLength(3);
    expect(document.querySelector(".u-practice__hint-btn")).toBeNull();
  });

  it("with all three shown at the start there is no button and no stray focus", async () => {
    await open(3);
    expect(shown()).toHaveLength(3);
    expect(document.querySelector(".u-practice__hint-btn")).toBeNull();
    expect(document.activeElement).toBe(document.body);
  });
});
