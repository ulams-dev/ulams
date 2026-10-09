/**
 * Branded progress bar for page navigations that really wait (a cold lesson that needs a
 * login and the program from the API). It only appears after 150 ms, so prefetched and
 * cached pages never show it.
 */
export function initNavigationProgress(): void {
  const root = document.documentElement;
  let timer: number | undefined;
  const stop = () => {
    window.clearTimeout(timer);
    root.classList.remove("is-navigating");
  };
  document.addEventListener("click", (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const link = (event.target as Element | null)?.closest?.("a[href]") as HTMLAnchorElement | null;
    if (!link || link.target || link.hasAttribute("download")) return;
    const url = new URL(link.href, location.href);
    if (url.origin !== location.origin || (url.pathname === location.pathname && url.hash)) return;
    stop();
    timer = window.setTimeout(() => root.classList.add("is-navigating"), 150);
  });
  window.addEventListener("pageshow", stop);
  window.addEventListener("pagehide", stop);
}
