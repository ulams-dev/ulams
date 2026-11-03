import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import type { Host, HostOptions } from "@ulams/interactive-bridge";
import type { Envelope, PackageMessage, RunResultPayload } from "@ulams/interactive-bridge/protocol";
import { attemptBody, buildErrors, CodeRunnerClient, draftKey, filesHash, loadDraft, saveDraft, type RunOutcome, type RunnerState } from "../src/lib/code-exercise.ts";

const files = [{ path: "sum.js", content: "export const sum = (a, b) => a + b;" }];
const tests = [{ path: "sum.test.js", content: "test('adds', () => expect(sum(1, 2)).toBe(3));" }];

function fake() {
  const calls: string[] = [];
  const runs: Array<{ runId: number; files: unknown }> = [];
  let options!: HostOptions;
  const frame = { src: "", removeAttribute: vi.fn() } as unknown as HTMLIFrameElement;
  let hosts = 0;
  const create = (_f: HTMLIFrameElement, o: HostOptions): Host => {
    options = o;
    hosts++;
    return {
      ready: false,
      goToStep() {},
      setTheme() {},
      setLocale() {},
      pause() {},
      resume() {},
      sendInit() {},
      loadExercise: (e) => void calls.push(`load:${e.language}:${e.timeoutMs}:${e.tests.length}`),
      run: (r) => {
        calls.push(`run:${r.runId}`);
        runs.push(r);
      },
      reset: () => void calls.push("reset"),
      destroy: () => void calls.push("destroy"),
    };
  };
  const states: RunnerState[] = [];
  const client = new CodeRunnerClient({ frame, src: "http://content.localhost/runtime/code-runner/1/runner.html", language: "javascript", tests, timeoutMs: 1000, graceMs: 500, readyTimeoutMs: 2000, createHost: create, onState: (s) => states.push(s) });
  const say = (m: Partial<PackageMessage> & { type: string }) => options.onMessage({ "ulams-ix": 1, nonce: "n".repeat(16), ...m } as Envelope<PackageMessage>);
  const result = (runId: number, patch: Partial<RunResultPayload> = {}): RunResultPayload => ({ runId, status: "passed", tests: [{ name: "adds", status: "pass" }], passed: 1, total: 1, durationMs: 4, ...patch });
  return { client, frame, calls, runs, states, say, result, timeoutReady: () => options.onTimeout?.(), hosts: () => hosts };
}

beforeEach(() => vi.useFakeTimers());
afterEach(() => vi.useRealTimers());

describe("CodeRunnerClient", () => {
  it("loads the runner into the frame, sends the tests once it is ready and runs files", async () => {
    const f = fake();
    f.client.start();
    expect(f.frame.src).toBe("http://content.localhost/runtime/code-runner/1/runner.html");
    expect(f.states).toEqual(["loading"]);
    f.say({ type: "ready", protocol: 1, steps: [], capabilities: {} } as never);
    expect(f.states).toEqual(["loading", "ready"]);
    expect(f.calls).toEqual(["load:javascript:1000:1"]);

    const pending = f.client.run(files);
    await Promise.resolve();
    expect(f.calls).toContain("run:1");
    f.say({ type: "runResult", ...f.result(1) });
    expect(await pending).toEqual({ kind: "result", result: f.result(1) });
  });

  it("waits for ready when asked to run early", async () => {
    const f = fake();
    f.client.start();
    const pending = f.client.run(files);
    await Promise.resolve();
    expect(f.calls).toEqual([]);
    f.say({ type: "ready", protocol: 1, steps: [], capabilities: {} } as never);
    await vi.advanceTimersByTimeAsync(0);
    expect(f.calls).toContain("run:1");
    f.say({ type: "runResult", ...f.result(1, { status: "failed", passed: 0, tests: [{ name: "adds", status: "fail", message: "no" }] }) });
    expect((await pending).kind).toBe("result");
  });

  it("is unavailable when the runner reports an error or never answers, and then answers runs with unavailable", async () => {
    const a = fake();
    a.client.start();
    const waiting = a.client.run(files);
    a.say({ type: "error", code: "runner-unavailable" });
    expect(await waiting).toEqual({ kind: "unavailable" });
    expect(a.client.current).toBe("unavailable");
    expect(await a.client.run(files)).toEqual({ kind: "unavailable" });

    const b = fake();
    b.client.start();
    b.timeoutReady();
    expect(b.states).toEqual(["loading", "unavailable"]);
    expect(await b.client.whenReady()).toBe(false);
  });

  it("ignores a result for another run id", async () => {
    const f = fake();
    f.client.start();
    f.say({ type: "ready", protocol: 1, steps: [], capabilities: {} } as never);
    const pending = f.client.run(files);
    await Promise.resolve();
    f.say({ type: "runResult", ...f.result(99) });
    let settled: RunOutcome | null = null;
    void pending.then((o) => (settled = o));
    await vi.advanceTimersByTimeAsync(10);
    expect(settled).toBeNull();
    f.say({ type: "runResult", ...f.result(1) });
    expect((await pending).kind).toBe("result");
  });

  it("reloads the runner when a run never answers, so the lesson page is never held up", async () => {
    const f = fake();
    f.client.start();
    f.say({ type: "ready", protocol: 1, steps: [], capabilities: {} } as never);
    const pending = f.client.run(files);
    await Promise.resolve();
    expect(f.hosts()).toBe(1);
    await vi.advanceTimersByTimeAsync(1000 + 500);
    expect(await pending).toEqual({ kind: "stopped" });
    expect(f.hosts()).toBe(2);
    expect(f.calls).toContain("destroy");
    expect(f.states.at(-1)).toBe("loading");
  });

  it("reset stops the run in progress and tells the runner", async () => {
    const f = fake();
    f.client.start();
    f.say({ type: "ready", protocol: 1, steps: [], capabilities: {} } as never);
    const pending = f.client.run(files);
    await Promise.resolve();
    f.client.reset();
    expect(await pending).toEqual({ kind: "stopped" });
    expect(f.calls.at(-1)).toBe("reset");
  });

  it("destroy stops everything and answers waiting callers", async () => {
    const f = fake();
    f.client.start();
    const waiting = f.client.whenReady();
    f.client.destroy();
    expect(await waiting).toBe(false);
    expect(f.calls).toContain("destroy");
  });
});

