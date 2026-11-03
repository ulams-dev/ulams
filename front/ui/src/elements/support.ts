import { createInsightsClient } from "@ulams/sdk";

/**
 * <ulams-support assignment-id="01J…">
 *   div[data-feedback][hidden]: button[data-answer=helpful|not_helpful][aria-pressed], button[data-hide]
 *   div[data-confirm][hidden]: button[data-hide-yes], button[data-hide-no]
 *   p[role=status][data-status-text]
 * </ulams-support>
 *
 * Progressive enhancement of the "Extra help for you" card: the controls stay hidden without scripts and
 * are shown here. "This helped" / "Not helpful" are toggle buttons (`aria-pressed`) that save the answer
 * through the BFF and say so in the polite status line. "Hide extra help for this lesson" asks first, in
 * place; on yes it hides the item, removes the card, announces it in a page-level polite region and moves
 * focus to the lesson heading so it is not lost.
 */
const insights = createInsightsClient({ baseUrl: "/bff", timeoutMs: 20_000 });

type Answer = "helpful" | "not_helpful";

/** One polite live region for the page, outside the card (the card is removed when the help is hidden). */
function announce(message: string): void {
  let region = document.getElementById("u-support-live");
  if (!region) {
    region = document.createElement("div");
    region.id = "u-support-live";
    region.className = "u-visually-hidden";
    region.setAttribute("role", "status");
    region.setAttribute("aria-live", "polite");
    document.body.append(region);
  }
  region.textContent = "";
  // clear first and set the text a moment later, so a repeat of the same text is read again
  setTimeout(() => {
    region!.textContent = message;
  }, 50);
}

function focusLessonHeading(): void {
  const heading = document.querySelector<HTMLElement>("main h1, h1");
  if (!heading) return;
  if (!heading.hasAttribute("tabindex")) heading.tabIndex = -1;
  heading.focus();
}

class UlamsSupport extends HTMLElement {
  connectedCallback(): void {
    if (this.dataset.ready) return;
    const assignmentId = this.getAttribute("assignment-id");
    const feedback = this.querySelector<HTMLElement>("[data-feedback]");
    if (!assignmentId || !feedback) return;
    this.dataset.ready = "1";
    feedback.hidden = false;
    const answers = [...this.querySelectorAll<HTMLButtonElement>("button[data-answer]")];
    for (const button of answers) button.addEventListener("click", () => void this.answer(assignmentId, button, answers));
    const confirm = this.querySelector<HTMLElement>("[data-confirm]");
    const hide = this.querySelector<HTMLButtonElement>("button[data-hide]");
    hide?.addEventListener("click", () => {
      feedback.hidden = true;
      if (confirm) confirm.hidden = false;
      this.querySelector<HTMLButtonElement>("button[data-hide-no]")?.focus();
    });
    this.querySelector<HTMLButtonElement>("button[data-hide-no]")?.addEventListener("click", () => {
      if (confirm) confirm.hidden = true;
      feedback.hidden = false;
      hide?.focus();
    });
    this.querySelector<HTMLButtonElement>("button[data-hide-yes]")?.addEventListener("click", (event) => void this.hide(assignmentId, event.currentTarget as HTMLButtonElement));
  }

  private status(text: string): void {
    const out = this.querySelector<HTMLElement>("[data-status-text]");
    if (out) out.textContent = text;
  }

  private async answer(assignmentId: string, button: HTMLButtonElement, all: HTMLButtonElement[]): Promise<void> {
    if (button.getAttribute("aria-pressed") === "true") return;
    const previous = all.find((b) => b.getAttribute("aria-pressed") === "true");
    const value = button.dataset.answer as Answer;
    for (const b of all) b.disabled = true;
    button.setAttribute("aria-busy", "true");
    try {
      await insights.feedback(assignmentId, value === "helpful");
      for (const b of all) b.setAttribute("aria-pressed", b === button ? "true" : "false");
      this.status(this.dataset.savedText ?? "Saved.");
    } catch {
      if (previous) previous.setAttribute("aria-pressed", "true");
      this.status(this.dataset.failedText ?? "Could not save that. Try again.");
    } finally {
      button.removeAttribute("aria-busy");
      for (const b of all) b.disabled = false;
    }
  }

  private async hide(assignmentId: string, yes: HTMLButtonElement): Promise<void> {
    yes.disabled = true;
    try {
      await insights.hide(assignmentId);
    } catch {
      yes.disabled = false;
      this.status(this.dataset.failedText ?? "Could not save that. Try again.");
      return;
    }
    const message = this.dataset.hiddenText ?? "Extra help hidden for this lesson.";
    this.closest("section")?.remove();
    announce(message);
    focusLessonHeading();
  }
}

if (!customElements.get("ulams-support")) customElements.define("ulams-support", UlamsSupport);
