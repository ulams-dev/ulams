import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { afterEach, beforeAll, describe, expect, it, vi } from "vitest";
import { renderDoc, setupDom } from "./astro-render.ts";
import { REMEDIATION_COMPONENTS, registry, remediationManifest } from "../src/registry.ts";
import { fallbackText, prepare, validateDocument, type UiNode } from "../src/render-core.ts";
import { validate } from "../src/schema.ts";
import { uiStrings, type UiStrings } from "../src/lib/i18n.ts";

const ID = "01JBCDEFGHJKMNPQRSTVWXYZ01";
const PRACTICE = {
  stem: "How much coffee do you need for 480 ml of water at a 1:16 ratio?",
  options: [
    { text: "20 g", correct: false },
    { text: "30 g", correct: true },
    { text: "48 g", correct: false },
  ],
  explanation: "480 divided by 16 is 30, so 30 g of coffee.",
};

const card = (props: Record<string, unknown> = {}, children: UiNode[] = []): UiNode => ({
  component: "SupportCard",
  props: { assignmentId: ID, status: "ready", summary: "A short explanation and one question to try.", reasons: ["You answered this question wrongly 2 times"], aiLabel: "AI-generated, based on this course's sources", ...props },
  children,
});
const BLOCKS: UiNode[] = [
  { component: "Prose", props: { title: "Another way to see it", markdown: "A **1:16** ratio means 1 g of coffee per 16 g of water." } },
  { component: "CitationList", props: { items: [{ label: "Brewing basics: the ratio" }] } },
  { component: "WorkedExample", props: { title: "Working out a ratio", markdown: "Step 1. Divide 300 by 16.\n\nStep 2. About 19 g." } },
  { component: "PracticeQuestion", props: { title: "Try it yourself", markdown: "Use the ratio.", practice: PRACTICE } },
  { component: "ElaborativeQuestion", props: { title: "Say it in your own words", markdown: "Why does a finer grind taste more bitter?" } },
];

// axe reads the DOM globals when it loads, so the jsdom window comes first
setupDom();
const { default: axe } = await import("axe-core");
beforeAll(async () => {
  await import("../src/elements/support.ts");
  await import("../src/elements/practice-question.ts");
});

