/**
 * <ulams-frame-slider> with [data-prev], [data-next], [data-status] and [data-frame] slides.
 *
 * Without JavaScript every frame stays visible. With it, one frame is shown at a time, the
 * arrows (and Left/Right when the player is focused) wrap around, and the status reads "3 / 12".
 */
class UlamsFrameSlider extends HTMLElement {
  private index = 0;
  private slides: HTMLElement[] = [];
  private status: HTMLElement | null = null;

  connectedCallback(): void {
    if (this.dataset.ready) return;
    this.dataset.ready = "1";
    this.slides = [...this.querySelectorAll<HTMLElement>("[data-frame]")];
    this.status = this.querySelector("[data-status]");
    this.querySelector("[data-prev]")?.addEventListener("click", () => this.go(-1));
    this.querySelector("[data-next]")?.addEventListener("click", () => this.go(1));
    this.addEventListener("keydown", (event) => {
      if (event.key === "ArrowLeft") {
        event.preventDefault();
        this.go(-1);
      } else if (event.key === "ArrowRight") {
        event.preventDefault();
        this.go(1);
      }
    });
    if (!this.hasAttribute("tabindex")) this.tabIndex = 0;
    this.show(0);
  }

  private go(delta: number): void {
    const total = this.slides.length;
    if (total === 0) return;
    this.show((this.index + delta + total) % total);
  }

  private show(index: number): void {
    this.index = index;
    this.slides.forEach((slide, i) => {
      const on = i === index;
      slide.hidden = !on;
      slide.classList.toggle("is-active", on);
    });
    if (this.status) this.status.textContent = `${index + 1} / ${this.slides.length}`;
  }
}

if (!customElements.get("ulams-frame-slider")) customElements.define("ulams-frame-slider", UlamsFrameSlider);

export {};
