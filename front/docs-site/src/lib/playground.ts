/**
 * Component playground data (ADR 0054): everything a catalogue page shows is computed here from
 * the @ulams/ui registry and the examples in front/ui/catalogue/examples, at build time.
 */
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { JSDOM } from "jsdom";
import axeSource from "axe-core/axe.min.js?raw";
import Render from "@ulams/ui/Render.astro";
import { registry, type ComponentName } from "@ulams/ui/registry";
import type { UiNode } from "@ulams/ui/render-core";

export interface Example {
  props: Record<string, unknown>;
  invalid: Record<string, unknown>;
  children?: UiNode[];
}

const modules = import.meta.glob<Example>("../../../ui/catalogue/examples/*.json", { eager: true, import: "default" });

export const names = Object.keys(registry) as ComponentName[];

export function exampleOf(name: ComponentName): Example {
  const found = Object.entries(modules).find(([path]) => path.endsWith(`/${name}.json`));
  if (!found) throw new Error(`front/ui/catalogue/examples/${name}.json is missing (the docs coverage check requires one per component)`);
  return found[1];
}

export const docOf = (name: ComponentName, example: Example): UiNode => ({ component: name, props: example.props, children: example.children });

let container: AstroContainer | undefined;

/** Server-renders the example with the real components (no styles, no scripts). */
export async function renderExample(doc: UiNode): Promise<string> {
  container ??= await AstroContainer.create();
  return container.renderToString(Render as never, { props: { doc } });
}

export interface AxeResult {
  violations: Array<{ id: string; impact: string | null | undefined; help: string; nodes: string[] }>;
  passes: number;
  incomplete: number;
}

/**
 * Runs axe-core on the rendered HTML in jsdom. Colour contrast and layout rules need a real
 * browser and are covered by the Playwright axe scans of the reference frontend.
 */
export async function runAxe(html: string, wrapInMain = true): Promise<AxeResult> {
  const body = wrapInMain ? `<main>${html}</main>` : html;
  const dom = new JSDOM(`<!doctype html><html lang="en"><head><title>Example</title></head><body>${body}</body></html>`, {
    runScripts: "outside-only",
    pretendToBeVisual: true,
  });
  try {
    dom.window.eval(axeSource);
    const axe = (dom.window as unknown as { axe: { run: (ctx: unknown, opts: unknown) => Promise<Record<string, any>> } }).axe;
    const result = await axe.run(dom.window.document.body, {
      rules: { "color-contrast": { enabled: false }, region: { enabled: false } },
    });
    return {
      violations: result.violations.map((v: any) => ({
        id: v.id,
        impact: v.impact,
        help: v.help,
        nodes: v.nodes.map((n: any) => String(n.html).slice(0, 160)),
      })),
      passes: result.passes.length,
      incomplete: result.incomplete.length,
    };
  } finally {
    dom.window.close();
  }
}

/** Nodes that are page structure: previewed as they are, not inside a <main>. */
export const isStructure = (name: ComponentName): boolean => name === "Page" || name === "Main";
