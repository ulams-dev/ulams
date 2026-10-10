import { createHost, newNonce, type Host } from "@ulams/interactive-bridge";
import type { PackageMessage } from "@ulams/interactive-bridge/protocol";
import { SHOWCASE_DWELL_MS, nextShowcaseStep, showcaseMode, themeTokens } from "../lib/interactive.ts";

/**
 * <ulams-showcase src locale steps="a b c" requires="webgl">
 *   <div data-stage aria-hidden="true"><img data-still><iframe data-src inert></iframe></div> …
 * </ulams-showcase>
 *
 * The landing hero's end of the `ulams-ix` bridge (ADR 0093): a decorative, self-running loop of an interactive
 * package. The still is what the page paints; the frame starts only after the page has loaded and the browser is
 * idle (so it never competes with the first paint or the LCP), only while the hero is on screen and the tab is
 * visible, and never under reduced motion (the still stays). The package runs with `showcase: true` (no text,
 * no controls) and this element moves it through the loop with `goToStep`. Nothing is tracked and nothing the
 * frame says is shown: a package that fails, times out or has no WebGL leaves the still.
 */
class UlamsShowcase extends HTMLElement {
  private host: Host | null = null;
  private iframe: HTMLIFrameElement | null = null;
  private loop: string[] = [];
  private current: string | undefined;
  private timer: ReturnType<typeof setInterval> | undefined;
  private observer: IntersectionObserver | null = null;
  private visible = true;
  private userPaused = false;
  private started = false;

  connectedCallback(): void {
    this.iframe = this.querySelector("iframe");
    this.loop = (this.getAttribute("steps") ?? "").split(/\s+/).filter(Boolean);
    this.current = this.loop[0];
    this.setAttribute("state", "still");
    const reduced = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches ?? false;
    const mode = showcaseMode({ reducedMotion: reduced, requires: (this.getAttribute("requires") ?? "").split(/\s+/).filter(Boolean), webgl: this.webgl() });
    if (mode === "still" || !this.iframe || this.loop.length === 0) return;

    this.querySelector<HTMLButtonElement>("[data-toggle]")?.addEventListener("click", () => this.toggle());
    document.addEventListener("visibilitychange", this.sync);
    this.observer = new IntersectionObserver((entries) => {
      this.visible = entries.some((e) => e.isIntersecting);
      this.sync();
    });
    this.observer.observe(this);
    this.afterFirstPaint(() => this.start());
  }

  disconnectedCallback(): void {
    document.removeEventListener("visibilitychange", this.sync);
    this.observer?.disconnect();
    this.stopTimer();
    this.host?.destroy();
  }

  private webgl(): boolean {
    try {
      return Boolean(document.createElement("canvas").getContext("webgl2"));
    } catch {
      return false;
    }
  }

  /** Runs `fn` once the page has loaded and the main thread is idle: the hero never delays first paint or LCP. */
  private afterFirstPaint(fn: () => void): void {
    const idle = () => {
      if (typeof window.requestIdleCallback === "function") window.requestIdleCallback(fn, { timeout: 2500 });
      else setTimeout(fn, 800);
    };
    if (document.readyState === "complete") idle();
    else window.addEventListener("load", idle, { once: true });
  }

  private start(): void {
    if (this.started || !this.iframe || !this.isConnected) return;
    this.started = true;
    this.setAttribute("state", "loading");
    this.host = createHost(this.iframe, {
      nonce: newNonce(),
      init: {
        locale: this.getAttribute("locale") ?? "en",
        theme: themeTokens((name) => getComputedStyle(document.documentElement).getPropertyValue(name)),
        reducedMotion: false,
        display: "inline",
        chrome: "none",
        showcase: true,
        ...(this.current ? { startStep: this.current } : {}),
      },
      onMessage: (message) => this.onMessage(message),
      onTimeout: () => this.giveUp(),
    });
    this.iframe.src = this.iframe.dataset.src ?? "";
  }

  private onMessage(message: PackageMessage): void {
    if (message.type === "ready") {
      this.setAttribute("state", "playing");
      this.sync();
    } else if (message.type === "error") {
      this.giveUp();
    }
  }

  /** The package did not start: the still stays, the frame goes. Nothing is shown to the visitor. */
  private giveUp(): void {
    this.stopTimer();
    this.host?.destroy();
    this.host = null;
    this.iframe?.removeAttribute("src");
    this.setAttribute("state", "still");
  }

  private readonly sync = (): void => {
    if (!this.host?.ready) return;
    const run = this.visible && !document.hidden && !this.userPaused;
    if (run) {
      this.host.resume();
      this.startTimer();
    } else {
      this.host.pause();
      this.stopTimer();
    }
    this.toggleAttribute("paused", !run && this.userPaused);
  };

  /** WCAG 2.2.2: the visitor can stop the motion. */
  private toggle(): void {
    this.userPaused = !this.userPaused;
    const button = this.querySelector<HTMLButtonElement>("[data-toggle]");
    if (button) {
      button.setAttribute("aria-pressed", String(this.userPaused));
      button.textContent = this.userPaused ? (button.dataset.playLabel ?? "Play") : (button.dataset.pauseLabel ?? "Pause");
    }
    this.sync();
  }

  private startTimer(): void {
    if (this.timer !== undefined || this.loop.length < 2) return;
    this.timer = setInterval(() => {
      const next = nextShowcaseStep(this.loop, this.current);
      if (!next) return;
      this.current = next;
      this.host?.goToStep(next);
    }, SHOWCASE_DWELL_MS);
  }

  private stopTimer(): void {
    if (this.timer !== undefined) clearInterval(this.timer);
    this.timer = undefined;
  }
}

if (!customElements.get("ulams-showcase")) customElements.define("ulams-showcase", UlamsShowcase);
