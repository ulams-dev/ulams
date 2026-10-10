/**
 * The conversation: turns AG-UI events into a thread of messages and A2UI surfaces rendered with
 * the @ulams/ui builder catalogue. Surfaces are replaced in place when the server re-sends them.
 * New actionable surfaces (a question, an outline, an apply or a patch proposal) take the focus
 * and are announced, but only for live events, not when the history is replayed on load.
 */
import { applyJsonPatch, EventType, isApplied, surfaceFromEvent, type AgUiEvent, type BuilderState } from "@ulams/sdk";
import { renderSurface } from "@ulams/ui/builder/renderer.ts";
import type { A2uiActionOut, BuilderContext } from "@ulams/ui/builder/components.ts";
import { h } from "@ulams/ui/builder/dom.ts";
import { announce } from "./common.ts";

export interface TimelineOptions {
  container: HTMLElement;
  dispatch: (action: A2uiActionOut) => Promise<void>;
  onState?: (state: BuilderState) => void;
  onCitation?: BuilderContext["onCitation"];
  onSelect?: BuilderContext["onSelect"];
  /** Only show surfaces of these kinds (all when omitted). */
  kinds?: string[];
  /** Show text messages only after the first surface of this kind appeared. */
  startAfterKind?: string;
  onCustom?: (name: string, value: Record<string, unknown>) => void;
}

const ACTIONABLE = new Set(["interview", "outline", "apply", "patch", "variants"]);

export class Timeline {
  state = {} as BuilderState;
  private surfaces = new Map<string, HTMLElement>();
  /** Last event of every patch surface, to re-render it when the apply catches up. */
  private patchEvents = new Map<string, AgUiEvent>();
  private applying = false;
  private messages = new Map<string, HTMLElement>();
  private busy: HTMLElement;
  private started: boolean;
  private readonly loadedAt = Date.now();

  constructor(private readonly options: TimelineOptions) {
    this.started = !options.startAfterKind;
    this.busy = h("div", { class: "st-busy", hidden: true, role: "status" }, h("span", { class: "st-dots", "aria-hidden": "true" }, h("span"), h("span"), h("span")), "Working…");
    options.container.after(this.busy);
  }

  private live(event: AgUiEvent): boolean {
    return typeof event.timestamp === "number" ? event.timestamp > this.loadedAt - 1500 : false;
  }

  /** Takes a state read from the session endpoint (authoritative) instead of waiting for the stream. */
  adopt(state: BuilderState): void {
    this.state = state;
    this.stateChanged();
  }

  private stateChanged(): void {
    this.options.onState?.(this.state);
    // an approved patch is "applied" only once the applied version has caught up with the current one
    const applying = Boolean(this.state.session?.currentVersionId) && !isApplied(this.state.session);
    if (applying === this.applying) return;
    this.applying = applying;
    for (const event of this.patchEvents.values()) this.surface(event);
  }

  handle(event: AgUiEvent): void {
    switch (event.type) {
      case EventType.STATE_SNAPSHOT:
        this.state = event.snapshot as BuilderState;
        this.stateChanged();
        break;
      case EventType.STATE_DELTA:
        this.state = applyJsonPatch(this.state as unknown as Record<string, unknown>, event.delta as Array<{ op: string; path: string; value?: unknown }>) as unknown as BuilderState;
        this.stateChanged();
        break;
      case EventType.TEXT_MESSAGE_START: {
        if (!this.started) break;
        const role = String(event.role ?? "assistant");
        const bubble = h("div", { class: `st-msg st-msg-${role}` },
          h("span", { class: "cb-sr" }, role === "user" ? "You said: " : "Assistant: "),
          h("p", { class: "st-msg-text" }));
        this.messages.set(String(event.messageId), bubble);
        this.options.container.append(bubble);
        break;
      }
      case EventType.TEXT_MESSAGE_CONTENT: {
        const bubble = this.messages.get(String(event.messageId));
        const p = bubble?.querySelector(".st-msg-text");
        if (p) p.textContent = (p.textContent ?? "") + String(event.delta ?? "");
        break;
      }
      case EventType.TEXT_MESSAGE_END: {
        const bubble = this.messages.get(String(event.messageId));
        if (bubble && this.live(event) && bubble.classList.contains("st-msg-assistant")) announce(bubble.textContent ?? "");
        break;
      }
      case EventType.ACTIVITY_SNAPSHOT:
        this.surface(event);
        break;
      case EventType.RUN_STARTED:
        this.busy.hidden = false;
        break;
      case EventType.RUN_FINISHED:
        this.busy.hidden = true;
        break;
      case EventType.RUN_ERROR: {
        this.busy.hidden = true;
        if (!this.started) break;
        const message = String(event.message ?? "Something went wrong.");
        const retry = h("button", { type: "button", class: "cb-btn cb-btn-small" }, "Try again");
        retry.addEventListener("click", () => {
          retry.disabled = true;
          void this.options.dispatch({ name: "retry", surfaceId: "", sourceComponentId: "retry", context: {} });
        });
        const box = h("div", { class: "st-msg st-msg-error", role: this.live(event) ? "alert" : null }, h("p", {}, message), event.code !== "cancelled" ? retry : null);
        this.options.container.append(box);
        break;
      }
      case EventType.CUSTOM:
        this.options.onCustom?.(String(event.name), (event.value ?? {}) as Record<string, unknown>);
        break;
    }
  }

