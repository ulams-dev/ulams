/**
 * Test helpers for the Astro components: server-render a page document with the container API
 * (node environment), then load the HTML into one jsdom window whose globals the custom elements
 * and axe-core use. Tests that call these run with the default node environment.
 */
import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { JSDOM } from "jsdom";
import Render from "../src/Render.astro";
import type { UiNode } from "../src/render-core.ts";

let container: AstroContainer | undefined;

/** Server-renders a page document with the real Astro components. */
export async function renderDoc(doc: UiNode, data?: unknown): Promise<string> {
  container ??= await AstroContainer.create();
  return container.renderToString(Render as never, { props: { doc, data } });
}

let dom: JSDOM | undefined;

/** One jsdom window for the whole test file; its globals are what element modules see. */
export function setupDom(): JSDOM {
  if (dom) return dom;
  dom = new JSDOM("<!doctype html><html lang=\"en\"><head><title>Test</title></head><body></body></html>", { pretendToBeVisual: true });
  const w = dom.window as unknown as Record<string, unknown>;
  for (const key of ["window", "document", "HTMLElement", "customElements", "CustomEvent", "Node", "Element", "MutationObserver", "getComputedStyle"]) {
    Object.defineProperty(globalThis, key, { value: key === "window" ? dom.window : w[key], configurable: true, writable: true });
  }
  Object.defineProperty(globalThis, "navigator", { value: dom.window.navigator, configurable: true, writable: true });
  return dom;
}

/** Puts server-rendered HTML in a <main> landmark (custom elements upgrade on insertion). */
export function mount(html: string): HTMLElement {
  const { document } = setupDom().window;
  document.body.innerHTML = `<main>${html}</main>`;
  return document.body.firstElementChild as HTMLElement;
}
