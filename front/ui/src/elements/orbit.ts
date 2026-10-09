/**
 * <ulams-orbit>: highlight cycling and pause for the capability orbit in the landing hero.
 * The rotation itself is CSS. Cards carry data-cap="n", captions data-cap-text="n". Every few seconds the
 * next card is highlighted (class is-on) and its caption shown; hover or focus on a card pauses the
 * orbit and shows its caption; the [data-pause] button pauses it for good (WCAG 2.2.2). Under
 * prefers-reduced-motion nothing cycles, hover and focus still show the captions.
 */
export class UlamsOrbit extends HTMLElement {
  connectedCallback(): void {
    const cards = [...this.querySelectorAll<HTMLElement>("[data-cap]")];
    const caps = [...this.querySelectorAll<HTMLElement>("[data-cap-text]")];
    const btn = this.querySelector<HTMLButtonElement>("[data-pause]");
    const still = matchMedia("(prefers-reduced-motion: reduce)");
    let at = 0;
    let held = false;
    let off = false;
    let seen = true;
    const show = (n: number): void => {
      at = n;
      cards.forEach((c, i) => c.classList.toggle("is-on", i === n));
      caps.forEach((c, i) => (c.hidden = i !== n));
    };
    const sync = (): void => {
      this.toggleAttribute("data-hold", held);
      this.toggleAttribute("data-paused", off);
    };
    const index = (e: Event): number =>
      cards.indexOf((e.target as Element).closest<HTMLElement>("[data-cap]")!);
    const enter = (e: Event): void => {
      const n = index(e);
      if (n < 0) return;
      held = true;
      show(n);
      sync();
    };
    const leave = (e: Event): void => {
      if (index(e) < 0) return;
      held = false;
      sync();
    };
    this.addEventListener("mouseover", enter);
    this.addEventListener("focusin", enter);
    this.addEventListener("mouseout", leave);
    this.addEventListener("focusout", leave);
    btn?.addEventListener("click", () => {
      off = !off;
      btn.setAttribute("aria-pressed", String(off));
      sync();
    });
    new IntersectionObserver(
      (entries) => (seen = !!entries[entries.length - 1]?.isIntersecting),
    ).observe(this);
    setInterval(() => {
      if (!held && !off && seen && !still.matches && !document.hidden)
        show((at + 1) % cards.length);
    }, 4200);
  }
}
if (!customElements.get("ulams-orbit"))
  customElements.define("ulams-orbit", UlamsOrbit);