const AXE = { rules: { "color-contrast": { enabled: false }, region: { enabled: false } } };
const violations = async (root: HTMLElement) => (await axe.run(root, AXE)).violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.html).join(" | ")}`);
async function mountDoc(doc: UiNode): Promise<HTMLElement> {
  document.body.innerHTML = `<main><h1 tabindex="-1">Brew ratios</h1>${await renderDoc(doc)}</main>`;
  return document.querySelector("main")!;
}

describe("support components: schema and fallback", () => {
  it("accepts the stored shape of a remediation block and rejects what the generator never writes", () => {
    expect(validate(registry.Prose.props, { title: "t", markdown: "m" }).issues).toEqual([]);
    expect(validate(registry.WorkedExample.props, { title: "t", markdown: "m" }).valid).toBe(true);
    expect(validate(registry.PracticeQuestion.props, { title: "t", markdown: "m", practice: PRACTICE }).issues).toEqual([]);
    expect(validate(registry.ElaborativeQuestion.props, { title: "t", markdown: "m" }).valid).toBe(true);
    expect(validate(registry.WorkedExample.props, { title: "t" }).valid).toBe(false);
    expect(validate(registry.WorkedExample.props, { title: "t", markdown: "x".repeat(1801) }).valid).toBe(false);
    expect(validate(registry.WorkedExample.props, { title: "t", markdown: "m", onclick: "x" }).valid).toBe(false);
    const one = { ...PRACTICE, options: PRACTICE.options.slice(0, 1) };
    const six = { ...PRACTICE, options: [...PRACTICE.options, ...PRACTICE.options] };
    expect(validate(registry.PracticeQuestion.props, { title: "t", markdown: "m", practice: one }).valid).toBe(false);
    expect(validate(registry.PracticeQuestion.props, { title: "t", markdown: "m", practice: six }).valid).toBe(false);
    expect(validate(registry.PracticeQuestion.props, { title: "t", markdown: "m", practice: { ...PRACTICE, explanation: undefined } }).valid).toBe(false);
  });

  it("CitationList, WhyThis, AiLabel and SupportCard bounds", () => {
    expect(validate(registry.CitationList.props, { items: [] }).valid).toBe(false);
    expect(validate(registry.CitationList.props, { items: Array.from({ length: 13 }, () => ({ label: "x" })) }).valid).toBe(false);
    expect(validate(registry.CitationList.props, { items: [{ label: "x", href: "javascript:alert(1)" }] }).valid).toBe(false);
    expect(validate(registry.CitationList.props, { items: [{ label: "x", href: "/learn/1/2" }] }).valid).toBe(true);
    expect(validate(registry.WhyThis.props, { reasons: [] }).valid).toBe(false);
    expect(validate(registry.WhyThis.props, { reasons: Array.from({ length: 7 }, () => "r") }).valid).toBe(false);
    expect(validate(registry.AiLabel.props, { text: "x", tone: "loud" }).valid).toBe(false);
    expect(validate(registry.AiLabel.props, { text: "x" }).value).toMatchObject({ tone: "generated" });
    expect(validate(registry.SupportCard.props, { status: "done" }).valid).toBe(false);
    expect(validate(registry.SupportCard.props, {}).value).toMatchObject({ status: "ready" });
  });

  it("Prose keeps working without a title and takes an optional one", () => {
    expect(validate(registry.Prose.props, { markdown: "m" }).valid).toBe(true);
    expect(validate(registry.Prose.props, { title: "x".repeat(121), markdown: "m" }).valid).toBe(false);
    expect(fallbackText({ component: "Prose", props: { title: "Title", markdown: "Body" } })).toBe("Title\nBody");
    expect(fallbackText({ component: "Prose", props: { markdown: "Body" } })).toBe("Body");
  });

  it("the plain-text fallback of a practice question leaves out the explanation and any marker of the right option", () => {
    const text = fallbackText({ component: "PracticeQuestion", props: { title: "Try it", markdown: "Use the ratio.", practice: PRACTICE } });
    expect(text).toContain(PRACTICE.stem);
    expect(text).toContain("- 30 g");
    expect(text).not.toContain(PRACTICE.explanation);
    expect(text.toLowerCase()).not.toContain("correct");
  });

  it("an invalid practice question renders its text fallback, not a broken widget", () => {
    const node = prepare({ component: "PracticeQuestion", props: { title: "t", markdown: "m", practice: { ...PRACTICE, options: [] } } });
    expect(node.kind).toBe("fallback");
  });

  it("falls back for the other blocks to the title and the Markdown, and for SupportCard to summary and reasons", () => {
    expect(fallbackText({ component: "WorkedExample", props: { title: "T", markdown: "M" } })).toBe("T\nM");
    expect(fallbackText({ component: "ElaborativeQuestion", props: { title: "T", markdown: "M" } })).toBe("T\nM");
    expect(fallbackText(card())).toBe("A short explanation and one question to try.\n- You answered this question wrongly 2 times");
    expect(fallbackText({ component: "CitationList", props: { items: [{ label: "A" }, { label: "B" }] } })).toBe("Sources\n- A\n- B");
  });
});

describe("remediation manifest", () => {
  const manifest = remediationManifest();
  const closedEverywhere = (schema: Record<string, unknown>, path = ""): string[] => {
    const problems: string[] = [];
    if (schema.type === "object" && schema.additionalProperties !== false) problems.push(path || "/");
    for (const [key, sub] of Object.entries((schema.properties ?? {}) as Record<string, Record<string, unknown>>)) problems.push(...closedEverywhere(sub, `${path}/${key}`));
    if (schema.items) problems.push(...closedEverywhere(schema.items as Record<string, unknown>, `${path}/items`));
    return problems;
  };

  it("lists exactly the four blocks the generator writes", () => {
    expect(REMEDIATION_COMPONENTS).toEqual(["Prose", "WorkedExample", "PracticeQuestion", "ElaborativeQuestion"]);
    expect(Object.keys(manifest.components)).toEqual([...REMEDIATION_COMPONENTS]);
  });

  it("closes every object and keeps a generated Prose block to the generation limit", () => {
    for (const [name, spec] of Object.entries(manifest.components)) expect(closedEverywhere(spec.props as never), name).toEqual([]);
    const prose = manifest.components.Prose!.props.properties!.markdown!;
    expect(prose.maxLength).toBe(1800);
    expect(registry.Prose.props.properties!.markdown!.maxLength).toBe(60000);
  });

  it("is committed with its API copy", () => {
    const json = `${JSON.stringify(manifest, null, 2)}\n`;
    const read = (path: string) => readFileSync(fileURLToPath(new URL(path, import.meta.url)), "utf8");
    expect(read("../catalogue/remediation-manifest.json")).toBe(json);
    expect(read("../../../api/packages/learner-insights/resources/remediation-manifest.json")).toBe(json);
  });
});

describe("the Extra help card renders accessibly", () => {
  it("is a labelled region with an h2, the blocks, sources, the AI label, a collapsed why and unhidden-by-script controls", async () => {
    const root = await mountDoc(card({}, BLOCKS));
    const region = root.querySelector<HTMLElement>("section[role=region]")!;
    expect(region.querySelector(`#${region.getAttribute("aria-labelledby")}`)?.textContent).toBe("Extra help for you");
    expect(region.querySelector("h2")).not.toBeNull();
    expect(region.textContent).toContain("AI-generated, based on this course's sources");
    expect(region.querySelectorAll("h3").length).toBe(4);
    expect(region.querySelector("h3")?.textContent).toBe("Another way to see it");
    expect(region.querySelector("h4")?.textContent).toBe("Sources");
    const why = region.querySelector("details")!;
    expect(why.hasAttribute("open")).toBe(false);
    expect(why.querySelector("summary")?.textContent).toBe("Why am I seeing this?");
    expect(why.textContent).toContain("You answered this question wrongly 2 times");
    // the controls are in the server HTML but hidden; the element shows them once scripts run
    expect(await renderDoc(card({}, []))).toMatch(/data-feedback[^>]*hidden/);
    expect(region.querySelector<HTMLElement>("[data-feedback]")!.hidden).toBe(false);
    expect(region.querySelectorAll("[role=status]").length).toBeGreaterThanOrEqual(2);
    expect(await violations(root)).toEqual([]);
  });

  it("feedback buttons are toggle buttons that start from the saved answer", async () => {
    const root = await mountDoc(card({ feedback: "helpful" }, BLOCKS.slice(0, 1)));
    const pressed = [...root.querySelectorAll<HTMLButtonElement>("button[data-answer]")].map((b) => `${b.textContent?.trim()}=${b.getAttribute("aria-pressed")}`);
    expect(pressed).toEqual(["This helped=true", "Not helpful=false"]);
    expect(await violations(root)).toEqual([]);
  });

  it("a generating item says it is being prepared, has no controls and no blocks", async () => {
    const root = await mountDoc(card({ status: "generating", assignmentId: undefined, summary: undefined, reasons: undefined }, BLOCKS));
    expect(root.textContent).toContain("Preparing an explanation…");
    expect(root.querySelector("button")).toBeNull();
    expect(root.querySelector("h3")).toBeNull();
    expect(await violations(root)).toEqual([]);
  });

  it("follows the locale prop in Polish and Chinese", async () => {
    const pl = await mountDoc(card({ locale: "pl" }, [BLOCKS[3]!, BLOCKS[4]!].map((b) => ({ ...b, props: { ...b.props, locale: "pl" } }))));
    expect(pl.textContent).toContain(uiStrings("pl").supportTitle);
    expect(pl.textContent).toContain(uiStrings("pl").supportWhy);
    expect(pl.textContent).toContain(uiStrings("pl").supportThoughts);
    expect(pl.querySelector("button[data-check]")?.textContent?.trim()).toBe(uiStrings("pl").supportCheck);
    expect(await violations(pl)).toEqual([]);
    const zh = await mountDoc(card({ locale: "zh" }, []));
    expect(zh.textContent).toContain(uiStrings("zh").supportTitle);
  });

  it("escapes HTML in blocks and in the plain-text props", async () => {
    const root = await mountDoc(card({ summary: "<img src=x onerror=alert(1)>" }, [{ component: "WorkedExample", props: { title: "<b>t</b>", markdown: "<script>alert(1)</script>" } }]));
    expect(root.querySelector("img")).toBeNull();
    expect([...root.querySelectorAll("script")].filter((s) => !s.getAttribute("src"))).toEqual([]);
    expect(root.querySelector("h3 b")).toBeNull();
  });

  it("two questions on a page never share a radio group or an id", async () => {
    const other = { ...PRACTICE, stem: "A different question?" };
    const root = await mountDoc(card({}, [BLOCKS[3]!, { component: "PracticeQuestion", props: { title: "Another", markdown: "m", practice: other } }]));
    const names = new Set([...root.querySelectorAll<HTMLInputElement>("input[type=radio]")].map((r) => r.name));
    expect(names.size).toBe(2);
    expect(await violations(root)).toEqual([]);
  });

  it("validates as a catalogue document", () => {
    expect(validateDocument(card({}, BLOCKS))).toEqual([]);
  });
});

