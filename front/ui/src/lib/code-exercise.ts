/**
 * Pure parts of the code exercise lesson (ADR 0170): the client of the sandboxed runner frame (a `ulams-ix`
 * host for `loadExercise`, `run` and `runResult`), the body of an attempt report and the learner's draft.
 * No DOM access beyond the frame element, so everything here is unit-testable in node with fake windows.
 */
import { createHost, newNonce, type Host, type HostOptions } from "@ulams/interactive-bridge";
import type { CodeFile, CodeLanguage, RunResultPayload, TestResult } from "@ulams/interactive-bridge/protocol";

export type { CodeFile, CodeLanguage, RunResultPayload, TestResult };

export type RunnerState = "idle" | "loading" | "ready" | "unavailable";

/** How a run ended from the page's point of view. */
export type RunOutcome = { kind: "result"; result: RunResultPayload } | { kind: "unavailable" } | { kind: "stopped" };

export interface RunnerClientOptions {
  frame: HTMLIFrameElement;
  /** The runner page on the content origin (from the launch). */
  src: string;
  language: CodeLanguage;
  tests: CodeFile[];
  timeoutMs: number;
  /** No `ready` within this time (esbuild's wasm file is 14 MB): the runner counts as unavailable. */
  readyTimeoutMs?: number;
  /** No answer within the exercise's limit plus this: the runner frame is reloaded. */
  graceMs?: number;
  reducedMotion?: boolean;
  onState?: (state: RunnerState) => void;
  /** Overrides for tests. */
  createHost?: (frame: HTMLIFrameElement, options: HostOptions) => Host;
  window?: Window;
}

/** Time on top of the exercise's limit that the page waits (the runner stops the code itself at the limit). */
export const DEFAULT_GRACE_MS = 6000;
export const DEFAULT_READY_TIMEOUT_MS = 30_000;

/**
 * Drives the runner frame. The frame must carry `sandbox="allow-scripts"` (no `allow-same-origin`) and have
 * no `src` yet: `start()` sets it. One run at a time; a run that never answers makes the client reload the
 * frame, so an exercise can never freeze the lesson page.
 */
export class CodeRunnerClient {
  private host: Host | null = null;
  private state: RunnerState = "idle";
  private runId = 0;
  private pending: { runId: number; resolve: (outcome: RunOutcome) => void; timer: ReturnType<typeof setTimeout> } | null = null;
  private readyWaiters: Array<(ok: boolean) => void> = [];
  private destroyed = false;

  constructor(private readonly options: RunnerClientOptions) {}

  get current(): RunnerState {
    return this.state;
  }

  start(): void {
    if (this.destroyed) return;
    this.setState("loading");
    const { frame, src } = this.options;
    const make = this.options.createHost ?? createHost;
    this.host?.destroy();
    this.host = make(frame, {
      nonce: newNonce(),
      init: { locale: "en", theme: {}, reducedMotion: this.options.reducedMotion ?? false, display: "inline", chrome: "none" },
      readyTimeoutMs: this.options.readyTimeoutMs ?? DEFAULT_READY_TIMEOUT_MS,
      onMessage: (message) => {
        if (message.type === "ready") {
          this.host?.loadExercise({ language: this.options.language, tests: this.options.tests, timeoutMs: this.options.timeoutMs });
          this.setState("ready");
        } else if (message.type === "runResult") {
          // the payload without the envelope
          const { type: _type, nonce: _nonce, "ulams-ix": _version, ...result } = message;
          this.settle(message.runId, { kind: "result", result });
        } else if (message.type === "error") {
          this.setState("unavailable");
        }
      },
      onTimeout: () => this.setState("unavailable"),
      ...(this.options.window ? { window: this.options.window } : {}),
    });
    frame.removeAttribute("src");
    frame.src = src;
  }

  /** Resolves true when the runner is ready, false when it is not going to be. */
  whenReady(): Promise<boolean> {
    if (this.state === "ready") return Promise.resolve(true);
    if (this.state === "unavailable" || this.destroyed) return Promise.resolve(false);
    return new Promise((resolve) => this.readyWaiters.push(resolve));
  }

  async run(files: CodeFile[]): Promise<RunOutcome> {
    if (!(await this.whenReady()) || !this.host) return { kind: "unavailable" };
    this.stopPending({ kind: "stopped" });
    const runId = ++this.runId;
    return new Promise<RunOutcome>((resolve) => {
      const timer = setTimeout(() => {
        // the runner did not answer: whatever it is doing, the lesson goes on without it
        this.settle(runId, { kind: "stopped" });
        this.restart();
      }, this.options.timeoutMs + (this.options.graceMs ?? DEFAULT_GRACE_MS));
      this.pending = { runId, resolve, timer };
      this.host?.run({ runId, files });
    });
  }

