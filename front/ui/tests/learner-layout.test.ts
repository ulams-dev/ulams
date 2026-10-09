import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { beforeAll, describe, expect, it, vi } from "vitest";
import axe from "axe-core";
import { LEARNER_LAYOUT_COMPONENTS, registry, type ComponentName } from "../src/registry.ts";
import { prepare, type UiNode } from "../src/render-core.ts";
import { validate } from "../src/schema.ts";
import { mount, renderDoc, setupDom } from "./astro-render.ts";

const example = (name: string): { props: Record<string, unknown>; invalid: Record<string, unknown> } =>
  JSON.parse(readFileSync(fileURLToPath(new URL(`../catalogue/examples/${name}.json`, import.meta.url)), "utf8"));

const NEW = ["Timeline", "FlipCards", "CodeBlock", "PracticeActivity"] as const;
const doc = (name: string, props: Record<string, unknown>): UiNode => ({ component: name, props });

beforeAll(async () => {
  setupDom();
  await import("../src/elements/flip-cards.ts");
  await import("../src/elements/code-block.ts");
  await import("../src/elements/practice-activity.ts");
});

describe("approved set for learner layouts", () => {
  it("names only registered components and includes the four new ones", () => {
    for (const name of LEARNER_LAYOUT_COMPONENTS) expect(registry[name as ComponentName], name).toBeDefined();
    for (const name of NEW) expect(LEARNER_LAYOUT_COMPONENTS).toContain(name);
    expect(LEARNER_LAYOUT_COMPONENTS).toHaveLength(9);
  });

  it("ships JavaScript only where a component is interactive", () => {
    expect(registry.Timeline.interactive).toBe(false);
    for (const name of ["FlipCards", "CodeBlock", "PracticeActivity"] as const) expect(registry[name].interactive, name).toBe(true);
  });
});

describe.each([...LEARNER_LAYOUT_COMPONENTS])("%s", (name) => {
  const ex = example(name);

  it("has a valid example and a plain-text fallback", () => {
    expect(validate(registry[name].props, ex.props).issues).toEqual([]);
    expect(typeof registry[name].fallback(ex.props)).toBe("string");
  });

  it("falls back to text for invalid props instead of breaking the page", () => {
    const node = prepare(doc(name, ex.invalid));
    expect(node.kind).toBe("fallback");
  });

  it("renders without WCAG violations (axe)", async () => {
    const main = mount(await renderDoc(doc(name, ex.props)));
    const result = await axe.run(main, { rules: { "color-contrast": { enabled: false }, region: { enabled: false } } });
    expect(result.violations.map((v) => `${v.id}: ${v.nodes[0]?.html}`)).toEqual([]);
  });
});

describe("Timeline", () => {
  it("is an ordered list with no script and marks the current entry", async () => {
    const html = await renderDoc(doc("Timeline", example("Timeline").props));
    expect(html).not.toContain("<script");
    const main = mount(html);
    expect(main.querySelectorAll("ol > li")).toHaveLength(4);
    expect(main.querySelector('[aria-current="step"]')?.textContent).toContain("Oxford");
  });
});

describe("FlipCards", () => {
  it("shows both sides without JS and hides each answer behind a native button with JS", async () => {
    const html = await renderDoc(doc("FlipCards", example("FlipCards").props));
    expect(html).toContain("Dissolving flavour compounds");
    const main = mount(html);
    const buttons = [...main.querySelectorAll("button")];
    expect(buttons).toHaveLength(3);
    for (const button of buttons) expect(button.tabIndex).toBeGreaterThanOrEqual(0);
    const first = buttons[0]!;
    const back = main.ownerDocument.getElementById(first.getAttribute("aria-controls")!)!;
    expect(back.hidden).toBe(true);
    expect(first.getAttribute("aria-expanded")).toBe("false");
    first.click();
    expect(back.hidden).toBe(false);
    expect(first.getAttribute("aria-expanded")).toBe("true");
    expect(first.textContent).toBe("Hide answer");
    first.click();
    expect(back.hidden).toBe(true);
  });
});

describe("CodeBlock", () => {
  it("renders a focusable, scrollable listing and copies the code without line numbers", async () => {
    const main = mount(await renderDoc(doc("CodeBlock", example("CodeBlock").props)));
    const pre = main.querySelector("pre")!;
    expect(pre.tabIndex).toBe(0);
    const writeText = vi.fn(async () => undefined);
    Object.defineProperty(main.ownerDocument.defaultView!.navigator, "clipboard", { value: { writeText }, configurable: true });
    const button = main.querySelector<HTMLButtonElement>(".u-code__copy")!;
    expect(button.textContent).toBe("Copy");
    button.click();
    await vi.waitFor(() => expect(writeText).toHaveBeenCalled());
    expect(writeText).toHaveBeenCalledWith(example("CodeBlock").props.code);
    expect(button.textContent).toBe("Copied");
    expect(main.querySelector("[data-live]")?.textContent).toContain("copied");
    expect(main.textContent).not.toMatch(/\bRun\b/);
  });
});

