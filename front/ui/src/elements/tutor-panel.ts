import { tutorFailure, TUTOR_QUESTION_CHARS, type TutorAnswer, type TutorRun, type TutorCitation, type TutorConversationSummary, type TutorMessage, type TutorRefusal, type TutorStatus } from "@ulams/sdk";
import { renderBasicMarkdown } from "../lib/markdown-basic.ts";
import { fill, tutorStrings, type TutorStrings } from "../lib/tutor-strings.ts";
import type { TutorContext } from "./tutor.ts";

/**
 * The tutor panel behind `<ulams-tutor>`, loaded when the launcher is first used. A native
 * `<dialog>`: a non-modal side sheet from 720 px up (the lesson stays readable), a full-screen
 * modal below. Focus goes to the question field on open; Esc and the close button return it to
 * the launcher. The conversation is a polite live log that receives a question and its answer
 * together, so each finished answer is announced once.
 */
const WIDE = "(min-width: 720px)";
const SHEET_WIDTH = 420;

const STYLE = `
:host { font: inherit; }
* { box-sizing: border-box; }
dialog { position: fixed; inset: 0 0 0 auto; margin: 0; width: min(${SHEET_WIDTH}px, 100vw); max-width: 100vw; height: 100dvh; max-height: none; padding: 0; border: 0; border-inline-start: 1px solid var(--ulams-color-border, #d0d0d8); background: var(--ulams-color-bg, #fff); color: var(--ulams-color-text, #1b1b1f); box-shadow: -8px 0 24px rgb(0 0 0 / 0.08); }
dialog[open] { display: flex; flex-direction: column; }
dialog::backdrop { background: rgb(0 0 0 / 0.4); }
@media (max-width: 719.98px) { dialog { inset: 0; width: 100vw; border: 0; box-shadow: none; } }
header { display: flex; align-items: center; gap: 8px; padding: 12px 16px; border-bottom: 1px solid var(--ulams-color-border, #d0d0d8); }
h2 { margin: 0; font-size: 1.1rem; flex: 1; }
p { margin: 0; }
button { font: inherit; color: inherit; }
button:focus-visible, a:focus-visible, textarea:focus-visible, summary:focus-visible, [tabindex]:focus-visible { outline: 3px solid var(--ulams-color-primary, #1d4ed8); outline-offset: 2px; }
.btn { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; min-width: 44px; padding: 0 16px; border: 1px solid var(--ulams-color-border, #d0d0d8); border-radius: 8px; background: var(--ulams-color-surface, #fff); font-weight: 600; cursor: pointer; }
.btn.primary { background: var(--ulams-color-primary, #1d4ed8); border-color: var(--ulams-color-primary, #1d4ed8); color: var(--ulams-color-on-primary, #fff); }
.btn[disabled] { opacity: 0.6; cursor: not-allowed; }
.muted { color: var(--ulams-color-muted, #595965); font-size: 0.875rem; }
.meta { display: grid; gap: 8px; padding: 12px 16px 0; }
.note { padding: 10px 12px; border: 1px solid var(--ulams-color-border, #d0d0d8); border-inline-start: 4px solid var(--ulams-color-primary, #1d4ed8); border-radius: 6px; background: var(--ulams-color-surface, #fff); font-size: 0.9rem; }
details { padding: 0 16px; }
summary { min-height: 44px; display: flex; align-items: center; cursor: pointer; font-weight: 600; }
.earlier { display: grid; gap: 8px; padding: 0 0 12px; margin: 0; list-style: none; }
.earlier li { display: grid; gap: 4px; padding: 8px 10px; border: 1px solid var(--ulams-color-border, #d0d0d8); border-radius: 8px; }
.row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.log { flex: 1; min-height: 120px; overflow-y: auto; display: grid; align-content: start; gap: 12px; padding: 12px 16px; }
.msg { display: grid; gap: 6px; }
.msg.learner { justify-self: end; max-width: 92%; padding: 8px 12px; border-radius: 12px; background: var(--ulams-color-surface, #f3f3f7); border: 1px solid var(--ulams-color-border, #d0d0d8); }
.msg.tutor { padding: 10px 12px; border: 1px solid var(--ulams-color-border, #d0d0d8); border-radius: 12px; }
.who { font-size: 0.8rem; font-weight: 700; color: var(--ulams-color-muted, #595965); }
.ai { display: inline-block; justify-self: start; padding: 2px 8px; border-radius: 999px; border: 1px solid var(--ulams-color-border, #d0d0d8); font-size: 0.75rem; font-weight: 700; color: var(--ulams-color-muted, #595965); }
.md { overflow-wrap: anywhere; line-height: 1.5; }
.md > :first-child { margin-top: 0; }
.md > :last-child { margin-bottom: 0; }
.md pre { overflow-x: auto; padding: 8px; border-radius: 6px; background: var(--ulams-color-surface, #f3f3f7); }
.md a, .sources a { color: var(--ulams-color-primary-on-light, #1d4ed8); text-underline-offset: 3px; }
.sources { display: grid; gap: 4px; }
.sources ul { margin: 0; padding-inline-start: 20px; }
.empty, .pending { padding: 0 16px; }
.pending { display: grid; gap: 4px; }
.draft { white-space: pre-wrap; overflow-wrap: anywhere; line-height: 1.5; }
form { display: grid; gap: 8px; padding: 12px 16px 16px; border-top: 1px solid var(--ulams-color-border, #d0d0d8); }
label { font-weight: 600; }
textarea { width: 100%; min-height: 88px; resize: vertical; padding: 10px; border: 1px solid var(--ulams-color-text, #1b1b1f); border-radius: 8px; background: var(--ulams-color-bg, #fff); color: inherit; font: inherit; }
.error { display: grid; gap: 8px; padding: 10px 12px; margin: 0 16px; border: 1px solid var(--ulams-color-danger, #b3261e); border-radius: 8px; }
[hidden] { display: none !important; }
.vh { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); white-space: nowrap; }
`;

