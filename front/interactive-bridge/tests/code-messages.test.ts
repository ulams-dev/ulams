import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { createHost } from "../src/host.ts";
import { connect } from "../src/package.ts";
import { MAX_CODE_MESSAGE_BYTES, MAX_MESSAGE_BYTES, parseMessage, withinSize } from "../src/protocol.ts";
import { FakeWindow } from "./fake-window.ts";
import { NONCE, VALID, env, init } from "./fixtures.ts";

/** The code exercise messages of ulams-ix v1 (ADR 0170): loadExercise, run, reset and runResult. */

const files = (n: number, content = "x") => Array.from({ length: n }, (_, i) => ({ path: `f${i}.js`, content }));

describe("loadExercise", () => {
  const ok = VALID.loadExercise!;
  it("is a parent message with a language, 1 to 10 test files and a timeout of 500 to 30000 ms", () => {
    expect(parseMessage(env(ok), "toPackage", NONCE)).not.toBeNull();
    for (const bad of [
      { ...ok, language: "python" },
      { ...ok, tests: [] },
      { ...ok, tests: files(11) },
      { ...ok, timeoutMs: 499 },
      { ...ok, timeoutMs: 30_001 },
      { ...ok, timeoutMs: 1000.5 },
      { ...ok, tests: [{ path: "../a.js", content: "x" }] },
      { ...ok, tests: [{ path: "/abs.js", content: "x" }] },
      { ...ok, tests: [{ path: "a.js", content: 5 }] },
      { ...ok, tests: [{ path: "a.js", content: "x".repeat(64 * 1024 + 1) }] },
    ]) {
      expect(parseMessage(env(bad as never), "toPackage", NONCE)).toBeNull();
    }
    expect(parseMessage(env(ok), "toParent", NONCE)).toBeNull();
  });
});

describe("run", () => {
  const ok = VALID.run!;
  it("carries a run id and 1 to 20 files", () => {
    expect(parseMessage(env(ok), "toPackage", NONCE)).not.toBeNull();
    expect(parseMessage(env({ ...ok, files: files(20) } as never), "toPackage", NONCE)).not.toBeNull();
    for (const bad of [{ ...ok, runId: 0 }, { ...ok, runId: "1" }, { ...ok, runId: 1.5 }, { ...ok, files: [] }, { ...ok, files: files(21) }, { ...ok, files: undefined }]) {
      expect(parseMessage(env(bad as never), "toPackage", NONCE)).toBeNull();
    }
  });
  it("may be larger than 16 KB, up to 640 KB, and no other type may", () => {
    const big = { ...ok, files: files(6, "a".repeat(60 * 1024)) };
    expect(JSON.stringify(env(big as never)).length).toBeGreaterThan(MAX_MESSAGE_BYTES);
    expect(parseMessage(env(big as never), "toPackage", NONCE)).not.toBeNull();
    const huge = { ...ok, files: files(20, "a".repeat(40 * 1024)) };
    expect(JSON.stringify(env(huge as never)).length).toBeGreaterThan(MAX_CODE_MESSAGE_BYTES);
    expect(parseMessage(env(huge as never), "toPackage", NONCE)).toBeNull();
    expect(withinSize({ type: "setTheme", theme: { "--a": "x".repeat(MAX_MESSAGE_BYTES) } })).toBe(false);
  });
});

describe("reset", () => {
  it("has no payload", () => {
    expect(parseMessage(env(VALID.reset!), "toPackage", NONCE)).not.toBeNull();
  });
});

