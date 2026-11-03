import { readFileSync } from "node:fs";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { JSDOM } from "jsdom";
import { beforeAll, describe, expect, it } from "vitest";
import type { UiNode } from "@ulams/ui/render-core";
import { landingDocFor } from "../../src/lib/docs.ts";
import { applyLandingStatus } from "../../src/lib/landing-status.ts";
import { comparisonModel } from "../../src/lib/comparison.ts";
import { workflowsModel } from "../../src/lib/workflows.ts";
import { homeStory, planHomeBody, STEP_KEYS, STORY_ID } from "../../src/lib/home-story.ts";
import { LOCALES, publicPagePath, type Locale } from "../../src/i18n/locales.ts";

const ofType = (node: UiNode, name: string): UiNode[] => [...(node.component === name ? [node] : []), ...(node.children ?? []).flatMap((c) => ofType(c, name))];

/** Shape of a copy object: keys, array lengths and number values, with the strings left out. */
const shape = (value: unknown): unknown => {
  if (Array.isArray(value)) return value.map(shape);
  if (value && typeof value === "object") return Object.fromEntries(Object.entries(value).map(([k, v]) => [k, shape(v)]));
  return typeof value === "number" ? value : typeof value;
};
const strings = (value: unknown): string[] =>
  typeof value === "string" ? [value] : Array.isArray(value) ? value.flatMap(strings) : value && typeof value === "object" ? Object.values(value).flatMap(strings) : [];

/** "7.5 minutes", "7,5 min" and the like: the run lasted 7 min 44 s and is never rounded to a decimal. */
const DECIMAL_MINUTES = /\b7[.,]5\b/;
/** A speed-up ratio in any of the three languages. */
const RATIO = /times faster|\d\s*(x|×)\b|\d+\s*times\b|razy szybciej|\d\s*razy\b|\d\s*倍/i;

describe("home story: copy", () => {
  it("has the same structure in every language and no empty text", () => {
    const en = shape(homeStory("en"));
    for (const locale of LOCALES) {
      expect(shape(homeStory(locale)), locale).toEqual(en);
      for (const text of strings(homeStory(locale))) expect(text.trim().length, locale).toBeGreaterThan(0);
    }
  });

  it("tells the six steps in order with unique anchors", () => {
    for (const locale of LOCALES) {
      const copy = homeStory(locale);
      const ids = STEP_KEYS.map((key) => copy[key].id);
      expect(new Set(ids).size).toBe(6);
      for (const key of STEP_KEYS) {
        const step = copy[key];
        for (const field of [step.title, step.text, step.why, step.alt]) expect(field.length).toBeGreaterThan(3);
        expect(step.label.length).toBeGreaterThan(1);
      }
    }
    expect(STEP_KEYS).toEqual(["sources", "draft", "review", "living", "learners", "evidence"]);
  });

  it("states the one measured run exactly, labelled as a draft for review", () => {
    for (const locale of LOCALES) {
      const { draft } = homeStory(locale);
      const facts = JSON.stringify(draft.run.facts);
      for (const figure of ["13", "6", "18", "35/35"]) expect(facts).toContain(`"${figure}"`);
      expect(facts).toMatch(/7 (min|分) 44 (s|秒)/);
      expect(facts).toMatch(/0[.,]28/);
      expect(draft.run.badge.length).toBeGreaterThan(8);
      expect(JSON.stringify(homeStory(locale))).not.toMatch(DECIMAL_MINUTES);
    }
    expect(homeStory("en").draft.run.badge).toBe("One measured run · draft for your review");
  });

  it("states the same run figures as the faster-courses block after it, and claims no speed-up ratio", () => {
    for (const locale of LOCALES) {
      const copy = homeStory(locale);
      const band = JSON.stringify(ofType(landingDocFor("platform", locale)!, "CtaBand").find((b) => b.props!.id === "cost-cta")!.props);
      for (const fact of copy.draft.run.facts) {
        const value = fact.value.replace(/^0,28 USD$|^0\.28 美元$/, "0.28");
        expect(band, `${locale}: ${fact.value}`).toContain(value);
      }
      expect(JSON.stringify(copy)).not.toMatch(RATIO);
    }
  });

  it("labels status honestly: variants in the builder available, learner routing coming", () => {
    for (const locale of LOCALES) {
      const states = homeStory(locale).learners.status.map((s) => s.state);
      expect(states).toEqual(["available", "coming"]);
    }
    expect(homeStory("en").learners.status[1]!.label).toMatch(/routing/i);
  });
});

