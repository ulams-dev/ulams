import { describe, expect, it } from "vitest";
import { ActivityTracker, electLeader, IDLE_MS, MAX_PER_CALL, minuteOf, newNonce, PEER_TTL_MS } from "../../src/studio/review-time-model.ts";

const T0 = Date.UTC(2026, 9, 20, 10, 30, 0);

describe("nonce", () => {
  it("is 22 URL-safe characters and different every time", () => {
    const a = newNonce();
    const b = newNonce();
    expect(a).toMatch(/^[A-Za-z0-9_-]{22}$/);
    expect(a).not.toBe(b);
  });
  it("carries nothing but randomness", () => {
    expect(newNonce(() => new Uint8Array(17))).toBe("AAAAAAAAAAAAAAAAAAAAAA");
  });
});

describe("what counts as an active minute", () => {
  it("counts a minute with recent input in a visible, focused tab", () => {
    const t = new ActivityTracker();
    t.input(T0 + 5_000);
    expect(t.tick(T0 + 10_000, true, true)).toBe(T0);
    expect(t.batch(T0 + 20_000)).toEqual([new Date(T0).toISOString()]);
  });

  it("does not count before any input", () => {
    expect(new ActivityTracker().tick(T0, true, true)).toBeNull();
  });

  it.each([
    ["a hidden tab", false, true],
    ["a window in the background", true, false],
  ])("does not count %s", (_name, visible, focused) => {
    const t = new ActivityTracker();
    t.input(T0);
    expect(t.tick(T0 + 1_000, visible, focused)).toBeNull();
    expect(t.size).toBe(0);
  });

  it("stops counting 120 seconds after the last input", () => {
    const t = new ActivityTracker();
    t.input(T0);
    expect(t.tick(T0 + IDLE_MS, true, true)).toBe(T0 + 120_000);
    expect(t.tick(T0 + IDLE_MS + 1, true, true)).toBeNull();
    t.input(T0 + IDLE_MS + 2);
    expect(t.tick(T0 + IDLE_MS + 3_000, true, true)).not.toBeNull();
  });

  it("counts a minute once however many ticks fall in it", () => {
    const t = new ActivityTracker();
    t.input(T0);
    for (let s = 0; s < 60; s += 5) t.tick(T0 + s * 1_000, true, true);
    expect(t.size).toBe(1);
  });
});

describe("batches", () => {
  it("send at most ten minutes, oldest first, and keep the rest pending", () => {
    const t = new ActivityTracker();
    for (let m = 0; m < 12; m++) t.adopt(T0 + m * 60_000);
    const first = t.batch(T0 + 12 * 60_000);
    expect(first).toHaveLength(MAX_PER_CALL);
    expect(first[0]).toBe(new Date(T0).toISOString());
    t.sent(first);
    expect(t.batch(T0 + 12 * 60_000)).toHaveLength(2);
  });

  it("drop minutes the server would refuse as too old", () => {
    const t = new ActivityTracker();
    t.adopt(T0);
    t.adopt(T0 + 30 * 60_000);
    expect(t.batch(T0 + 30 * 60_000 + 30_000)).toEqual([new Date(T0 + 30 * 60_000).toISOString()]);
    expect(t.size).toBe(1);
  });

  it("keep a minute pending until it was sent", () => {
    const t = new ActivityTracker();
    t.adopt(T0);
    expect(t.batch(T0 + 60_000)).toHaveLength(1);
    expect(t.batch(T0 + 61_000)).toHaveLength(1);
    t.drop([new Date(T0).toISOString()]);
    expect(t.batch(T0 + 62_000)).toHaveLength(0);
  });

  it("merge the minutes of other tabs without counting a minute twice", () => {
    const t = new ActivityTracker();
    t.input(T0);
    t.tick(T0 + 1_000, true, true);
    t.adopt(T0);
    t.adopt(T0 + 60_000);
    expect(t.batch(T0 + 90_000)).toHaveLength(2);
  });
});

describe("one sender among the tabs", () => {
  const seen = (...entries: Array<[string, number]>) => new Map(entries);
  it("is the smallest nonce heard from recently", () => {
    expect(electLeader("b", seen(["c", T0], ["a", T0]), T0)).toBe("a");
    expect(electLeader("a", seen(["c", T0], ["b", T0]), T0)).toBe("a");
  });
  it("ignores a tab that went quiet", () => {
    expect(electLeader("b", seen(["a", T0 - PEER_TTL_MS - 1]), T0)).toBe("b");
    expect(electLeader("b", seen(["a", T0 - PEER_TTL_MS]), T0)).toBe("a");
  });
  it("is the tab itself when it is alone", () => {
    expect(electLeader("x", seen(), T0)).toBe("x");
  });
  it("cuts a time to its minute", () => expect(minuteOf(T0 + 59_999)).toBe(T0));
});