describe("runResult", () => {
  const ok = VALID.runResult!;
  it("is a package message with the outcome of one run", () => {
    expect(parseMessage(env(ok), "toParent", NONCE)).not.toBeNull();
    expect(parseMessage(env(ok), "toPackage", NONCE)).toBeNull();
    const timeout = { type: "runResult", runId: 2, status: "timeout", tests: [], passed: 0, total: 0, durationMs: 5000, error: "The run took longer than 5 s." };
    expect(parseMessage(env(timeout as never), "toParent", NONCE)).not.toBeNull();
  });
  it("is bounded in every field", () => {
    const test = { name: "a", status: "pass" };
    for (const bad of [
      { ...ok, status: "done" },
      { ...ok, runId: 0 },
      { ...ok, passed: 3 },
      { ...ok, total: 201, passed: 0, tests: [] },
      { ...ok, tests: Array.from({ length: 201 }, () => test) },
      { ...ok, tests: [{ name: "", status: "pass" }] },
      { ...ok, tests: [{ name: "a", status: "skipped" }] },
      { ...ok, tests: [{ name: "a".repeat(161), status: "pass" }] },
      { ...ok, tests: [{ name: "a", status: "fail", message: "m".repeat(501) }] },
      { ...ok, output: "o".repeat(8193) },
      { ...ok, error: "e".repeat(501) },
      { ...ok, durationMs: -1 },
      { ...ok, truncated: "yes" },
    ]) {
      expect(parseMessage(env(bad as never), "toParent", NONCE)).toBeNull();
    }
  });
});

describe("host and package ends", () => {
  beforeEach(() => vi.useFakeTimers());
  afterEach(() => vi.useRealTimers());

  it("the host sends loadExercise, run and reset; the package receives them and answers runResult", () => {
    const parent = new FakeWindow();
    const win = new FakeWindow();
    win.parent = parent;
    const onLoadExercise = vi.fn();
    const onRun = vi.fn();
    const onReset = vi.fn();
    const bridge = connect({ window: win as unknown as Window, onLoadExercise, onRun, onReset });
    win.dispatch(env(init as never), parent);

    win.dispatch(env(VALID.loadExercise!), parent);
    win.dispatch(env(VALID.run!), parent);
    win.dispatch(env(VALID.reset!), parent);
    expect(onLoadExercise).toHaveBeenCalledWith(expect.objectContaining({ type: "loadExercise", language: "javascript", timeoutMs: 5000 }));
    expect(onRun).toHaveBeenCalledWith(expect.objectContaining({ type: "run", runId: 1 }));
    expect(onReset).toHaveBeenCalledOnce();

    bridge.runResult({ runId: 1, status: "passed", tests: [{ name: "a", status: "pass" }], passed: 1, total: 1, durationMs: 3 });
    const sent = parent.posted.at(-1)!.message as unknown as Record<string, unknown>;
    expect(sent).toMatchObject({ "ulams-ix": 1, type: "runResult", nonce: NONCE, runId: 1, status: "passed" });
  });

  it("the host posts the code messages to the frame with the nonce and accepts runResult only after ready", () => {
    const hostWin = new FakeWindow();
    const frameWin = new FakeWindow();
    const frame = { contentWindow: frameWin, addEventListener() {}, removeEventListener() {} };
    const onMessage = vi.fn();
    const host = createHost(frame as unknown as HTMLIFrameElement, { nonce: NONCE, init: { ...init, theme: {}, range: {} }, onMessage, window: hostWin as unknown as Window });

    host.loadExercise({ language: "typescript", tests: [{ path: "a.test.ts", content: "x" }], timeoutMs: 1000 });
    host.run({ runId: 7, files: [{ path: "a.ts", content: "y" }] });
    host.reset();
    expect(frameWin.posted.map((p) => p.message.type)).toEqual(["loadExercise", "run", "reset"]);
    expect(frameWin.posted.every((p) => p.message.nonce === NONCE && p.target === "*")).toBe(true);

    hostWin.dispatch(env(VALID.runResult!), frameWin);
    expect(onMessage).not.toHaveBeenCalled();
    hostWin.dispatch(env(VALID.ready!), frameWin);
    hostWin.dispatch(env(VALID.runResult!), frameWin);
    expect(onMessage.mock.calls.map((c) => c[0].type)).toEqual(["ready", "runResult"]);
  });
});
