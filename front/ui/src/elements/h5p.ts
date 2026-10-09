import { isCompletingStatement, isH5PEmbedMessage, type H5PParentToEmbed } from "@ulams/sdk/h5p";
import { isTrustedFrameMessage } from "@ulams/sdk/frames";
import { announceComplete, bff } from "./bff.ts";

/**
 * <ulams-h5p src="/h5p/embed/play/2" topic-id="5"> <iframe> </ulams-h5p>
 * Frames the H5P service's player (served through this site's /h5p proxy, so the frame is
 * same-origin), answers its handshake with the theme, resizes the frame, forwards xAPI
 * statements to progress and completes the topic on a completing statement.
 */
class UlamsH5P extends HTMLElement {
  private iframe: HTMLIFrameElement | null = null;

  connectedCallback(): void {
    this.iframe = this.querySelector("iframe");
    window.addEventListener("message", this.onMessage);
  }

  disconnectedCallback(): void {
    window.removeEventListener("message", this.onMessage);
  }

  /** The frame is served through this site's /h5p proxy, so its origin is ours and nothing else. */
  private send(message: H5PParentToEmbed): void {
    this.iframe?.contentWindow?.postMessage(message, window.location.origin);
  }

  private themeCss(): string {
    const s = getComputedStyle(document.documentElement);
    const v = (name: string) => s.getPropertyValue(name).trim();
    return `:root{--h5p-theme-main-cta-base:${v("--ulams-color-primary")};--h5p-theme-text-primary:${v("--ulams-color-text")};}
body{font-family:${v("--ulams-font-family-body")};}
.h5p-joubelui-button,.h5p-question-check-answer,.h5p-question-try-again{background:${v("--ulams-color-primary")}!important;color:${v("--ulams-color-on-primary")}!important;border-radius:${v("--ulams-radius-button")}!important;}`;
  }

  private onMessage = (event: MessageEvent) => {
    if (!this.iframe || !isTrustedFrameMessage(event, { frame: this.iframe.contentWindow, origin: window.location.origin }) || !isH5PEmbedMessage(event.data)) return;
    const message = event.data;
    const topicId = Number(this.getAttribute("topic-id"));
    switch (message.type) {
      case "ulams-h5p:ready":
        this.send({ type: "ulams-h5p:style", css: this.themeCss(), urls: [] });
        this.send({ type: "ulams-h5p:token", token: null });
        break;
      case "ulams-h5p:loaded":
        this.setAttribute("state", "loaded");
        break;
      case "ulams-h5p:resize":
        if (Number.isFinite(message.height) && message.height > 0) this.iframe.style.height = `${Math.ceil(message.height)}px`;
        break;
      case "ulams-h5p:xapi":
        if (topicId) void bff.progress.h5p(topicId, message.statement).catch(() => undefined);
        if (isCompletingStatement(message.statement)) announceComplete("h5p");
        break;
      case "ulams-h5p:error":
        this.setAttribute("state", "error");
        break;
    }
  };
}

if (!customElements.get("ulams-h5p")) customElements.define("ulams-h5p", UlamsH5P);
