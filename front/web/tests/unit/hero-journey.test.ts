import { readFileSync } from "node:fs";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { JSDOM } from "jsdom";
import { beforeAll, describe, expect, it } from "vitest";
import { landingDocFor } from "../../src/lib/docs.ts";
import { applyLandingStatus } from "../../src/lib/landing-status.ts";
import { comparisonModel } from "../../src/lib/comparison.ts";
import { workflowsModel } from "../../src/lib/workflows.ts";
import { BEATS, BEAT_MS, JOURNEY_ID, STATIC_BEAT, heroJourneyCopy } from "../../src/lib/hero-journey.ts";
import { LOCALES, type Locale } from "../../src/i18n/locales.ts";

const shape = (value: unknown): unknown => {
  if (Array.isArray(value)) return value.map(shape);
  if (value && typeof value === "object") return Object.fromEntries(Object.entries(value).map(([k, v]) => [k, shape(v)]));
  return typeof value === "number" ? value : typeof value;
};
const strings = (value: unknown): string[] =>
  typeof value === "string" ? [value] : Array.isArray(value) ? value.flatMap(strings) : value && typeof value === "object" ? Object.values(value).flatMap(strings) : [];

describe("hero journey: copy", () => {
  it("has the same structure (and the same illustrative shapes) in every language, with no empty text", () => {
    const en = shape(heroJourneyCopy("en"));
    for (const locale of LOCALES) {
      expect(shape(heroJourneyCopy(locale)), locale).toEqual(en);
      for (const text of strings(heroJourneyCopy(locale))) expect(text.trim().length, locale).toBeGreaterThan(0);
    }
  });

  it("covers the whole journey in eight one-line beats, with a loop long enough to need the pause control", () => {
    expect(BEATS).toEqual(["source", "chat", "draft", "review", "learners", "analytics", "change", "update"]);
    expect(BEATS.length * BEAT_MS).toBeGreaterThan(5000);
    for (const locale of LOCALES) for (const beat of BEATS) expect(heroJourneyCopy(locale).captions[beat].length, `${locale} ${beat}`).toBeLessThanOrEqual(72);
  });

  it("chats with the builder's own question wording", () => {
    const qs = heroJourneyCopy("en").chat.lines.map((l) => l.q);
    expect(qs.slice(0, 4)).toEqual(["Who is this course for?", "What is the starting level of your learners?", "What tone should the course have?", "How long should the whole course take?"]);
    expect(qs[4]).toMatch(/level or role/);
  });

  it("labels the analytics as an illustration and routing to a version as coming", () => {
    const labels: Record<Locale, { coming: string }> = { en: { coming: "Coming" }, pl: { coming: "Wkrótce" }, zh: { coming: "即将推出" } };
    for (const locale of LOCALES) {
      const copy = heroJourneyCopy(locale);
      expect(copy.learners.coming).toBe(labels[locale].coming);
      expect(copy.analytics.badge.length).toBeGreaterThan(5);
    }
  });
});

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

describe("hero journey: rendered markup", () => {
  for (const locale of LOCALES) {
    it(`${locale}: sits in the product hero after the unchanged headline and CTAs, as a static summary until the script runs`, async () => {
      const document = await render(locale);
      const hero = document.querySelector("main#main > section.u-hero")!;
      expect(hero.querySelectorAll("h1")).toHaveLength(1);
      expect(hero.querySelector(".u-actions a")!.getAttribute("href")).toMatch(/#demos$/);
      const journey = hero.querySelector(`#${JOURNEY_ID}`)!;
      expect(journey).not.toBeNull();
      // the headline comes first in the markup: the animation never sits in front of the LCP text
      expect(hero.querySelector("h1")!.compareDocumentPosition(journey) & 4).toBe(4);
      const copy = heroJourneyCopy(locale);
      expect([...journey.querySelectorAll("[data-hj-caption]")].map((li) => li.textContent!.replace(/^\d+/, ""))).toEqual(BEATS.map((b) => copy.captions[b]));
      expect([...journey.querySelectorAll("[data-hj-scene].is-on")].map((s) => s.getAttribute("data-hj-scene"))).toEqual([STATIC_BEAT]);
      expect(journey.querySelector(".hj-stage")!.getAttribute("aria-hidden")).toBe("true");
      expect(journey.querySelector("[data-hj-controls]")!.hasAttribute("hidden")).toBe(true);
      expect(journey.classList.contains("is-live")).toBe(false);
      for (const button of journey.querySelectorAll("button")) expect(button.getAttribute("type")).toBe("button");
      expect(journey.querySelector(".hj-coming")!.textContent).toBe(copy.learners.coming);
      expect(journey.querySelector(".hj-badge")!.textContent).toBe(copy.analytics.badge);
      expect(journey.querySelectorAll(".hj-chat li")).toHaveLength(5);
      expect(journey.querySelectorAll("img")).toHaveLength(0);
    });
  }
});

describe("hero journey: styles", () => {
  const css = readFileSync(new URL("../../src/components/diagrams/hero-journey.css", import.meta.url), "utf8");
  const [base, motion] = css.split("@media (prefers-reduced-motion: no-preference)");
  it("hides nothing by opacity and moves nothing outside the motion block", () => {
    expect(motion).toBeDefined();
    expect(base).not.toMatch(/opacity:\s*0[;\s]/);
    expect(base).not.toMatch(/animation:|@keyframes|transition:/);
  });
  it("animates only opacity and transform, and only once the script is live", () => {
    for (const rule of motion!.split("@keyframes")[0]!.trim().replace(/^\{/, "").split("}").filter((r) => r.includes("{"))) {
      const [selector, body] = rule.split("{");
      if (!body!.trim()) continue;
      expect(selector).toContain(".hj.is-live");
      for (const property of body!.split(";").map((d) => d.split(":")[0]!.trim()).filter(Boolean)) {
        expect(["opacity", "transform", "transition", "animation", "animation-delay"]).toContain(property);
      }
    }
    for (const frame of motion!.split("@keyframes").slice(1)) for (const property of frame.match(/[a-z-]+(?=\s*:)/g) ?? []) expect(["opacity", "transform"]).toContain(property);
  });
});
