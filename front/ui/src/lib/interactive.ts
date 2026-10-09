/**
 * Pure helpers of the Interactive lesson (ADR 0086): which steps a topic plays, whether the live frame
 * should start at all (reduced motion, WebGL), the theme tokens sent to the package and the queue that
 * batches the bridge's events for the BFF. No DOM access, so they are unit-testable in node.
 */

export interface StepInfo {
  id: string;
  title: string;
  text: string;
  poster?: string;
}

/** The steps of the range `[start, end]` (both optional, inclusive); all steps when a bound is unknown. */
export function stepsInRange<T extends { id: string }>(steps: readonly T[], start?: string, end?: string): T[] {
  const from = start ? steps.findIndex((s) => s.id === start) : 0;
  const to = end ? steps.findIndex((s) => s.id === end) : steps.length - 1;
  const a = from < 0 ? 0 : from;
  const b = to < 0 ? steps.length - 1 : to;
  return a <= b ? steps.slice(a, b + 1) : [...steps];
}

export type StartMode = "frame" | "reduced-motion" | "webgl";

/**
 * Whether the live frame starts. With reduced motion on, a package that does not handle it itself
 * is replaced by the step's poster and text (the learner can still play it); a package that needs
 * WebGL is replaced the same way when the browser has none.
 */
export function startMode(input: { reducedMotion: boolean; reducedMotionSupported: boolean; requires: readonly string[]; webgl: boolean }): StartMode {
  if (input.requires.includes("webgl") && !input.webgl) return "webgl";
  if (input.reducedMotion && !input.reducedMotionSupported) return "reduced-motion";
  return "frame";
}

/** The `--ulams-*` tokens sent to the package in `init` (name to value; empty values are skipped). */
export const THEME_TOKENS = [
  "--ulams-color-primary",
  "--ulams-color-on-primary",
  "--ulams-color-text",
  "--ulams-color-muted",
  "--ulams-color-bg",
  "--ulams-color-surface",
  "--ulams-color-card-bg",
  "--ulams-color-border",
  "--ulams-color-accent",
  "--ulams-color-header",
  "--ulams-font-family",
  "--ulams-font-family-body",
  "--ulams-font-mono",
  "--ulams-radius",
  "--ulams-radius-button",
  "--ulams-radius-card",
] as const;

export function themeTokens(read: (name: string) => string): Record<string, string> {
  const out: Record<string, string> = {};
  for (const name of THEME_TOKENS) {
    const value = read(name).trim();
    if (value !== "" && value.length <= 200) out[name] = value;
  }
  return out;
}

export interface BridgeEvent {
  type: string;
  [key: string]: unknown;
}

export interface QueueOptions {
  /** Sends one batch (at most `maxBatch` events). The resolved value is passed to `onResult`. */
  send: (events: BridgeEvent[]) => Promise<unknown>;
  onResult?: (result: unknown) => void;
  intervalMs?: number;
  maxBatch?: number;
  maxPerSecond?: number;
  now?: () => number;
}

/**
 * Batches bridge events for `POST /bff/api/interactive/topics/{topic}/events`: one request every
 * `intervalMs` (2 s), at most `maxBatch` events per request (40, the API's limit), at most
 * `maxPerSecond` events accepted (20, ADR 0087). A `progress` event replaces a queued one (only the
 * latest value matters); `complete` and `score` are never dropped by the rate limit.
 */
export class EventQueue {
  private queue: BridgeEvent[] = [];
  private timer: ReturnType<typeof setInterval> | undefined;
  private stamps: number[] = [];
  private readonly intervalMs: number;
  private readonly maxBatch: number;
  private readonly maxPerSecond: number;
  private readonly now: () => number;

  constructor(private readonly options: QueueOptions) {
    this.intervalMs = options.intervalMs ?? 2000;
    this.maxBatch = options.maxBatch ?? 40;
    this.maxPerSecond = options.maxPerSecond ?? 20;
    this.now = options.now ?? Date.now;
  }

  get size(): number {
    return this.queue.length;
  }

  push(event: BridgeEvent): boolean {
    const critical = event.type === "complete" || event.type === "score";
    const t = this.now();
    this.stamps = this.stamps.filter((s) => t - s < 1000);
    if (!critical && this.stamps.length >= this.maxPerSecond) return false;
    this.stamps.push(t);
    if (event.type === "progress") {
      const at = this.queue.findIndex((e) => e.type === "progress");
      if (at >= 0) this.queue.splice(at, 1);
    }
    this.queue.push(event);
    return true;
  }

  start(): void {
    if (this.timer === undefined) this.timer = setInterval(() => void this.flush(), this.intervalMs);
  }

  stop(): void {
    if (this.timer !== undefined) clearInterval(this.timer);
    this.timer = undefined;
  }

  /** Sends everything queued, in batches. Failed batches are put back at the front for the next flush. */
  async flush(): Promise<void> {
    while (this.queue.length > 0) {
      const batch = this.queue.splice(0, this.maxBatch);
      try {
        this.options.onResult?.(await this.options.send(batch));
      } catch {
        this.queue.unshift(...batch);
        return;
      }
    }
  }
}

/** "Step 3 of 5". */
export const stepCounter = (index: number, total: number): string => `Step ${index + 1} of ${total}`;
