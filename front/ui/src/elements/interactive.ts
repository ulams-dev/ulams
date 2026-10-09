import { createHost, newNonce, type Host } from "@ulams/interactive-bridge";
import type { PackageMessage } from "@ulams/interactive-bridge/protocol";
import { announceComplete } from "./bff.ts";
import { EventQueue, startMode, stepCounter, themeTokens, type BridgeEvent, type StepInfo } from "../lib/interactive.ts";

/**
 * <ulams-interactive src topic-id display start-step end-step locale requires reduced-motion-supported>
 *   <iframe data-src sandbox> … stepper, text version … </iframe>
 * </ulams-interactive>
 *
 * The lesson-page end of the `ulams-ix` bridge (ADR 0087). It loads the package into a sandboxed frame
 * (no allow-same-origin, ADR 0086), accepts messages only from that frame with origin "null" and the
 * launch nonce, announces step changes, falls back to the step's poster and text (reduced motion, no
 * WebGL, no `ready` within 10 s, an `error`), and forwards progress through this site's own BFF with
 * the learner's session, so the frame never holds a token.
 */
class UlamsInteractive extends HTMLElement {
  private iframe: HTMLIFrameElement | null = null;
  private host: Host | null = null;
  private queue: EventQueue | null = null;
  private steps: StepInfo[] = [];
  private index = 0;
  private completed = false;
  private playing = false;
  private readonly onKey = (event: KeyboardEvent) => {
    if (event.key === "Escape" && this.explore) this.backToLesson();
  };
  private readonly onHide = () => void this.queue?.flush();

  private get background(): boolean {
    return this.getAttribute("display") === "background";
  }
  private get explore(): boolean {
    return this.getAttribute("explore") === "true";
  }
  private q<T extends HTMLElement>(selector: string): T | null {
    return this.querySelector<T>(selector);
  }

  connectedCallback(): void {
    this.iframe = this.q<HTMLIFrameElement>("iframe");
    this.steps = [...this.querySelectorAll<HTMLElement>("[data-step]")].map((el) => ({
      id: el.dataset.stepId ?? "",
      title: el.dataset.stepTitle ?? "",
      text: el.querySelector("[data-step-text]")?.textContent?.trim() ?? "",
      ...(el.dataset.stepPoster ? { poster: el.dataset.stepPoster } : {}),
    }));
    this.index = Math.max(0, this.steps.findIndex((s) => s.id === this.getAttribute("start-step")));
    this.setAttribute("state", "idle");

    this.q("[data-prev]")?.addEventListener("click", () => this.go(this.index - 1));
    this.q("[data-next]")?.addEventListener("click", () => this.go(this.index + 1));
    this.q("[data-explore]")?.addEventListener("click", () => this.startExplore());
    this.q("[data-back]")?.addEventListener("click", () => this.backToLesson());
    this.q("[data-play-anyway]")?.addEventListener("click", () => this.play());
    document.addEventListener("keydown", this.onKey);
    window.addEventListener("pagehide", this.onHide);
    this.renderStep(false);

    const topicId = Number(this.getAttribute("topic-id"));
    if (topicId) {
      this.queue = new EventQueue({
        send: (events) => this.send(topicId, events),
        onResult: (result) => this.onResult(result),
      });
      this.queue.start();
    }

    const mode = startMode({
      reducedMotion: window.matchMedia?.("(prefers-reduced-motion: reduce)").matches ?? false,
      reducedMotionSupported: this.hasAttribute("reduced-motion-supported"),
      requires: (this.getAttribute("requires") ?? "").split(/\s+/).filter(Boolean),
      webgl: this.webgl(),
    });
    if (mode === "frame") this.play();
    else this.fallback(mode);
  }

  disconnectedCallback(): void {
    document.removeEventListener("keydown", this.onKey);
    window.removeEventListener("pagehide", this.onHide);
    this.queue?.stop();
    this.host?.destroy();
  }

  private webgl(): boolean {
    try {
      return Boolean(document.createElement("canvas").getContext("webgl2"));
    } catch {
      return false;
    }
  }

  /** Loads the package into the frame and starts the bridge. */
  private play(): void {
    if (!this.iframe || this.playing) return;
    this.playing = true;
    this.removeAttribute("fallback");
    this.setAttribute("state", "loading");
    const current = this.steps[this.index];
    this.host = createHost(this.iframe, {
      nonce: newNonce(),
      init: {
        locale: this.getAttribute("locale") ?? "en",
        theme: themeTokens((name) => getComputedStyle(document.documentElement).getPropertyValue(name)),
        reducedMotion: window.matchMedia?.("(prefers-reduced-motion: reduce)").matches ?? false,
        display: this.background ? "background" : "inline",
        chrome: this.background ? "none" : "full",
        ...(current ? { startStep: current.id } : {}),
        range: { ...(this.steps[0] ? { from: this.steps[0].id } : {}), ...(this.steps.at(-1) ? { to: this.steps.at(-1)!.id } : {}) },
      },
      onMessage: (message) => this.onMessage(message),
      onTimeout: () => this.fallback("timeout"),
    });
    this.iframe.src = this.iframe.dataset.src ?? "";
  }

