import type { Envelope, Message } from "../src/protocol.ts";

export const NONCE = "0123456789abcdef0123456789abcdef";

export const init = {
  type: "init",
  locale: "en",
  theme: { "--ulams-color-surface": "#fff" },
  reducedMotion: false,
  display: "background",
  chrome: "minimal",
  startStep: "inertia",
  range: { from: "inertia", to: "too-slow" },
  readOnly: false,
} as const;

/** One valid example of every message type. */
export const VALID: Record<string, Message> = {
  init,
  goToStep: { type: "goToStep", step: "too-slow" },
  setTheme: { type: "setTheme", theme: { "--ulams-color-text": "#111" } },
  setLocale: { type: "setLocale", locale: "pl" },
  pause: { type: "pause" },
  resume: { type: "resume" },
  ready: { type: "ready", protocol: 1, steps: ["inertia", "too-slow"], capabilities: { steps: true, reducedMotion: true, locales: ["en", "pl"] } },
  resize: { type: "resize", height: 640 },
  stepChanged: { type: "stepChanged", step: "inertia" },
  progress: { type: "progress", value: 0.5 },
  complete: { type: "complete" },
  score: { type: "score", raw: 8, max: 10, passed: true },
  event: { type: "event", verb: "http://adlnet.gov/expapi/verbs/interacted", object: "slider-velocity", result: { response: "11.2" } },
  error: { type: "error", code: "webgl-unavailable", message: "no webgl" },
  loadExercise: { type: "loadExercise", language: "javascript", tests: [{ path: "sum.test.js", content: "test('adds', () => expect(1 + 2).toBe(3));" }], timeoutMs: 5000 },
  run: { type: "run", runId: 1, files: [{ path: "sum.js", content: "export const sum = (a, b) => a + b;" }] },
  reset: { type: "reset" },
  runResult: {
    type: "runResult",
    runId: 1,
    status: "failed",
    tests: [
      { name: "adds", status: "pass" },
      { name: "subtracts", status: "fail", message: "Expected 1, received 3" },
    ],
    passed: 1,
    total: 2,
    durationMs: 12,
    output: "hello",
  },
};

export const env = (m: Message, nonce = NONCE): Envelope => ({ "ulams-ix": 1, nonce, ...m }) as Envelope;