describe("<ulams-practice-question>", () => {
  async function question() {
    const root = await mountDoc(BLOCKS[3]!);
    const el = root.querySelector("ulams-practice-question") as HTMLElement;
    const radios = [...el.querySelectorAll<HTMLInputElement>("input[type=radio]")];
    return { root, el, radios, result: el.querySelector<HTMLElement>("[data-result]")!, check: el.querySelector<HTMLButtonElement>("button[data-check]")! };
  }

  it("shows the Check button only with scripts and keeps the explanation out of the page until an attempt", async () => {
    const { el, check } = await question();
    expect(check.hidden).toBe(false);
    expect(el.querySelector("[data-nojs]")?.hasAttribute("hidden")).toBe(true);
    expect(el.textContent).not.toContain(PRACTICE.explanation);
  });

  it("asks to choose first, then states a right answer in words with the explanation", async () => {
    const { radios, result, check } = await question();
    check.click();
    expect(result.textContent).toBe("Choose an answer first.");
    radios[1]!.checked = true;
    check.click();
    expect(result.textContent).toBe(`Correct. ${PRACTICE.explanation}`);
    expect(radios[1]!.closest("label")!.textContent).toContain("Correct");
    expect(radios[1]!.closest("label")!.classList.contains("u-pq__option--correct")).toBe(true);
  });

  it("states a wrong answer in words, moves the mark and can be tried again", async () => {
    const { radios, result, check } = await question();
    radios[0]!.checked = true;
    check.click();
    expect(result.textContent).toBe(`Not quite. ${PRACTICE.explanation}`);
    expect(radios[0]!.closest("label")!.textContent).toContain("Not quite");
    radios[1]!.checked = true;
    check.click();
    expect(radios[0]!.closest("label")!.textContent).not.toContain("Not quite");
    expect(radios[1]!.closest("label")!.textContent).toContain("Correct");
  });

  it("is operable by keyboard: the radios and the button are native controls in tab order", async () => {
    const { el } = await question();
    for (const control of el.querySelectorAll<HTMLElement>("input, button:not([hidden])")) expect(control.tabIndex).toBeGreaterThanOrEqual(0);
    expect(el.querySelector("fieldset legend")?.textContent).toBe(PRACTICE.stem);
  });
});

