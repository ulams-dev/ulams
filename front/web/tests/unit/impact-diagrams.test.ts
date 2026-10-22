import { describe, expect, it } from "vitest";
import { IMPACT_PAGES, impactCopy, impactPage } from "../../src/lib/impact-pages.ts";
import { costDiagrams, planImpactBody, reportingDiagrams, RUN_SECONDS, type DiagramName } from "../../src/lib/impact-diagrams.ts";
import { LOCALES, publicPagePath, type Locale } from "../../src/i18n/locales.ts";

const doc = (page: (typeof IMPACT_PAGES)[number], locale: Locale) => {
  const path = publicPagePath(locale, page);
  return impactPage(page, locale, new Request(`https://ulams.app${path}`), new URL(`https://ulams.app${path}`))!.doc;
};

/** Shape of a copy object: keys, array lengths and number values, with the strings left out. */
const shape = (value: unknown): unknown => {
  if (Array.isArray(value)) return value.map(shape);
  if (value && typeof value === "object") return Object.fromEntries(Object.entries(value).map(([k, v]) => [k, shape(v)]));
  return typeof value === "number" ? value : typeof value;
};
const strings = (value: unknown): string[] =>
  typeof value === "string" ? [value] : Array.isArray(value) ? value.flatMap(strings) : value && typeof value === "object" ? Object.values(value).flatMap(strings) : [];

describe("impact-page diagrams: copy", () => {
  it("has the same structure in every language, with no empty text", () => {
    for (const locale of LOCALES) {
      expect(shape(costDiagrams(locale))).toEqual(shape(costDiagrams("en")));
      expect(shape(reportingDiagrams(locale))).toEqual(shape(reportingDiagrams("en")));
      for (const text of [...strings(costDiagrams(locale)), ...strings(reportingDiagrams(locale))]) expect(text.trim().length).toBeGreaterThan(0);
    }
  });

  it("translates the text instead of repeating the English", () => {
    for (const locale of ["pl", "zh"] as const) {
      expect(costDiagrams(locale).pipeline.title).not.toBe(costDiagrams("en").pipeline.title);
      expect(reportingDiagrams(locale).dashboard.badge).not.toBe(reportingDiagrams("en").dashboard.badge);
      expect(costDiagrams(locale).controls.pause).not.toBe(costDiagrams("en").controls.pause);
    }
  });

  it("the pipeline states exactly the figures of the measured run in the page copy, labelled as one run", () => {
    expect(RUN_SECONDS).toEqual([24, 417, 23]);
    for (const locale of LOCALES) {
      const run = impactCopy("content-cost", locale).run!;
      const pipe = costDiagrams(locale).pipeline;
      expect(pipe.timing.map((t) => t.seconds)).toEqual([...RUN_SECONDS]);
      const figures = strings(pipe).join(" ").match(/\d+(?:[.,]\d+)?/g)!;
      // every number on the diagram is one of the run's figures (passage 7 is the schematic example)
      for (const n of figures) expect(["13", "24", "6", "57", "18", "35", "23", "27", "0.28", "0,28", "7", "44"]).toContain(n);
      // the stated total is the sum of the stage times, as in the page copy (7 min 44 s), never "7.5 minutes"
      expect(RUN_SECONDS.reduce((a, b) => a + b, 0)).toBe(7 * 60 + 44);
      expect(pipe.timingTitle).toMatch(/7\s*(min|分)\s*44/);
      expect(run).toMatch(/7\s*(min|分)\s*44/);
      expect(strings(pipe).join(" ")).not.toMatch(/7[.,]5/);
      for (const n of ["13", "24", "57", "18", "35", "23", "27"]) expect(run).toContain(n);
      expect(pipe.badge.length).toBeGreaterThan(0);
    }
    expect(costDiagrams("en").pipeline.badge).toBe("One measured run");
  });

  it("never states a ratio or a speed-up anywhere on the diagrams", () => {
    for (const locale of LOCALES) {
      const all = [...strings(costDiagrams(locale)), ...strings(reportingDiagrams(locale))].join("\n");
      expect(all).not.toMatch(/\d\s*(x|×|times|razy|倍)/i);
      expect(all).not.toMatch(/faster|szybciej|更快|\bsaves?\b|oszczędz|节省/i);
    }
  });

  it("the benchmark keeps the cited Chapman range and says it is not comparable", () => {
    for (const locale of LOCALES) {
      const bench = costDiagrams(locale).benchmark;
      expect(bench.low.value).toMatch(/^49\b/);
      expect(bench.high.value).toMatch(/^716\b/);
      expect(bench.summary).toMatch(/49/);
      expect(bench.summary).toMatch(/716/);
      expect(bench.badge).toMatch(/2010/);
      expect(bench.noteTitle.length).toBeGreaterThan(0);
    }
  });

  it("the dashboard is labelled an illustration and carries no numbers", () => {
    for (const locale of LOCALES) {
      const dash = reportingDiagrams(locale).dashboard;
      const text = strings(dash).filter((s) => s !== dash.hidden.text).join(" ");
      expect(text).not.toMatch(/\d/);
      // the one number: the small-group threshold the page already states
      expect(dash.hidden.text).toContain("10");
      expect(impactCopy("reporting", locale).today[0]!.text).toContain("10");
    }
    expect(reportingDiagrams("en").dashboard.badge).toBe("Illustration, not live data");
  });

  it("planned features stay planned: learner routing to a variant, the report builder and the BI connector", () => {
    for (const locale of LOCALES) {
      const coming = impactCopy("reporting", locale).coming.map((i) => i.title);
      const flow = reportingDiagrams(locale).flow.columns.flatMap((c) => c.coming ?? []);
      expect(flow).toEqual([coming[0], coming[1]]);
      expect(costDiagrams(locale).audiences.badge).toBe(["Available, routing coming", "Dostępne, kierowanie wkrótce", "已可用,分配即将推出"][LOCALES.indexOf(locale)]);
    }
  });
});

