import { describe, expect, it, vi } from "vitest";
import { connect } from "../src/package.ts";
import { FakeWindow } from "./fake-window.ts";
import { NONCE, VALID, env } from "./fixtures.ts";

const setup = (options = {}) => {
  const parent = new FakeWindow();
  const win = new FakeWindow();
  win.parent = parent;
  const bridge = connect({ ...options, window: win as unknown as Window });
  return { parent, win, bridge };
};

describe("connect (package side)", () => {
  it("is a no-op when there is no parent window", () => {
    const win = new FakeWindow();
    const bridge = connect({ window: win as unknown as Window });
    bridge.complete();
    bridge.stepChanged("a");
    expect(bridge.connected).toBe(false);
    expect(win.listeners).toHaveLength(0);
    expect(win.posted).toHaveLength(0);
  });

  it("queues calls until init, then answers ready and flushes in order", () => {
    const onInit = vi.fn();
    const { parent, win, bridge } = setup({ steps: ["inertia"], capabilities: { steps: true }, onInit });
    bridge.stepChanged("inertia");
    bridge.progress(2);
    expect(parent.posted).toHaveLength(0);
    win.dispatch(env(VALID.init!), parent);
    expect(onInit).toHaveBeenCalledOnce();
    const types = parent.posted.map((p) => p.message.type);
    expect(types).toEqual(["ready", "stepChanged", "progress"]);
    expect(parent.posted.every((p) => p.message.nonce === NONCE && p.target === "*")).toBe(true);
    expect((parent.posted[2]!.message as { value: number }).value).toBe(1);
  });

  it("holds calls made inside onInit until ready has been sent (the host ignores anything before ready)", () => {
    const holder: { bridge?: ReturnType<typeof connect> } = {};
    const { parent, win, bridge } = setup({ onInit: () => holder.bridge?.stepChanged("too-slow") });
    holder.bridge = bridge;
    win.dispatch(env(VALID.init!), parent);
    expect(parent.posted.map((p) => p.message.type)).toEqual(["ready", "stepChanged"]);
    bridge.stepChanged("too-fast");
    expect(parent.posted.map((p) => p.message.type)).toEqual(["ready", "stepChanged", "stepChanged"]);
  });

  it("holds ready until whenReady settles, reads the steps then, and queues calls meanwhile", async () => {
    let finish!: () => void;
    const loading = new Promise<void>((resolve) => (finish = resolve));
    let ids: string[] = [];
    const holder: { bridge?: ReturnType<typeof connect> } = {};
    const { parent, win, bridge } = setup({ steps: () => ids, whenReady: loading });
    holder.bridge = bridge;
    win.dispatch(env(VALID.init!), parent);
    bridge.stepChanged("too-slow");
    expect(parent.posted).toHaveLength(0);
    ids = ["too-slow", "too-fast"];
    finish();
    await loading;
    await Promise.resolve();
    expect(parent.posted.map((p) => p.message.type)).toEqual(["ready", "stepChanged"]);
    expect((parent.posted[0]!.message as { steps: string[] }).steps).toEqual(["too-slow", "too-fast"]);
  });

  it("answers ready even when whenReady rejects (the package reports its own error)", async () => {
    const { parent, win } = setup({ whenReady: Promise.reject(new Error("no data")) });
    win.dispatch(env(VALID.init!), parent);
    await Promise.resolve();
    await Promise.resolve();
    expect(parent.posted.map((p) => p.message.type)).toEqual(["ready"]);
  });

  it("ignores messages that do not come from the parent, with a wrong nonce, or a second init", () => {
    const onGo = vi.fn();
    const { parent, win } = setup({ onGoToStep: onGo });
    win.dispatch(env(VALID.init!), {});
    expect(parent.posted).toHaveLength(0);
    win.dispatch(env(VALID.init!), parent);
    win.dispatch(env(VALID.goToStep!, "f".repeat(32)), parent);
    win.dispatch(env(VALID.goToStep!), {});
    expect(onGo).not.toHaveBeenCalled();
    win.dispatch(env(VALID.init!, "g".repeat(32)), parent);
    win.dispatch(env(VALID.goToStep!), parent);
    expect(onGo).toHaveBeenCalledWith("too-slow");
    expect(parent.posted.filter((p) => p.message.type === "ready")).toHaveLength(1);
  });

  it("routes theme, locale, pause and resume", () => {
    const o = { onTheme: vi.fn(), onLocale: vi.fn(), onPause: vi.fn(), onResume: vi.fn() };
    const { parent, win } = setup(o);
    win.dispatch(env(VALID.init!), parent);
    for (const t of ["setTheme", "setLocale", "pause", "resume"]) win.dispatch(env(VALID[t]!), parent);
    expect(o.onTheme).toHaveBeenCalledOnce();
    expect(o.onLocale).toHaveBeenCalledWith("pl");
    expect(o.onPause).toHaveBeenCalledOnce();
    expect(o.onResume).toHaveBeenCalledOnce();
  });

  it("sends every message type after init and stops after destroy", () => {
    const { parent, win, bridge } = setup();
    win.dispatch(env(VALID.init!), parent);
    parent.posted.length = 0;
    bridge.stepChanged("a");
    bridge.progress(0.25);
    bridge.complete();
    bridge.score(1, 2);
    bridge.score(1, 2, true);
    bridge.event("http://adlnet.gov/expapi/verbs/interacted", "x", { response: "1" });
    bridge.resize(100.4);
    bridge.error("webgl-unavailable");
    expect(parent.posted.map((p) => p.message.type)).toEqual(["stepChanged", "progress", "complete", "score", "score", "event", "resize", "error"]);
    expect((parent.posted[6]!.message as { height: number }).height).toBe(100);
    bridge.destroy();
    expect(win.listeners).toHaveLength(0);
  });

  it("drops oversized outgoing messages", () => {
    const { parent, win, bridge } = setup();
    win.dispatch(env(VALID.init!), parent);
    parent.posted.length = 0;
    bridge.event("http://x.y/z", "a", { response: "a".repeat(20_000) });
    expect(parent.posted).toHaveLength(0);
  });
});