  private onMessage(message: PackageMessage): void {
    switch (message.type) {
      case "ready":
        this.setAttribute("state", "ready");
        break;
      case "resize":
        if (!this.background && !this.explore && this.iframe) this.iframe.style.height = `${Math.min(2000, Math.max(240, Math.ceil(message.height)))}px`;
        return;
      case "stepChanged": {
        const at = this.steps.findIndex((s) => s.id === message.step);
        if (at >= 0) {
          this.index = at;
          this.renderStep(true);
        }
        break;
      }
      case "complete":
        this.completed = true;
        break;
      case "error":
        this.fallback(message.code === "webgl-unavailable" ? "webgl" : "error");
        break;
    }
    if (message.type === "stepChanged" || message.type === "progress" || message.type === "complete" || message.type === "score" || message.type === "event") {
      const { type, ...rest } = message as BridgeEvent & { nonce?: string; "ulams-ix"?: number };
      delete rest.nonce;
      delete rest["ulams-ix"];
      this.queue?.push({ type, ...rest });
      if (type === "complete" || type === "score") void this.queue?.flush();
    }
  }

  private async send(topicId: number, events: BridgeEvent[]): Promise<unknown> {
    const response = await fetch(`/bff/api/interactive/topics/${topicId}/events`, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ events }),
      keepalive: true,
    });
    if (!response.ok) throw new Error(String(response.status));
    return response.json().catch(() => null);
  }

  private onResult(result: unknown): void {
    const status = (result as { data?: { status?: number } } | null)?.data?.status;
    if (status === 1 && !this.hasAttribute("done")) {
      this.setAttribute("done", "");
      announceComplete("interactive");
    }
  }

  /** The stepper: asks the package to go to a step in the range (it answers with `stepChanged`). */
  private go(to: number): void {
    const target = this.steps[to];
    if (!target) return;
    this.index = to;
    this.renderStep(true);
    this.host?.goToStep(target.id);
    if (this.getAttribute("fallback")) this.showPoster();
  }

  private renderStep(announce: boolean): void {
    const step = this.steps[this.index];
    if (!step) return;
    const set = (selector: string, text: string) => {
      const el = this.q(selector);
      if (el) el.textContent = text;
    };
    set("[data-counter]", stepCounter(this.index, this.steps.length));
    set("[data-current-title]", step.title);
    set("[data-current-text]", step.text);
    const prev = this.q<HTMLButtonElement>("[data-prev]");
    const next = this.q<HTMLButtonElement>("[data-next]");
    if (prev) prev.disabled = this.index <= 0;
    if (next) next.disabled = this.index >= this.steps.length - 1;
    this.querySelectorAll<HTMLElement>("[data-step]").forEach((el, i) => el.toggleAttribute("data-current", i === this.index));
    if (announce) set("[data-live]", `Step ${this.index + 1}: ${step.title}`);
    if (this.getAttribute("fallback")) this.showPoster();
  }

  /** The step's poster and text instead of (or after a failure of) the live frame. */
  private fallback(reason: "reduced-motion" | "webgl" | "timeout" | "error"): void {
    this.setAttribute("fallback", reason);
    this.setAttribute("state", reason === "timeout" || reason === "error" ? "error" : "idle");
    this.host?.destroy();
    this.host = null;
    this.playing = false;
    if (this.iframe && reason !== "reduced-motion" && reason !== "webgl") this.iframe.removeAttribute("src");
    const notice = this.q("[data-notice]");
    if (notice) {
      notice.textContent =
        reason === "reduced-motion"
          ? "Motion is reduced on your device, so the animation is not playing. The picture and text below show this step."
          : reason === "webgl"
            ? "Your browser cannot show this 3D view. The picture and text below show this step."
            : "The interactive did not start. The text below has this step.";
    }
    this.showPoster();
  }

  private showPoster(): void {
    const step = this.steps[this.index];
    const img = this.q<HTMLImageElement>("[data-poster]");
    if (!img || !step) return;
    if (step.poster) {
      img.src = step.poster;
      img.alt = step.text || step.title;
      img.hidden = false;
    } else {
      img.hidden = true;
    }
  }

  /** "Explore freely": hide the text card, give the frame the focus; Escape or the button comes back. */
  private startExplore(): void {
    if (!this.iframe) return;
    this.setAttribute("explore", "true");
    document.documentElement.setAttribute("data-ix-explore", "");
    this.iframe.removeAttribute("tabindex");
    this.iframe.focus();
    this.setAttribute("data-explore-live", "");
    const live = this.q("[data-live]");
    if (live) live.textContent = "Exploring freely. Press Escape to go back to the lesson.";
  }

  private backToLesson(): void {
    this.removeAttribute("explore");
    document.documentElement.removeAttribute("data-ix-explore");
    this.iframe?.setAttribute("tabindex", "-1");
    this.q<HTMLElement>("[data-explore]")?.focus();
  }

  get isCompleted(): boolean {
    return this.completed;
  }
}

if (!customElements.get("ulams-interactive")) customElements.define("ulams-interactive", UlamsInteractive);
