import { describe, expect, it } from "vitest";
import { createRunner, GRACE_MS, type ExecHandle, type RunnerDeps } from "../src/runner-core.ts";
import type { FromExec } from "../src/exec-protocol.ts";
import { esbuild, file } from "./helpers.ts";

type Outcome = Exclude<FromExec, { type: "ready" }>;
const result = (tests: Array<{ name: string; status: "pass" | "fail" | "error" }>): Outcome => ({ kind: "ulams-exec", nonce: "n", type: "result", tests, output: "", truncated: false });

function setup(answer: () => Promise<Outcome> | Outcome | "never") {
  let clock = 0;
  const timers: Array<{ at: number; fn: () => void; live: boolean }> = [];
  const opened: Array<{ code: string; timeoutMs: number; closed: number }> = [];
  const deps: RunnerDeps = {
    esbuild,
    openExec(code, timeoutMs): ExecHandle {
      const record = { code, timeoutMs, closed: 0 };
      opened.push(record);
      const a = answer();
      return { outcome: a === "never" ? new Promise(() => undefined) : Promise.resolve(a), close: () => void record.closed++ };
    },
    now: () => clock,
    setTimeout: (fn, ms) => {
      const t = { at: clock + ms, fn, live: true };
      timers.push(t);
      return t;
    },
    clearTimeout: (h) => {
      const timer = h as { live: boolean } | undefined;
      if (timer) timer.live = false;
    },
  };
  return {
    runner: createRunner(deps),
    opened,
    advance(ms: number) {
      clock += ms;
      for (const t of timers) {
        if (t.live && t.at <= clock) {
          t.live = false;
          t.fn();
        }
      }
    },
  };
}

const exercise = { language: "javascript" as const, timeoutMs: 1000, tests: [file("sum.test.js", "import { sum } from './sum.js';\ntest('adds', () => expect(sum(1, 2)).toBe(3));")] };
const files = [file("sum.js", "export const sum = (a, b) => a + b;")];

describe("createRunner", () => {
  it("bundles, runs in a fresh frame, closes it and reports passed", async () => {
    const { runner, opened } = setup(() => result([{ name: "adds", status: "pass" }]));
    runner.load(exercise);
    const answer = await runner.run(1, files);
    expect(answer).toMatchObject({ runId: 1, status: "passed", passed: 1, total: 1, tests: [{ name: "adds", status: "pass" }] });
    expect(opened).toHaveLength(1);
    expect(opened[0]!.closed).toBe(1);
    expect(opened[0]!.code).toContain("adds");
    expect(opened[0]!.timeoutMs).toBe(1000);
  });

  it("reports failed when a test fails and error when no test ran", async () => {
    const failed = setup(() => result([{ name: "a", status: "pass" }, { name: "b", status: "fail" }]));
    failed.runner.load(exercise);
    expect(await failed.runner.run(2, files)).toMatchObject({ status: "failed", passed: 1, total: 2 });
    const none = setup(() => result([]));
    none.runner.load(exercise);
    expect(await none.runner.run(3, files)).toMatchObject({ status: "error", total: 0, error: expect.stringContaining("No tests ran") });
  });

  it("answers a build error without opening a frame", async () => {
    const { runner, opened } = setup(() => result([]));
    runner.load(exercise);
    const answer = await runner.run(4, [file("sum.js", "export const sum = (a, b) => a +;")]);
    expect(answer).toMatchObject({ runId: 4, status: "error", total: 0, error: expect.stringMatching(/^sum\.js:1:/) });
    expect(opened).toHaveLength(0);
  });

  it("answers a crash and a timeout from the frame", async () => {
    const crash = setup(() => ({ kind: "ulams-exec", nonce: "n", type: "crash", message: "boom" }));
    crash.runner.load(exercise);
    expect(await crash.runner.run(5, files)).toMatchObject({ status: "error", error: "boom" });
    const timeout = setup(() => ({ kind: "ulams-exec", nonce: "n", type: "timeout" }));
    timeout.runner.load(exercise);
    expect(await timeout.runner.run(6, files)).toMatchObject({ status: "timeout", error: expect.stringContaining("1 s") });
  });

  it("stops a frame that never answers after the limit plus the grace time, and removes it", async () => {
    const { runner, opened, advance } = setup(() => "never");
    runner.load(exercise);
    const pending = runner.run(7, files);
    await new Promise((r) => setTimeout(r, 200)); // the bundle is built; the frame is open
    expect(opened).toHaveLength(1);
    advance(1000 + GRACE_MS - 1);
    advance(2);
    expect(await pending).toMatchObject({ runId: 7, status: "timeout" });
    expect(opened[0]!.closed).toBe(1);
  });

  it("a reset or a newer run stops the active run without an answer", async () => {
    const first = setup(() => "never");
    first.runner.load(exercise);
    const a = first.runner.run(8, files);
    await new Promise((r) => setTimeout(r, 200));
    first.runner.reset();
    expect(await a).toBeNull();
    expect(first.opened[0]!.closed).toBe(1);

    const second = setup(() => "never");
    second.runner.load(exercise);
    const b = second.runner.run(9, files);
    await new Promise((r) => setTimeout(r, 200));
    const c = second.runner.run(10, files);
    expect(await b).toBeNull();
    second.runner.dispose();
    expect(await c).toBeNull();
  });

  it("answers an error for a run before the exercise is loaded", async () => {
    const { runner } = setup(() => result([]));
    expect(await runner.run(11, files)).toMatchObject({ status: "error", error: "The exercise has not been loaded." });
  });
});
