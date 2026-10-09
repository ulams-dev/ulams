/**
 * A2UI v0.9 surface renderer for the builder catalogue (ADR 0011). Walks the flat component list
 * from `root`, validates each node's props against its schema, renders catalogue components,
 * shows a skeleton while required props are still missing (progressive streaming), and falls back
 * to the component's plain text for unknown components or invalid props: a bad node never breaks
 * the surface.
 */
import { validate } from "../schema.ts";
import { builderCatalogue, isBuilderComponent } from "./catalogue.ts";
import { builderComponents, type BuilderContext } from "./components.ts";
import { h } from "./dom.ts";

export interface FlatComponent {
  id: string;
  component: string;
  children?: string[];
  [prop: string]: unknown;
}

export type NodeOutcome = "rendered" | "fallback" | "skeleton";

export interface RenderReport {
  outcomes: Record<string, NodeOutcome>;
}

export function propsOf(node: FlatComponent): Record<string, unknown> {
  const { id: _id, component: _component, children: _children, ...props } = node;
  return props;
}

export function renderSurface(components: FlatComponent[], ctx: BuilderContext, report: RenderReport = { outcomes: {} }): HTMLElement {
  const byId = new Map(components.map((c) => [c.id, c]));
  const visiting = new Set<string>();

  const renderNode = (id: string): HTMLElement => {
    const node = byId.get(id);
    if (!node || visiting.has(id)) return h("span", { hidden: true });
    visiting.add(id);
    try {
      if (!isBuilderComponent(node.component)) {
        report.outcomes[id] = "fallback";
        return fallback(textOf(node));
      }
      const spec = builderCatalogue[node.component];
      const result = validate<Record<string, unknown>>(spec.props, propsOf(node));
      if (!result.valid) {
        const onlyMissing = result.issues.every((issue) => issue.message === "is required");
        report.outcomes[id] = onlyMissing ? "skeleton" : "fallback";
        if (onlyMissing) return h("div", { class: "cb-card cb-skeleton", "aria-busy": "true", "aria-label": "Loading" }, h("span", {}), h("span", {}), h("span", {}));
        return fallback(spec.fallback(propsOf(node)) || textOf(node));
      }
      const children = spec.children ? (node.children ?? []).map(renderNode) : [];
      report.outcomes[id] = "rendered";
      const el = builderComponents[node.component]!(result.value, ctx, id, children);
      el.dataset.component = node.component;
      return el;
    } catch {
      report.outcomes[id] = "fallback";
      return fallback(textOf(node));
    } finally {
      visiting.delete(id);
    }
  };

  return renderNode(byId.has("root") ? "root" : (components[0]?.id ?? "root"));
}

function textOf(node: FlatComponent): string {
  const props = propsOf(node);
  for (const key of ["text", "label", "title", "stem", "name"]) {
    if (typeof props[key] === "string") return props[key] as string;
  }
  return "This part of the conversation cannot be shown.";
}

function fallback(text: string): HTMLElement {
  return h("p", { class: "cb-text cb-fallback" }, text);
}
