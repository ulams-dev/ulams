/**
 * <ulams-practice activity-id="practice"> … li[data-challenge] … </ulams-practice>
 *
 * Scaffolded practice. Per challenge:
 * - hints (templates, one per tier) are shown one at a time, in tier order, on request;
 * - a learner answer (radio option, or "I have tried it" for open tasks) is an *attempt*: it
 *   shows the feedback that explains why and fires `ulams:practice-attempt`
 *   ({ activityId, challengeId, optionIndex, correct }, bubbles) and `ulams:complete` on the document;
 * - the worked solution stays inside an inert <template> until an attempt event for that
 *   challenge arrives, then it is inserted into the page.
 * All text comes from template content (set by the server as text), never as HTML.
 */
export interface PracticeAttemptDetail {
  activityId: string;
  challengeId: string;
  optionIndex: number | null;
  correct: boolean | null;
}

export const ATTEMPT_EVENT = "ulams:practice-attempt";

const textOf = (tpl: HTMLTemplateElement | null): string => tpl?.content.textContent?.trim() ?? "";

class UlamsPractice extends HTMLElement {
  private hintsShown = new WeakMap<Element, number>();

  connectedCallback(): void {
    if (this.dataset.ready) return;
    this.dataset.ready = "1";
    this.querySelector("[data-nojs]")?.setAttribute("hidden", "");
    this.addEventListener(ATTEMPT_EVENT, (event) => this.onAttempt(event as CustomEvent<PracticeAttemptDetail>));
    for (const challenge of this.querySelectorAll<HTMLElement>("[data-challenge]")) this.setup(challenge);
  }

  private setup(challenge: HTMLElement): void {
    const hints = [...challenge.querySelectorAll<HTMLTemplateElement>("template[data-hint]")];
    const hintBox = challenge.querySelector<HTMLElement>("[data-hints]");
    if (hintBox && hints.length > 0) {
      hintBox.hidden = false;
      const button = document.createElement("button");
      button.type = "button";
      button.className = "u-practice__hint-btn";
      const list = document.createElement("div");
      list.setAttribute("role", "list");
      const label = (): string => {
        const next = hints[this.hintsShown.get(challenge) ?? 0];
        return next ? `Show a hint (${next.dataset.label ?? "next"})` : "";
      };
      button.textContent = label();
      button.addEventListener("click", () => {
        const shown = this.hintsShown.get(challenge) ?? 0;
        const tpl = hints[shown];
        if (!tpl) return;
        const p = document.createElement("p");
        p.className = "u-practice__hint";
        p.setAttribute("role", "listitem");
        const tier = document.createElement("strong");
        tier.textContent = `${tpl.dataset.label ?? "Hint"}: `;
        p.append(tier, textOf(tpl));
        list.append(p);
        this.hintsShown.set(challenge, shown + 1);
        if (shown + 1 >= hints.length) {
          // The button is about to disappear: move focus to the hint so it is not lost.
          button.remove();
          p.tabIndex = -1;
          p.focus();
        } else {
          button.textContent = label();
        }
      });
      hintBox.append(list, button);
    }
    const actions = challenge.querySelector<HTMLElement>("[data-actions]");
    if (actions) actions.hidden = false;
    challenge.querySelector<HTMLButtonElement>("[data-check]")?.addEventListener("click", () => this.attempt(challenge));
  }

  private attempt(challenge: HTMLElement): void {
    const id = challenge.dataset.challenge ?? "";
    const radios = [...challenge.querySelectorAll<HTMLInputElement>("[data-options] input[type=radio]")];
    const out = challenge.querySelector<HTMLElement>("[data-feedback-out]");
    let optionIndex: number | null = null;
    let correct: boolean | null = null;
    if (radios.length > 0) {
      const chosen = radios.find((r) => r.checked);
      if (!chosen) {
        if (out) out.textContent = "Choose an answer first.";
        return;
      }
      optionIndex = Number(chosen.value);
      correct = chosen.dataset.correct === "1";
      for (const r of radios) r.closest("label")?.classList.remove("u-practice__option--correct", "u-practice__option--wrong");
      chosen.closest("label")?.classList.add(correct ? "u-practice__option--correct" : "u-practice__option--wrong");
      const why = textOf(chosen.closest("label")?.querySelector<HTMLTemplateElement>("template[data-feedback]") ?? null);
      if (out) out.textContent = `${correct ? "Correct" : "Not quite"}. ${why}`;
    } else if (out) {
      out.textContent = "Good. Compare your work with the worked solution below.";
    }
    const detail: PracticeAttemptDetail = { activityId: this.getAttribute("activity-id") ?? "", challengeId: id, optionIndex, correct };
    this.dispatchEvent(new CustomEvent<PracticeAttemptDetail>(ATTEMPT_EVENT, { bubbles: true, detail }));
    // A checked attempt completes a Layout lesson (ADR 0052); only the lesson player's <ulams-progress>
    // listens, and it ignores the event once the topic is complete.
    document.dispatchEvent(new CustomEvent("ulams:complete", { detail: { source: "practice" } }));
  }

  /** The worked solution leaves its template only here, after an attempt. */
  private onAttempt(event: CustomEvent<PracticeAttemptDetail>): void {
    const id = event.detail?.challengeId;
    const challenge = [...this.querySelectorAll<HTMLElement>("[data-challenge]")].find((c) => c.dataset.challenge === id);
    const out = challenge?.querySelector<HTMLElement>("[data-solution-out]");
    if (!challenge || !out || out.childElementCount > 0) return;
    const body = document.createElement("div");
    body.className = "u-practice__solution-body";
    const heading = document.createElement("strong");
    heading.textContent = "Worked solution";
    const p = document.createElement("p");
    p.textContent = textOf(challenge.querySelector<HTMLTemplateElement>("template[data-solution]"));
    body.append(heading, p);
    out.append(body);
  }
}

if (!customElements.get("ulams-practice")) customElements.define("ulams-practice", UlamsPractice);
