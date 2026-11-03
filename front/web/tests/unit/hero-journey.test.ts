import { readFileSync } from "node:fs";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { JSDOM } from "jsdom";
import { beforeAll, describe, expect, it } from "vitest";
import { landingDocFor } from "../../src/lib/docs.ts";
import { applyLandingStatus } from "../../src/lib/landing-status.ts";
import { comparisonModel } from "../../src/lib/comparison.ts";
import { workflowsModel } from "../../src/lib/workflows.ts";
import { BEATS, BEAT_MS, BLOCK_ICONS, JOURNEY_ID, STATIC_BEAT, heroJourneyCopy } from "../../src/lib/hero-journey.ts";
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

  it("covers the whole journey in eight beats, from your material to the update, with a loop long enough to need the pause control", () => {
    expect(BEATS).toEqual(["source", "chat", "brand", "draft", "review", "learners", "analytics", "update"]);
    expect(BEATS.length * BEAT_MS).toBeGreaterThan(5000);
    for (const locale of LOCALES) {
      const copy = heroJourneyCopy(locale);
      for (const beat of BEATS) {
        expect(copy.tabs[beat].length, `${locale} ${beat}`).toBeLessThanOrEqual(20);
        expect(copy.steps[beat].title.length, `${locale} ${beat}`).toBeLessThanOrEqual(60);
        expect(copy.steps[beat].why.length, `${locale} ${beat}`).toBeGreaterThan(10);
      }
    }
  });

  it("uses a neutral example, never a policy, and names Confluence only as an export", () => {
    const text = JSON.stringify(heroJourneyCopy("en"));
    expect(text).not.toMatch(/polic/i);
    expect(text).toMatch(/Confluence export/);
    expect(heroJourneyCopy("en").steps.source.text).toMatch(/Confluence space comes in as an export/);
  });

  it("chats with the builder's own question wording and its preset options", () => {
    const lines = heroJourneyCopy("en").chat.lines;
    expect(lines.map((l) => l.q)).toEqual(["Who is this course for?", "What is the starting level of your learners?", "One course, or versions by level or role?"]);
    // the first answer is typed, the others are picked from the options the builder offers (ChoiceChips)
    expect(lines[0]!.options).toBeUndefined();
    for (const line of lines.slice(1)) expect(line.options).toContain(line.a);
    for (const locale of LOCALES) expect(heroJourneyCopy(locale).chat.lines.slice(1).every((l) => l.options!.includes(l.a)), locale).toBe(true);
  });

  it("assembles a full course: modules of lessons, each its text plus shipped blocks, then a final test and the certificate", () => {
    expect(STATIC_BEAT).toBe("draft");
    for (const locale of LOCALES) {
      const { draft } = heroJourneyCopy(locale);
      expect(draft.blocks).toHaveLength(BLOCK_ICONS.length);
      expect(draft.chip).toContain("§");
      expect(draft.modules.length).toBeGreaterThanOrEqual(2);
      for (const module of draft.modules) for (const lesson of module.lessons) for (const block of lesson.blocks) expect(draft.blocks[block], locale).toBeDefined();
      expect(draft.final.blocks.at(-1)).toBe(BLOCK_ICONS.length - 1);
    }
    expect(heroJourneyCopy("en").draft.blocks).toEqual(["Cited text", "Quiz", "Drag and drop", "Image hotspots", "Dialog cards", "Video + transcript", "Code exercise", "Certificate"]);
    expect(JSON.stringify(heroJourneyCopy("en").draft)).not.toMatch(/H5P/);
  });

  it("reviews a lesson block by block: accepted, replaced through an instruction, rejected", () => {
    for (const locale of LOCALES) expect(heroJourneyCopy(locale).review.items.map((i) => i.state), locale).toEqual(["accept", "accept", "replace", "reject"]);
  });

  it("publishes a version per role on its own subdomain, and the update as a new course version", () => {
    for (const locale of LOCALES) {
      const { learners, update, analytics } = heroJourneyCopy(locale);
      expect(new Set(learners.versions.map((v) => v.domain)).size, locale).toBe(learners.versions.length);
      expect(update.before).not.toBe(update.after);
      expect(analytics.blocks.filter((b) => b.warn)).toHaveLength(1);
    }
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
      // every beat is real text: the step title, what happens and why it matters; tabs come with the script
      expect([...journey.querySelectorAll("[data-hj-caption] h2")].map((h) => h.textContent)).toEqual(BEATS.map((b) => copy.steps[b].title));
      expect(journey.querySelectorAll("[data-hj-caption] .hj-why")).toHaveLength(BEATS.length);
      expect(journey.querySelector("[data-hj-tabs]")!.hasAttribute("hidden")).toBe(true);
      expect([...journey.querySelectorAll("[role=tab]")].map((t) => t.textContent!.replace(/^\d+/, "").trim())).toEqual(BEATS.map((b) => copy.tabs[b]));
      expect([...journey.querySelectorAll("[data-hj-scene].is-on")].map((s) => s.getAttribute("data-hj-scene"))).toEqual([STATIC_BEAT]);
      expect(journey.querySelector(".hj-stage")!.getAttribute("aria-hidden")).toBe("true");
      // one example tag for the whole stage, so it never moves between beats
      expect(journey.querySelectorAll(".hj-tag")).toHaveLength(1);
      expect(journey.querySelector("[data-hj-dot]")!.getAttribute("aria-hidden")).toBe("true");
      expect(journey.querySelector("[data-hj-controls]")!.hasAttribute("hidden")).toBe(true);
      expect(journey.classList.contains("is-live")).toBe(false);
      for (const button of journey.querySelectorAll("button")) expect(button.getAttribute("type")).toBe("button");
      expect(journey.querySelector(".hj-coming")!.textContent).toBe(copy.learners.coming);
      expect(journey.querySelector(".hj-badge")!.textContent).toBe(copy.analytics.badge);
      expect(journey.querySelectorAll(".hj-chat > li")).toHaveLength(copy.chat.lines.length);
      expect(journey.querySelectorAll(".hj-options .is-picked")).toHaveLength(copy.chat.lines.filter((l) => l.options).length);
      expect([...journey.querySelectorAll(".hj-academy__bar")].map((b) => b.textContent)).toEqual(copy.learners.versions.map((v) => v.domain));
      expect(journey.querySelectorAll(".hj-perblock li")).toHaveLength(copy.analytics.blocks.length);
      // the static scene is the full course: every lesson starts with its text, the first one cites the source
      const draft = journey.querySelector(".hj-scene.is-on")!;
      expect(draft.querySelectorAll(".hj-lesson")).toHaveLength(copy.draft.modules.flatMap((m) => m.lessons).length);
      expect(draft.querySelectorAll(".hj-lesson .hj-block--text")).toHaveLength(copy.draft.modules.flatMap((m) => m.lessons).length);
      expect(draft.querySelector(".hj-chip")!.textContent).toBe(copy.draft.chip);
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
