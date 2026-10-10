// @vitest-environment node
import { describe, expect, it } from "vitest";
import type { UiNode } from "@ulams/ui/render-core";
import { validateDocument } from "@ulams/ui/render-core";
import { uiStrings } from "@ulams/ui/i18n";
import { landingDocFor, landingDocs, localizedDocs, withLanguages } from "../../src/lib/docs.ts";
import { applyComparisonStatus, applyLandingStatus } from "../../src/lib/landing-status.ts";
import { comparisonData, comparisonModel, comparisonTranslations, valueKind } from "../../src/lib/comparison.ts";
import { workflowsData, workflowsModel, workflowsTranslations } from "../../src/lib/workflows.ts";
import { DEMO_COPY } from "../../src/i18n/demos.ts";
import { DEFAULT_LOCALE, LOCALES, LOCALE_META, alternateLinks, canonicalUrl, languageLinks, publicOrigin } from "../../src/i18n/locales.ts";

const LANGS = ["pl", "zh"] as const;
const en = landingDocs.platform!;

/** Keys whose value is structure, not prose: ids, anchors, icons, statuses, bindings, numbers. They must be identical. */
const STRUCTURAL = new Set(["component", "theme", "id", "variant", "icon", "href", "kind", "mark", "status", "syncStatus", "$data", "window", "key"]);
/** Keys that hold code, file names, hosts, colours or example data: never translated. */
const VERBATIM = new Set(["code", "commands", "host", "value", "logo", "file", "citation", "questionCite", "brand"]);
/** Product and standard names that an English string mentions: the translation must still contain them. */
const NAMES = ["ulams", "Claude Code", "Claude", "Anthropic", "MCP", "H5P", "SCORM", "cmi5", "xAPI", "LTI 1.3", "OpenAPI", "Docker Compose", "Sylius", "Figma", "Stitch", "GitHub", "MIT", "TypeScript", "REST API", "@ulams/sdk"];

interface Problem {
  path: string;
  message: string;
}

function parallel(a: unknown, b: unknown, path: string, key: string, out: Problem[]): void {
  if (Array.isArray(a)) {
    if (!Array.isArray(b) || a.length !== b.length) return void out.push({ path, message: `array length ${(b as unknown[])?.length} != ${a.length}` });
    a.forEach((item, i) => parallel(item, b[i], `${path}[${i}]`, key, out));
    return;
  }
  if (a && typeof a === "object") {
    if (!b || typeof b !== "object" || Array.isArray(b)) return void out.push({ path, message: "not an object" });
    const ka = Object.keys(a as object);
    const kb = Object.keys(b as object);
    if (ka.join("|") !== kb.join("|")) out.push({ path, message: `keys ${kb.join(",")} != ${ka.join(",")}` });
    for (const k of ka) parallel((a as Record<string, unknown>)[k], (b as Record<string, unknown>)[k], `${path}.${k}`, k, out);
    return;
  }
  if (typeof a === "string") {
    if (typeof b !== "string" || b.trim() === "") return void out.push({ path, message: "missing text" });
    const bare = key.replace(/\[\]$/, "");
    if (STRUCTURAL.has(bare) || VERBATIM.has(bare)) {
      if (a !== b) out.push({ path, message: `must stay "${a}", is "${b}"` });
    }
    // an untranslated sentence (still English) is a missing translation
    if (!STRUCTURAL.has(bare) && !VERBATIM.has(bare) && a.length > 40 && a === b) out.push({ path, message: "still English" });
    for (const name of NAMES) if (a.includes(name) && !b.includes(name)) out.push({ path, message: `lost the name "${name}"` });
    return;
  }
  if (a !== b) out.push({ path, message: `${String(b)} != ${String(a)}` });
}

const diff = (a: unknown, b: unknown): Problem[] => {
  const out: Problem[] = [];
  parallel(a, b, "$", "", out);
  return out;
};