const h = <K extends keyof HTMLElementTagNameMap>(tag: K, attrs: Record<string, string> = {}, ...kids: Array<Node | string>): HTMLElementTagNameMap[K] => {
  const el = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs)) el.setAttribute(k, v);
  for (const kid of kids) el.append(kid);
  return el;
};

/**
 * The part of a streamed draft to show. The server already writes text in sentence-sized steps; with reduced
 * motion the panel also holds back an unfinished sentence, so text never grows word by word.
 */
export function visibleDraft(text: string, reducedMotion: boolean): string {
  if (!reducedMotion) return text;
  const match = /^[\s\S]*[.!?…。！？]["”’)\]]*(\s|$)/.exec(text);
  return match ? match[0] : "";
}

const RUN_KEY = (course: number) => `ulams-tutor-run:${course}`;

/** Fills `{course}` and `{topic}`; only relative and http(s) templates are accepted. */
export function citationHref(template: string, course: number, topic: number): string {
  const ok = /^\/(?!\/)/.test(template) || /^https?:\/\//i.test(template);
  return (ok ? template : "#").replace("{course}", String(course)).replace("{topic}", String(topic));
}

export class TutorPanel {
  private readonly s: TutorStrings;
  private readonly dialog: HTMLDialogElement;
  private readonly host: HTMLElement;
  private readonly log: HTMLElement;
  private readonly pending: HTMLElement;
  private readonly empty: HTMLElement;
  private readonly quota: HTMLElement;
  private readonly attempt: HTMLElement;
  private readonly error: HTMLElement;
  private readonly status: HTMLElement;
  private readonly field: HTMLTextAreaElement;
  private readonly counter: HTMLElement;
  private readonly send: HTMLButtonElement;
  private readonly earlierBody: HTMLElement;
  private readonly earlier: HTMLDetailsElement;
  private conversationId: string | null = null;
  private remaining: number | null = null;
  private limit: number | null = null;
  private busy = false;
  private streaming = false;
  private previousInset = "";

  constructor(private readonly ctx: TutorContext) {
    this.s = tutorStrings(ctx.lang);
    const s = this.s;

    const close = h("button", { type: "button", class: "btn", "aria-label": s.close }, "×");
    close.addEventListener("click", () => this.close());
    const fresh = h("button", { type: "button", class: "btn" }, s.newConversation);
    fresh.addEventListener("click", () => this.reset(true));
    const heading = h("h2", { id: "tutor-title" }, s.title);
    this.quota = h("p", { class: "muted", "data-quota": "" });
    this.attempt = h("p", { class: "note", role: "note", hidden: "" }, s.attemptNotice);
    this.earlierBody = h("div", {});
    this.earlier = h("details", { "data-earlier": "" }, h("summary", {}, s.earlier), this.earlierBody);
    this.log = h("div", { class: "log", role: "log", "aria-live": "polite", "aria-label": s.log, tabindex: "0", "data-log": "" });
    this.empty = h("p", { class: "empty muted" }, s.empty);
    this.pending = h("div", { class: "pending", hidden: "" });
    this.error = h("div", { class: "error", role: "alert", hidden: "" });
    this.status = h("p", { class: "vh", role: "status" });
    this.counter = h("span", { class: "muted", id: "tutor-counter" });
    this.field = h("textarea", { id: "tutor-question", name: "question", maxlength: String(TUTOR_QUESTION_CHARS), "aria-describedby": "tutor-hint tutor-counter", rows: "3" });
    this.send = h("button", { type: "submit", class: "btn primary" }, s.ask);
    const form = h(
      "form",
      { novalidate: "" },
      h("label", { for: "tutor-question" }, s.question),
      this.field,
      h("p", { class: "muted", id: "tutor-hint" }, s.hint),
      h("div", { class: "row" }, this.send, this.counter)
    );
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      void this.submit();
    });
    this.field.addEventListener("input", () => this.updateCounter());
    this.field.addEventListener("keydown", (event) => {
      if (event.key === "Enter" && (event.ctrlKey || event.metaKey)) {
        event.preventDefault();
        void this.submit();
      }
    });

    this.dialog = h(
      "dialog",
      { "aria-labelledby": "tutor-title" },
      h("header", {}, heading, fresh, close),
      h("div", { class: "meta" }, this.quota, this.attempt),
      this.earlier,
      this.log,
      this.pending,
      this.error,
      form,
      this.status
    );
    this.dialog.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && this.dialog.open) {
        event.stopPropagation();
        this.close();
      }
    });
    this.dialog.addEventListener("close", () => this.afterClose());
    // The panel lives under <body>, in a shadow root of its own: a `position: fixed` dialog inside the sticky
    // lesson bar would be positioned against the bar (its backdrop-filter makes it the containing block).
    this.host = h("div", { "data-tutor-panel": "" });
    const style = h("style", {});
    style.textContent = STYLE;
    this.host.attachShadow({ mode: "open" }).append(style, this.dialog);
    document.body.append(this.host);
    this.reset(false);
    this.updateCounter();
  }

  open(): void {
    if (this.dialog.open) return;
    const wide = matchMedia(WIDE).matches;
    if (wide) {
      this.dialog.show();
      this.previousInset = document.body.style.paddingInlineEnd;
      document.body.style.paddingInlineEnd = `${SHEET_WIDTH}px`;
    } else {
      this.dialog.showModal();
    }
    this.ctx.launcher.setAttribute("aria-expanded", "true");
    this.field.focus();
    void this.refresh().then(() => this.resume());
  }

  close(): void {
    if (this.dialog.open) this.dialog.close();
  }

  /** Removes the panel (the element left the page). */
  destroy(): void {
    this.close();
    this.host.remove();
  }

  private afterClose(): void {
    document.body.style.paddingInlineEnd = this.previousInset;
    this.ctx.launcher.setAttribute("aria-expanded", "false");
    this.ctx.launcher.focus();
  }

  private say(text: string): void {
    this.status.textContent = "";
    // a changed node is announced even when the sentence repeats
    queueMicrotask(() => (this.status.textContent = text));
  }

  private date(iso: string): string {
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    try {
      return new Intl.DateTimeFormat(this.ctx.lang || undefined, { dateStyle: "medium", timeStyle: "short" }).format(d);
    } catch {
      return d.toLocaleString();
    }
  }

  private updateCounter(): void {
    this.counter.textContent = fill(this.s.counter, { n: this.field.value.length, max: TUTOR_QUESTION_CHARS });
  }

  private paintStatus(status: TutorStatus): void {
    this.streaming = status.streaming === true;
    this.remaining = status.remaining;
    this.limit = status.dailyLimit;
    this.quota.textContent = status.remaining > 0 ? fill(this.s.remaining, { n: status.remaining, limit: status.dailyLimit }) : fill(this.s.remainingNone, { n: status.dailyLimit });
    this.attempt.hidden = !status.attemptActive;
    const blocked = status.remaining <= 0;
    this.send.disabled = blocked;
    this.field.disabled = blocked;
  }

  /** Reloads the status line and the list of earlier conversations. */
  private async refresh(): Promise<void> {
    const { client, courseId } = this.ctx;
    this.quota.textContent = this.s.loading;
    try {
      this.paintStatus(await client.status(courseId));
    } catch (error) {
      this.quota.textContent = "";
      this.fail(error);
    }
    try {
      this.paintEarlier(await client.conversations(courseId));
    } catch {
      this.earlierBody.replaceChildren(h("p", { class: "muted" }, this.s.noEarlier));
    }
  }

  private paintEarlier(list: TutorConversationSummary[]): void {
    if (list.length === 0) {
      this.earlierBody.replaceChildren(h("p", { class: "muted" }, this.s.noEarlier));
      return;
    }
    const ul = h("ul", { class: "earlier", role: "list" });
    for (const item of list) ul.append(this.earlierItem(item));
    this.earlierBody.replaceChildren(ul);
  }

  private earlierItem(item: TutorConversationSummary): HTMLElement {
    const s = this.s;
    const name = fill(s.conversationOf, { date: this.date(item.lastMessageAt) });
    const li = h("li", { "data-conversation": item.id });
    const controls = h("div", { class: "row" });
    const open = h("button", { type: "button", class: "btn", "aria-label": `${s.open}: ${name}` }, s.open);
    const del = h("button", { type: "button", class: "btn", "aria-label": `${s.del}: ${name}` }, s.del);
    open.addEventListener("click", () => void this.openConversation(item.id));
    del.addEventListener("click", () => {
      const keep = h("button", { type: "button", class: "btn" }, s.deleteCancel);
      const yes = h("button", { type: "button", class: "btn primary" }, s.deleteYes);
      keep.addEventListener("click", () => {
        controls.replaceChildren(open, del);
        del.focus();
      });
      yes.addEventListener("click", () => void this.deleteConversation(item.id, li));
      controls.replaceChildren(h("p", { role: "alert" }, s.deleteConfirm), yes, keep);
      keep.focus();
    });
    controls.append(open, del);
    li.append(h("p", {}, name), h("p", { class: "muted" }, fill(s.keptUntil, { date: this.date(item.expiresAt) })), controls);
    return li;
  }

  private async openConversation(id: string): Promise<void> {
    this.clearError();
    try {
      const conversation = await this.ctx.client.conversation(id);
      this.conversationId = conversation.id;
      this.log.replaceChildren(...conversation.messages.map((m) => this.fromHistory(m)));
      if (conversation.messages.length === 0) this.log.append(this.empty);
      this.earlier.open = false;
      this.log.scrollTop = this.log.scrollHeight;
      this.log.focus();
      this.say(this.s.opened);
    } catch (error) {
      this.fail(error);
    }
  }

  private async deleteConversation(id: string, row: HTMLElement): Promise<void> {
    try {
      await this.ctx.client.deleteConversation(id);
    } catch (error) {
      this.fail(error);
      return;
    }
    const next = (row.nextElementSibling ?? row.previousElementSibling) as HTMLElement | null;
    row.remove();
    if (this.conversationId === id) this.reset(false);
    if (!this.earlierBody.querySelector("li")) this.earlierBody.replaceChildren(h("p", { class: "muted" }, this.s.noEarlier));
    (next?.querySelector("button") ?? this.earlier.querySelector("summary"))?.focus();
    this.say(this.s.deleted);
  }

  private reset(focus: boolean): void {
    this.conversationId = null;
    this.log.replaceChildren(this.empty);
    this.clearError();
    if (focus) {
      this.field.focus();
    }
  }

  private clearError(): void {
    this.error.hidden = true;
    this.error.replaceChildren();
  }

  private fail(error: unknown, retry?: () => void): void {
    const s = this.s;
    const kind = tutorFailure(error);
    const text = kind === "limit" ? s.limit : kind === "disabled" ? s.disabled : kind === "network" ? s.network : kind === "busy" ? s.busy : s.failed;
    const kids: Array<Node | string> = [h("p", {}, text)];
    if (kind === "limit") {
      this.remaining = 0;
      this.send.disabled = true;
      const back = h("button", { type: "button", class: "btn" }, s.backToLesson);
      back.addEventListener("click", () => this.close());
      kids.push(back);
    } else if (retry) {
      const again = h("button", { type: "button", class: "btn" }, s.retry);
      again.addEventListener("click", retry);
      kids.push(again);
    }
    this.error.replaceChildren(...kids);
    this.error.hidden = false;
  }

  private async submit(): Promise<void> {
    if (this.busy) return;
    const text = this.field.value.trim();
    if (text.length < 2 || text.length > TUTOR_QUESTION_CHARS) {
      this.field.focus();
      return;
    }
    this.busy = true;
    this.clearError();
    this.log.setAttribute("aria-busy", "true");
    this.send.disabled = true;
    this.showPending(text);
    const isNew = this.conversationId === null;
    try {
      const question = { text, conversationId: this.conversationId, topicId: this.ctx.topicId };
      const answer = this.streaming
        ? await this.ctx.client.askStream(this.ctx.courseId, question, { onRun: (run) => this.remember(run, text), onDraft: (draft) => this.paintDraft(draft) })
        : await this.ctx.client.ask(this.ctx.courseId, question);
      this.finishAnswer(text, answer, isNew);
    } catch (error) {
      this.pending.hidden = true;
      this.fail(error, () => void this.submit());
    } finally {
      this.settle();
    }
  }

  private showPending(text: string): void {
    this.pending.replaceChildren(
      h("p", { class: "who" }, this.s.you),
      h("p", {}, text),
      // the draft is not part of the live log and is marked busy: screen readers hear the answer once, when it is final
      h("p", { class: "draft", "data-draft": "", "aria-busy": "true", hidden: "" }),
      h("p", { class: "muted", "data-writing": "" }, this.s.writing)
    );
    this.pending.hidden = false;
  }

  private paintDraft(text: string): void {
    const draft = this.pending.querySelector<HTMLElement>("[data-draft]");
    if (!draft) return;
    const reduced = typeof matchMedia === "function" && matchMedia("(prefers-reduced-motion: reduce)").matches;
    draft.textContent = visibleDraft(text, reduced);
    draft.hidden = draft.textContent === "";
    this.log.scrollTop = this.log.scrollHeight;
  }

  /** The validated answer replaces the draft: question and answer land together, so the live log announces one finished answer. */
  private finishAnswer(text: string, answer: TutorAnswer, isNew: boolean): void {
    this.forget();
    this.conversationId = answer.conversationId;
    this.remaining = answer.remaining;
    this.pending.hidden = true;
    if (this.log.contains(this.empty)) this.empty.remove();
    this.log.append(this.learnerMessage(text), this.tutorMessage(answer.answer, answer.citations, answer.refusal));
    this.log.scrollTop = this.log.scrollHeight;
    this.field.value = "";
    this.updateCounter();
    this.quota.textContent = answer.remaining > 0 ? fill(this.s.remaining, { n: answer.remaining, limit: this.dailyLimit() }) : fill(this.s.remainingNone, { n: this.dailyLimit() });
    if (isNew) void this.refreshEarlier();
  }

  private settle(): void {
    this.busy = false;
    this.log.removeAttribute("aria-busy");
    const blocked = this.remaining !== null && this.remaining <= 0;
    this.send.disabled = blocked;
    this.field.disabled = blocked;
    if (!blocked) this.field.focus();
  }

  /** Keeps the running answer across a reload (session only; the text stays on this device). */
  private remember(run: TutorRun, text: string): void {
    try {
      sessionStorage.setItem(RUN_KEY(this.ctx.courseId), JSON.stringify({ run, text }));
    } catch {
      /* storage can be blocked: the answer is still stored on the server */
    }
  }

  private forget(): void {
    try {
      sessionStorage.removeItem(RUN_KEY(this.ctx.courseId));
    } catch {
      /* ignore */
    }
  }

  /** After a reload mid-answer: rejoin the run, replay its events and show the answer when it is final. */
  private async resume(): Promise<void> {
    if (this.busy) return;
    let saved: { run: TutorRun; text: string } | null;
    try {
      saved = JSON.parse(sessionStorage.getItem(RUN_KEY(this.ctx.courseId)) ?? "null");
    } catch {
      saved = null;
    }
    if (!saved?.run?.runId || typeof saved.text !== "string" || !this.streaming) {
      this.forget();
      return;
    }
    this.busy = true;
    this.clearError();
    this.log.setAttribute("aria-busy", "true");
    this.send.disabled = true;
    this.conversationId = saved.run.conversationId;
    this.showPending(saved.text);
    try {
      const answer = await this.ctx.client.followRun(saved.run, { onDraft: (draft) => this.paintDraft(draft) });
      this.finishAnswer(saved.text, answer, true);
    } catch (error) {
      this.forget();
      this.pending.hidden = true;
      this.fail(error);
    } finally {
      this.settle();
    }
  }

  private dailyLimit(): number {
    return this.limit ?? 0;
  }

  private async refreshEarlier(): Promise<void> {
    try {
      this.paintEarlier(await this.ctx.client.conversations(this.ctx.courseId));
    } catch {
      /* the list is a convenience */
    }
  }

  private learnerMessage(text: string): HTMLElement {
    return h("article", { class: "msg learner" }, h("p", { class: "who" }, this.s.you), h("p", {}, text));
  }

  private fromHistory(m: TutorMessage): HTMLElement {
    return m.role === "learner" ? this.learnerMessage(m.text) : this.tutorMessage(m.text, m.citations, m.refusal ?? "none");
  }

  private tutorMessage(markdown: string, citations: TutorCitation[], refusal: TutorRefusal): HTMLElement {
    const s = this.s;
    const article = h("article", { class: "msg tutor" }, h("p", { class: "who" }, s.tutor), h("p", { class: "ai" }, s.aiLabel));
    // These two answers are fixed English sentences from the server: say it in the page language instead.
    const note = refusal === "attempt_active" ? s.refusalAttempt : refusal === "unsafe" ? s.refusalUnsafe : refusal === "out_of_scope" ? s.refusalOutOfScope : null;
    if (refusal === "none" || refusal === "out_of_scope") {
      const body = h("div", { class: "md" });
      body.innerHTML = renderBasicMarkdown(markdown);
      article.append(body);
    }
    if (note) article.append(h("p", { class: "note", role: "note" }, note));
    const linked = citations.filter((c) => c.label);
    if (linked.length > 0) {
      const ul = h("ul", { role: "list" });
      for (const c of linked) {
        const li = h("li", {});
        if (c.topicId) li.append(h("a", { href: citationHref(this.ctx.learnHref, this.ctx.courseId, c.topicId) }, c.label));
        else li.append(c.label);
        ul.append(li);
      }
      article.append(h("div", { class: "sources" }, h("p", { class: "who" }, s.sources), ul));
    }
    return article;
  }
}