describe("impact-page diagrams: placement", () => {
  const expected: Record<string, DiagramName[]> = {
    "content-cost": ["benchmark", "pipeline", "living-loop", "audiences"],
    reporting: ["dashboard", "dataflow", "roles"],
  };

  for (const page of IMPACT_PAGES) {
    for (const locale of LOCALES) {
      it(`${page} ${locale}: every diagram finds the section it explains`, () => {
        const plan = planImpactBody(doc(page, locale), page, locale)!;
        expect(plan).not.toBeNull();
        expect(plan.unanchored).toEqual([]);
        expect(plan.main.filter((p) => p.kind === "diagram").map((p) => (p.kind === "diagram" ? p.name : ""))).toEqual(expected[page]);
        expect(plan.before.map((n) => n.component)).toEqual(["SiteHeader"]);
        expect(plan.after.map((n) => n.component)).toEqual(["SiteFooter"]);
        // every node of the main landmark is still rendered, in order
        const nodes = plan.main.flatMap((p) => (p.kind === "node" ? [p.node] : []));
        const main = doc(page, locale).children!.find((n) => n.component === "Main")!;
        expect(nodes).toEqual(main.children);
      });
    }
  }

  it("places a diagram whose section is missing before the closing call to action", () => {
    const base = doc("content-cost", "en");
    const main = base.children!.find((n) => n.component === "Main")!;
    const trimmed = { ...base, children: base.children!.map((n) => (n === main ? { ...main, children: main.children!.filter((c) => c.props?.id !== "coming") } : n)) };
    const plan = planImpactBody(trimmed, "content-cost", "en")!;
    expect(plan.unanchored).toEqual(["audiences"]);
    const names = plan.main.map((p) => (p.kind === "diagram" ? p.name : p.node.component));
    expect(names.indexOf("audiences")).toBe(names.indexOf("CtaBand") - 1);
  });

  it("leaves a document without a main landmark alone", () => {
    expect(planImpactBody({ component: "Stack" }, "reporting", "en")).toBeNull();
  });
});