  /** Stops the run in progress; its caller gets `stopped`. */
  reset(): void {
    this.stopPending({ kind: "stopped" });
    this.host?.reset();
  }

  destroy(): void {
    this.destroyed = true;
    this.stopPending({ kind: "stopped" });
    this.host?.destroy();
    this.host = null;
    this.flush(false);
  }

  private restart(): void {
    if (this.destroyed) return;
    this.start();
  }

  private setState(state: RunnerState): void {
    if (this.state === state) return;
    this.state = state;
    this.options.onState?.(state);
    if (state === "ready") this.flush(true);
    if (state === "unavailable") {
      this.stopPending({ kind: "unavailable" });
      this.flush(false);
    }
  }

  private flush(ok: boolean): void {
    const waiters = this.readyWaiters;
    this.readyWaiters = [];
    for (const w of waiters) w(ok);
  }

  private settle(runId: number, outcome: RunOutcome): void {
    if (this.pending?.runId !== runId) return;
    this.stopPending(outcome);
  }

  private stopPending(outcome: RunOutcome): void {
    const p = this.pending;
    if (!p) return;
    this.pending = null;
    clearTimeout(p.timer);
    p.resolve(outcome);
  }
}

/** The body of `POST /api/code-exercises/topics/{id}/attempts` for a finished run, or null when nothing should be reported. */
export function attemptBody(result: RunResultPayload, filesHash: string | null): { results: TestResult[]; files_hash?: string; runner: string; duration_ms: number } | null {
  // a build error, a crash or a timeout produced no test results: nothing to grade
  if (result.total === 0 || result.tests.length === 0 || (result.status !== "passed" && result.status !== "failed")) return null;
  return {
    results: result.tests.map((t) => ({ name: t.name, status: t.status, ...(t.message ? { message: t.message } : {}) })),
    ...(filesHash ? { files_hash: filesHash } : {}),
    runner: "browser:1",
    duration_ms: Math.min(3_600_000, Math.max(0, Math.round(result.durationMs))),
  };
}

/** sha256 of the files (path, then content, in order) as 64 hex characters; null where SubtleCrypto is not available. */
export async function filesHash(files: readonly CodeFile[]): Promise<string | null> {
  try {
    const text = JSON.stringify(files.map((f) => [f.path, f.content]));
    const digest = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(text));
    return Array.from(new Uint8Array(digest), (b) => b.toString(16).padStart(2, "0")).join("");
  } catch {
    return null;
  }
}

export type DraftStore = Pick<Storage, "getItem" | "setItem" | "removeItem">;

export const draftKey = (topicId: number | string): string => `ulams:code-exercise:${topicId}`;

/** The learner's saved edits keyed by path, or {} (storage can be missing or throw in private windows). */
export function loadDraft(store: DraftStore | null, topicId: number | string, starter: readonly CodeFile[]): Record<string, string> {
  try {
    const raw = store?.getItem(draftKey(topicId));
    if (!raw) return {};
    const saved = JSON.parse(raw) as { files?: Record<string, unknown>; starter?: string };
    // a draft belongs to one version of the starter files: the exercise changed, the draft is dropped
    if (saved.starter !== starterSignature(starter) || typeof saved.files !== "object" || saved.files === null) return {};
    const paths = new Set(starter.map((f) => f.path));
    return Object.fromEntries(Object.entries(saved.files).filter(([path, content]) => paths.has(path) && typeof content === "string" && content.length <= 64 * 1024)) as Record<string, string>;
  } catch {
    return {};
  }
}

export function saveDraft(store: DraftStore | null, topicId: number | string, starter: readonly CodeFile[], files: readonly CodeFile[]): void {
  try {
    const changed = files.filter((f) => starter.find((s) => s.path === f.path)?.content !== f.content);
    if (changed.length === 0) store?.removeItem(draftKey(topicId));
    else store?.setItem(draftKey(topicId), JSON.stringify({ starter: starterSignature(starter), files: Object.fromEntries(changed.map((f) => [f.path, f.content])) }));
  } catch {
    /* no storage: the draft is simply not kept */
  }
}

/** A short fingerprint of the starter files (not a hash for security: it only detects a changed exercise). */
function starterSignature(starter: readonly CodeFile[]): string {
  let h = 5381;
  for (const f of starter) for (const ch of `${f.path}\n${f.content}\n`) h = ((h << 5) + h + ch.charCodeAt(0)) | 0;
  return String(h >>> 0);
}

/** `x = 1` lines of a build error list for the page, at most five, each cut to 300 characters. */
export function buildErrors(result: RunResultPayload): string[] {
  return (result.error ?? "").split("\n").map((l) => l.slice(0, 300)).filter(Boolean).slice(0, 5);
}
