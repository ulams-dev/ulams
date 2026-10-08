import type { UiNode } from "@ulams/ui/render-core";
import { resolveBindings } from "@ulams/ui/render-core";
import type { ThemeName } from "@ulams/ui/registry";

/**
 * Landing documents, one per tenant theme (src/docs/<theme>.json). They have the shape the
 * Course Builder will generate: a tree of catalogue components with `$data` bindings.
 */
const modules = import.meta.glob<{ default: UiNode }>("../docs/*.json", { eager: true });

export const landingDocs: Partial<Record<ThemeName, UiNode>> = Object.fromEntries(
  Object.entries(modules).map(([path, mod]) => [path.replace(/^.*\/(\w+)\.json$/, "$1"), mod.default])
);

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
