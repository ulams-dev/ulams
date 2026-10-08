import type { QuizAnswerValue, QuizAttempt, QuizQuestion } from "@ulams/sdk";
import { announceComplete, bff } from "./bff.ts";

/**
 * <ulams-quiz quiz-id="1" pass-score="5" finish-href="/learn/1/17"> [data-start] … </ulams-quiz>
 *
 * GIFT quiz through the quiz-attempts API: start (or resume) an attempt, one question per
 * screen, answers saved as the learner moves on, timer from `end_at`, finish, score and a
 * per-question review. All API text is inserted with textContent (untrusted content).
 */

type El = HTMLElement;

function h<K extends keyof HTMLElementTagNameMap>(
  tag: K,
  attrs: Record<string, string | number | boolean | undefined> = {},
  ...children: Array<Node | string | null | undefined | false>
): HTMLElementTagNameMap[K] {
  const el = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs)) {
    if (v === undefined || v === false) continue;
    if (k === "class") el.className = String(v);
    else el.setAttribute(k, v === true ? "" : String(v));
  }
  for (const child of children) if (child !== null && child !== undefined && child !== false) el.append(child);
  return el;
}

const TYPE_LABEL: Record<string, string> = {
  multiple_choice: "Choose one answer",
  multiple_choice_with_multiple_right_answers: "Choose all that apply",
  true_false: "True or false?",
  short_answers: "Type a short answer",
  matching: "Match each item",
  numerical_question: "Enter a number",
  essay: "Write your answer",
  description: "Take a breath",
};

const answers = (q: QuizQuestion): string[] => (Array.isArray(q.options) ? [] : (q.options.answers ?? []));

class UlamsQuiz extends HTMLElement {
  private attempt: QuizAttempt | null = null;
  private index = 0;
  private values = new Map<number, QuizAnswerValue>();
  /** Questions whose answer changed since it was last saved. */
  private dirty = new Set<number>();
  private stage!: El;
  private live!: El;
  private tick: number | undefined;

  connectedCallback(): void {
    this.stage = this.querySelector<El>("[data-stage]") ?? this;
    this.live = this.querySelector<El>("[data-live]") ?? h("p", { class: "u-visually-hidden", "aria-live": "polite" });
    if (!this.live.isConnected) this.append(this.live);
    this.querySelector<HTMLButtonElement>("[data-start]")?.addEventListener("click", () => void this.start());
  }

  disconnectedCallback(): void {
    window.clearInterval(this.tick);
  }

  private say(text: string): void {
    this.live.textContent = text;
  }

  private async start(): Promise<void> {
    const quizId = Number(this.getAttribute("quiz-id"));
    this.setAttribute("state", "loading");
    this.stage.replaceChildren(this.skeleton());
    try {
      this.attempt = await bff.quiz.start(quizId);
      for (const a of this.attempt.answers ?? []) this.values.set(a.topic_gift_question_id, a.answer);
      this.attempt.questions.sort((a, b) => (a.order ?? 0) - (b.order ?? 0));
      this.index = 0;
      this.setAttribute("state", "running");
      this.startTimer();
      this.renderQuestion();
    } catch (error) {
      this.setAttribute("state", "error");
      const message = (error as { status?: number }).status === 403 ? "No attempts left for this quiz." : "The quiz could not start. Try again in a moment.";
      this.stage.replaceChildren(h("p", { class: "u-quiz__error", role: "alert" }, message), this.retryButton());
    }
  }

  private skeleton(): El {
    return h(
      "div",
      { class: "u-quiz__skeleton", "aria-hidden": "true" },
      h("div", { class: "u-skeleton", style: "height:18px;width:30%" }),
      h("div", { class: "u-skeleton", style: "height:34px;width:85%" }),
      h("div", { class: "u-skeleton", style: "height:52px" }),
      h("div", { class: "u-skeleton", style: "height:52px" }),
      h("div", { class: "u-skeleton", style: "height:52px" })
    );
  }

  private retryButton(): El {
    const b = h("button", { class: "u-btn", type: "button" }, "Try again");
    b.addEventListener("click", () => void this.start());
    return b;
  }

  private startTimer(): void {
    const end = this.attempt?.end_at ? Date.parse(this.attempt.end_at) : NaN;
    const clock = this.querySelector<El>("[data-clock]");
    if (!clock || Number.isNaN(end)) return;
    clock.hidden = false;
    const update = () => {
      const left = Math.max(0, Math.round((end - Date.now()) / 1000));
      clock.textContent = `${Math.floor(left / 60)}:${String(left % 60).padStart(2, "0")}`;
      clock.toggleAttribute("data-low", left < 60);
      if (left === 0) {
        window.clearInterval(this.tick);
        void this.finish();
      }
    };
    update();
    this.tick = window.setInterval(update, 1000);
  }

  private get question(): QuizQuestion | undefined {
    return this.attempt?.questions[this.index];
  }

