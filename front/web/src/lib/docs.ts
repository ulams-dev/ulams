import type { UiNode } from "@ulams/ui/render-core";
import { resolveBindings } from "@ulams/ui/render-core";
import type { ThemeName } from "@ulams/ui/registry";
import type { Locale } from "../i18n/locales.ts";

/**
 * Landing documents, one per tenant theme (src/docs/<theme>.json). They have the shape the
 * Course Builder will generate: a tree of catalogue components with `$data` bindings.
 */
const modules = import.meta.glob<{ default: UiNode }>("../docs/*.json", { eager: true });

export const landingDocs: Partial<Record<ThemeName, UiNode>> = Object.fromEntries(
  Object.entries(modules).map(([path, mod]) => [path.replace(/^.*\/(\w+)\.json$/, "$1"), mod.default])
);

/**
 * Translated landing documents (src/docs/i18n/<theme>.<locale>.json): the same tree as the English one
 * (a unit test keeps them parallel), with the visible text translated.
 */
const localized = import.meta.glob<{ default: UiNode }>("../docs/i18n/*.json", { eager: true });
export const localizedDocs: Record<string, Partial<Record<Exclude<Locale, "en">, UiNode>>> = {};
for (const [path, mod] of Object.entries(localized)) {
  const match = /\/(\w+)\.(pl|zh)\.json$/.exec(path);
  if (match) (localizedDocs[match[1]!] ??= {})[match[2] as "pl" | "zh"] = mod.default;
}

/** The landing document of a theme in a language (English when there is no translation). */
export const landingDocFor = (theme: ThemeName, locale: Locale = "en"): UiNode | undefined =>
  locale === "en" ? landingDocs[theme] : (localizedDocs[theme]?.[locale] ?? landingDocs[theme]);

/** Title, description and language from the document's root Page node. */
export function pageMeta(doc: UiNode, data: unknown): { title: string; description?: string; lang: string } {
  const props = resolveBindings(doc.props ?? {}, data) as Record<string, unknown>;
  return {
    title: typeof props.title === "string" ? props.title : "ulams",
    description: typeof props.description === "string" ? props.description : undefined,
    lang: typeof props.lang === "string" ? props.lang : "en",
  };
}

function findNode(node: UiNode, component: string): UiNode | null {
  if (node.component === component) return node;
  for (const child of node.children ?? []) {
    const found = findNode(child, component);
    if (found) return found;
  }
  return null;
}

/** In-page links of the landing ("#pricing") point back to the landing from other pages. */
function absolutise(value: unknown): unknown {
  if (typeof value === "string") return value.startsWith("#") ? `/${value}` : value;
  if (Array.isArray(value)) return value.map(absolutise);
  if (value && typeof value === "object") {
    return Object.fromEntries(Object.entries(value).map(([k, v]) => [k, k === "href" ? absolutise(v) : absolutiseDeep(v)]));
  }
  return value;
}
const absolutiseDeep = (value: unknown): unknown =>
  Array.isArray(value) || (value && typeof value === "object") ? absolutise(value) : value;

/** The tenant's site header and footer (from its landing document), for every other page. */
export function chromeFor(theme: ThemeName): { header: UiNode | null; footer: UiNode | null } {
  const doc = landingDocs[theme];
  if (!doc) return { header: null, footer: null };
  const header = findNode(doc, "SiteHeader");
  const footer = findNode(doc, "SiteFooter");
  return {
    header: header ? { ...header, props: absolutise(header.props) as Record<string, unknown> } : null,
    footer: footer ? { ...footer, props: absolutise(footer.props) as Record<string, unknown> } : null,
  };
}

/** The header's sign-in link becomes "My learning" for a visitor with a session. */
export function withSessionLink(doc: UiNode, loggedIn: boolean): UiNode {
  if (!loggedIn) return doc;
  const visit = (node: UiNode): UiNode => {
    if (node.component === "SiteHeader") {
      return { ...node, props: { ...(node.props ?? {}), signIn: { label: "My learning", href: "/account" } } };
    }
    return node.children ? { ...node, children: node.children.map(visit) } : node;
  };
  return visit(doc);
}

/** The site header gets the language switcher and the language home (the brand link). */
export function withLanguages(doc: UiNode, languages: Array<{ code: string; label: string; href: string; current: boolean }>, homeHref: string): UiNode {
  const visit = (node: UiNode): UiNode => {
    if (node.component === "SiteHeader") return { ...node, props: { ...(node.props ?? {}), languages, homeHref } };
    return node.children ? { ...node, children: node.children.map(visit) } : node;
  };
  return visit(doc);
}