describe("attemptBody", () => {
  const base: RunResultPayload = { runId: 1, status: "failed", tests: [{ name: "a", status: "pass" }, { name: "b", status: "fail", message: "boom" }], passed: 1, total: 2, durationMs: 12.7 };
  it("reports the tests of a finished run with a rounded duration and the runner label", () => {
    expect(attemptBody(base, "a".repeat(64))).toEqual({
      results: [{ name: "a", status: "pass" }, { name: "b", status: "fail", message: "boom" }],
      files_hash: "a".repeat(64),
      runner: "browser:1",
      duration_ms: 13,
    });
    expect(attemptBody(base, null)).not.toHaveProperty("files_hash");
  });
  it("reports nothing for a build error, a crash, a timeout or a run without tests", () => {
    expect(attemptBody({ ...base, status: "error", tests: [], passed: 0, total: 0, error: "x" }, null)).toBeNull();
    expect(attemptBody({ ...base, status: "timeout", tests: [], passed: 0, total: 0 }, null)).toBeNull();
    expect(attemptBody({ ...base, status: "passed", tests: [], total: 0, passed: 0 }, null)).toBeNull();
  });
});

describe("filesHash", () => {
  it("is a stable sha256 of paths and contents, different when a character changes", async () => {
    const a = await filesHash(files);
    expect(a).toMatch(/^[a-f0-9]{64}$/);
    expect(await filesHash(files)).toBe(a);
    expect(await filesHash([{ path: "sum.js", content: "export const sum = (a, b) => a - b;" }])).not.toBe(a);
  });
});

describe("drafts", () => {
  const starter = [{ path: "a.js", content: "// start" }, { path: "b.js", content: "// b" }];
  const memory = () => {
    const data = new Map<string, string>();
    return { getItem: (k: string) => data.get(k) ?? null, setItem: (k: string, v: string) => void data.set(k, v), removeItem: (k: string) => void data.delete(k), data };
  };

  it("keeps only the files the learner changed and restores them", () => {
    const store = memory();
    saveDraft(store, 7, starter, [{ path: "a.js", content: "// mine" }, { path: "b.js", content: "// b" }]);
    expect(JSON.parse(store.data.get(draftKey(7))!).files).toEqual({ "a.js": "// mine" });
    expect(loadDraft(store, 7, starter)).toEqual({ "a.js": "// mine" });
  });

  it("removes the draft when the files are back at the starting point", () => {
    const store = memory();
    saveDraft(store, 7, starter, [{ path: "a.js", content: "// mine" }, { path: "b.js", content: "// b" }]);
    saveDraft(store, 7, starter, starter);
    expect(store.data.size).toBe(0);
  });

  it("drops a draft when the exercise changed, and ignores files that no longer exist and bad data", () => {
    const store = memory();
    saveDraft(store, 7, starter, [{ path: "a.js", content: "// mine" }, { path: "b.js", content: "// b" }]);
    expect(loadDraft(store, 7, [{ path: "a.js", content: "// a new start" }, starter[1]!])).toEqual({});
    store.setItem(draftKey(8), "{not json");
    expect(loadDraft(store, 8, starter)).toEqual({});
    store.setItem(draftKey(9), JSON.stringify({ starter: "x", files: { "../evil": "x" } }));
    expect(loadDraft(store, 9, starter)).toEqual({});
  });

  it("works without storage and when storage throws", () => {
    const broken = { getItem: () => { throw new Error("blocked"); }, setItem: () => { throw new Error("blocked"); }, removeItem: () => { throw new Error("blocked"); } };
    expect(() => saveDraft(broken, 1, starter, [{ path: "a.js", content: "x" }])).not.toThrow();
    expect(loadDraft(broken, 1, starter)).toEqual({});
    expect(loadDraft(null, 1, starter)).toEqual({});
  });
});

describe("buildErrors", () => {
  it("splits the error text into at most five short lines", () => {
    expect(buildErrors({ runId: 1, status: "error", tests: [], passed: 0, total: 0, durationMs: 1, error: Array.from({ length: 8 }, (_, i) => `sum.js:${i}:1: bad`).join("\n") })).toHaveLength(5);
    expect(buildErrors({ runId: 1, status: "error", tests: [], passed: 0, total: 0, durationMs: 1 })).toEqual([]);
  });
});
