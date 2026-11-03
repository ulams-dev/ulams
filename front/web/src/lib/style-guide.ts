/**
 * The style guide of the public product site: the home-page section and the /style-guide/ page.
 *
 * Nothing here is drawn by hand. The design tokens are read from the platform theme stylesheet itself
 * (`@ulams/ui/styles/themes/platform.css` and `base.css`), the theme presets from the one preset list the admin theme
 * screen uses (`@ulams/ui/theme/presets`, #378), the contrast verdicts from the same report that blocks a failing
 * accent in the admin (`@ulams/ui/theme/report`), the brand-import diff from the importer (`@ulams/ui/theme/brand-import`,
 * #297) and the component list from the catalogue registry (`@ulams/ui/registry`). Only the words around them are copy.
 */
import platformCss from "@ulams/ui/styles/themes/platform.css?raw";
import baseCss from "@ulams/ui/styles/base.css?raw";
import { catalogueJson } from "@ulams/ui/registry";
import type { UiNode } from "@ulams/ui/render-core";
import { contrast, parseHex } from "@ulams/ui/theme/contrast.ts";
import { THEME_PRESETS, TENANT_THEMES, type TenantThemeName } from "@ulams/ui/theme/presets.ts";
import { contrastReport, type ReportCheck } from "@ulams/ui/theme/report.ts";
import { proposeBrandTheme, themeDiff, type ThemeChange } from "@ulams/ui/theme/brand-import.ts";
import type { Locale } from "../i18n/locales.ts";
import { productShell } from "./product-pages.ts";

export const STYLE_GUIDE_ID = "style-guide";
export const DOCS = "https://ulams-dev.github.io/ulams/";
export const CATALOGUE_DOCS = `${DOCS}catalogue/`;
export const CATALOGUE_REFERENCE = `${DOCS}reference/ui-catalogue/`;
export const THEME_DOCS = `${DOCS}operators/theme/`;
export const TENANCY_DOCS = `${DOCS}developers/tenancy/`;
export const COMPONENT_DOCS = `${DOCS}extending/ui-components/`;

/* ---------- Tokens, read from the stylesheets ---------- */

interface CssRule {
  selector: string;
  body: string;
}

