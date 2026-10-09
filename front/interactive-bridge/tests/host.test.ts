import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { createHost, newNonce } from "../src/host.ts";
import { init, NONCE, VALID, env } from "./fixtures.ts";
import { FakeWindow } from "./fake-window.ts";

class FakeFrame {
  contentWindow: FakeWindow | null = new FakeWindow();
  loadListeners: Array<() => void> = [];
  addEventListener(_t: string, l: () => void) {
    this.loadListeners.push(l);
  }
  removeEventListener(_t: string, l: () => void) {
    this.loadListeners = this.loadListeners.filter((x) => x !== l);
  }
}

const setup = (extra = {}) => {
  const win = new FakeWindow();
  const frame = new FakeFrame();
  const onMessage = vi.fn();
  const onReady = vi.fn();
  const onTimeout = vi.fn();
  const host = createHost(frame as unknown as HTMLIFrameElement, { nonce: NONCE, init: { ...init, theme: { ...init.theme }, range: { ...init.range } }, onMessage, onReady, onTimeout, window: win as unknown as Window, ...extra });
  return { win, frame, host, onMessage, onReady, onTimeout };
};

beforeEach(() => vi.useFakeTimers());
afterEach(() => vi.useRealTimers());

describe("createHost (page side)", () => {
  it("sends init with the nonce on the frame load event", () => {
    const { frame } = setup();
    frame.loadListeners.forEach((l) => l());
    const sent = frame.contentWindow!.posted;
    expect(sent).toHaveLength(1);
    expect(sent[0]!.message).toMatchObject({ "ulams-ix": 1, type: "init", nonce: NONCE, display: "background" });
    expect(sent[0]!.target).toBe("*");
  });

  it("accepts a message only from the frame, with origin null and the nonce", () => {
    const { win, frame, onMessage, onReady } = setup();
    const f = frame.contentWindow;
    win.dispatch(env(VALID.ready!), {}, "null");
    win.dispatch(env(VALID.ready!), f, "https://evil.example");
    win.dispatch(env(VALID.ready!, "z".repeat(32)), f, "null");
    expect(onMessage).not.toHaveBeenCalled();
    win.dispatch(env(VALID.ready!), f, "null");
    expect(onReady).toHaveBeenCalledOnce();
    win.dispatch(env(VALID.stepChanged!), f, "null");
    expect(onMessage.mock.calls.map((c) => c[0].type)).toEqual(["ready", "stepChanged"]);
  });

  it("ignores everything before ready, invalid payloads and oversized messages", () => {
    const { win, frame, onMessage } = setup();
    const f = frame.contentWindow;
    win.dispatch(env(VALID.complete!), f);
    expect(onMessage).not.toHaveBeenCalled();
    win.dispatch(env(VALID.ready!), f);
    win.dispatch({ ...env(VALID.progress!), value: 9 }, f);
    win.dispatch({ ...env(VALID.event!), result: { response: "a".repeat(20_000) } }, f);
    expect(onMessage).toHaveBeenCalledTimes(1);
  });

  it("calls onTimeout when ready does not arrive in 10 s, and not after ready", () => {
    const a = setup();
    vi.advanceTimersByTime(9_999);
    expect(a.onTimeout).not.toHaveBeenCalled();
    vi.advanceTimersByTime(2);
    expect(a.onTimeout).toHaveBeenCalledOnce();
    const b = setup();
    b.win.dispatch(env(VALID.ready!), b.frame.contentWindow);
    vi.advanceTimersByTime(20_000);
    expect(b.onTimeout).not.toHaveBeenCalled();
  });

  it("sends goToStep, theme, locale, pause and resume to the frame", () => {
    const { frame, host } = setup();
    host.goToStep("too-slow");
    host.setTheme({ "--a": "1" });
    host.setLocale("pl");
    host.pause();
    host.resume();
    expect(frame.contentWindow!.posted.map((p) => p.message.type)).toEqual(["goToStep", "setTheme", "setLocale", "pause", "resume"]);
  });

  it("stops listening after destroy", () => {
    const { win, frame, host, onMessage } = setup();
    host.destroy();
    win.dispatch(env(VALID.ready!), frame.contentWindow);
    host.goToStep("a");
    expect(onMessage).not.toHaveBeenCalled();
    expect(frame.contentWindow!.posted).toHaveLength(0);
    expect(win.listeners).toHaveLength(0);
  });

  it("makes unique 32-character hex nonces", () => {
    const a = newNonce();
    expect(a).toMatch(/^[0-9a-f]{32}$/);
    expect(newNonce()).not.toBe(a);
  });
});