  private renderQuestion(): void {
    const q = this.question;
    const attempt = this.attempt;
    if (!q || !attempt) return;
    const total = attempt.questions.length;
    const legendId = `q-${q.id}-legend`;

    const dots = h(
      "ol",
      { class: "u-quiz__dots", "aria-label": "Questions" },
      ...attempt.questions.map((x, i) => {
        const b = h(
          "button",
          { type: "button", "aria-label": `Question ${i + 1}`, "aria-current": i === this.index ? "step" : undefined, "data-answered": this.values.has(x.id) ? "true" : undefined },
          String(i + 1)
        );
        b.addEventListener("click", () => this.go(i));
        return h("li", {}, b);
      })
    );

    const fieldset = h(
      "fieldset",
      { class: "u-quiz__q", "aria-describedby": `q-${q.id}-hint` },
      h("legend", { id: legendId, tabindex: -1 }, q.question),
      h("p", { class: "u-quiz__hint", id: `q-${q.id}-hint` }, TYPE_LABEL[q.type] ?? "", q.score ? ` · ${q.score} pt${q.score > 1 ? "s" : ""}` : ""),
      this.input(q)
    );

    const prev = h("button", { type: "button", class: "u-btn u-btn--ghost", disabled: this.index === 0 }, "Previous");
    prev.addEventListener("click", () => this.go(this.index - 1));
    const last = this.index === total - 1;
    const next = h("button", { type: "button", class: "u-btn" }, last ? "Finish quiz" : "Next");
    next.addEventListener("click", () => (last ? void this.finish() : this.go(this.index + 1)));

    this.stage.replaceChildren(
      h("div", { class: "u-quiz__progress" }, h("span", {}, `Question ${this.index + 1} of ${total}`), h("span", { class: "u-quiz__bar", style: `--p:${((this.index + 1) / total) * 100}%` })),
      dots,
      fieldset,
      h("div", { class: "u-quiz__nav" }, prev, next)
    );
    this.querySelector<El>(`#${legendId}`)?.focus({ preventScroll: true });
  }

  private input(q: QuizQuestion): Node {
    const current = this.values.get(q.id) as Record<string, unknown> | undefined;
    const name = `q${q.id}`;
    const set = (value: QuizAnswerValue) => {
      this.values.set(q.id, value);
      this.dirty.add(q.id);
    };

    if (q.type === "multiple_choice" || q.type === "multiple_choice_with_multiple_right_answers") {
      const multi = q.type !== "multiple_choice";
      const chosen = new Set<string>(multi ? ((current?.multiple as string[]) ?? []) : current?.text ? [String(current.text)] : []);
      return h(
        "div",
        { class: "u-quiz__options" },
        ...answers(q).map((text, i) => {
          const input = h("input", { type: multi ? "checkbox" : "radio", name, value: text, id: `${name}-${i}`, checked: chosen.has(text) });
          input.addEventListener("change", () => {
            if (multi) {
              const all = [...this.querySelectorAll<HTMLInputElement>(`input[name="${name}"]:checked`)].map((x) => x.value);
              set({ multiple: all });
            } else set({ text });
          });
          return h("label", { class: "u-quiz__option", for: `${name}-${i}` }, input, h("span", {}, text));
        })
      );
    }
    if (q.type === "true_false") {
      const value = current?.bool as boolean | undefined;
      return h(
        "div",
        { class: "u-quiz__options u-quiz__options--tf" },
        ...[true, false].map((b) => {
          const input = h("input", { type: "radio", name, id: `${name}-${b}`, checked: value === b });
          input.addEventListener("change", () => set({ bool: b }));
          return h("label", { class: "u-quiz__option", for: `${name}-${b}` }, input, h("span", {}, b ? "True" : "False"));
        })
      );
    }
    if (q.type === "short_answers") {
      const input = h("input", { type: "text", class: "u-quiz__field", id: name, value: String(current?.text ?? ""), autocomplete: "off", "aria-labelledby": `q-${q.id}-legend` });
      input.addEventListener("input", () => set({ text: input.value }));
      return input;
    }
    if (q.type === "numerical_question") {
      const input = h("input", { type: "number", inputmode: "decimal", step: "any", class: "u-quiz__field u-quiz__field--num", id: name, value: current?.numeric !== undefined ? String(current.numeric) : "", "aria-labelledby": `q-${q.id}-legend` });
      input.addEventListener("input", () => {
        if (input.value !== "" && !Number.isNaN(Number(input.value))) set({ numeric: Number(input.value) });
      });
      return input;
    }
    if (q.type === "essay") {
      const area = h("textarea", { class: "u-quiz__field", id: name, rows: 6, "aria-labelledby": `q-${q.id}-legend` });
      area.value = String(current?.text ?? "");
      const counter = h("p", { class: "u-quiz__count", "aria-live": "polite" });
      const count = () => (counter.textContent = `${area.value.trim().split(/\s+/).filter(Boolean).length} words`);
      area.addEventListener("input", () => {
        set({ text: area.value });
        count();
      });
      count();
      return h("div", {}, area, counter);
    }
    if (q.type === "matching" && !Array.isArray(q.options)) {
      const subs = q.options.sub_questions ?? [];
      const options = q.options.sub_answers ?? [];
      const chosen = { ...((current?.matching as Record<string, string>) ?? {}) };
      return h(
        "div",
        { class: "u-quiz__matching" },
        ...subs.map((sub, i) => {
          const select = h("select", { id: `${name}-${i}`, class: "u-quiz__field" }, h("option", { value: "" }, "Choose…"), ...options.map((o) => h("option", { value: o, selected: chosen[sub] === o }, o)));
          select.addEventListener("change", () => {
            chosen[sub] = select.value;
            set({ matching: { ...chosen } });
          });
          return h("div", { class: "u-quiz__pair" }, h("label", { for: `${name}-${i}` }, sub), select);
        })
      );
    }
    // description: nothing to answer
    return h("p", { class: "u-quiz__note" }, "No answer needed. Continue when you are ready.");
  }