describe("translated landing documents keep the English structure", () => {
  it("has a Polish and a Chinese platform document", () => {
    expect(Object.keys(localizedDocs.platform ?? {}).sort()).toEqual(["pl", "zh"]);
  });

  for (const lang of LANGS) {
    const doc = localizedDocs.platform![lang]!;
    it(`${lang}: same components, order, ids, links and icons as English`, () => {
      expect(diff(en, doc)).toEqual([]);
    });
    it(`${lang}: is valid against the catalogue, with the translated data, in both display modes`, () => {
      for (const mode of ["final", "actual"] as const) {
        const demo = { title: "Demo", theme: "coffee", facts: [], primary: { label: "Open", href: "/learn/1" }, secondary: { label: "Admin", href: "/admin" } };
        const data = { demos: [demo], comparison: comparisonModel(applyComparisonStatus(comparisonData, mode), lang), workflows: workflowsModel(mode, lang) };
        expect(validateDocument(applyLandingStatus(doc, mode) as UiNode, data), `${lang} ${mode}`).toEqual([]);
      }
    });
    it(`${lang}: declares its language and titles the page`, () => {
      expect(doc.props?.lang).toBe(LOCALE_META[lang].lang);
      expect(String(doc.props?.title)).toContain("ulams");
      expect(String(doc.props?.description).length).toBeLessThanOrEqual(160);
    });
    it(`${lang}: keeps six header links at most, the same anchors, and the switcher is not one of them`, () => {
      const header = doc.children!.find((c) => c.component === "SiteHeader")!;
      const links = header.props!.links as Array<{ href: string }>;
      const enHeader = en.children!.find((c) => c.component === "SiteHeader")!;
      expect(links.length).toBeLessThanOrEqual(6);
      expect(links.map((l) => l.href)).toEqual((enHeader.props!.links as Array<{ href: string }>).map((l) => l.href));
      expect(header.props).not.toHaveProperty("languages");
    });
    it(`${lang}: the final display mode leaves no roadmap wording`, () => {
      const text = JSON.stringify(applyLandingStatus(doc, "final"));
      expect(text).not.toMatch(/"status":"(coming|preview)"/);
      expect(text).not.toContain('"final"');
      expect(text).not.toMatch(lang === "pl" ? /Wkrótce|w planach|mapie drogowej/i : /即将推出|路线图/);
    });
    it(`${lang}: the actual display mode keeps the roadmap labels`, () => {
      expect(JSON.stringify(applyLandingStatus(doc, "actual"))).toContain('"status":"coming"');
    });
  }

  it("falls back to English for a theme without a translation", () => {
    expect(landingDocFor("coffee", "pl")).toBe(landingDocs.coffee);
    expect(landingDocFor("platform", "en")).toBe(en);
    expect(landingDocFor("platform", "zh")).toBe(localizedDocs.platform!.zh);
  });
});

describe("translated workflows keep the English transcript", () => {
  for (const lang of LANGS) {
    it(`${lang}: same tabs, commands, tool calls and output`, () => {
      const t = workflowsTranslations[lang]!;
      expect(diff(workflowsData, t).filter((p) => !/\.(description|label|title|caption|text|result|args)$/.test(p.path))).toEqual([]);
      workflowsData.tabs.forEach((tab, i) => {
        const tt = t.tabs[i]!;
        expect(tt.key).toBe(tab.key);
        (tab.lines as Array<{ kind: string; text: string; args?: string }>).forEach((line, j) => {
          const l = (tt.lines as Array<{ kind: string; text: string; args?: string }>)[j]!;
          expect(l.kind).toBe(line.kind);
          // commands, shell output and tool names are not prose
          if (["cmd", "cont", "out", "tool"].includes(line.kind)) expect(l.text, `${tab.key} #${j}`).toBe(line.text);
          if (line.kind === "tool") expect(l.args).toBe(line.args);
        });
      });
    });
    it(`${lang}: the status switch works on the translated tabs`, () => {
      expect(JSON.stringify(workflowsModel("final", lang))).not.toContain('"status"');
      expect(workflowsModel("actual", lang).tabs.map((t) => t.status)).toEqual(workflowsData.tabs.map((t) => t.status));
    });
  }
});

describe("translated comparison", () => {
  for (const lang of LANGS) {
    const tr = comparisonTranslations[lang]!;
    it(`${lang}: translates every group, section, row, product descriptor and value`, () => {
      expect(Object.keys(tr.groups).sort()).toEqual(comparisonData.groups.map((g) => g.key).sort());
      expect(Object.keys(tr.sections).sort()).toEqual(comparisonData.sections.map((s) => s.key).sort());
      expect(Object.keys(tr.rows).sort()).toEqual(comparisonData.rows.map((r) => r.key).sort());
      expect(Object.keys(tr.systems).sort()).toEqual(comparisonData.systems.map((s) => s.key).sort());
      const values = new Set(comparisonData.systems.flatMap((s) => Object.values(s.cells).map((c) => c.value)));
      for (const v of values) expect(tr.values[v], `value "${v}"`).toBeTruthy();
      for (const row of comparisonData.rows) expect(Boolean(tr.rows[row.key]?.help), row.key).toBe(Boolean(row.help));
    });
    it(`${lang}: keeps product names, sources, notes and the kind of every cell`, () => {
      const model = comparisonModel(comparisonData, lang);
      const english = comparisonModel(comparisonData, "en");
      model.groups.forEach((g, gi) => {
        const eg = english.groups[gi]!;
        expect(g.columns.map((c) => c.label)).toEqual(eg.columns.map((c) => c.label));
        expect(g.sections.map((s) => s.rows.length)).toEqual(eg.sections.map((s) => s.rows.length));
        g.sections.forEach((s, si) =>
          s.rows.forEach((r, ri) =>
            r.cells.forEach((cell, ci) => {
              const ecell = eg.sections[si]!.rows[ri]!.cells[ci]!;
              expect((cell as { note?: string }).note).toBe((ecell as { note?: string }).note);
              expect((cell as { kind?: string }).kind).toBe(valueKind(ecell.value));
            })
          )
        );
      });
      expect(model.sources.map((s) => s.href)).toEqual(english.sources.map((s) => s.href));
    });
    it(`${lang}: the final switch still lifts ulams Coming and Partial cells to Yes, before translating`, () => {
      const model = comparisonModel(applyComparisonStatus(comparisonData, "final"), lang);
      const ulams = model.groups[0]!.columns.findIndex((c) => c.highlight);
      const values = model.groups.flatMap((g) => g.sections.flatMap((s) => s.rows.map((r) => r.cells[ulams]!.value)));
      expect(values).not.toContain(tr.values.Coming);
      expect(values).not.toContain(tr.values.Partial);
      const actual = comparisonModel(comparisonData, lang);
      expect(actual.groups[1]!.sections.flatMap((s) => s.rows.map((r) => r.cells[0]!.value))).toContain(tr.values.Coming);
    });
  }
  it("English is untouched", () => {
    const model = comparisonModel(comparisonData);
    expect(model.groups[0]!.label).toBe("Open source & creator platforms");
    expect(JSON.stringify(model)).not.toContain('"kind"');
  });
});