describe("home story: placement", () => {
  it("goes right after the hero and before the two call-to-action blocks; the old hero story is gone", () => {
    for (const locale of LOCALES) {
      const doc = landingDocFor("platform", locale)!;
      const plan = planHomeBody(doc)!;
      expect(plan.before.map((n) => n.component)).toEqual(["SiteHeader"]);
      expect(plan.after.map((n) => n.component)).toEqual(["SiteFooter"]);
      const order = plan.main.map((p) => (p.kind === "story" ? "story" : p.node.component));
      expect(order.slice(0, 3)).toEqual(["Hero", "story", "CtaBand"]);
      const ids = plan.main.map((p) => (p.kind === "story" ? "story" : String(p.node.props?.id ?? p.node.component)));
      expect(ids.indexOf("story")).toBeLessThan(ids.indexOf("cost-cta"));
      expect(ids.indexOf("story")).toBeLessThan(ids.indexOf("reporting-cta"));
      expect(order.filter((name) => name === "story")).toHaveLength(1);
      const json = JSON.stringify(doc);
      expect(json).not.toContain('"diff"');
    }
  });

  it("puts the story first when a document has no hero, and returns null without a main landmark", () => {
    const doc: UiNode = { component: "Page", children: [{ component: "Main", children: [{ component: "Faq", props: { items: [] } }] }] };
    expect(planHomeBody(doc)!.main[0]).toEqual({ kind: "story" });
    expect(planHomeBody({ component: "Hero", props: { title: "x" } })).toBeNull();
  });
});

// tsc has no types for .astro files (astro check covers them), so the component is imported by a plain string
const COMPONENT = "../../src/components/diagrams/HomeBody.astro";
let container: AstroContainer;
let HomeBody: unknown;
beforeAll(async () => {
  container = await AstroContainer.create();
  HomeBody = ((await import(/* @vite-ignore */ COMPONENT)) as { default: unknown }).default;
});

async function render(locale: Locale): Promise<Document> {
  const doc = applyLandingStatus(landingDocFor("platform", locale)!, "actual");
  const data = { demos: [], demoCount: 0, comparison: comparisonModel(), workflows: workflowsModel("actual", locale) };
  const html = await container.renderToString(HomeBody as never, { props: { doc, data, locale } });
  return new JSDOM(`<!doctype html><html><body>${html}</body></html>`).window.document;
}

