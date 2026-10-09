import { PROTOCOL, parseMessage, withinSize, type Capabilities, type Envelope, type InitPayload, type PackageMessage, type ParentMessage } from "./protocol.ts";

export interface ConnectOptions {
  /** The step ids this package knows (or a function that returns them when `ready` is sent). */
  steps?: string[] | (() => string[]);
  /**
   * Hold `ready` until this settles, for a package that is still loading its data when `init` arrives.
   * Calls made meanwhile are queued and flushed right after `ready`.
   */
  whenReady?: Promise<unknown>;
  capabilities?: Capabilities;
  onInit?: (init: InitPayload) => void;
  onGoToStep?: (step: string) => void;
  onTheme?: (tokens: Record<string, string>) => void;
  onLocale?: (locale: string) => void;
  onPause?: () => void;
  onResume?: () => void;
  /** Override for tests; defaults to the global window. */
  window?: Window;
}

export interface Bridge {
  /** False when the package runs standalone (no parent window): every call is then a no-op. */
  readonly connected: boolean;
  stepChanged(step: string): void;
  progress(value: number): void;
  complete(): void;
  score(raw: number, max: number, passed?: boolean): void;
  event(verb: string, object: string, result?: { response?: string; success?: boolean; score?: number }): void;
  resize(height: number): void;
  error(code: string, message?: string): void;
  destroy(): void;
}

type Outgoing = PackageMessage;

const NOOP: Bridge = {
  connected: false,
  stepChanged() {},
  progress() {},
  complete() {},
  score() {},
  event() {},
  resize() {},
  error() {},
  destroy() {},
};

/** Connects a package to the lesson page. Safe to call when the package runs on its own. */
export function connect(options: ConnectOptions = {}): Bridge {
  const win = options.window ?? (typeof window === "undefined" ? undefined : window);
  if (!win || win.parent === win) return NOOP;
  const parent = win.parent;
  let nonce: string | null = null;
  let initializing = false;
  let holding = false;
  let queue: Outgoing[] = [];

  const post = (m: Outgoing) => {
    const msg = { "ulams-ix": PROTOCOL, nonce, ...m };
    if (!withinSize(msg)) return;
    // The frame is opaque, so the parent's origin is unknowable: "*" is the only option, and
    // nothing secret is ever sent (ADR 0087).
    parent.postMessage(msg, "*");
  };
  const send = (m: Outgoing) => {
    // Before `init`, and inside `onInit`, calls wait: the host ignores everything that arrives before `ready`.
    if (nonce === null || initializing || holding) {
      if (queue.length < 100) queue.push(m);
      return;
    }
    post(m);
  };

  const listener = (event: MessageEvent) => {
    if (event.source !== parent) return;
    const msg: Envelope<ParentMessage> | null = parseMessage(event.data, "toPackage", nonce);
    if (!msg) return;
    switch (msg.type) {
      case "init": {
        if (nonce !== null) return; // the nonce is fixed by the first init
        nonce = msg.nonce;
        initializing = true;
        try {
          options.onInit?.(msg);
        } finally {
          initializing = false;
        }
        const announce = () => {
          holding = false;
          const steps = typeof options.steps === "function" ? options.steps() : (options.steps ?? []);
          post({ type: "ready", protocol: PROTOCOL, steps, capabilities: options.capabilities ?? {} });
          const pending = queue;
          queue = [];
          pending.forEach(post);
        };
        if (options.whenReady) {
          holding = true;
          options.whenReady.then(announce, announce);
        } else announce();
        return;
      }
      case "goToStep":
        options.onGoToStep?.(msg.step);
        return;
      case "setTheme":
        options.onTheme?.(msg.theme);
        return;
      case "setLocale":
        options.onLocale?.(msg.locale);
        return;
      case "pause":
        options.onPause?.();
        return;
      case "resume":
        options.onResume?.();
    }
  };
  win.addEventListener("message", listener);

  return {
    connected: true,
    stepChanged: (step) => send({ type: "stepChanged", step }),
    progress: (value) => send({ type: "progress", value: Math.min(1, Math.max(0, value)) }),
    complete: () => send({ type: "complete" }),
    score: (raw, max, passed) => send(passed === undefined ? { type: "score", raw, max } : { type: "score", raw, max, passed }),
    event: (verb, object, result) => send(result ? { type: "event", verb, object, result } : { type: "event", verb, object }),
    resize: (height) => send({ type: "resize", height: Math.round(height) }),
    error: (code, message) => send(message === undefined ? { type: "error", code } : { type: "error", code, message }),
    destroy: () => win.removeEventListener("message", listener),
  };
}