  private surface(event: AgUiEvent): void {
    const surface = surfaceFromEvent(event);
    if (!surface) return;
    // Development only: check the envelope against the vendored A2UI v0.9 schema (never shipped).
    if (import.meta.env.DEV) {
      void import("@ulams/ui/builder/a2ui-validate.ts").then(({ validateSurfaceContent }) => {
        const issues = validateSurfaceContent((event as { content?: unknown }).content);
        if (issues.length) console.warn(`a2ui-surface ${surface.surfaceId} is not valid A2UI v0.9`, issues);
      });
    }
    const kind = surface.kind ?? "";
    if (kind === "patch") this.patchEvents.set(surface.surfaceId, event);
    if (!this.started && kind === this.options.startAfterKind) this.started = true;
    if (this.options.kinds && !this.options.kinds.includes(kind)) return;
    if (!this.started) return;
    const existing = this.surfaces.get(surface.surfaceId);
    if (surface.deleted) {
      existing?.remove();
      this.surfaces.delete(surface.surfaceId);
      return;
    }
    const ctx: BuilderContext = {
      surfaceId: surface.surfaceId,
      dispatch: (action) => {
        wrapper.setAttribute("aria-busy", "true");
        wrapper.querySelectorAll<HTMLButtonElement>("button").forEach((b) => (b.disabled = true));
        void this.options.dispatch(action).finally(() => {
          wrapper.removeAttribute("aria-busy");
          if (wrapper.isConnected) wrapper.querySelectorAll<HTMLButtonElement>("button").forEach((b) => (b.disabled = false));
        });
      },
      onCitation: this.options.onCitation,
      onSelect: this.options.onSelect,
      applying: this.applying,
    };
    const wrapper = h("div", { class: `st-surface st-surface-${kind}`, tabindex: -1, "data-surface": surface.surfaceId, "data-label": SURFACE_LABEL[kind] ?? "Assistant card" });
    wrapper.append(renderSurface(surface.components, ctx));
    if (existing) {
      const hadFocus = existing.contains(document.activeElement);
      existing.replaceWith(wrapper);
      if (hadFocus) this.focus(wrapper);
    } else {
      this.options.container.append(wrapper);
      if (this.live(event) && ACTIONABLE.has(kind)) this.focus(wrapper);
    }
    this.surfaces.set(surface.surfaceId, wrapper);
    if (this.live(event) && kind === "interview") this.focus(wrapper);
  }

  /** Moves focus to the first control of a new surface, unless the author is typing. */
  private focus(wrapper: HTMLElement): void {
    const active = document.activeElement;
    if (active instanceof HTMLTextAreaElement || (active instanceof HTMLInputElement && active.type === "text")) return;
    const target = wrapper.querySelector<HTMLElement>(".cb-question-open button, .cb-question-open input, .cb-question-open select, h3, button") ?? wrapper;
    if (target.tagName === "H3") target.setAttribute("tabindex", "-1");
    target.focus({ preventScroll: false });
    target.scrollIntoView({ block: "nearest", behavior: matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth" });
    announce(wrapper.dataset.label ?? "");
  }
}

const SURFACE_LABEL: Record<string, string> = {
  source: "Your source",
  interview: "Interview question",
  outline: "Proposed outline",
  progress: "Generation progress",
  apply: "Apply to your academy",
  patch: "Proposed change",
};
