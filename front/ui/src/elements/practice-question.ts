/**
 * <ulams-practice-question data-correct-text data-wrong-text data-choose-text>
 *   fieldset radios (data-correct), template[data-explanation], button[data-check], p[data-result]
 * </ulams-practice-question>
 *
 * One practice question in extra help. "Check" states the result in words ("Correct." / "Not quite.")
 * and marks the chosen option with a word and an outline style, never colour alone; the explanation
 * stays in an inert <template> until the first check. Nothing is recorded or sent (open question O5:
 * practice stays on the device). All text is inserted as text, never HTML.
 */
class UlamsPracticeQuestion extends HTMLElement {
  connectedCallback(): void {
    if (this.dataset.ready) return;
    this.dataset.ready = "1";
    this.querySelector("[data-nojs]")?.setAttribute("hidden", "");
    const button = this.querySelector<HTMLButtonElement>("button[data-check]");
    if (!button) return;
    button.hidden = false;
    button.addEventListener("click", () => this.check());
  }

  private check(): void {
    const radios = [...this.querySelectorAll<HTMLInputElement>("input[type=radio]")];
    const result = this.querySelector<HTMLElement>("[data-result]");
    if (!result) return;
    const chosen = radios.find((r) => r.checked);
    if (!chosen) {
      result.textContent = this.dataset.chooseText ?? "Choose an answer first.";
      return;
    }
    const correct = chosen.dataset.correct === "1";
    for (const radio of radios) {
      const label = radio.closest("label");
      label?.classList.remove("u-pq__option--correct", "u-pq__option--wrong");
      const mark = label?.querySelector<HTMLElement>("[data-mark]");
      if (mark) mark.textContent = "";
    }
    const label = chosen.closest("label");
    label?.classList.add(correct ? "u-pq__option--correct" : "u-pq__option--wrong");
    const word = correct ? (this.dataset.correctText ?? "Correct") : (this.dataset.wrongText ?? "Not quite");
    const mark = label?.querySelector<HTMLElement>("[data-mark]");
    if (mark) mark.textContent = word;
    const explanation = this.querySelector<HTMLTemplateElement>("template[data-explanation]")?.content.textContent?.trim() ?? "";
    result.textContent = `${word}. ${explanation}`.trim();
  }
}

if (!customElements.get("ulams-practice-question")) customElements.define("ulams-practice-question", UlamsPracticeQuestion);

export {};