describe("home story: rendered markup", () => {
  for (const locale of LOCALES) {
    it(`${locale}: one main landmark; the story follows the hero with a heading, a step list and six captioned steps`, async () => {
      const document = await render(locale);
      const copy = homeStory(locale);
      expect(document.querySelectorAll("main#main")).toHaveLength(1);
      const story = document.querySelector(`main section#${STORY_ID}`)!;
      expect(story).not.toBeNull();
      expect(story.previousElementSibling?.classList.contains("u-hero")).toBe(true);
      expect(document.getElementById(story.getAttribute("aria-labelledby")!)!.tagName).toBe("H2");
      expect(document.querySelector(".u-hero ulams-policy-story, .policy-story")).toBeNull();
      // the step list links every step
      const rail = [...story.querySelectorAll("nav.hs-rail a")];
      expect(rail.map((a) => a.getAttribute("href"))).toEqual(STEP_KEYS.map((key) => `#${copy[key].id}`));
      expect(story.querySelector("nav.hs-rail")!.getAttribute("aria-label")).toBe(copy.stepsLabel);
      // six steps, each with a real-text caption and the business reason, visible to everyone
      const steps = [...story.querySelectorAll("ol.hs__steps > li.hs-step")];
      expect(steps.map((s) => s.id)).toEqual(STEP_KEYS.map((key) => copy[key].id));
      steps.forEach((step, i) => {
        const key = STEP_KEYS[i]!;
        expect(step.querySelector("h3")!.textContent).toBe(copy[key].title);
        expect(step.querySelector(".hs-step__text")!.textContent).toBe(copy[key].text);
        const why = step.querySelector(".hs-why")!;
        expect(why.querySelector(".hs-why__label")!.textContent).toBe(copy.whyLabel);
        expect(why.textContent).toContain(copy[key].why);
        expect(why.closest("[aria-hidden='true'], [role='img']")).toBeNull();
        // the picture is one image with a text alternative; its shapes are not read out on their own
        const visual = step.querySelector(".hs-visual")!;
        expect(visual.getAttribute("role")).toBe("img");
        expect(visual.getAttribute("aria-label")).toBe(copy[key].alt);
      });
      expect(story.querySelector(".hs-again h3")!.textContent).toBe(copy.again.title);
    });

    it(`${locale}: figures and evidence labels are real text outside the pictures; the links stay on the blocks below`, async () => {
      const document = await render(locale);
      const copy = homeStory(locale);
      const draft = document.getElementById(copy.draft.id)!;
      const facts = [...draft.querySelectorAll(".hs-run dd")].map((dd) => dd.textContent);
      expect(facts).toEqual(copy.draft.run.facts.map((f) => f.value));
      expect(draft.querySelector(".hs-run")!.closest("[role='img']")).toBeNull();
      expect(draft.querySelector(".dg__badge--run")!.textContent).toBe(copy.draft.run.badge);
      // the variants status is shown as text badges, not only in the picture
      const learners = document.getElementById(copy.learners.id)!;
      expect(learners.querySelectorAll(".hs-status .u-status--available")).toHaveLength(1);
      expect(learners.querySelectorAll(".hs-status .u-status--coming")).toHaveLength(1);
      expect(document.getElementById(copy.living.id)!.querySelector(".hs-step__label .u-status--available")).not.toBeNull();
      // the story does not repeat the blocks' buttons: each detail page has one link, on its block, with its Umami event
      const story = document.querySelector(`#${STORY_ID}`)!;
      expect(story.querySelectorAll("a[data-umami-event]")).toHaveLength(0);
      let next = story.nextElementSibling;
      while (next && next.tagName !== "SECTION") next = next.nextElementSibling;
      expect(next?.id).toBe("cost-cta");
      for (const [id, slug, event] of [["cost-cta", "content-cost", "cost_page_open"], ["reporting-cta", "reporting", "reporting_page_open"]] as const) {
        const links = document.querySelectorAll(`main a[href="${publicPagePath(locale, slug)}"]`);
        expect(links).toHaveLength(1);
        expect(links[0]!.closest("section")!.id).toBe(id);
        expect(links[0]!.getAttribute("data-umami-event")).toBe(event);
      }
      const text = document.querySelector(`#${STORY_ID}`)!.textContent!;
      expect(text).not.toMatch(DECIMAL_MINUTES);
      expect(text).not.toMatch(RATIO);
    });

    it(`${locale}: the static markup is the final state (no JS, reduced motion) and the replay button waits for the script`, async () => {
      const document = await render(locale);
      const story = document.querySelector(`#${STORY_ID}`)!;
      expect(story.hasAttribute("data-dg-group")).toBe(true);
      expect(story.querySelectorAll("[data-dg]")).toHaveLength(7);
      for (const el of story.querySelectorAll("[data-dg]")) expect(el.classList.contains("is-armed")).toBe(false);
      for (const el of story.querySelectorAll("[style]")) expect(el.getAttribute("style")).not.toMatch(/opacity\s*:\s*0|scale\(0|display\s*:\s*none/);
      const buttons = [...story.querySelectorAll("button")];
      expect(buttons).toHaveLength(1);
      expect(buttons[0]!.getAttribute("type")).toBe("button");
      expect(buttons[0]!.hasAttribute("hidden")).toBe(true);
      expect(buttons[0]!.textContent!.trim()).toBe(homeStory(locale).replay);
      expect(buttons[0]!.getAttribute("data-dg-control")).toBe("replay-group");
    });
  }
});

describe("home story: styles", () => {
  const css = readFileSync(new URL("../../src/components/diagrams/home-story.css", import.meta.url), "utf8");
  const [base, motion] = css.split("@media (prefers-reduced-motion: no-preference)");

  it("hides nothing and moves nothing outside the motion-allowed block", () => {
    expect(motion).toBeDefined();
    expect(base).not.toMatch(/opacity:\s*0[;\s]/);
    expect(base).not.toMatch(/scale[XY]?\(0[.)]|rotate\(/);
    expect(base).not.toMatch(/animation:|@keyframes/);
  });

  it("animates only transform (and the shared opacity reveal) and only for armed diagrams with JS", () => {
    for (const rule of motion!.trim().replace(/^\{/, "").split("}").filter((r) => r.includes("{"))) {
      const [selector, body] = rule.split("{");
      if (!body || !body.trim()) continue;
      expect(selector).toContain(".js-dg .dg.is-armed");
      for (const property of body.split(";").map((d) => d.split(":")[0]!.trim()).filter(Boolean)) {
        expect(["transform", "transition"]).toContain(property);
      }
    }
  });
});

describe("home page: white label and the policy-to-proof path", () => {
  for (const locale of LOCALES) {
    it(`${locale}: has the white-label band with six points and says a per-course subdomain is not built`, () => {
      const doc = landingDocFor("platform", locale)!;
      const band = ofType(doc, "CtaBand").find((b) => b.props!.id === "white-label")!;
      expect((band.props!.bullets as unknown[]).length).toBe(6);
      expect((band.props!.cta as { href: string }).href).toBe("#demos");
      expect(String(band.props!.note).length).toBeGreaterThan(40);
      const order = planHomeBody(doc)!.main.map((p) => (p.kind === "story" ? "story" : String(p.node.props?.id ?? p.node.component)));
      expect(order.indexOf("white-label")).toBeGreaterThan(order.indexOf("reporting-cta"));
      expect(order.indexOf("white-label")).toBeLessThan(order.indexOf("agents"));
    });
    it(`${locale}: labels what is not built in the policy-to-proof list`, () => {
      const list = ofType(landingDocFor("platform", locale)!, "FeatureList")[0]!;
      const items = list.props!.items as Array<{ status?: string }>;
      expect(items).toHaveLength(6);
      expect(items.filter((i) => i.status === "coming")).toHaveLength(3);
    });
  }
});