  private async save(q: QuizQuestion | undefined): Promise<void> {
    if (!q || !this.attempt || q.type === "description") return;
    const value = this.values.get(q.id);
    if (!value || !this.dirty.has(q.id)) return;
    this.dirty.delete(q.id);
    try {
      await bff.quiz.answer(this.attempt.id, q.id, value);
    } catch {
      this.dirty.add(q.id);
      this.say("Your last answer could not be saved.");
    }
  }

  private go(index: number): void {
    if (!this.attempt) return;
    const leaving = this.question;
    void this.save(leaving);
    this.index = Math.max(0, Math.min(index, this.attempt.questions.length - 1));
    this.renderQuestion();
  }

  private async finish(): Promise<void> {
    if (!this.attempt || this.getAttribute("state") === "finishing") return;
    this.setAttribute("state", "finishing");
    window.clearInterval(this.tick);
    this.stage.replaceChildren(h("div", { class: "u-quiz__scoring" }, h("span", { class: "u-quiz__spinner", "aria-hidden": "true" }), h("p", {}, "Scoring your answers…")));
    try {
      // the API scores answers as they are saved; ending closes the attempt
      await Promise.all(this.attempt.questions.map((q) => this.save(q)));
      const ended = await bff.quiz.end(this.attempt.id);
      const result = await bff.quiz.get(ended.id).catch(() => ended);
      this.renderResult(result);
    } catch {
      this.setAttribute("state", "running");
      this.say("Finishing failed. Try again.");
      this.renderQuestion();
    }
  }

  private renderResult(result: QuizAttempt): void {
    this.setAttribute("state", "done");
    const clock = this.querySelector<El>("[data-clock]");
    if (clock) clock.hidden = true;
    const score = Math.round((result.result_score ?? 0) * 10) / 10;
    const max = result.max_score || 1;
    const pass = Number(this.getAttribute("pass-score") || result.min_pass_score || 0);
    const passed = result.is_passed ?? score >= pass;
    const percent = Math.round((score / max) * 100);
    const byQuestion = new Map((result.answers ?? []).map((a) => [a.topic_gift_question_id, a]));

    const review = h(
      "ol",
      { class: "u-quiz__review" },
      ...result.questions
        .filter((q) => q.type !== "description")
        .map((q) => {
          const a = byQuestion.get(q.id);
          const pending = q.type === "essay";
          const ok = !pending && a?.score !== undefined && a?.score !== null && a.score >= q.score;
          const state = pending ? "pending" : ok ? "ok" : "miss";
          return h(
            "li",
            { "data-result": state },
            h("span", { class: "u-quiz__mark", "aria-hidden": "true" }, pending ? "…" : ok ? "✓" : "✗"),
            h("span", {}, q.question, h("small", {}, pending ? "Reviewed by your tutor" : ok ? "Correct" : a ? "Not quite" : "Not answered")),
            a?.feedback ? h("em", {}, a.feedback) : null
          );
        })
    );

    const continueHref = this.getAttribute("finish-href");
    this.stage.replaceChildren(
      h(
        "div",
        { class: "u-quiz__result", "data-passed": String(passed) },
        h("div", { class: "u-quiz__ring", style: `--p:${percent}`, role: "img", "aria-label": `Score ${score} of ${max}` }, h("strong", {}, `${score}/${max}`)),
        h("h3", {}, passed ? "Passed. Well done!" : "Not passed yet"),
        h("p", {}, passed ? `You scored ${percent}%. Answers that need a tutor are reviewed separately.` : `You need ${pass} points to pass. Review the answers and try again.`),
        review,
        h(
          "div",
          { class: "u-quiz__nav" },
          (() => {
            const again = h("button", { type: "button", class: "u-btn u-btn--ghost" }, "New attempt");
            again.addEventListener("click", () => {
              this.values.clear();
              this.dirty.clear();
              void this.start();
            });
            return again;
          })(),
          continueHref ? h("a", { class: "u-btn", href: continueHref }, "Continue") : null
        )
      )
    );
    this.say(`Quiz finished. Score ${score} of ${max}.`);
    if (passed) announceComplete("quiz");
    this.querySelector<El>(".u-quiz__result h3")?.setAttribute("tabindex", "-1");
    this.querySelector<El>(".u-quiz__result h3")?.focus();
  }
}

if (!customElements.get("ulams-quiz")) customElements.define("ulams-quiz", UlamsQuiz);
