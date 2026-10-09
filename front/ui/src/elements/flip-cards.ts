/**
 * <ulams-flip-cards> … li > .u-flip__front + [data-back] … </ulams-flip-cards>
 *
 * Progressive enhancement: the server renders front and back; this hides each back behind a
 * native button ("Show answer" / "Hide answer", aria-expanded + aria-controls), so Enter and
 * Space work and screen readers hear the state. Without JavaScript both sides are visible.
 */
class UlamsFlipCards extends HTMLElement {
  connectedCallback(): void {
    for (const back of this.querySelectorAll<HTMLElement>("[data-back]")) {
      if (back.dataset.ready) continue;
      back.dataset.ready = "1";
      const button = document.createElement("button");
      button.type = "button";
      button.className = "u-flip__toggle";
      button.setAttribute("aria-controls", back.id);
      const set = (open: boolean): void => {
        back.hidden = !open;
        button.setAttribute("aria-expanded", String(open));
        button.textContent = open ? "Hide answer" : "Show answer";
      };
      button.addEventListener("click", () => set(back.hidden));
      set(false);
      back.before(button);
    }
  }
}

if (!customElements.get("ulams-flip-cards")) customElements.define("ulams-flip-cards", UlamsFlipCards);

export {};
