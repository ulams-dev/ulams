/**
 * <ulams-parts> … nav[data-nav] (button[data-prev], p[data-status], button[data-next]) … section[data-part] …
 *
 * A lesson shown one part at a time (adaptive lesson view). Without the script every part is on the page;
 * with it only the current part shows. Moving to another part keeps the learner's place: focus goes to
 * the part heading and the status line ("Part 2 of 4") is announced. The page's end-of-content sentinel
 * stays hidden until the last part is open, so a lesson read "by view" is not completed after part one.
 */
class UlamsParts extends HTMLElement {
  private index = 0;
  private parts: HTMLElement[] = [];

  connectedCallback(): void {
    if (this.dataset.ready) return;
    this.dataset.ready = "1";
    this.parts = [...this.querySelectorAll<HTMLElement>("[data-part]")];
    if (this.parts.length < 2) return;
    this.querySelector<HTMLElement>("[data-nav]")?.removeAttribute("hidden");
    this.querySelector("[data-prev]")?.addEventListener("click", () => this.go(this.index - 1));
    this.querySelector("[data-next]")?.addEventListener("click", () => this.go(this.index + 1));
    this.show(false);
  }

  private go(next: number): void {
    const clamped = Math.max(0, Math.min(this.parts.length - 1, next));
    if (clamped === this.index) return;
    this.index = clamped;
    this.show(true);
  }

  private show(focus: boolean): void {
    this.parts.forEach((part, i) => {
      part.hidden = i !== this.index;
    });
    const total = this.parts.length;
    const prev = this.querySelector<HTMLButtonElement>("[data-prev]");
    const next = this.querySelector<HTMLButtonElement>("[data-next]");
    const atEnd = this.index === total - 1;
    // focus always moves to the part heading below, so a button that becomes disabled never held it
    if (prev) prev.disabled = this.index === 0;
    if (next) next.disabled = atEnd;
    const status = this.querySelector<HTMLElement>("[data-status]");
    if (status) status.textContent = (this.dataset.statusText ?? "Part {n} of {total}").replace("{n}", String(this.index + 1)).replace("{total}", String(total));
    const sentinel = document.querySelector<HTMLElement>("[data-end-of-content]");
    if (sentinel) sentinel.hidden = !atEnd;
    if (focus) this.parts[this.index]?.querySelector<HTMLElement>("[tabindex]")?.focus();
  }
}

if (!customElements.get("ulams-parts")) customElements.define("ulams-parts", UlamsParts);

export {};
