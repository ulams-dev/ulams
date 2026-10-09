/**
 * <ulams-code-block> … [data-code] … [data-live] </ulams-code-block>
 *
 * Adds a "Copy" button to the bar above the listing. Copies the code text (never the line
 * numbers, which are CSS counters) and announces the result in a polite live region.
 * "Run" is Phase 7.2 and is not offered here.
 */
class UlamsCodeBlock extends HTMLElement {
  connectedCallback(): void {
    const bar = this.querySelector<HTMLElement>(".u-code__bar");
    if (!bar || bar.querySelector(".u-code__copy")) return;
    const button = document.createElement("button");
    button.type = "button";
    button.className = "u-code__copy";
    button.textContent = "Copy";
    button.addEventListener("click", () => void this.copy(button));
    bar.append(button);
  }

  private async copy(button: HTMLButtonElement): Promise<void> {
    const code = this.querySelector("[data-code]")?.textContent ?? "";
    const live = this.querySelector("[data-live]");
    try {
      await navigator.clipboard.writeText(code.replace(/\n$/, ""));
      button.textContent = "Copied";
      if (live) live.textContent = "Code copied to the clipboard";
    } catch {
      button.textContent = "Copy failed";
      if (live) live.textContent = "The code could not be copied. Select it and copy it by hand.";
    }
    window.setTimeout(() => {
      button.textContent = "Copy";
      if (live) live.textContent = "";
    }, 2000);
  }
}

if (!customElements.get("ulams-code-block")) customElements.define("ulams-code-block", UlamsCodeBlock);

export {};
