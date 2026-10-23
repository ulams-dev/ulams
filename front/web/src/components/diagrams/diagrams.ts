/**
 * Scroll-triggered animation of the impact-page diagrams and the home-page story. The markup is the final, static state: without JS, with
 * reduced motion or without IntersectionObserver nothing is hidden and nothing moves. Otherwise a diagram below the
 * fold is "armed" (its parts start hidden, opacity and transform only) and plays once it scrolls into view. A
 * looping diagram pauses off screen and has a pause button; the others have a replay button.
 */
/**
 * Puts a diagram back in its hidden starting state at once. Without `is-rewinding` the parts would start a
 * transition towards hidden and simply reverse it when `is-played` comes back, so nothing would visibly replay.
 */
function rewind(el: HTMLElement): void {
  el.classList.add("is-rewinding");
  el.classList.remove("is-played");
  el.classList.add("is-armed");
  void el.offsetWidth;
  el.classList.remove("is-rewinding");
}

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
      rewind(el);
      el.classList.add("is-played");
    });
  }

  // A group of diagrams told as one story (the home page): one button re-arms every part and goes back to the start,
  // so each part plays again as it scrolls into view.
  for (const group of document.querySelectorAll<HTMLElement>("[data-dg-group]")) {
    const button = group.querySelector<HTMLButtonElement>("[data-dg-control='replay-group']");
    if (!button) continue;
    button.hidden = false;
    button.addEventListener("click", () => {
      const parts = [...group.querySelectorAll<HTMLElement>("[data-dg]")];
      for (const el of parts) rewind(el);
      const start = group.querySelector<HTMLElement>("[data-dg-start]") ?? group;
      start.scrollIntoView({ behavior: "smooth", block: "start" });
      start.focus({ preventScroll: true });
      // parts already on screen get no new intersection entry, so they play right away
      for (const el of parts) {
        const box = el.getBoundingClientRect();
        if (box.top < window.innerHeight && box.bottom > 0) el.classList.add("is-played");
      }
    });
  }
}
