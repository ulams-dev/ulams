import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { EventQueue, nextShowcaseStep, showcaseMode, startMode, stepCounter, stepsInRange, themeTokens, THEME_TOKENS } from "../src/lib/interactive.ts";

const steps = ["a", "b", "c", "d"].map((id) => ({ id }));

describe("stepsInRange", () => {
  it("returns the inclusive range, or everything when a bound is missing or unknown", () => {
    expect(stepsInRange(steps, "b", "c").map((s) => s.id)).toEqual(["b", "c"]);
    expect(stepsInRange(steps, "c").map((s) => s.id)).toEqual(["c", "d"]);
    expect(stepsInRange(steps, undefined, "b").map((s) => s.id)).toEqual(["a", "b"]);
    expect(stepsInRange(steps).map((s) => s.id)).toEqual(["a", "b", "c", "d"]);
    expect(stepsInRange(steps, "x", "y").map((s) => s.id)).toEqual(["a", "b", "c", "d"]);
    expect(stepsInRange(steps, "d", "a").map((s) => s.id)).toEqual(["a", "b", "c", "d"]);
  });
});

describe("startMode", () => {
  const base = { reducedMotion: false, reducedMotionSupported: false, requires: [] as string[], webgl: true };
  it("starts the frame by default", () => expect(startMode(base)).toBe("frame"));
  it("shows the poster under reduced motion unless the package handles it", () => {
    expect(startMode({ ...base, reducedMotion: true })).toBe("reduced-motion");
    expect(startMode({ ...base, reducedMotion: true, reducedMotionSupported: true })).toBe("frame");
  });
  it("shows the poster when the package needs WebGL and the browser has none", () => {
    expect(startMode({ ...base, requires: ["webgl"], webgl: false })).toBe("webgl");
    expect(startMode({ ...base, requires: ["webgl"], webgl: true })).toBe("frame");
    expect(startMode({ ...base, requires: [], webgl: false })).toBe("frame");
    expect(startMode({ ...base, requires: ["webgl"], webgl: false, reducedMotion: true })).toBe("webgl");
  });
});

describe("themeTokens", () => {
  it("collects the non-empty tokens by name and drops oversized values", () => {
    const tokens = themeTokens((name) => (name === "--ulams-color-primary" ? " #c2552d " : name === "--ulams-color-text" ? "x".repeat(201) : ""));
    expect(tokens).toEqual({ "--ulams-color-primary": "#c2552d" });
    expect(THEME_TOKENS.every((n) => /^--ulams-[a-z-]+$/.test(n))).toBe(true);
  });
});

describe("stepCounter", () => {
  it("is one-based", () => expect(stepCounter(2, 5)).toBe("Step 3 of 5"));
});

describe("EventQueue", () => {
  beforeEach(() => vi.useFakeTimers());
  afterEach(() => vi.useRealTimers());

  const make = (send = vi.fn().mockResolvedValue({ data: { status: 2 } }), extra = {}) => {
    const onResult = vi.fn();
    const queue = new EventQueue({ send, onResult, now: () => Date.now(), ...extra });
    return { queue, send, onResult };
  };

  it("sends the queued events every two seconds and reports the result", async () => {
    const { queue, send, onResult } = make();
    queue.start();
    queue.push({ type: "stepChanged", step: "a" });
    await vi.advanceTimersByTimeAsync(1999);
    expect(send).not.toHaveBeenCalled();
    await vi.advanceTimersByTimeAsync(2);
    expect(send).toHaveBeenCalledWith([{ type: "stepChanged", step: "a" }]);
    expect(onResult).toHaveBeenCalledWith({ data: { status: 2 } });
    await vi.advanceTimersByTimeAsync(4000);
    expect(send).toHaveBeenCalledTimes(1);
    queue.stop();
  });

  it("keeps only the latest progress value", () => {
    const { queue } = make();
    queue.push({ type: "progress", value: 0.1 });
    queue.push({ type: "stepChanged", step: "a" });
    queue.push({ type: "progress", value: 0.6 });
    expect(queue.size).toBe(2);
  });

  it("accepts at most 20 events a second, but never drops complete or score", () => {
    const { queue } = make();
    let accepted = 0;
    for (let i = 0; i < 30; i++) if (queue.push({ type: "stepChanged", step: `s${i}` })) accepted++;
    expect(accepted).toBe(20);
    expect(queue.push({ type: "complete" })).toBe(true);
    expect(queue.push({ type: "score", raw: 1, max: 2 })).toBe(true);
    vi.advanceTimersByTime(1001);
    expect(queue.push({ type: "stepChanged", step: "later" })).toBe(true);
  });

  it("splits a flush into batches of at most 40 events", async () => {
    const { queue, send } = make(undefined, { maxPerSecond: 1000 });
    for (let i = 0; i < 95; i++) queue.push({ type: "event", verb: "http://x.y/z", object: `o${i}` });
    await queue.flush();
    expect(send.mock.calls.map((c) => c[0].length)).toEqual([40, 40, 15]);
    expect(queue.size).toBe(0);
  });

  it("puts a failed batch back for the next flush, in order", async () => {
    const send = vi.fn().mockRejectedValueOnce(new Error("503")).mockResolvedValue(null);
    const { queue } = make(send);
    queue.push({ type: "stepChanged", step: "a" });
    queue.push({ type: "stepChanged", step: "b" });
    await queue.flush();
    expect(queue.size).toBe(2);
    queue.push({ type: "stepChanged", step: "c" });
    await queue.flush();
    expect(send.mock.calls[1]![0].map((e: { step: string }) => e.step)).toEqual(["a", "b", "c"]);
  });
});

describe("landing hero loop", () => {
  it("is a still under reduced motion and without WebGL when the package needs it", () => {
    expect(showcaseMode({ reducedMotion: false, requires: [], webgl: false })).toBe("loop");
    expect(showcaseMode({ reducedMotion: false, requires: ["webgl"], webgl: true })).toBe("loop");
    expect(showcaseMode({ reducedMotion: true, requires: [], webgl: true })).toBe("still");
    expect(showcaseMode({ reducedMotion: false, requires: ["webgl"], webgl: false })).toBe("still");
  });

  it("moves to the next step of the loop and wraps", () => {
    expect(nextShowcaseStep(["a", "b", "c"], "a")).toBe("b");
    expect(nextShowcaseStep(["a", "b", "c"], "c")).toBe("a");
    expect(nextShowcaseStep(["a", "b", "c"], "zzz")).toBe("a");
    expect(nextShowcaseStep(["a", "b", "c"], undefined)).toBe("a");
    expect(nextShowcaseStep([], "a")).toBeUndefined();
  });
});
