import { PROTOCOL, READY_TIMEOUT_MS, parseMessage, withinSize, type Envelope, type InitPayload, type PackageMessage, type ParentMessage } from "./protocol.ts";

export interface HostOptions {
  /** The per-launch random nonce (at least 8 characters). */
  nonce: string;
  init: InitPayload;
  /** Called for every valid message from the frame (including `ready`). */
  onMessage: (message: Envelope<PackageMessage>) => void;
  onReady?: (message: Extract<PackageMessage, { type: "ready" }>) => void;
  /** No `ready` within `readyTimeoutMs`: show the text alternative. */
  onTimeout?: () => void;
  readyTimeoutMs?: number;
  /** Override for tests; defaults to the global window. */
  window?: Window;
}

export interface Host {
  readonly ready: boolean;
  goToStep(step: string): void;
  setTheme(theme: Record<string, string>): void;
  setLocale(locale: string): void;
  pause(): void;
  resume(): void;
  /** Sends `init` now (also done on the frame's `load` event). */
  sendInit(): void;
  destroy(): void;
}

/**
 * The lesson-page end of the bridge. A message is accepted only when `event.source` is the frame's
 * window, `event.origin` is `"null"` (an opaque, sandboxed frame), the nonce matches, the size is
 * within 16 KB and the payload is valid. Messages other than `ready` are ignored until `ready`.
 */
export function createHost(frame: HTMLIFrameElement, options: HostOptions): Host {
  const win = options.window ?? window;
  let ready = false;
  let destroyed = false;
  const timer = setTimeout(() => {
    if (!ready && !destroyed) options.onTimeout?.();
  }, options.readyTimeoutMs ?? READY_TIMEOUT_MS);

  const post = (m: ParentMessage) => {
    const target = frame.contentWindow;
    if (!target || destroyed) return;
    const msg = { "ulams-ix": PROTOCOL, nonce: options.nonce, ...m };
    if (!withinSize(msg)) return;
    // Opaque frame: no origin to target. `init` and the rest carry nothing secret.
    target.postMessage(msg, "*");
  };

  const sendInit = () => post({ type: "init", ...options.init });

  const listener = (event: MessageEvent) => {
    if (destroyed) return;
    if (!frame.contentWindow || event.source !== frame.contentWindow || event.origin !== "null") return;
    const msg = parseMessage(event.data, "toParent", options.nonce);
    if (!msg) return;
    if (msg.type === "ready") {
      if (ready) return;
      ready = true;
      clearTimeout(timer);
      options.onReady?.(msg);
    } else if (!ready) {
      return;
    }
    options.onMessage(msg);
  };

  win.addEventListener("message", listener);
  const onLoad = () => sendInit();
  frame.addEventListener("load", onLoad);

  return {
    get ready() {
      return ready;
    },
    goToStep: (step) => post({ type: "goToStep", step }),
    setTheme: (theme) => post({ type: "setTheme", theme }),
    setLocale: (locale) => post({ type: "setLocale", locale }),
    pause: () => post({ type: "pause" }),
    resume: () => post({ type: "resume" }),
    sendInit,
    destroy() {
      destroyed = true;
      clearTimeout(timer);
      win.removeEventListener("message", listener);
      frame.removeEventListener("load", onLoad);
    },
  };
}

/** A random per-launch nonce (32 hex characters). */
export function newNonce(): string {
  const bytes = new Uint8Array(16);
  crypto.getRandomValues(bytes);
  return Array.from(bytes, (b) => b.toString(16).padStart(2, "0")).join("");
}