describe("PracticeActivity", () => {
  const solution = "Sour and thin points to under-extraction";
  const mountActivity = async () => mount(await renderDoc(doc("PracticeActivity", example("PracticeActivity").props)));

  it("keeps the worked solution out of the page until an attempt event", async () => {
    const main = await mountActivity();
    expect(main.textContent).not.toContain(solution);
    expect(main.querySelector("[data-solution-out]")?.childElementCount).toBe(0);
    const el = main.querySelector("ulams-practice")!;
    el.dispatchEvent(
      new (main.ownerDocument.defaultView!.CustomEvent)("ulams:practice-attempt", { bubbles: true, detail: { activityId: "practice", challengeId: "c1" } })
    );
    expect(main.textContent).toContain(solution);
    // only the attempted challenge is revealed
    expect(main.textContent).not.toContain("Bitter and dry means over-extraction");
  });

  it("treats a chosen answer as an attempt: explains why and then reveals the solution", async () => {
    const main = await mountActivity();
    const first = main.querySelector<HTMLElement>("[data-challenge='c1']")!;
    const events: unknown[] = [];
    main.addEventListener("ulams:practice-attempt", (e) => events.push((e as CustomEvent).detail));
    const check = first.querySelector<HTMLButtonElement>("[data-check]")!;
    check.click();
    expect(first.querySelector("[data-feedback-out]")?.textContent).toContain("Choose an answer first");
    expect(events).toHaveLength(0);
    expect(main.textContent).not.toContain(solution);
    const wrong = first.querySelectorAll<HTMLInputElement>("input[type=radio]")[1]!;
    wrong.checked = true;
    check.click();
    expect(first.querySelector("[data-feedback-out]")?.textContent).toMatch(/^Not quite\. Cooler water dissolves less/);
    expect(events).toEqual([{ activityId: "practice", challengeId: "c1", optionIndex: 1, correct: false }]);
    expect(main.textContent).toContain(solution);
  });

  it("offers an open task as 'I have tried it' and reveals only after it", async () => {
    const main = await mountActivity();
    const open = main.querySelector<HTMLElement>("[data-challenge='c2']")!;
    expect(open.querySelector("input")).toBeNull();
    const button = open.querySelector<HTMLButtonElement>("[data-check]")!;
    expect(button.textContent?.trim()).toBe("I have tried it");
    expect(main.textContent).not.toContain("Bitter and dry means over-extraction");
    button.click();
    expect(main.textContent).toContain("Bitter and dry means over-extraction");
  });

  it("reveals hints one at a time in tier order, whatever order they were authored in", async () => {
    const main = await mountActivity();
    const first = main.querySelector<HTMLElement>("[data-challenge='c1']")!;
    const button = first.querySelector<HTMLButtonElement>(".u-practice__hint-btn")!;
    expect(button.textContent).toContain("Nudge");
    button.click();
    expect(first.textContent).toContain("What does sourness tell you");
    expect(first.textContent).not.toContain("Look at the grind");
    first.querySelector<HTMLButtonElement>(".u-practice__hint-btn")!.click();
    expect(first.textContent).toContain("Look at the grind");
    expect(first.textContent).not.toContain("Sour usually means under-extraction");
    first.querySelector<HTMLButtonElement>(".u-practice__hint-btn")!.click();
    expect(first.textContent).toContain("Sour usually means under-extraction");
    expect(first.querySelector(".u-practice__hint-btn")).toBeNull();
  });

  it("never puts hints or worked solutions in the text fallback", () => {
    const text = registry.PracticeActivity.fallback(example("PracticeActivity").props);
    expect(text).toContain("Level 1");
    expect(text).not.toContain(solution);
    expect(text).not.toContain("Nudge");
    expect(text).not.toContain("What does sourness tell you");
  });

  it("requires the scaffolding slots and valid levels, tiers and options", () => {
    const base = example("PracticeActivity").props as { challenges: Array<Record<string, unknown>> };
    const bad = (patch: Record<string, unknown>) => validate(registry.PracticeActivity.props, { ...base, ...patch }).valid;
    expect(bad({})).toBe(true);
    for (const slot of ["intro", "toolbox", "challenges"]) expect(bad({ [slot]: undefined }), slot).toBe(false);
    expect(bad({ toolbox: [] })).toBe(false);
    expect(bad({ challenges: [] })).toBe(false);
    expect(bad({ challenges: [{ ...base.challenges[0], level: 4 }] })).toBe(false);
    expect(bad({ challenges: [{ ...base.challenges[0], level: 0 }] })).toBe(false);
    expect(bad({ challenges: [{ ...base.challenges[0], hints: [{ tier: "answer", text: "x" }] }] })).toBe(false);
    expect(bad({ challenges: [{ ...base.challenges[0], workedSolution: undefined }] })).toBe(false);
    expect(bad({ challenges: [{ ...base.challenges[0], options: [{ label: "A" }] }] })).toBe(false);
  });
});

describe("learner layout manifest", () => {
  it("lists the approved components with closed schemas and matches the committed file", async () => {
    const { learnerLayoutManifest } = await import("../src/registry.ts");
    const manifest = learnerLayoutManifest();
    expect(Object.keys(manifest.components)).toEqual([...LEARNER_LAYOUT_COMPONENTS]);
    const open: string[] = [];
    const walk = (schema: Record<string, unknown>, path: string) => {
      if (schema.type === "object" && schema.additionalProperties !== false) open.push(path);
      for (const [k, v] of Object.entries((schema.properties ?? {}) as Record<string, Record<string, unknown>>)) walk(v, `${path}.${k}`);
      if (schema.items) walk(schema.items as Record<string, unknown>, `${path}[]`);
    };
    for (const [name, spec] of Object.entries(manifest.components)) walk(spec.props as Record<string, unknown>, name);
    expect(open).toEqual([]);
    const file = JSON.parse(readFileSync(fileURLToPath(new URL("../catalogue/learner-layout-manifest.json", import.meta.url)), "utf8"));
    expect(file).toEqual(JSON.parse(JSON.stringify(manifest)));
  });
});
