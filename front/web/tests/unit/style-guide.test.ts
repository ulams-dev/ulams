import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { JSDOM } from "jsdom";
import { beforeAll, describe, expect, it } from "vitest";
import { catalogueJson } from "@ulams/ui/registry";
import { validateDocument } from "@ulams/ui/render-core";
import { THEME_PRESETS, TENANT_THEMES } from "@ulams/ui/theme/presets.ts";
import {
  BRAND_CURRENT,
  CATEGORIES,
  COLOUR_TOKENS,
  GUARD_ACCENTS,
  STYLE_GUIDE_ID,
  TYPE_STEPS,
  brandExample,
  catalogueGroups,
  componentDocs,
  cssValue,
  fontName,
  guardExamples,
  liveExamples,
  platformTokens,
  presetCards,
  styleGuideCopy,
  styleGuidePage,
  tokenModel,
} from "../../src/lib/style-guide.ts";
import { LOCALES, publicPagePath, type Locale } from "../../src/i18n/locales.ts";

const shape = (value: unknown): unknown => {
  if (Array.isArray(value)) return value.map(shape);
  if (value && typeof value === "object") return Object.fromEntries(Object.entries(value).map(([k, v]) => [k, shape(v)]));
  return typeof value;
};
const strings = (value: unknown): string[] =>
  typeof value === "string" ? [value] : Array.isArray(value) ? value.flatMap(strings) : value && typeof value === "object" ? Object.values(value).flatMap(strings) : [];
const pageFor = (locale: Locale) => {
  const path = publicPagePath(locale, "style-guide");
  return styleGuidePage(locale, new Request(`https://ulams.app${path}`), new URL(`https://ulams.app${path}`))!;
};

