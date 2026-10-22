import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { JSDOM } from "jsdom";
import { beforeAll, describe, expect, it } from "vitest";
import { IMPACT_PAGES, impactPage } from "../../src/lib/impact-pages.ts";
import { costDiagrams, reportingDiagrams } from "../../src/lib/impact-diagrams.ts";
import { LOCALES, publicPagePath, type Locale } from "../../src/i18n/locales.ts";

// tsc has no types for .astro files (astro check covers them), so the component is imported by a plain string
const COMPONENT = "../../src/components/diagrams/ImpactBody.astro";
let container: AstroContainer;
let ImpactBody: unknown;
beforeAll(async () => {
  container = await AstroContainer.create();
  ImpactBody = ((await import(/* @vite-ignore */ COMPONENT)) as { default: unknown }).default;
});

async function render(page: (typeof IMPACT_PAGES)[number], locale: Locale): Promise<Document> {
  const path = publicPagePath(locale, page);
  const result = impactPage(page, locale, new Request(`https://ulams.app${path}`), new URL(`https://ulams.app${path}`))!;
  const html = await container.renderToString(ImpactBody as never, { props: { doc: result.doc, data: result.data, page, locale } });
  return new JSDOM(`<!doctype html><html><body>${html}</body></html>`).window.document;
}

const IDS = {
  "content-cost": ["dg-benchmark", "dg-pipeline", "dg-living-loop", "dg-audiences"],
  reporting: ["dg-dashboard", "dg-dataflow", "dg-roles"],
} as const;

describe("impact-page diagrams: rendered markup", () => {
  for (const page of IMPACT_PAGES) {
    for (const locale of LOCALES) {
      it(`${page} ${locale}: one main landmark with the page sections and every diagram`, async () => {
        const document = await render(page, locale);
        expect(document.querySelectorAll("main#main")).toHaveLength(1);
        expect(document.querySelectorAll("header, .u-header").length).toBeGreaterThan(0);
        expect(document.querySelector("main #today")).not.toBeNull();
        expect(document.querySelector("main #coming")).not.toBeNull();
        expect([...document.querySelectorAll("main section.dg")].map((s) => s.id)).toEqual([...IDS[page]]);
        // the footer stays outside the main landmark
        expect(document.querySelector("main footer")).toBeNull();
      });

      it(`${page} ${locale}: every diagram is a captioned figure whose content is text, not only shapes`, async () => {
        const document = await render(page, locale);
        for (const section of document.querySelectorAll("section.dg")) {
          const figure = section.querySelector("figure")!;
          const title = document.getElementById(figure.getAttribute("aria-labelledby")!)!;
          const lede = document.getElementById(figure.getAttribute("aria-describedby")!)!;
          expect(title.tagName).toBe("H3");
          expect(title.textContent!.trim().length).toBeGreaterThan(3);
          expect(lede.textContent!.trim().length).toBeGreaterThan(10);
          expect(figure.querySelector("figcaption")).toBe(figure.firstElementChild);
          // shapes are hidden from assistive technology; the readable text outside them carries the content
          for (const svg of figure.querySelectorAll("svg")) expect(svg.closest("[aria-hidden='true']")).not.toBeNull();
          const readable = [...figure.querySelectorAll(".dg__body li, .dg__body h4, .dg__body p, .dg__body dt, .dg__body dd")]
            .filter((el) => !el.closest("[aria-hidden='true']"))
            .map((el) => el.textContent!.trim())
            .filter(Boolean);
          expect(readable.length).toBeGreaterThanOrEqual(4);
        }
      });

      it(`${page} ${locale}: the static markup is the final state, controls wait for the script`, async () => {
        const document = await render(page, locale);
        for (const section of document.querySelectorAll("section.dg")) {
          // nothing is hidden or armed in the server markup: without JS or with reduced motion this is what shows
          expect(section.classList.contains("is-armed")).toBe(false);
          for (const el of section.querySelectorAll("[style]")) expect(el.getAttribute("style")).not.toMatch(/opacity\s*:\s*0|scale\(0/);
          for (const button of section.querySelectorAll("button")) {
            expect(button.hasAttribute("hidden")).toBe(true);
            expect(button.getAttribute("type")).toBe("button");
            expect(button.textContent!.trim().length).toBeGreaterThan(0);
          }
        }
      });
    }
  }

  it("the looping Living Course diagram has a pause button with both labels; one-shot diagrams have replay", async () => {
    for (const locale of LOCALES) {
      const document = await render("content-cost", locale);
      const pause = document.querySelector("#dg-living-loop [data-dg-control='pause']")!;
      expect(pause.getAttribute("data-pause")).toBe(costDiagrams(locale).controls.pause);
      expect(pause.getAttribute("data-play")).toBe(costDiagrams(locale).controls.play);
      expect(document.querySelectorAll("[data-dg-control='replay']")).toHaveLength(3);
    }
  });

  it("labels the evidence on each diagram: one run, an outside figure, shipped, coming, an illustration", async () => {
    const cost = await render("content-cost", "en");
    expect(cost.querySelector("#dg-pipeline .dg__badge--run")!.textContent).toBe("One measured run");
    expect(cost.querySelector("#dg-benchmark .dg__badge--outside")!.textContent).toContain("2010");
    expect(cost.querySelector("#dg-living-loop .u-status--available")).not.toBeNull();
    // variants are drafted in the builder (CV-4); only routing learners to them is coming (CV-5)
    expect(cost.querySelector("#dg-audiences .u-status--available")!.textContent).toBe("Available, routing coming");
    expect(cost.querySelector("#dg-pipeline")!.textContent).toContain("6 min 57 s");
    expect(cost.querySelector("#dg-pipeline")!.textContent).toContain("USD 0.28");
    // nothing of ULAMS is drawn on the benchmark scale
    expect(cost.querySelector("#dg-benchmark")!.textContent).not.toMatch(/0\.28|6 min|ULAMS/);

    const reporting = await render("reporting", "pl");
    expect(reporting.querySelector("#dg-dashboard .dg__badge--illustration")!.textContent).toBe(reportingDiagrams("pl").dashboard.badge);
    expect(reporting.querySelectorAll("#dg-dataflow .is-coming .u-status--coming")).toHaveLength(2);
    expect([...reporting.querySelectorAll("#dg-dataflow .u-status--coming")].every((el) => el.textContent === "Wkrótce")).toBe(true);
    expect(reporting.querySelector("#dg-roles [data-dg-control]")).toBeNull();
    expect(reporting.querySelector("#dg-roles")!.hasAttribute("data-dg")).toBe(false);
  });
});
