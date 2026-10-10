/**
 * Language switcher of the site header: keeps the section the visitor is reading. The links carry the
 * plain language home (`data-base`), so they work without JavaScript; this adds the current `#anchor`
 * to them, on load and whenever the hash changes.
 */
export const withHash = (base: string, hash: string): string => (hash && hash !== "#" ? base.split("#")[0] + hash : base);

export function syncLanguageLinks(root: ParentNode = document, hash: string = location.hash): void {
  for (const link of root.querySelectorAll<HTMLAnchorElement>("a[data-lang-switch]")) {
    link.setAttribute("href", withHash(link.dataset.base ?? link.getAttribute("href") ?? "/", hash));
  }
}

if (typeof window !== "undefined" && typeof document !== "undefined") {
  syncLanguageLinks();
  window.addEventListener("hashchange", () => syncLanguageLinks());
}
