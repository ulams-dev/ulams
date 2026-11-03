import { announceComplete } from "./bff.ts";
import { attemptBody, buildErrors, CodeRunnerClient, filesHash, loadDraft, saveDraft, type CodeFile, type RunResultPayload } from "../lib/code-exercise.ts";
import { codeStrings, fill, type CodeStrings } from "../lib/code-exercise-strings.ts";

/**
 * <ulams-code-exercise topic-id language timeout-ms data-tests>
 *   one labelled <textarea data-editor data-path> per file, the visible tests, a results list,
 *   <iframe data-src sandbox="allow-scripts"> (the runner on the content origin)
 * </ulams-code-exercise>
 *
 * The lesson-page end of a code exercise (ADR 0170). The learner edits the files in plain text areas (they
 * work with a keyboard and a screen reader without any script, and the Tab key moves on), presses "Run
 * tests" and gets the result of each test. The code never runs on this page: it goes to the runner frame
 * (an opaque origin on the content origin) over the `ulams-ix` bridge. A finished run is reported through
 * this site's own BFF with the learner's session, as a practice attempt. The frame never holds a token.
 */
class UlamsCodeExercise extends HTMLElement {
  private client: CodeRunnerClient | null = null;
  private s: CodeStrings = codeStrings("en");
  private starter: CodeFile[] = [];
  private tests: CodeFile[] = [];
  private busy = false;
  private completed = false;
  private saveTimer: ReturnType<typeof setTimeout> | null = null;

  private q<T extends HTMLElement>(selector: string): T | null {
    return this.querySelector<T>(selector);
  }

  private get topicId(): number {
    return Number(this.getAttribute("topic-id")) || 0;
  }

  connectedCallback(): void {
    if (this.dataset.ready) return;
    this.dataset.ready = "1";
    this.s = codeStrings(this.getAttribute("lang") ?? document.documentElement.lang);
    this.starter = this.editors().map((e) => ({ path: e.dataset.path ?? "", content: e.defaultValue }));
    this.tests = this.parseTests();

    const draft = this.topicId ? loadDraft(this.store(), this.topicId, this.starter) : {};
    if (Object.keys(draft).length > 0) {
      for (const editor of this.editors()) if (draft[editor.dataset.path ?? ""] !== undefined) editor.value = draft[editor.dataset.path ?? ""]!;
      this.live(this.s.draftRestored);
    }

    this.q("[data-run]")?.addEventListener("click", () => void this.run());
    this.q("[data-reset]")?.addEventListener("click", () => this.askReset());
    this.q("[data-reset-yes]")?.addEventListener("click", () => this.reset());
    this.q("[data-reset-keep]")?.addEventListener("click", () => this.cancelReset());
    for (const editor of this.editors()) {
      editor.addEventListener("keydown", (event) => {
        if (event.key === "Enter" && (event.ctrlKey || event.metaKey)) {
          event.preventDefault();
          void this.run();
        }
      });
      editor.addEventListener("input", () => this.scheduleSave());
    }
    window.addEventListener("pagehide", () => this.saveNow());

    this.start();
  }

  disconnectedCallback(): void {
    this.saveNow();
    this.client?.destroy();
  }

  /** The visible tests, from the `data-tests` attribute (exact text; the copy in the page is for reading only). */
  private parseTests(): CodeFile[] {
    try {
      const parsed = JSON.parse(this.dataset.tests ?? "[]") as unknown;
      return Array.isArray(parsed) ? parsed.filter((f): f is CodeFile => typeof f?.path === "string" && typeof f?.content === "string") : [];
    } catch {
      return [];
    }
  }

  private editors(): HTMLTextAreaElement[] {
    return [...this.querySelectorAll<HTMLTextAreaElement>("textarea[data-editor]")];
  }

  private files(): CodeFile[] {
    return this.editors().map((e) => ({ path: e.dataset.path ?? "", content: e.value }));
  }

  private store(): Storage | null {
    try {
      return window.localStorage;
    } catch {
      return null;
    }
  }

  private scheduleSave(): void {
    if (this.saveTimer) clearTimeout(this.saveTimer);
    this.saveTimer = setTimeout(() => this.saveNow(), 800);
  }

  private saveNow(): void {
    if (this.saveTimer) clearTimeout(this.saveTimer);
    this.saveTimer = null;
    if (this.topicId) saveDraft(this.store(), this.topicId, this.starter, this.files());
  }

  private start(): void {
    const frame = this.q<HTMLIFrameElement>("iframe[data-src]");
    const src = frame?.dataset.src;
    if (!frame || !src) return this.setRunner("unavailable");
    this.setRunner("loading");
    this.client = new CodeRunnerClient({
      frame,
      src,
      language: this.getAttribute("language") === "typescript" ? "typescript" : "javascript",
      tests: this.tests,
      timeoutMs: Number(this.getAttribute("timeout-ms")) || 5000,
      reducedMotion: window.matchMedia?.("(prefers-reduced-motion: reduce)").matches ?? false,
      onState: (state) => this.setRunner(state),
    });
    this.client.start();
  }

