import type { CodeFile, CodeLanguage, RunResultPayload, TestResult } from "@ulams/interactive-bridge/protocol";
import { bundleExercise, type EsbuildLike } from "./bundle.ts";
import type { FromExec } from "./exec-protocol.ts";

/**
 * The runner's logic, without the DOM (ADR 0170): bundle the exercise, hand the bundle to a fresh exec frame,
 * wait for its answer within the time limit, and turn what comes back into one `runResult`. At most one
 * run is active; starting another, `reset` and `dispose` all stop the active one, whose frame is removed.
 */

export interface ExecHandle {
  /** Resolves with the frame's answer: a result, a crash or a timeout. */
  outcome: Promise<Exclude<FromExec, { type: "ready" }>>;
  /** Removes the frame (and with it the Worker). Safe to call more than once. */
  close(): void;
}

export interface RunnerDeps {
  esbuild: EsbuildLike;
  /** Opens a fresh sandboxed exec frame and sends it the code. */
  openExec(code: string, timeoutMs: number): ExecHandle;
  now(): number;
  setTimeout(callback: () => void, ms: number): unknown;
  clearTimeout(handle: unknown): void;
}

export interface LoadedExercise {
  language: CodeLanguage;
  tests: CodeFile[];
  timeoutMs: number;
}

/** Extra time over the exercise's limit that the runner waits for exec.html to answer before it stops the frame itself. */
export const GRACE_MS = 2000;

const result = (runId: number, started: number, now: number, patch: Partial<RunResultPayload>): RunResultPayload => ({
  runId,
  status: "error",
  tests: [],
  passed: 0,
  total: 0,
  durationMs: Math.max(0, Math.round(now - started)),
  ...patch,
});

export function createRunner(deps: RunnerDeps) {
  let exercise: LoadedExercise | null = null;
  let active: { runId: number; handle: ExecHandle | null; cancelled: boolean; timer: unknown; cancel: () => void } | null = null;

  const stop = () => {
    if (!active) return;
    active.cancelled = true;
    active.cancel();
    deps.clearTimeout(active.timer);
    active.handle?.close();
    active = null;
  };

  return {
    load(next: LoadedExercise): void {
      stop();
      exercise = next;
    },

    /** Stops the active run without an answer: the page that asked has already given up on it. */
    reset(): void {
      stop();
    },

    dispose(): void {
      stop();
      exercise = null;
    },

    /** The `runResult` of one run, or null when the run was stopped by `reset`, a newer run or `dispose`. */
    async run(runId: number, files: CodeFile[]): Promise<RunResultPayload | null> {
      stop();
      const started = deps.now();
      if (!exercise) return result(runId, started, deps.now(), { error: "The exercise has not been loaded." });
      const current = exercise;
      let cancel: () => void = () => undefined;
      const cancelled = new Promise<"cancelled">((resolve) => {
        cancel = () => resolve("cancelled");
      });
      const me = { runId, handle: null as ExecHandle | null, cancelled: false, timer: undefined as unknown, cancel };
      active = me;

      const built = await bundleExercise(deps.esbuild, { language: current.language, files, tests: current.tests });
      if (me.cancelled) return null;
      if (!built.ok) {
        active = null;
        return result(runId, started, deps.now(), { error: built.errors.join("\n").slice(0, 500), output: built.errors.join("\n").slice(0, 8192) });
      }

      const timeout = new Promise<"stopped">((resolve) => {
        me.timer = deps.setTimeout(() => resolve("stopped"), current.timeoutMs + GRACE_MS);
      });
      const execStarted = deps.now();
      me.handle = deps.openExec(built.code, current.timeoutMs);
      const outcome = await Promise.race([me.handle.outcome, timeout, cancelled]);
      if (me.cancelled || outcome === "cancelled") return null;
      deps.clearTimeout(me.timer);
      me.handle.close();
      active = null;
      const took = deps.now();

      if (outcome === "stopped" || outcome.type === "timeout") {
        return result(runId, execStarted, took, { status: "timeout", error: `The tests did not finish within ${current.timeoutMs / 1000} s and were stopped.` });
      }
      if (outcome.type === "crash") {
        return result(runId, execStarted, took, { error: outcome.message.slice(0, 500) });
      }
      const tests: TestResult[] = outcome.tests;
      const passed = tests.filter((t) => t.status === "pass").length;
      const base: Partial<RunResultPayload> = { tests, passed, total: tests.length, ...(outcome.output ? { output: outcome.output, truncated: outcome.truncated } : {}) };
      if (tests.length === 0) return result(runId, execStarted, took, { ...base, error: "No tests ran. The test files must call test(name, fn)." });
      return result(runId, execStarted, took, { ...base, status: passed === tests.length ? "passed" : "failed" });
    },
  };
}
