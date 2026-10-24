import { existsSync, readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { JSDOM } from "jsdom";
import { beforeAll, describe, expect, it } from "vitest";
import { landingDocFor } from "../../src/lib/docs.ts";
import { applyLandingStatus } from "../../src/lib/landing-status.ts";
import { comparisonModel } from "../../src/lib/comparison.ts";
import { workflowsModel } from "../../src/lib/workflows.ts";
import { SHOTS, generationCopy } from "../../src/lib/course-generation.ts";
import { WALKTHROUGH_ID, WALK_FRAMES, stepStatus, walkthroughCopy, walkthroughSteps } from "../../src/lib/builder-walkthrough.ts";
import { LOCALES, publicPagePath, type Locale } from "../../src/i18n/locales.ts";

const shape = (value: unknown): unknown => {
  if (Array.isArray(value)) return value.map(shape);
  if (value && typeof value === "object") return Object.fromEntries(Object.entries(value).map(([k, v]) => [k, shape(v)]));
  return typeof value;
};
const strings = (value: unknown): string[] =>
  typeof value === "string" ? [value] : Array.isArray(value) ? value.flatMap(strings) : value && typeof value === "object" ? Object.values(value).flatMap(strings) : [];
const RATIO = /times faster|\d\s*(x|×)\b|\d+\s*times\b|razy szybciej|\d\s*razy\b|\d\s*倍/i;
const publicDir = (path: string) => fileURLToPath(new URL(`../../public${path}`, import.meta.url));

/** The builder's own question labels (api/packages/course-builder: the brief questions and InterviewService::fixedQuestions). */
const BUILDER_QUESTIONS = ["Who is this course for?", "What is the starting level of your learners?", "What tone should the course have?", "Should learners be tested?", "In which language should the course be written?", "Will the course be free or paid?", "Which look should the site have?"];

describe("builder walkthrough: copy", () => {
  it("has the same structure in every language and no empty text", () => {
    const en = shape(walkthroughCopy("en"));
    for (const locale of LOCALES) {
      expect(shape(walkthroughCopy(locale)), locale).toEqual(en);
      for (const text of strings(walkthroughCopy(locale))) expect(text.trim().length, locale).toBeGreaterThan(0);
    }
  });

  it("asks the builder's own questions in English, and something on every screen where the author answers", () => {
    const asks = Object.values(walkthroughCopy("en").steps).flatMap((step) => step.asks).join(" ");
    for (const question of BUILDER_QUESTIONS) expect(asks).toContain(question.replace("?", ""));
    for (const locale of LOCALES) {
      const steps = walkthroughCopy(locale).steps;
      for (const key of ["connect", "audience", "tone", "brief", "outline", "checks", "publish"] as const) expect(steps[key].asks.length, `${locale} ${key}`).toBeGreaterThan(0);
      expect(steps.progress.asks).toEqual([]);
    }
  });

  it("shows the one measured run, labelled as such, with no speed-up ratio", () => {
    for (const locale of LOCALES) {
      const { result } = walkthroughCopy(locale);
      const facts = result.facts.map((f) => f.value);
      expect(facts.slice(0, 4)).toEqual(["13", "6", "18", "35/35"]);
      expect(facts[4]).toMatch(/7\D+44/);
      expect(facts[5]).toContain(locale === "pl" ? "0,28" : "0.28");
      expect(result.badge.length).toBeGreaterThan(10);
      expect(result.modules.flatMap((m) => m.lessons)).toHaveLength(6);
      expect(result.cases.map((c) => c.page)).toEqual(["case-docs-repo", "case-notion"]);
      expect(strings(walkthroughCopy(locale)).join(" ")).not.toMatch(RATIO);
    }
  });
});

describe("builder walkthrough: frames", () => {
  it("uses real case-study screenshots that exist, with their geometry and the case study's own caption", () => {
    for (const locale of LOCALES) {
      const steps = walkthroughSteps(locale);
      expect(steps.map((s) => s.key)).toEqual(WALK_FRAMES.map((f) => f.key));
      steps.forEach((step, i) => {
        const frame = WALK_FRAMES[i]!;
        const shots = SHOTS[frame.run];
        const index = shots.frames.findIndex((s) => s.file === frame.file);
        expect(index, frame.file).toBeGreaterThanOrEqual(0);
        expect(step.image).toEqual({ src: `/course-generation/${shots.folder}/${frame.file}`, width: shots.frames[index]!.width, height: shots.frames[index]!.height });
        expect(existsSync(publicDir(step.image.src)), step.image.src).toBe(true);
        expect(step.caption).toBe(generationCopy(locale).cases[frame.run].captions[index]);
        expect(step.alt.length).toBeGreaterThan(15);
      });
    }
  });

  it("fills the step status", () => {
    expect(stepStatus(walkthroughCopy("en").status, 3, 9)).toBe("Step 3 of 9");
    expect(stepStatus(walkthroughCopy("pl").status, 1, 9)).toBe("Krok 1 z 9");
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

describe("builder walkthrough: rendered markup", () => {
  for (const locale of LOCALES) {
    it(`${locale}: every step is in the static markup with its questions, screenshot and caption as real text`, async () => {
      const document = await render(locale);
      const section = document.getElementById(WALKTHROUGH_ID)!;
      expect(section).not.toBeNull();
      expect(section.querySelectorAll("h2")).toHaveLength(1);
      const steps = walkthroughSteps(locale);
      const panels = [...section.querySelectorAll<HTMLElement>("[data-wt-panel]")];
      expect(panels.map((p) => p.id)).toEqual(steps.map((s) => s.id));
      for (const panel of panels) expect(panel.hasAttribute("hidden")).toBe(false);
      steps.forEach((step, i) => {
        const panel = panels[i]!;
        expect(panel.querySelector("h3")!.textContent).toBe(step.title);
        expect([...panel.querySelectorAll(".wt-asks li")].map((li) => li.textContent)).toEqual(step.asks);
        expect(panel.querySelector("figcaption")!.textContent).toContain(step.caption);
        const img = panel.querySelector("img")!;
        expect(img.getAttribute("alt")).toBe(step.alt);
        expect(img.getAttribute("width")).toBe(String(step.image.width));
        expect(img.getAttribute("height")).toBe(String(step.image.height));
        expect(img.getAttribute("loading")).toBe("lazy");
      });
      // step links point at the panels; the Previous / Next controls wait for the script
      expect([...section.querySelectorAll("[data-wt-link]")].map((a) => a.getAttribute("href"))).toEqual(steps.map((s) => `#${s.id}`));
      expect(section.querySelector("[data-wt-controls]")!.hasAttribute("hidden")).toBe(true);
      for (const button of section.querySelectorAll("button")) expect(button.getAttribute("type")).toBe("button");
    });

    it(`${locale}: the result shows the course, the run and the two case studies with their Umami event`, async () => {
      const document = await render(locale);
      const section = document.getElementById(WALKTHROUGH_ID)!;
      const copy = walkthroughCopy(locale);
      expect([...section.querySelectorAll(".wt-facts dd")].map((dd) => dd.textContent)).toEqual(copy.result.facts.map((f) => f.value));
      expect(section.querySelector(".wt-badge")!.textContent).toBe(copy.result.badge);
      expect(section.querySelectorAll(".wt-modules ul li")).toHaveLength(6);
      expect(section.querySelector(".wt-modules")!.getAttribute("lang")).toBe("en");
      const links = [...section.querySelectorAll<HTMLAnchorElement>(".wt-cases a")];
      expect(links.map((a) => a.getAttribute("href"))).toEqual([publicPagePath(locale, "case-docs-repo"), publicPagePath(locale, "case-notion")]);
      for (const link of links) expect(link.getAttribute("data-umami-event")).toBe("case_study_open");
    });
  }
});

describe("builder walkthrough: styles", () => {
  const css = readFileSync(new URL("../../src/components/diagrams/builder-walkthrough.css", import.meta.url), "utf8");
  it("animates only when motion is allowed, and never hides a step without the script", () => {
    const [base, motion] = css.split("@media (prefers-reduced-motion: no-preference)");
    expect(motion).toBeDefined();
    expect(base).not.toMatch(/animation:|@keyframes|transition:/);
    expect(base).not.toMatch(/opacity:\s*0[;\s]/);
    expect(motion).toContain(".wt.is-ready");
  });
});
