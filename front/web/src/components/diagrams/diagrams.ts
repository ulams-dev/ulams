/**
 * Scroll-triggered animation of the impact-page diagrams. The markup is the final, static state: without JS, with
 * reduced motion or without IntersectionObserver nothing is hidden and nothing moves. Otherwise a diagram below the
 * fold is "armed" (its parts start hidden, opacity and transform only) and plays once it scrolls into view. A
 * looping diagram pauses off screen and has a pause button; the others have a replay button.
 */
export function initDiagrams(): void {
  const root = document.documentElement;
  if (root.classList.contains("js-dg")) return;
  if (matchMedia("(prefers-reduced-motion: reduce)").matches || !("IntersectionObserver" in window)) return;
  const diagrams = [...document.querySelectorAll<HTMLElement>("[data-dg]")];
  if (diagrams.length === 0) return;
  root.classList.add("js-dg");

  const fold = window.innerHeight;
  const io = new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        const el = entry.target as HTMLElement;
        el.classList.toggle("is-offscreen", !entry.isIntersecting);
        if (entry.isIntersecting) el.classList.add("is-played");
      }
    },
    { threshold: 0.2 },
  );

  for (const el of diagrams) {
    // Already on screen at load: shown as it is, so nothing flashes; the loop still runs.
    if (el.getBoundingClientRect().top < fold) el.classList.add("is-played");
    else el.classList.add("is-armed");
    io.observe(el);
    const button = el.querySelector<HTMLButtonElement>("[data-dg-control]");
    if (!button) continue;
    button.hidden = false;
    button.addEventListener("click", () => {
      if (button.dataset.dgControl === "pause") {
        const paused = el.classList.toggle("is-paused");
        button.textContent = paused ? (button.dataset.play ?? "") : (button.dataset.pause ?? "");
        return;
      }
      el.classList.remove("is-played");
      el.classList.add("is-armed");
      void el.offsetWidth;
      el.classList.add("is-played");
    });
  }
}