describe("<ulams-support>", () => {
  const calls: Array<{ url: string; method: string; body: string | undefined }> = [];
  const replies: Array<{ status: number; body: unknown }> = [];
  afterEach(() => {
    vi.unstubAllGlobals();
    vi.useRealTimers();
    calls.length = 0;
    replies.length = 0;
  });

  async function support(feedback?: "helpful" | "not_helpful") {
    vi.stubGlobal(
      "fetch",
      vi.fn(async (url: string, init: RequestInit) => {
        calls.push({ url: String(url), method: String(init.method), body: init.body as string | undefined });
        const next = replies.shift() ?? { status: 200, body: { data: {} } };
        return new Response(JSON.stringify(next.body), { status: next.status });
      })
    );
    const root = await mountDoc(card({ feedback }, BLOCKS.slice(0, 1)));
    const el = root.querySelector("ulams-support") as HTMLElement;
    const q = (s: string) => el.querySelector<HTMLButtonElement>(s)!;
    const flush = async () => {
      for (let i = 0; i < 5; i++) await Promise.resolve();
      await new Promise((r) => setTimeout(r, 0));
    };
    return { root, el, q, flush, status: el.querySelector<HTMLElement>("[data-status-text]")! };
  }

  it("shows the controls and saves 'This helped' through the BFF, toggling aria-pressed and saying so", async () => {
    replies.push({ status: 200, body: { data: { feedback: "helpful" } } });
    const { el, q, flush, status } = await support();
    expect(el.querySelector<HTMLElement>("[data-feedback]")!.hidden).toBe(false);
    q("[data-answer=helpful]").click();
    await flush();
    expect(calls).toEqual([{ url: `/bff/api/learner-insights/assignments/${ID}/feedback`, method: "POST", body: '{"helpful":true}' }]);
    expect(q("[data-answer=helpful]").getAttribute("aria-pressed")).toBe("true");
    expect(q("[data-answer=not_helpful]").getAttribute("aria-pressed")).toBe("false");
    expect(status.textContent).toBe("Thanks. Your answer is saved.");
  });

  it("switches the answer, ignores a second press of the same one, and restores the old answer on a failure", async () => {
    replies.push({ status: 200, body: { data: {} } }, { status: 503, body: { message: "down" } });
    const { q, flush, status } = await support("helpful");
    q("[data-answer=not_helpful]").click();
    await flush();
    expect(q("[data-answer=not_helpful]").getAttribute("aria-pressed")).toBe("true");
    q("[data-answer=not_helpful]").click();
    await flush();
    expect(calls).toHaveLength(1);
    q("[data-answer=helpful]").click();
    await flush();
    expect(calls).toHaveLength(2);
    expect(q("[data-answer=not_helpful]").getAttribute("aria-pressed")).toBe("true");
    expect(q("[data-answer=helpful]").getAttribute("aria-pressed")).toBe("false");
    expect(status.textContent).toBe("Could not save that. Try again.");
  });

  it("asks before hiding, in place, and 'Keep it' returns to the controls with focus on Hide", async () => {
    const { el, q } = await support();
    q("[data-hide]").click();
    expect(el.querySelector<HTMLElement>("[data-confirm]")!.hidden).toBe(false);
    expect(el.querySelector<HTMLElement>("[data-feedback]")!.hidden).toBe(true);
    expect(el.querySelector("[data-confirm]")?.textContent).toContain("It will not come back for this lesson.");
    expect(calls).toHaveLength(0);
    q("[data-hide-no]").click();
    expect(el.querySelector<HTMLElement>("[data-feedback]")!.hidden).toBe(false);
    expect(document.activeElement).toBe(q("[data-hide]"));
    expect(calls).toHaveLength(0);
  });

  it("hides the help after confirming: the card goes, a polite region says so and focus lands on the lesson heading", async () => {
    replies.push({ status: 200, body: { data: { hidden: true } } });
    const { root, q } = await support();
    q("[data-hide]").click();
    q("[data-hide-yes]").click();
    await new Promise((r) => setTimeout(r, 120));
    expect(calls).toEqual([{ url: `/bff/api/learner-insights/assignments/${ID}/hide`, method: "POST", body: undefined }]);
    expect(root.querySelector("section[role=region]")).toBeNull();
    const live = document.getElementById("u-support-live")!;
    expect(live.getAttribute("role")).toBe("status");
    expect(live.getAttribute("aria-live")).toBe("polite");
    expect(live.textContent).toBe("Extra help hidden for this lesson.");
    expect(document.activeElement).toBe(root.querySelector("h1"));
  });

  it("keeps the card and says so when hiding fails", async () => {
    replies.push({ status: 500, body: { message: "boom" } });
    const { root, q, flush, status } = await support();
    q("[data-hide]").click();
    q("[data-hide-yes]").click();
    await flush();
    expect(root.querySelector("section[role=region]")).not.toBeNull();
    expect(status.textContent).toBe("Could not save that. Try again.");
    expect(q("[data-hide-yes]").disabled).toBe(false);
  });
});

describe("interface strings: English, Polish and Chinese stay in step", () => {
  const keys = (strings: UiStrings) => Object.keys(strings).sort();
  it("every key exists and is filled in all three languages", () => {
    const en = uiStrings("en");
    for (const locale of ["pl", "zh"]) {
      const other = uiStrings(locale);
      expect(keys(other), locale).toEqual(keys(en));
      for (const key of Object.keys(en) as Array<keyof UiStrings>) {
        const value = other[key];
        if (typeof value === "string") expect(value.trim(), `${locale}.${key}`).not.toBe("");
      }
    }
  });

  it("the support strings are translated, not copied", () => {
    const en = uiStrings("en") as unknown as Record<string, string>;
    for (const locale of ["pl", "zh"]) {
      const other = uiStrings(locale) as unknown as Record<string, string>;
      for (const key of Object.keys(en).filter((k) => k.startsWith("support"))) {
        if (key === "supportCheck" && locale === "pl") continue;
        expect(other[key], `${locale}.${key}`).not.toBe(en[key]);
      }
    }
  });
});