/** Flat rules of a stylesheet (nested at-rule blocks are skipped: the tokens live at the top level). */
function rules(css: string): CssRule[] {
  const out: CssRule[] = [];
  const clean = css.replace(/\/\*[\s\S]*?\*\//g, "");
  for (const m of clean.matchAll(/([^{}]+)\{([^{}]*)\}/g)) out.push({ selector: m[1]!.trim().replace(/\s+/g, " "), body: m[2]! });
  return out;
}

function declarations(body: string): Record<string, string> {
  const out: Record<string, string> = {};
  for (const part of body.split(";")) {
    const i = part.indexOf(":");
    if (i > 0) out[part.slice(0, i).trim()] = part.slice(i + 1).trim();
  }
  return out;
}

const PLATFORM_RULES = rules(platformCss);
const BASE_RULES = rules(baseCss);

/** Every `--ulams-*` custom property of the platform theme: the base `:root` values overridden by `[data-theme="platform"]`. */
export function platformTokens(): Record<string, string> {
  const root = BASE_RULES.filter((r) => r.selector === ":root").map((r) => declarations(r.body));
  const theme = PLATFORM_RULES.filter((r) => r.selector === '[data-theme="platform"]').map((r) => declarations(r.body));
  const merged = Object.assign({}, ...root, ...theme) as Record<string, string>;
  return Object.fromEntries(Object.entries(merged).filter(([name]) => name.startsWith("--ulams-")));
}

/** A declaration of a selector as the platform pages get it: the last platform rule wins over the base rule. */
export function cssValue(selector: string, property: string): string | null {
  let value: string | null = null;
  for (const rule of BASE_RULES) if (rule.selector === selector) value = declarations(rule.body)[property] ?? value;
  for (const rule of PLATFORM_RULES) if (rule.selector === `[data-theme="platform"] ${selector}`) value = declarations(rule.body)[property] ?? value;
  return value;
}

export const COLOUR_TOKENS = ["primary", "accent", "header", "text", "muted", "bg", "surface", "card-bg", "border", "accent-bg", "positive", "warn", "error", "info"] as const;
export type ColourToken = (typeof COLOUR_TOKENS)[number];
export const RADIUS_TOKENS = ["radius-button", "radius-input", "radius", "radius-card", "radius-modal"] as const;
export const SPACE_TOKENS = ["gutter", "block-gap", "section-space", "container"] as const;
export const FONT_TOKENS = ["font-family", "font-family-body", "font-mono"] as const;
/** The type scale, from the largest: each step names the selector whose size the platform pages use. */
export const TYPE_STEPS = [
  { key: "display", selector: ".u-hero--product .u-hero__title", font: "font-family" },
  { key: "h2", selector: ".u-h2", font: "font-family" },
  { key: "lead", selector: ".u-lead", font: "font-family-body" },
  { key: "body", selector: "body", font: "font-family-body" },
  { key: "eyebrow", selector: ".u-eyebrow", font: "font-mono" },
] as const;
export type TypeStep = (typeof TYPE_STEPS)[number]["key"];

/** The first named family of a font stack (`var(--font-baskerville), "Libre Baskerville", Georgia, serif` → Libre Baskerville). */
export const fontName = (stack: string): string => /"([^"]+)"/.exec(stack)?.[1] ?? stack.split(",").map((s) => s.trim()).find((s) => !s.startsWith("var("))!;

const ratio = (a: string, b: string): number | null => {
  const x = parseHex(a);
  const y = parseHex(b);
  return x && y ? Math.round(contrast(x, y) * 100) / 100 : null;
};

export interface TokenModel {
  colours: Array<{ key: ColourToken; name: string; value: string; onPage: number | null }>;
  radii: Array<{ name: string; value: string }>;
  space: Array<{ name: string; value: string }>;
  fonts: Array<{ name: string; family: string }>;
  type: Array<{ key: TypeStep; selector: string; size: string; family: string }>;
}

export function tokenModel(): TokenModel {
  const tokens = platformTokens();
  const value = (name: string) => tokens[`--ulams-${name}`] ?? "";
  const bg = value("color-bg");
  return {
    colours: COLOUR_TOKENS.map((key) => ({ key, name: `--ulams-color-${key}`, value: value(`color-${key}`), onPage: ratio(value(`color-${key}`), bg) })),
    radii: RADIUS_TOKENS.map((name) => ({ name: `--ulams-${name}`, value: value(name) })),
    space: SPACE_TOKENS.map((name) => ({ name: `--ulams-${name}`, value: value(name) })),
    fonts: FONT_TOKENS.map((name) => ({ name: `--ulams-${name}`, family: fontName(value(name)) })),
    type: TYPE_STEPS.map((step) => ({ key: step.key, selector: step.selector, size: cssValue(step.selector, "font-size") ?? "", family: `var(--ulams-${step.font})` })),
  };
}

/* ---------- Presets, the contrast guard and the brand import ---------- */

export interface PresetCard {
  name: TenantThemeName;
  label: string;
  dark: boolean;
  swatches: Array<{ role: "bg" | "card" | "text" | "primary" | "accent"; value: string }>;
  /** The report's own rows for the preset: body and secondary text on the page background. */
  text: ReportCheck;
  muted: ReportCheck;
}

export function presetCards(): PresetCard[] {
  return TENANT_THEMES.map((name) => {
    const preset = THEME_PRESETS[name];
    const report = contrastReport(name, preset.primary);
    return {
      name,
      label: preset.label,
      dark: preset.dark,
      swatches: (["bg", "card", "text", "primary", "accent"] as const).map((role) => ({ role, value: preset[role] })),
      text: report.checks.find((c) => c.id === "preset-text")!,
      muted: report.checks.find((c) => c.id === "preset-muted")!,
    };
  });
}

/** The guard of the admin theme screen, on the Coffee Atlas preset: the preset's own accent saves, the ULAMS brand orange is blocked. */
export const GUARD_THEME: TenantThemeName = "coffee";
export const GUARD_ACCENTS = { pass: THEME_PRESETS.coffee.primary, fail: "#ff7a2e" } as const;

export function guardExamples() {
  return (["pass", "fail"] as const).map((kind) => {
    const accent = GUARD_ACCENTS[kind];
    const report = contrastReport(GUARD_THEME, accent);
    return { kind, accent, blocked: report.failures.length > 0, checks: report.checks.filter((c) => c.blocking), suggestion: report.suggestion };
  });
}

/** Pasted brand colours, and the academy's settings before the import. */
export const BRAND_INPUT = '{ "primary": "#1d6f42", "background": "#ffffff", "text": "#1b1b1b" }';
export const BRAND_CURRENT = { theme: "coffee", accent: THEME_PRESETS.coffee.accent } as const;

export function brandExample(): { input: string; changes: ThemeChange[]; checks: Array<{ ratio: number; required: number; pass: boolean }>; rendered: string } {
  const proposal = proposeBrandTheme(BRAND_INPUT)!;
  return {
    input: BRAND_INPUT,
    changes: themeDiff(proposal, BRAND_CURRENT),
    checks: proposal.checks.map(({ ratio: r, required, pass }) => ({ ratio: r, required, pass })),
    rendered: proposal.renderedAccent,
  };
}

/* ---------- The component catalogue ---------- */

export const CATEGORIES = ["structure", "section", "course", "learning"] as const;
export type Category = (typeof CATEGORIES)[number];

/** Docs playground page of a component (front/docs-site generator 45-playground: kebab-case slug). */
export const componentDocs = (name: string): string => `${CATALOGUE_DOCS}${name.replace(/([a-z0-9])([A-Z])/g, "$1-$2").toLowerCase()}/`;

export function catalogueGroups(): Array<{ category: Category; names: string[] }> {
  const catalogue = catalogueJson();
  return CATEGORIES.map((category) => ({ category, names: Object.keys(catalogue).filter((name) => catalogue[name]!.category === category) }));
}

/** Components the home section names as examples of the page catalogue. */
export const HOME_COMPONENTS = ["Hero", "CtaBand", "Showcase", "FrameSlider", "FeatureList", "Faq", "ComparisonTable", "WorkflowShowcase"] as const;

/* ---------- Copy ---------- */

interface Item { title: string; text: string }

export interface StyleGuideCopy {
  title: string;
  description: string;
  eyebrow: string;
  heading: string;
  subtitle: string;
  body: string;
  primary: string;
  secondary: string;
  home: { eyebrow: string; title: string; lede: string; tokens: string; components: string; cta: string; docs: string };
  tokens: {
    title: string;
    intro: string;
    colours: string;
    colourNames: Record<ColourToken, string>;
    /** "{ratio}:1 on the page". */
    onPage: string;
    type: string;
    typeIntro: string;
    specimens: Record<TypeStep, string>;
    typeNames: Record<TypeStep, string>;
    radii: string;
    space: string;
    fonts: string;
  };
  components: { title: string; intro: string; categories: Record<Category, string>; count: string; live: string; liveIntro: string; reference: string; playground: string };
  examples: {
    aiLabel: string;
    citations: string[];
    callout: Item;
    chips: { eyebrow: string; title: string; items: Array<{ label: string; status: "available" | "coming" }> };
    steps: { title: string; items: Item[] };
  };
  presets: { title: string; intro: string; light: string; dark: string; text: string; muted: string; moods: Record<TenantThemeName, string>; roles: Record<PresetCard["swatches"][number]["role"], string> };
  guard: {
    title: string;
    intro: string;
    accent: string;
    saved: string;
    blocked: string;
    /** "Nearest shade that passes: {hex}". */
    suggestion: string;
    checks: Record<"accent-text-page" | "accent-text-card" | "accent-ui-page" | "on-accent", string>;
    pass: string;
    fail: string;
    needs: string;
  };
  brand: { title: string; intro: string; input: string; field: Record<ThemeChange["field"], string>; before: string; after: string; checks: string[]; note: string; diffLabel: string };
  whiteLabel: { title: string; intro: string; items: Item[] };
  docs: { title: string; theme: string; tenancy: string; reference: string; playground: string; extend: string };
}

const EN: StyleGuideCopy = {
  title: "ulams — Style guide and design system",
  description: "The real design tokens, components and theme presets behind ULAMS sites, with the WCAG 2.2 AA contrast guard and brand import as a diff.",
  eyebrow: "Style guide",
  heading: "One design system, a look for every academy",
  subtitle: "The tokens, components and theme presets every ULAMS site is built from.",
  body: "Everything on this page is read from the code: the stylesheet tokens, the preset list of the admin theme screen, the contrast report that refuses a failing accent and the component catalogue that page documents are checked against.",
  primary: "See the tokens",
  secondary: "Component playground",
  home: {
    eyebrow: "Design system and white label",
    title: "A look per academy, never at the cost of contrast",
    lede: "Every academy picks a theme preset, an accent and a logo. The admin checks the accent against WCAG 2.2 AA before saving, and brand colours arrive as a diff to approve.",
    tokens: "Platform colour tokens",
    components: "Built from the same catalogue",
    cta: "Open the style guide",
    docs: "Component playground",
  },
  tokens: {
    title: "Design tokens",
    intro: "The custom properties of the platform theme, as the stylesheet defines them. Tenant themes set the same names with their own values.",
    colours: "Colour",
    colourNames: {
      primary: "Primary · navy",
      accent: "Accent · terracotta",
      header: "Headings",
      text: "Body text",
      muted: "Secondary text",
      bg: "Page",
      surface: "Surface · grid paper",
      "card-bg": "Card",
      border: "Border",
      "accent-bg": "Accent wash",
      positive: "Positive",
      warn: "Warning",
      error: "Error",
      info: "Information",
    },
    onPage: "{ratio}:1 on the page",
    type: "Type scale",
    typeIntro: "Libre Baskerville for headings, Plus Jakarta Sans for text, JetBrains Mono for labels and code. Sizes are fluid: the value is the CSS that sets them.",
    specimens: { display: "Courses built from your docs", h2: "Every claim cited", lead: "A cited draft in minutes, reviewed by you.", body: "Learners keep their progress when a lesson is updated.", eyebrow: "Living course" },
    typeNames: { display: "Display", h2: "Section heading", lead: "Lead", body: "Body", eyebrow: "Eyebrow" },
    radii: "Radius",
    space: "Spacing and layout",
    fonts: "Font families",
  },
  components: {
    title: "Component catalogue",
    intro: "Pages and lessons are documents of catalogue components, validated against their JSON schemas before they render. Each name opens its live playground in the documentation.",
    categories: { structure: "Structure", section: "Page sections", course: "Course pages", learning: "Learning" },
    count: "{n} components",
    live: "Live examples",
    liveIntro: "Rendered here by the catalogue itself, from the same documents an agent or the Course Builder writes.",
    reference: "Props reference",
    playground: "Playground",
  },
  examples: {
    aiLabel: "AI-generated, based on this course's sources",
    citations: ["Privacy policy § 4 Customer data", "Onboarding guide § 2 Returns"],
    callout: { title: "Uploaded content is data", text: "A source can never change what the assistant does. Nothing reaches the academy until an author approves it." },
    chips: { eyebrow: "Status", title: "Labelled honestly", items: [{ label: "Theme presets", status: "available" }, { label: "Brand import as a diff", status: "available" }, { label: "Branding per course inside one academy", status: "coming" }] },
    steps: { title: "Brand an academy in three steps", items: [{ title: "Pick a preset", text: "Six presets, light and dark." }, { title: "Set the accent", text: "Checked against WCAG 2.2 AA before saving." }, { title: "Upload the logo", text: "PNG or SVG, with alt text." }] },
  },
  presets: {
    title: "Theme presets",
    intro: "The six presets of the admin theme screen, from the one list the admin, the Studio, the sites and the API share. Each card shows the preset's own colours and its text contrast.",
    light: "Light",
    dark: "Dark",
    text: "Body text",
    muted: "Secondary text",
    roles: { bg: "Page", card: "Card", text: "Text", primary: "Primary", accent: "Accent" },
    moods: {
      coffee: "Warm, editorial, paper-like",
      oncall: "Dense, dark, technical",
      nightsky: "Playful, night-blue",
      gravity: "Dark space, calm starfield",
      poland: "Cartographic paper, graticule",
      ulam: "Archival notebook, squared paper",
    },
  },
  guard: {
    title: "An accent that fails AA is not saved",
    intro: "The admin measures an accent on the preset's own backgrounds. While any of the four checks fails, the Save button stays off and the nearest passing shade is offered. Two accents on The Coffee Atlas:",
    accent: "Accent",
    saved: "Can be saved",
    blocked: "Blocked: cannot be saved",
    suggestion: "Nearest shade that passes: {hex}",
    checks: {
      "accent-text-page": "As text on the page",
      "accent-text-card": "As text on a card",
      "accent-ui-page": "As a border or icon",
      "on-accent": "Text on an accent button",
    },
    pass: "Passes",
    fail: "Fails",
    needs: "needs {ratio}:1",
  },
  brand: {
    title: "Brand import arrives as a diff",
    intro: "Paste brand colours (JSON, CSS variables or hex codes). The importer picks the closest preset, keeps the brand colour as the accent and measures it. An admin approves the change; nothing is applied before that.",
    input: "Pasted brand colours",
    field: { "theme.theme": "Theme preset", "theme.accent": "Accent" },
    before: "Now",
    after: "Proposed",
    checks: ["Brand accent on the preset background", "Accent as it will render", "Brand text on the brand background"],
    note: "Approved in Settings, Theme. The preset keeps its own page colours: the brand background only chooses light or dark.",
    diffLabel: "Proposed theme change",
  },
  whiteLabel: {
    title: "White label per academy",
    intro: "Each academy is a tenant with its own subdomain, data and look, on one ULAMS installation.",
    items: [
      { title: "Own theme and logo", text: "A preset, an AA-checked accent and a logo with alt text, set per academy." },
      { title: "Own web identity", text: "A web manifest with the academy's name, colour and logo, and schema.org Course data on course pages." },
      { title: "Own templates", text: "E-mail, SMS and PDF templates, certificates included, edited per academy." },
    ],
  },
  docs: { title: "In the documentation", theme: "Theme and logo guide", tenancy: "How tenants work", reference: "UI catalogue reference", playground: "Component playground", extend: "Adding a UI component" },
};

const PL: StyleGuideCopy = {
  title: "ulams — Przewodnik stylu i system projektowy",
  description: "Prawdziwe tokeny, komponenty i motywy stron ULAMS, ze strażnikiem kontrastu WCAG 2.2 AA i importem marki jako diffem do zatwierdzenia.",
  eyebrow: "Przewodnik stylu",
  heading: "Jeden system projektowy, własny wygląd każdej akademii",
  subtitle: "Tokeny, komponenty i motywy, z których powstaje każda strona ULAMS.",
  body: "Wszystko na tej stronie jest odczytane z kodu: tokeny z arkusza stylów, lista motywów z ekranu motywu w panelu administratora, raport kontrastu, który odrzuca zbyt słaby akcent, i katalog komponentów, względem którego sprawdzane są dokumenty stron.",
  primary: "Zobacz tokeny",
  secondary: "Plac zabaw komponentów",
  home: {
    eyebrow: "System projektowy i white label",
    title: "Własny wygląd akademii, nigdy kosztem kontrastu",
    lede: "Każda akademia wybiera motyw, kolor akcentu i logo. Panel sprawdza akcent względem WCAG 2.2 AA przed zapisem, a kolory marki przychodzą jako diff do zatwierdzenia.",
    tokens: "Tokeny kolorów platformy",
    components: "Zbudowane z tego samego katalogu",
    cta: "Otwórz przewodnik stylu",
    docs: "Plac zabaw komponentów",
  },
  tokens: {
    title: "Tokeny projektowe",
    intro: "Zmienne CSS motywu platformy, tak jak definiuje je arkusz stylów. Motywy akademii ustawiają te same nazwy z własnymi wartościami.",
    colours: "Kolor",
    colourNames: {
      primary: "Główny · granat",
      accent: "Akcent · terakota",
      header: "Nagłówki",
      text: "Tekst",
      muted: "Tekst drugorzędny",
      bg: "Strona",
      surface: "Powierzchnia · papier w kratkę",
      "card-bg": "Karta",
      border: "Obramowanie",
      "accent-bg": "Tło akcentu",
      positive: "Pozytywny",
      warn: "Ostrzeżenie",
      error: "Błąd",
      info: "Informacja",
    },
    onPage: "{ratio}:1 na stronie",
    type: "Skala typograficzna",
    typeIntro: "Libre Baskerville w nagłówkach, Plus Jakarta Sans w tekście, JetBrains Mono w etykietach i kodzie. Rozmiary są płynne: wartość to CSS, który je ustawia.",
    specimens: { display: "Kursy z Twoich dokumentów", h2: "Każde twierdzenie z cytatem", lead: "Szkic z cytatami w kilka minut, przejrzany przez Ciebie.", body: "Uczący się zachowują postęp, gdy lekcja zostaje zaktualizowana.", eyebrow: "Żywy kurs" },
    typeNames: { display: "Tytuł", h2: "Nagłówek sekcji", lead: "Wstęp", body: "Tekst", eyebrow: "Etykieta" },
    radii: "Zaokrąglenia",
    space: "Odstępy i układ",
    fonts: "Kroje pisma",
  },
  components: {
    title: "Katalog komponentów",
    intro: "Strony i lekcje to dokumenty złożone z komponentów katalogu, sprawdzane względem ich schematów JSON przed wyrenderowaniem. Każda nazwa otwiera swój plac zabaw w dokumentacji.",
    categories: { structure: "Struktura", section: "Sekcje stron", course: "Strony kursu", learning: "Nauka" },
    count: "{n} komponentów",
    live: "Przykłady na żywo",
    liveIntro: "Wyrenderowane tutaj przez sam katalog, z takich samych dokumentów, jakie pisze agent lub kreator kursów.",
    reference: "Opis właściwości",
    playground: "Plac zabaw",
  },
  examples: {
    aiLabel: "Wygenerowane przez AI na podstawie źródeł tego kursu",
    citations: ["Polityka prywatności § 4 Dane klientów", "Poradnik wdrożeniowy § 2 Zwroty"],
    callout: { title: "Wgrana treść to dane", text: "Źródło nigdy nie zmienia tego, co robi asystent. Nic nie trafia do akademii, dopóki autor tego nie zatwierdzi." },
    chips: { eyebrow: "Status", title: "Uczciwie oznaczone", items: [{ label: "Motywy", status: "available" }, { label: "Import marki jako diff", status: "available" }, { label: "Marka dla kursu w jednej akademii", status: "coming" }] },
    steps: { title: "Marka akademii w trzech krokach", items: [{ title: "Wybierz motyw", text: "Sześć motywów, jasne i ciemne." }, { title: "Ustaw akcent", text: "Sprawdzany względem WCAG 2.2 AA przed zapisem." }, { title: "Wgraj logo", text: "PNG lub SVG, z tekstem alternatywnym." }] },
  },
  presets: {
    title: "Motywy",
    intro: "Sześć motywów z ekranu motywu w panelu, z jednej listy wspólnej dla panelu, Studio, stron i API. Każda karta pokazuje kolory motywu i kontrast jego tekstu.",
    light: "Jasny",
    dark: "Ciemny",
    text: "Tekst",
    muted: "Tekst drugorzędny",
    roles: { bg: "Strona", card: "Karta", text: "Tekst", primary: "Główny", accent: "Akcent" },
    moods: {
      coffee: "Ciepły, redakcyjny, papierowy",
      oncall: "Gęsty, ciemny, techniczny",
      nightsky: "Zabawny, nocny granat",
      gravity: "Ciemny kosmos, spokojne gwiazdy",
      poland: "Papier kartograficzny, siatka",
      ulam: "Archiwalny zeszyt w kratkę",
    },
  },
  guard: {
    title: "Akcent, który nie spełnia AA, nie zostanie zapisany",
    intro: "Panel mierzy akcent na tłach samego motywu. Dopóki któraś z czterech kontroli nie przechodzi, przycisk Zapisz jest nieaktywny, a panel proponuje najbliższy odcień, który przechodzi. Dwa akcenty na motywie The Coffee Atlas:",
    accent: "Akcent",
    saved: "Można zapisać",
    blocked: "Zablokowany: nie można zapisać",
    suggestion: "Najbliższy odcień, który przechodzi: {hex}",
    checks: {
      "accent-text-page": "Jako tekst na stronie",
      "accent-text-card": "Jako tekst na karcie",
      "accent-ui-page": "Jako obramowanie lub ikona",
      "on-accent": "Tekst na przycisku w kolorze akcentu",
    },
    pass: "Przechodzi",
    fail: "Nie przechodzi",
    needs: "wymaga {ratio}:1",
  },
  brand: {
    title: "Import marki przychodzi jako diff",
    intro: "Wklej kolory marki (JSON, zmienne CSS lub kody hex). Importer wybiera najbliższy motyw, zostawia kolor marki jako akcent i go mierzy. Zmianę zatwierdza administrator; wcześniej nic nie jest stosowane.",
    input: "Wklejone kolory marki",
    field: { "theme.theme": "Motyw", "theme.accent": "Akcent" },
    before: "Teraz",
    after: "Propozycja",
    checks: ["Akcent marki na tle motywu", "Akcent tak, jak zostanie wyświetlony", "Tekst marki na tle marki"],
    note: "Zatwierdzane w Ustawieniach, w sekcji Motyw. Motyw zachowuje własne kolory strony: tło marki decyduje tylko o wersji jasnej lub ciemnej.",
    diffLabel: "Proponowana zmiana motywu",
  },
  whiteLabel: {
    title: "White label dla każdej akademii",
    intro: "Każda akademia to najemca z własną subdomeną, danymi i wyglądem, na jednej instalacji ULAMS.",
    items: [
      { title: "Własny motyw i logo", text: "Motyw, akcent sprawdzony pod kątem AA i logo z tekstem alternatywnym, ustawiane dla każdej akademii." },
      { title: "Własna tożsamość w sieci", text: "Manifest aplikacji z nazwą, kolorem i logo akademii oraz dane schema.org Course na stronach kursów." },
      { title: "Własne szablony", text: "Szablony e-maili, SMS-ów i PDF, w tym certyfikaty, edytowane osobno w każdej akademii." },
    ],
  },
  docs: { title: "W dokumentacji", theme: "Przewodnik: motyw i logo", tenancy: "Jak działają najemcy", reference: "Opis katalogu UI", playground: "Plac zabaw komponentów", extend: "Dodawanie komponentu UI" },
};

const ZH: StyleGuideCopy = {
  title: "ulams — 样式指南与设计系统",
  description: "ULAMS 网站背后真实的设计令牌、组件和主题预设，以及 WCAG 2.2 AA 对比度把关和以差异形式提交的品牌导入。",
  eyebrow: "样式指南",
  heading: "一套设计系统，每个学院各有外观",
  subtitle: "构建每个 ULAMS 网站所用的令牌、组件和主题预设。",
  body: "本页的一切都读取自代码：样式表中的令牌、管理后台主题界面的预设列表、拒绝不合格强调色的对比度报告，以及用来校验页面文档的组件目录。",
  primary: "查看令牌",
  secondary: "组件演练场",
  home: {
    eyebrow: "设计系统与白标",
    title: "每个学院自有外观，但绝不牺牲对比度",
    lede: "每个学院选择主题预设、强调色和标志。管理后台在保存前按 WCAG 2.2 AA 检查强调色，品牌颜色以待批准的差异形式提交。",
    tokens: "平台颜色令牌",
    components: "由同一个组件目录构建",
    cta: "打开样式指南",
    docs: "组件演练场",
  },
  tokens: {
    title: "设计令牌",
    intro: "平台主题的 CSS 自定义属性，与样式表中的定义一致。各学院主题用自己的值设置同样的名称。",
    colours: "颜色",
    colourNames: {
      primary: "主色 · 海军蓝",
      accent: "强调色 · 赤陶色",
      header: "标题",
      text: "正文",
      muted: "次要文字",
      bg: "页面",
      surface: "底面 · 方格纸",
      "card-bg": "卡片",
      border: "边框",
      "accent-bg": "强调色底",
      positive: "正面",
      warn: "警告",
      error: "错误",
      info: "信息",
    },
    onPage: "在页面上为 {ratio}:1",
    type: "字号层级",
    typeIntro: "标题用 Libre Baskerville，正文用 Plus Jakarta Sans，标签和代码用 JetBrains Mono。字号是流式的：显示的值就是设置它的 CSS。",
    specimens: { display: "用你的文档构建课程", h2: "每个说法都有出处", lead: "几分钟内得到带引用的草稿，由你审阅。", body: "课时更新后，学员的进度仍然保留。", eyebrow: "活课程" },
    typeNames: { display: "大标题", h2: "章节标题", lead: "导语", body: "正文", eyebrow: "眉题" },
    radii: "圆角",
    space: "间距与布局",
    fonts: "字体",
  },
  components: {
    title: "组件目录",
    intro: "页面和课时都是由目录组件组成的文档，渲染前会按各组件的 JSON Schema 校验。每个名称都会打开文档中的在线演练场。",
    categories: { structure: "结构", section: "页面区块", course: "课程页面", learning: "学习" },
    count: "{n} 个组件",
    live: "实时示例",
    liveIntro: "由组件目录本身在此渲染，使用的文档与智能体或课程生成器写出的文档相同。",
    reference: "属性参考",
    playground: "演练场",
  },
  examples: {
    aiLabel: "由 AI 根据本课程的来源生成",
    citations: ["隐私政策 § 4 客户数据", "入职指南 § 2 退货"],
    callout: { title: "上传的内容只是数据", text: "来源永远不能改变助手的行为。在作者批准之前，什么都不会进入学院。" },
    chips: { eyebrow: "状态", title: "如实标注", items: [{ label: "主题预设", status: "available" }, { label: "以差异形式导入品牌", status: "available" }, { label: "同一学院内按课程设置品牌", status: "coming" }] },
    steps: { title: "三步为学院设置品牌", items: [{ title: "选择预设", text: "六个预设，浅色和深色都有。" }, { title: "设置强调色", text: "保存前按 WCAG 2.2 AA 检查。" }, { title: "上传标志", text: "PNG 或 SVG，并附替代文本。" }] },
  },
  presets: {
    title: "主题预设",
    intro: "管理后台主题界面中的六个预设，来自管理后台、Studio、网站和 API 共用的同一个列表。每张卡片显示预设自己的颜色及其文字对比度。",
    light: "浅色",
    dark: "深色",
    text: "正文",
    muted: "次要文字",
    roles: { bg: "页面", card: "卡片", text: "文字", primary: "主色", accent: "强调色" },
    moods: {
      coffee: "温暖、编辑感、纸张质感",
      oncall: "紧凑、深色、技术感",
      nightsky: "活泼、夜空蓝",
      gravity: "深邃太空、宁静星空",
      poland: "地图纸、经纬网格",
      ulam: "档案笔记本、方格纸",
    },
  },
  guard: {
    title: "不符合 AA 的强调色不会被保存",
    intro: "管理后台在预设自己的背景上测量强调色。只要四项检查中有任何一项不通过，保存按钮就不可用，并会给出最接近的合格色调。The Coffee Atlas 上的两个强调色：",
    accent: "强调色",
    saved: "可以保存",
    blocked: "已阻止：无法保存",
    suggestion: "最接近的合格色调：{hex}",
    checks: {
      "accent-text-page": "作为页面上的文字",
      "accent-text-card": "作为卡片上的文字",
      "accent-ui-page": "作为边框或图标",
      "on-accent": "强调色按钮上的文字",
    },
    pass: "通过",
    fail: "不通过",
    needs: "需要 {ratio}:1",
  },
  brand: {
    title: "品牌导入以差异形式提交",
    intro: "粘贴品牌颜色（JSON、CSS 变量或十六进制色值）。导入器选择最接近的预设，保留品牌色作为强调色并进行测量。由管理员批准更改；在此之前不会应用任何内容。",
    input: "粘贴的品牌颜色",
    field: { "theme.theme": "主题预设", "theme.accent": "强调色" },
    before: "当前",
    after: "建议",
    checks: ["品牌强调色在预设背景上", "强调色的实际显示效果", "品牌文字在品牌背景上"],
    note: "在“设置 > 主题”中批准。预设保留自己的页面颜色：品牌背景只决定浅色或深色。",
    diffLabel: "建议的主题更改",
  },
  whiteLabel: {
    title: "每个学院独立白标",
    intro: "每个学院都是一个租户，在同一套 ULAMS 安装上拥有自己的子域名、数据和外观。",
    items: [
      { title: "自己的主题和标志", text: "每个学院分别设置预设、经过 AA 检查的强调色以及带替代文本的标志。" },
      { title: "自己的网络身份", text: "包含学院名称、颜色和标志的 Web 应用清单，以及课程页面上的 schema.org Course 数据。" },
      { title: "自己的模板", text: "电子邮件、短信和 PDF 模板（包括证书），按学院分别编辑。" },
    ],
  },
  docs: { title: "文档", theme: "主题与标志指南", tenancy: "租户如何运作", reference: "UI 组件目录参考", playground: "组件演练场", extend: "添加 UI 组件" },
};

const COPY: Record<Locale, StyleGuideCopy> = { en: EN, pl: PL, zh: ZH };

export const styleGuideCopy = (locale: Locale): StyleGuideCopy => COPY[locale];

/** The live examples of the page: catalogue documents rendered by `<Render>`, in the page language. */
export function liveExamples(locale: Locale): UiNode[] {
  const e = COPY[locale].examples;
  return [
    { component: "AiLabel", props: { text: e.aiLabel, tone: "generated" } },
    { component: "CitationList", props: { items: e.citations.map((label) => ({ label })) } },
    { component: "Callout", props: { tone: "key", title: e.callout.title, text: e.callout.text } },
    { component: "Chips", props: { eyebrow: e.chips.eyebrow, title: e.chips.title, items: e.chips.items } },
    { component: "Steps", props: { id: "sg-steps", title: e.steps.title, items: e.steps.items.map((item, i) => ({ icon: ["layers", "eye", "star"][i], ...item })) } },
  ];
}

/** "{ratio}:1", "{n}" and "{hex}" placeholders filled in. */
export const fill = (template: string, values: Record<string, string | number>): string => template.replace(/\{(\w+)\}/g, (_, key: string) => String(values[key] ?? `{${key}}`));

/** The /style-guide/ page shell in one language (lib/product-pages.ts); StyleGuideBody renders the sections. */
export function styleGuidePage(locale: Locale, request: Request, url: URL) {
  const c = COPY[locale];
  return productShell("style-guide", locale, {
    title: c.title,
    description: c.description,
    eyebrow: c.eyebrow,
    heading: c.heading,
    subtitle: c.subtitle,
    body: c.body,
    primary: { label: c.primary, href: "#tokens" },
    secondary: { label: c.secondary, href: CATALOGUE_DOCS },
  }, request, url);
}
