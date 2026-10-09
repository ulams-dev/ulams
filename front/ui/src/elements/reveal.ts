/**
 * Scroll reveal: elements with [data-reveal] fade in when they enter the viewport.
 * Content is visible without JS; the hidden state is only applied once this runs.
 */
export function initReveal(): void {
  const root = document.documentElement;
  if (matchMedia("(prefers-reduced-motion: reduce)").matches || !("IntersectionObserver" in window)) return;
  const items = [...document.querySelectorAll<HTMLElement>("[data-reveal]")];
  const fold = window.innerHeight;
  // Already on screen at load: no animation (avoids a flash and keeps LCP/CLS stable).
  for (const el of items) if (el.getBoundingClientRect().top < fold) el.classList.add("is-revealed");
  root.classList.add("js-reveal");
  const io = new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-revealed");
          io.unobserve(entry.target);
        }
      }
    },
    { rootMargin: "0px 0px -8% 0px", threshold: 0.08 }
  );
  for (const el of items) if (!el.classList.contains("is-revealed")) io.observe(el);
}
