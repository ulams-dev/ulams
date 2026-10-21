import { ApiError, createInsightsClient } from "@ulams/sdk";

/**
 * <ulams-reflection assignment-id="01J…" data-max="2000" data-min="10" data-*-text="…">
 *   form[data-form][hidden]: textarea[data-text], p[data-count], button[type=submit]
 *   p[role=status][data-status]
 *   div[data-result][hidden]: p[data-correct|data-missing|data-follow-up|data-left], div[data-sources], button[data-again]
 * </ulams-reflection>
 *
 * "Explain it in your own words" (Feynman reflection). Progressive enhancement: the form stays hidden
 * without scripts. The text goes through the BFF once and is not kept anywhere on the client: nothing in
 * storage, and the box is emptied as soon as feedback arrives. On a failure the text stays so it can be
 * sent again. Feedback is written with `textContent` only. The counter is not a live region (it would
 * speak on every key); limits and failures are announced through the polite status line.
 */
const insights = createInsightsClient({ baseUrl: "/bff", timeoutMs: 45_000 });

const fill = (template: string, values: Record<string, string | number>): string => template.replace(/\{(\w+)\}/g, (m, k: string) => (k in values ? String(values[k]) : m));

class UlamsReflection extends HTMLElement {
  connectedCallback(): void {
    if (this.dataset.ready) return;
    const assignmentId = this.getAttribute("assignment-id");
    const form = this.querySelector<HTMLFormElement>("[data-form]");
    const text = this.querySelector<HTMLTextAreaElement>("[data-text]");
    if (!assignmentId || !form || !text) return;
    this.dataset.ready = "1";
    form.hidden = false;
    this.querySelector<HTMLElement>("[data-nojs]")?.setAttribute("hidden", "");
    const max = Number(this.dataset.max) || 2000;
    const min = Number(this.dataset.min) || 10;
    const count = this.querySelector<HTMLElement>("[data-count]");
    const updateCount = () => {
      if (count) count.textContent = fill(this.dataset.counterText ?? "{n} of {max} characters", { n: text.value.length, max });
    };
    text.addEventListener("input", updateCount);
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      void this.submit(assignmentId, form, text, min, updateCount);
    });
    this.querySelector<HTMLButtonElement>("[data-again]")?.addEventListener("click", () => {
      const result = this.querySelector<HTMLElement>("[data-result]");
      if (result) result.hidden = true;
      form.hidden = false;
      this.say("");
      text.focus();
    });
  }

  private say(message: string): void {
    const out = this.querySelector<HTMLElement>("[data-status]");
    if (out) out.textContent = message;
  }

  private async submit(assignmentId: string, form: HTMLFormElement, text: HTMLTextAreaElement, min: number, updateCount: () => void): Promise<void> {
    const submit = form.querySelector<HTMLButtonElement>("[data-submit]")!;
    if (submit.disabled) return;
    const value = text.value.trim();
    if (value.length < min) {
      this.say(this.dataset.shortText ?? "Write at least 10 characters first.");
      text.focus();
      return;
    }
    submit.disabled = true;
    submit.setAttribute("aria-busy", "true");
    this.say(this.dataset.busyText ?? "Getting feedback…");
    try {
      const feedback = await insights.reflection(assignmentId, value);
      this.show(feedback, form);
      text.value = "";
      updateCount();
    } catch (error) {
      const status = error instanceof ApiError ? error.status : 0;
      this.say(status === 429 ? (this.dataset.limitText ?? "") : status === 503 ? (this.dataset.offText ?? "") : (this.dataset.errorText ?? ""));
      submit.focus();
    } finally {
      submit.disabled = false;
      submit.removeAttribute("aria-busy");
    }
  }

  private show(feedback: Awaited<ReturnType<typeof insights.reflection>>, form: HTMLFormElement): void {
    const set = (selector: string, value: string) => {
      const el = this.querySelector<HTMLElement>(selector);
      if (el) el.textContent = value;
    };
    set("[data-correct]", feedback.correct);
    set("[data-missing]", feedback.missing);
    set("[data-follow-up]", feedback.followUp);
    set("[data-left]", fill(this.dataset.leftText ?? "{n} left today", { n: feedback.remaining }));
    const sources = this.querySelector<HTMLElement>("[data-sources]");
    const list = this.querySelector<HTMLElement>("[data-source-list]");
    if (sources && list) {
      list.replaceChildren(
        ...[...new Set(feedback.citations.map((c) => c.label))].slice(0, 12).map((label) => {
          const li = document.createElement("li");
          li.textContent = label;
          return li;
        })
      );
      sources.hidden = list.childElementCount === 0;
    }
    this.say("");
    form.hidden = true;
    const result = this.querySelector<HTMLElement>("[data-result]");
    if (result) {
      result.hidden = false;
      result.focus();
    }
  }
}

if (!customElements.get("ulams-reflection")) customElements.define("ulams-reflection", UlamsReflection);