describe("style guide: tokens come from the stylesheets", () => {
  it("reads the platform theme's colours, radii, spacing and fonts", () => {
    const tokens = platformTokens();
    expect(tokens["--ulams-color-primary"]).toBe("#0f2b46");
    expect(tokens["--ulams-color-accent"]).toBe("#b44a1f");
    expect(tokens["--ulams-radius-card"]).toBe("12px");
    // a base value that the platform theme does not override
    expect(tokens["--ulams-block-gap"]).toBe("22px");
    const model = tokenModel();
    expect(model.colours.map((c) => c.key)).toEqual([...COLOUR_TOKENS]);
    for (const colour of model.colours) expect(colour.value, colour.name).toMatch(/^#[0-9a-f]{3,6}$/i);
    expect(model.colours.find((c) => c.key === "text")!.onPage).toBeGreaterThanOrEqual(4.5);
    expect(model.fonts.map((f) => f.family)).toEqual(["Libre Baskerville", "Plus Jakarta Sans", "JetBrains Mono"]);
    for (const item of [...model.radii, ...model.space]) expect(item.value, item.name).not.toBe("");
  });

  it("takes each type size from the rule the platform pages use", () => {
    expect(cssValue(".u-h2", "font-size")).toBe("clamp(2rem, 3.5vw, 3rem)");
    expect(cssValue(".u-lead", "font-size")).toBe("clamp(1rem, 0.95rem + 0.3vw, 1.125rem)");
    const model = tokenModel();
    expect(model.type.map((t) => t.key)).toEqual(TYPE_STEPS.map((t) => t.key));
    for (const step of model.type) expect(step.size, step.selector).toMatch(/rem|px|clamp/);
    expect(fontName('var(--font-jakarta), "Plus Jakarta Sans", Inter, system-ui')).toBe("Plus Jakarta Sans");
  });
});

describe("style guide: presets, guard and brand import come from the theme modules", () => {
  it("shows the six presets of the admin theme screen with their own colours and passing text contrast", () => {
    const cards = presetCards();
    expect(cards.map((c) => c.name)).toEqual(TENANT_THEMES);
    for (const card of cards) {
      expect(card.swatches.find((s) => s.role === "bg")!.value).toBe(THEME_PRESETS[card.name].bg);
      expect(card.text.pass && card.muted.pass, card.name).toBe(true);
    }
  });

  it("saves the preset's own accent and blocks the brand orange, with the nearest passing shade", () => {
    const [pass, fail] = guardExamples();
    expect(pass!.accent).toBe(GUARD_ACCENTS.pass);
    expect(pass!.blocked).toBe(false);
    expect(fail!.accent).toBe("#ff7a2e");
    expect(fail!.blocked).toBe(true);
    expect(fail!.checks.filter((c) => !c.pass).length).toBeGreaterThan(0);
    expect(fail!.suggestion).toMatch(/^#[0-9a-f]{6}$/);
  });

  it("proposes brand colours as a diff against the current settings", () => {
    const brand = brandExample();
    expect(brand.changes.map((c) => c.field)).toEqual(["theme.theme", "theme.accent"]);
    expect(brand.changes[0]!.from).toBe(BRAND_CURRENT.theme);
    expect(brand.changes[1]!.to).toBe("#1d6f42");
    expect(brand.checks.every((c) => c.pass)).toBe(true);
  });
});

describe("style guide: catalogue", () => {
  it("lists every registry component once, in its category, with its docs playground link", () => {
    const groups = catalogueGroups();
    expect(groups.map((g) => g.category)).toEqual([...CATEGORIES]);
    expect(groups.flatMap((g) => g.names).sort()).toEqual(Object.keys(catalogueJson()).sort());
    expect(componentDocs("FrameSlider")).toBe("https://ulams-dev.github.io/ulams/catalogue/frame-slider/");
  });

  it("renders live examples that are valid catalogue documents in every language", () => {
    for (const locale of LOCALES) {
      for (const node of liveExamples(locale)) expect(validateDocument(node, {}), `${locale} ${node.component}`).toEqual([]);
    }
  });
});

describe("style guide: copy and page", () => {
  it("has the same structure in every language and no empty text", () => {
    const en = shape(styleGuideCopy("en"));
    for (const locale of LOCALES) {
      expect(shape(styleGuideCopy(locale)), locale).toEqual(en);
      for (const text of strings(styleGuideCopy(locale))) expect(text.trim().length, locale).toBeGreaterThan(0);
    }
  });

  for (const locale of LOCALES) {
    it(`${locale}: the page is a valid, canonical document with one hero`, () => {
      const page = pageFor(locale);
      expect(validateDocument(page.doc, {})).toEqual([]);
      expect(page.canonical).toBe(`https://ulams.app${publicPagePath(locale, "style-guide")}`);
      expect(page.meta.title.length).toBeLessThanOrEqual(70);
      expect(page.meta.description.length).toBeLessThanOrEqual(160);
      expect(page.alternates.map((a) => a.hreflang)).toEqual(["en", "pl", "zh-Hans", "x-default"]);
    });
  }
});

let container: AstroContainer;
const components: Record<string, unknown> = {};
beforeAll(async () => {
  container = await AstroContainer.create();
  for (const name of ["StyleGuideBody", "StyleGuideSection"]) {
    // tsc has no types for .astro files (astro check covers them), so the component is imported by a plain string
    const path = `../../src/components/style-guide/${name}.astro`;
    components[name] = ((await import(/* @vite-ignore */ path)) as { default: unknown }).default;
  }
});
const dom = (html: string) => new JSDOM(`<!doctype html><html><body>${html}</body></html>`).window.document;

describe("style guide: rendered markup", () => {
  for (const locale of LOCALES) {
    it(`${locale}: the page has one main landmark, the token, catalogue, theme and white-label sections, and the live examples`, async () => {
      const document = dom(await container.renderToString(components.StyleGuideBody as never, { props: { shell: pageFor(locale), locale } }));
      expect(document.querySelectorAll("main#main")).toHaveLength(1);
      expect(document.querySelectorAll("h1")).toHaveLength(1);
      for (const id of ["tokens", "components", "themes", "white-label"]) expect(document.getElementById(id), id).not.toBeNull();
      expect(document.querySelectorAll("#tokens .sg-tokens li")).toHaveLength(COLOUR_TOKENS.length);
      expect(document.querySelectorAll(".sg-live__item")).toHaveLength(liveExamples(locale).length);
      expect(document.querySelectorAll("#components .sg-names a")).toHaveLength(Object.keys(catalogueJson()).length);
      expect(document.querySelectorAll(".sg-preset")).toHaveLength(6);
      expect(document.querySelectorAll(".sg-guard.is-blocked")).toHaveLength(1);
      expect(document.querySelectorAll(".sg-diff tbody tr")).toHaveLength(2);
      for (const link of document.querySelectorAll<HTMLAnchorElement>("main a[href^='https://ulams-dev.github.io']")) expect(link.getAttribute("data-umami-event")).toBe("docs_open");
    });

    it(`${locale}: the home section shows the presets, the guard, the brand diff and links to the full guide`, async () => {
      const document = dom(await container.renderToString(components.StyleGuideSection as never, { props: { locale } }));
      const section = document.getElementById(STYLE_GUIDE_ID)!;
      expect(section.querySelectorAll("h2")).toHaveLength(1);
      expect(section.querySelectorAll(".sg-preset")).toHaveLength(6);
      expect(section.querySelector(".sg-guard.is-blocked .sg-guard__suggest")).not.toBeNull();
      const link = section.querySelector<HTMLAnchorElement>(`a[href="${publicPagePath(locale, "style-guide")}"]`)!;
      expect(link.getAttribute("data-umami-event")).toBe("style_guide_open");
      // a colour that fails AA is only a swatch: no text is set in it
      for (const el of section.querySelectorAll<HTMLElement>("[style*='color:#ff7a2e']")) expect(el.classList.contains("sg-swatch")).toBe(true);
    });
  }
});