describe("language switcher", () => {
  it("lists the three languages with the current one marked", () => {
    const links = languageLinks("pl");
    expect(links.map((l) => [l.label, l.href, l.current])).toEqual([
      ["EN", "/", false],
      ["PL", "/pl/", true],
      ["中文", "/zh/", false],
    ]);
    expect(links.map((l) => l.code)).toEqual(["en", "pl", "zh-Hans"]);
  });

  it("is added to the header as a control of its own and does not change the link list", () => {
    const doc = withLanguages(localizedDocs.platform!.zh!, languageLinks("zh"), "/zh/");
    const header = doc.children!.find((c) => c.component === "SiteHeader")!;
    expect(header.props!.homeHref).toBe("/zh/");
    expect((header.props!.languages as unknown[]).length).toBe(3);
    expect((header.props!.links as unknown[]).length).toBe(6);
    expect(JSON.stringify(validateDocument(doc, { demos: [] }).filter((p) => p.component === "SiteHeader"))).toBe("[]");
  });
});

describe("hreflang and canonical", () => {
  const origin = "https://app.ulams.app";
  it("lists every language and x-default", () => {
    expect(alternateLinks(origin)).toEqual([
      { hreflang: "en", href: "https://app.ulams.app/" },
      { hreflang: "pl", href: "https://app.ulams.app/pl/" },
      { hreflang: "zh-Hans", href: "https://app.ulams.app/zh/" },
      { hreflang: "x-default", href: "https://app.ulams.app/" },
    ]);
  });
  it("has one canonical URL per language", () => {
    expect(LOCALES.map((l) => canonicalUrl(origin, l))).toEqual(["https://app.ulams.app/", "https://app.ulams.app/pl/", "https://app.ulams.app/zh/"]);
    expect(DEFAULT_LOCALE).toBe("en");
  });
  it("uses the host and scheme the visitor used behind the proxy", () => {
    const url = new URL("http://127.0.0.1:4321/pl/");
    expect(publicOrigin(new Headers({ host: "127.0.0.1:4321" }), url)).toBe("http://127.0.0.1:4321");
    expect(publicOrigin(new Headers({ "x-forwarded-host": "app.ulams.app", "x-forwarded-proto": "https" }), url)).toBe("https://app.ulams.app");
  });
});

describe("interface strings and demo copy", () => {
  it("every language fills every key", () => {
    const keys = Object.keys(uiStrings("en")).sort();
    for (const lang of LANGS) {
      expect(Object.keys(uiStrings(lang)).sort()).toEqual(keys);
      expect(Object.keys(uiStrings(lang).sr).sort()).toEqual(Object.keys(uiStrings("en").sr).sort());
      for (const [k, v] of Object.entries(uiStrings(lang))) if (typeof v === "string") expect(v.length, k).toBeGreaterThan(0);
    }
  });
  it("maps zh-Hans to Chinese and anything unknown to English", () => {
    expect(uiStrings("zh-Hans").coming).toBe(uiStrings("zh").coming);
    expect(uiStrings("fr").coming).toBe("Coming");
    expect(uiStrings(undefined).coming).toBe("Coming");
  });
  it("describes the same six demos in every language", () => {
    for (const lang of LANGS) expect(Object.keys(DEMO_COPY[lang].style).sort()).toEqual(["coffee", "gravity", "nightsky", "oncall", "poland", "ulam"]);
  });
});
