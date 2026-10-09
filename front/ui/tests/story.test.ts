// @vitest-environment jsdom
import { describe, expect, it, vi } from "vitest";
import { StoryPlayer, countAt, isOn, typedLength } from "../src/elements/story.ts";

const markup = `
  <div data-total="1000" data-hold="500">
    <p id="a" data-at="100" data-until="600">a</p>
    <p id="t" data-at="200" data-type="400">hello world</p>
    <p id="c" data-at="0" data-count="0,0.18,1000,2" data-prefix="$">$0.18</p>
    <p id="m" data-at="300" data-at2="700">m</p>
  </div>`;
const root = () => {
  document.body.innerHTML = markup;
  return document.body.firstElementChild as HTMLElement;
};
const shown = (el: Element) => (el.firstChild as Text).data;

describe("pure schedule helpers", () => {
  it("switches on inside [at, until)", () => {
    expect(isOn(99, 100, 600)).toBe(false);
    expect(isOn(100, 100, 600)).toBe(true);
    expect(isOn(600, 100, 600)).toBe(false);
    expect(isOn(5000, 100)).toBe(true);
  });
  it("types and counts proportionally and clamps at the ends", () => {
    expect(typedLength(100, 200, 400, 10)).toBe(0);
    expect(typedLength(400, 200, 400, 10)).toBe(5);
    expect(typedLength(900, 200, 400, 10)).toBe(10);
    expect(countAt(0, 0, 1000, 0, 0.18)).toBe(0);
    expect(countAt(500, 0, 1000, 0, 0.18)).toBe(0.09);
    expect(countAt(5000, 0, 1000, 0, 0.18)).toBe(0.18);
  });
});

describe("StoryPlayer", () => {
  it("renders a pure function of time", () => {
    const r = root();
    const p = new StoryPlayer(r);
    p.render(150);
    expect(r.querySelector("#a")!.classList.contains("on")).toBe(true);
    expect(r.querySelector("#m")!.classList.contains("on")).toBe(false);
    p.render(400);
    expect(r.querySelector("#a")!.classList.contains("on")).toBe(true);
    expect(shown(r.querySelector("#t")!)).toBe("hello");
    p.render(800);
    expect(r.querySelector("#a")!.classList.contains("on")).toBe(false);
    expect(r.querySelector("#m")!.classList.contains("on2")).toBe(true);
    expect(r.querySelector("#c")!.textContent).toBe("$0.14");
  });

  it("keeps the full text in the DOM while typing (no layout shift)", () => {
    const r = root();
    new StoryPlayer(r).render(300);
    expect(r.querySelector("#t")!.textContent).toBe("hello world");
  });

  it("shows the final frame at once and never runs under reduced motion", () => {
    const r = root();
    const p = new StoryPlayer(r, { reduced: true });
    p.set({ visible: true });
    expect(r.hasAttribute("data-static")).toBe(true);
    expect(p.active).toBe(false);
    expect(shown(r.querySelector("#t")!)).toBe("hello world");
    expect(r.querySelector("#a")!.classList.contains("on")).toBe(false);
    expect(r.querySelector("#m")!.classList.contains("on2")).toBe(true);
    p.tick(300);
    p.replay();
    expect(p.t).toBe(1000);
  });

  it("only advances while visible, and pauses when hidden, paused by the user or by the host", () => {
    const p = new StoryPlayer(root());
    p.tick(100);
    expect(p.t).toBe(0);
    p.set({ visible: true });
    p.tick(100);
    expect(p.t).toBe(100);
    p.set({ docVisible: false });
    p.tick(100);
    expect(p.t).toBe(100);
    p.set({ docVisible: true, userPaused: true });
    p.tick(100);
    expect(p.t).toBe(100);
    p.set({ userPaused: false, hostPaused: true });
    p.tick(100);
    expect(p.t).toBe(100);
    p.set({ hostPaused: false, visible: false });
    p.tick(100);
    expect(p.t).toBe(100);
  });

  it("loops, or ends once and reports it", () => {
    const looping = new StoryPlayer(root(), { loop: true });
    looping.set({ visible: true });
    looping.tick(100);
    looping.tick(90);
    expect(looping.t).toBe(190);
    for (let i = 0; i < 15; i++) looping.tick(100);
    expect(looping.t).toBeLessThan(1500);
    expect(looping.ended).toBe(false);

    const onEnd = vi.fn();
    const once = new StoryPlayer(root(), { onEnd });
    once.set({ visible: true });
    for (let i = 0; i < 20; i++) once.tick(100);
    expect(once.ended).toBe(true);
    expect(onEnd).toHaveBeenCalledTimes(1);
    once.replay();
    expect(once.ended).toBe(false);
    expect(once.t).toBe(0);
  });
});
