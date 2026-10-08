/**
 * Framework-free part of the renderer: data bindings, validation and fallbacks.
 * `Render.astro` calls `prepare()` once per document and renders the result.
 */
import { isComponentName, registry, type ComponentName } from "./registry.ts";
import { validate, type ValidationIssue } from "./schema.ts";

/** A node of a page document (A2UI-shaped tree). */
export interface UiNode {
  component: string;
  props?: Record<string, unknown>;
  children?: UiNode[];
  /** Optional stable id (A2UI component id); used as the element id when set. */
  id?: string;
}

export type PreparedNode =
  | {
      kind: "component";
      component: ComponentName;
      props: Record<string, unknown>;
      children: PreparedNode[];
      id?: string;
    }
  | {
      kind: "fallback";
      component: string;
      text: string;
      issues: ValidationIssue[];
      /** A container with invalid props still renders its (valid) children. */
      children: PreparedNode[];
    };

export interface Binding {
  $data: string;
  $default?: unknown;
}

export const isBinding = (value: unknown): value is Binding =>
  !!value && typeof value === "object" && !Array.isArray(value) && typeof (value as Binding).$data === "string";

/** RFC 6901 JSON Pointer lookup ("" = whole document). */
export function getPointer(data: unknown, pointer: string): unknown {
  if (pointer === "" || pointer === "/") return data;
  if (!pointer.startsWith("/")) return undefined;
  let current: unknown = data;
  for (const raw of pointer.slice(1).split("/")) {
    const key = raw.replace(/~1/g, "/").replace(/~0/g, "~");
    if (current === null || current === undefined) return undefined;
    if (Array.isArray(current)) {
      const index = Number(key);
      current = Number.isInteger(index) ? current[index] : undefined;
    } else if (typeof current === "object") {
      current = Object.prototype.hasOwnProperty.call(current, key) ? (current as Record<string, unknown>)[key] : undefined;
    } else {
      return undefined;
    }
  }
  return current;
}

/**
 * Replaces every `{ "$data": "/pointer", "$default": … }` in `value` with the data model value.
 * Missing data resolves to `$default`, or `undefined` (so the schema default applies).
 */
export function resolveBindings(value: unknown, data: unknown): unknown {
  if (isBinding(value)) {
    const found = getPointer(data, value.$data);
    const empty = found === undefined || found === null || (Array.isArray(found) && found.length === 0) || found === "";
    return empty ? value.$default : found;
  }
  if (Array.isArray(value)) {
    return value.map((item) => resolveBindings(item, data)).filter((item) => item !== undefined);
  }
  if (value && typeof value === "object") {
    const out: Record<string, unknown> = {};
    for (const [key, child] of Object.entries(value)) {
      const resolved = resolveBindings(child, data);
      if (resolved !== undefined) out[key] = resolved;
    }
    return out;
  }
  return value;
}

/** Plain-text rendering of a node, for invalid props and unknown components. */
export function fallbackText(node: UiNode): string {
  const props = (node.props ?? {}) as Record<string, unknown>;
  if (isComponentName(node.component)) {
    try {
      return registry[node.component].fallback(props);
    } catch {
      return "";
    }
  }
  const strings = Object.values(props).filter((v): v is string => typeof v === "string");
  return strings.join("\n");
}

export interface PrepareOptions {
  /** Data model for `$data` bindings. */
  data?: unknown;
  /** Called for each node that falls back (log it in development). */
  onIssue?: (component: string, issues: ValidationIssue[]) => void;
  /** Max tree depth (guards against runaway generated documents). */
  maxDepth?: number;
}

/** Resolves bindings, validates props against the registry and applies defaults. */
export function prepare(node: UiNode, options: PrepareOptions = {}, depth = 0): PreparedNode {
  const maxDepth = options.maxDepth ?? 12;
  if (!node || typeof node !== "object" || typeof node.component !== "string") {
    const issues = [{ path: "/", message: "not a component node" }];
    options.onIssue?.("?", issues);
    return { kind: "fallback", component: "?", text: "", issues, children: [] };
  }
  const resolvedProps = resolveBindings(node.props ?? {}, options.data) as Record<string, unknown>;
  const resolvedNode: UiNode = { ...node, props: resolvedProps };

  if (!isComponentName(node.component)) {
    const issues = [{ path: "/component", message: `unknown component "${node.component}"` }];
    options.onIssue?.(node.component, issues);
    return { kind: "fallback", component: node.component, text: fallbackText(resolvedNode), issues, children: [] };
  }
  if (depth > maxDepth) {
    const issues = [{ path: "/", message: `deeper than ${maxDepth} levels` }];
    options.onIssue?.(node.component, issues);
    return { kind: "fallback", component: node.component, text: fallbackText(resolvedNode), issues, children: [] };
  }

  const spec = registry[node.component];
  const result = validate<Record<string, unknown>>(spec.props, resolvedProps);
  const children = spec.children ? (node.children ?? []).map((child) => prepare(child, options, depth + 1)) : [];
  if (!result.valid) {
    options.onIssue?.(node.component, result.issues);
    // structural containers have no text of their own worth showing
    const text = spec.children ? "" : fallbackText(resolvedNode);
    return { kind: "fallback", component: node.component, text, issues: result.issues, children };
  }
  return { kind: "component", component: node.component, props: result.value, children, id: node.id };
}

/** Validates a whole document without rendering it (for agents and tests). */
export function validateDocument(doc: UiNode, data?: unknown): Array<{ component: string; issues: ValidationIssue[] }> {
  const problems: Array<{ component: string; issues: ValidationIssue[] }> = [];
  prepare(doc, { data, onIssue: (component, issues) => problems.push({ component, issues }) });
  return problems;
}