  private setRunner(state: "idle" | "loading" | "ready" | "unavailable"): void {
    this.setAttribute("runner", state);
    const run = this.q<HTMLButtonElement>("[data-run]");
    if (run) run.disabled = state === "unavailable" || this.busy;
    const status = this.q("[data-runner-status]");
    if (status) status.textContent = state === "loading" ? this.s.loading : state === "unavailable" ? this.s.unavailable : "";
    if (state === "unavailable") this.q("[data-runner-status]")?.removeAttribute("hidden");
  }

  private async run(): Promise<void> {
    if (this.busy || !this.client || this.getAttribute("runner") === "unavailable") return;
    this.busy = true;
    this.saveNow();
    const run = this.q<HTMLButtonElement>("[data-run]");
    if (run) {
      run.disabled = true;
      run.setAttribute("aria-busy", "true");
      run.textContent = this.s.running;
    }
    this.setAttribute("state", "running");
    const files = this.files();
    const outcome = await this.client.run(files);
    this.busy = false;
    if (run) {
      run.disabled = this.getAttribute("runner") === "unavailable";
      run.removeAttribute("aria-busy");
      run.textContent = this.s.run;
    }
    this.setAttribute("state", "done");

    if (outcome.kind === "unavailable") return void this.show(this.s.unavailable, []);
    if (outcome.kind === "stopped") return void this.show(this.s.stopped, []);
    this.render(outcome.result);
    const body = attemptBody(outcome.result, await filesHash(files));
    if (body) await this.report(body);
  }

  private render(result: RunResultPayload): void {
    const passedAll = result.status === "passed";
    let summary: string;
    if (result.status === "timeout") summary = this.s.timeout;
    else if (result.tests.length === 0) summary = result.error ? this.s.buildError : this.s.noTests;
    else summary = passedAll ? fill(this.s.summaryAll, { total: result.total }) : fill(this.s.summaryPartial, { passed: result.passed, total: result.total });
    this.setAttribute("result", result.status);
    this.show(summary, result.tests.length === 0 ? buildErrors(result) : [], result);
  }

  /** Writes the summary line, the list of tests (or of build errors) and the console output. */
  private show(summary: string, lines: string[], result?: RunResultPayload): void {
    const status = this.q("[data-summary]");
    if (status) status.textContent = summary;
    const list = this.q<HTMLElement>("[data-results]");
    if (list) {
      list.replaceChildren();
      for (const line of lines) {
        const li = document.createElement("li");
        li.className = "u-ce__line u-ce__line--error";
        const code = document.createElement("code");
        code.textContent = line;
        li.append(code);
        list.append(li);
      }
      for (const test of result?.tests ?? []) {
        const li = document.createElement("li");
        li.className = `u-ce__line u-ce__line--${test.status}`;
        const badge = document.createElement("strong");
        badge.className = "u-ce__badge";
        badge.textContent = test.status === "pass" ? this.s.passed : test.status === "fail" ? this.s.failed : this.s.error;
        const name = document.createElement("span");
        name.className = "u-ce__name";
        name.textContent = test.name;
        li.append(badge, " ", name);
        if (test.message) {
          const message = document.createElement("pre");
          message.className = "u-ce__message";
          message.textContent = test.message;
          li.append(message);
        }
        list.append(li);
      }
    }
    const out = this.q<HTMLDetailsElement>("[data-output]");
    if (out) {
      const pre = out.querySelector("pre");
      const text = result?.output ?? "";
      if (pre) pre.textContent = text + (result?.truncated ? `\n${this.s.outputCut}` : "");
      out.hidden = text === "";
    }
  }

  private async report(body: NonNullable<ReturnType<typeof attemptBody>>): Promise<void> {
    const topicId = this.topicId;
    const note = this.q("[data-saved]");
    if (!topicId) return; // a preview tracks nothing
    try {
      const response = await fetch(`/bff/api/code-exercises/topics/${topicId}/attempts`, {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify(body),
      });
      if (!response.ok) throw new Error(String(response.status));
      const data = ((await response.json().catch(() => null)) as { data?: { attempt?: { attempt_no?: number }; status?: number } } | null)?.data;
      if (note) note.textContent = fill(this.s.saved, { n: data?.attempt?.attempt_no ?? "" });
      if (data?.status === 1 && !this.completed) {
        this.completed = true;
        this.setAttribute("done", "");
        this.live(this.s.completed);
        announceComplete("code-exercise");
      }
    } catch {
      if (note) note.textContent = this.s.notSaved;
    }
  }

  private askReset(): void {
    this.q("[data-reset-confirm]")?.removeAttribute("hidden");
    this.q<HTMLElement>("[data-reset-yes]")?.focus();
  }

  private cancelReset(): void {
    this.q("[data-reset-confirm]")?.setAttribute("hidden", "");
    this.q<HTMLElement>("[data-reset]")?.focus();
  }

  private reset(): void {
    this.client?.reset();
    for (const editor of this.editors()) editor.value = editor.defaultValue;
    this.saveNow();
    this.q("[data-reset-confirm]")?.setAttribute("hidden", "");
    this.show(this.s.noResults, []);
    this.removeAttribute("result");
    this.q<HTMLElement>("[data-reset]")?.focus();
    this.live(this.s.resetDone);
  }

  private live(text: string): void {
    const el = this.q("[data-live]");
    if (el) el.textContent = text;
  }
}

if (!customElements.get("ulams-code-exercise")) customElements.define("ulams-code-exercise", UlamsCodeExercise);
