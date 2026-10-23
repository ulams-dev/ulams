/**
 * Review time in the studio (ADR 0194, AM-5): when the academy records it, counts the author's active minutes on the
 * course of the page and reports them once a minute. Off unless `GET /studio/api/authoring-metrics/activity/status`
 * says it is on; a 404 from the server (the setting was switched off) stops it at once. The rules are in
 * review-time-model.ts; this file only wires them to the page: input events, visibility, a BroadcastChannel to elect
 * one sender among the tabs of this browser, and the posts. Nothing identifies the person.
 */
import { ActivityTracker, electLeader, newNonce } from "./review-time-model.ts";

const TICK_MS = 5_000;
const FLUSH_MS = 60_000;
const HELLO_MS = 5_000;
const STUDIO_URL = "/studio/api/authoring-metrics/activity";

type Message = { type: "hello"; id: string } | { type: "minute"; id: string; minute: number } | { type: "bye"; id: string };

export interface ReviewEnvironment {
  now: () => number;
  fetch: typeof fetch;
  visible: () => boolean;
  focused: () => boolean;
  channel: (name: string) => Pick<BroadcastChannel, "postMessage" | "close"> & { onmessage: ((e: MessageEvent<Message>) => void) | null };
  beacon: (url: string, body: string) => boolean;
}

/** `endpoint` is the BFF path in the studio and the API path in the admin course editor, which imports this file. */
export function startReviewTime(courseId: number, env: ReviewEnvironment, surface: "studio" | "admin" = "studio", endpoint: string = STUDIO_URL): { stop: () => void; tracker: ActivityTracker; flush: () => Promise<void>; tick: () => void; id: string } {
  const id = newNonce();
  const tracker = new ActivityTracker();
  const peers = new Map<string, number>();
  const channel = env.channel(`ulams-review-time:${courseId}`);
  let stopped = false;

  const leader = (): boolean => electLeader(id, peers, env.now()) === id;
  const say = (message: Message): void => channel.postMessage(message);

  channel.onmessage = (event) => {
    const m = event.data;
    if (!m || m.id === id) return;
    if (m.type === "bye") peers.delete(m.id);
    else peers.set(m.id, env.now());
    if (m.type === "minute") tracker.adopt(m.minute);
  };

  const tick = (): void => {
    const minute = tracker.tick(env.now(), env.visible(), env.focused());
    if (minute !== null) say({ type: "minute", id, minute });
  };

  const body = (minutes: string[]): string => JSON.stringify({ course_id: courseId, minutes, surface, tab_nonce: id });

  async function flush(): Promise<void> {
    if (stopped || !leader()) return;
    const minutes = tracker.batch(env.now());
    if (minutes.length === 0) return;
    try {
      const response = await env.fetch(endpoint, { method: "POST", headers: { "Content-Type": "application/json", Accept: "application/json" }, body: body(minutes), keepalive: true });
      if (response.status === 404) return stop(); // the setting was switched off, or the course is not a builder course
      if (response.ok) tracker.sent(minutes);
      else if (response.status === 403 || response.status === 422) tracker.drop(minutes);
    } catch {
      // offline: the minutes stay pending and go with the next call, until they are too old
    }
  }

  /** The page is going away: hand over what is pending without waiting for an answer. */
  function leaving(): void {
    tick(); // what the person is doing right now still counts
    say({ type: "bye", id });
    if (stopped || !leader()) return;
    const minutes = tracker.batch(env.now());
    if (minutes.length > 0 && env.beacon(endpoint, body(minutes))) tracker.sent(minutes);
  }

  const timers = [setInterval(tick, TICK_MS), setInterval(() => void flush(), FLUSH_MS), setInterval(() => say({ type: "hello", id }), HELLO_MS)];
  say({ type: "hello", id });
  const inputs = ["keydown", "pointerdown", "pointermove", "wheel", "scroll", "touchstart"] as const;
  const onInput = (): void => tracker.input(env.now());
  if (typeof window !== "undefined") {
    for (const name of inputs) window.addEventListener(name, onInput, { passive: true, capture: true });
    window.addEventListener("pagehide", leaving);
  }

  function stop(): void {
    stopped = true;
    for (const timer of timers) clearInterval(timer);
    if (typeof window !== "undefined") {
      for (const name of inputs) window.removeEventListener(name, onInput, { capture: true });
      window.removeEventListener("pagehide", leaving);
    }
    channel.close();
  }

  return { stop, tracker, flush, tick, id };
}

const browserEnvironment = (): ReviewEnvironment => ({
  now: () => Date.now(),
  fetch: (input, init) => fetch(input, init),
  visible: () => document.visibilityState === "visible",
  focused: () => document.hasFocus(),
  channel: (name) => (typeof BroadcastChannel === "undefined" ? { postMessage: () => undefined, close: () => undefined, onmessage: null } : new BroadcastChannel(name)),
  beacon: (url, body) => navigator.sendBeacon(url, new Blob([body], { type: "application/json" })),
});

/** Starts when the academy records review time and the page knows its course; shows the indicator while it runs. */
export async function mountReviewTime(env: ReviewEnvironment = browserEnvironment()): Promise<void> {
  const indicator = document.querySelector<HTMLElement>("[data-review-indicator]");
  let started = false;

  async function begin(courseId: number): Promise<void> {
    if (started || !Number.isInteger(courseId) || courseId < 1) return;
    started = true;
    try {
      const response = await env.fetch(`${STUDIO_URL}/status`, { headers: { Accept: "application/json" } });
      const enabled = response.ok && ((await response.json()) as { data?: { enabled?: boolean } }).data?.enabled === true;
      if (!enabled) return;
    } catch {
      return;
    }
    if (indicator) indicator.hidden = false;
    startReviewTime(courseId, env);
  }

  // client-driven pages learn the course after the session loads
  window.addEventListener("ulams:review-course", (event) => void begin(Number((event as CustomEvent).detail)));
  const fromPage = Number(document.body.dataset.reviewCourse);
  if (fromPage) await begin(fromPage);
}

if (typeof document !== "undefined" && document.querySelector("[data-review-indicator]")) void mountReviewTime();
