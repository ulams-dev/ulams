import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from "vitest";
import { renderDoc, setupDom } from "./astro-render.ts";
import { SHOWCASE_DWELL_MS } from "../src/lib/interactive.ts";
import type { UiNode } from "../src/render-core.ts";

const props = {
  src: "https://content.test/interactive/k/v1/index.html",
  title: "Solar system",
  steps: [
    { id: "a", title: "A", text: "First." },
    { id: "b", title: "B", text: "Second." },
    { id: "c", title: "C", text: "Third." },
  ],
  showcase: { steps: ["a", "b", "c"], poster: "https://content.test/p/showcase.webp" },
  href: "/learn/1",
  requires: ["webgl"],
};

interface Ctx {
  el: HTMLElement;
  frame: HTMLIFrameElement;
  win: Window & typeof globalThis;
  post: ReturnType<typeof vi.fn>;
}

let reduced = false;
let webgl = true;
let intersect: ((entries: Array<{ isIntersecting: boolean }>) => void) | null = null;
const fetchMock = vi.fn();

async function mountShowcase(extra: Record<string, unknown> = {}): Promise<Ctx> {
  const win = setupDom().window as unknown as Window & typeof globalThis;
  const doc: UiNode = { component: "Hero", props: { variant: "cosmos", title: "Fall into it", showcase: { ...props, ...extra } } };
  document.body.innerHTML = `<main>${await renderDoc(doc)}</main>`;
  const el = document.querySelector("ulams-showcase") as HTMLElement;
  const frame = el.querySelector("iframe")!;
  const post = vi.fn();
  Object.defineProperty(frame, "contentWindow", { value: { postMessage: post }, configurable: true });
  return { el, frame, win, post };
}

/** The page has loaded and the browser is idle: the frame starts. */
const startFrame = async (ctx: Ctx) => {
  await vi.advanceTimersByTimeAsync(1000);
  ctx.frame.dispatchEvent(new ctx.win.Event("load"));
  const init = ctx.post.mock.calls.map((c) => c[0]).find((m) => m.type === "init");
  return init as Record<string, unknown> | undefined;
};
const send = (ctx: Ctx, data: unknown) => {
  const event = new ctx.win.MessageEvent("message", { data, origin: "null" });
  Object.defineProperty(event, "source", { value: ctx.frame.contentWindow });
  ctx.win.dispatchEvent(event);
};
const ready = (nonce: string) => ({ "ulams-ix": 1, nonce, type: "ready", protocol: 1, steps: ["a", "b", "c"], capabilities: { steps: true } });
const sent = (ctx: Ctx) => ctx.post.mock.calls.map((c) => c[0]);

beforeAll(async () => {
  setupDom();
  const w = globalThis.window as unknown as Record<string, unknown>;
  w.matchMedia = (query: string) => ({ matches: query.includes("reduce") && reduced, addEventListener() {}, removeEventListener() {} });
  for (const key of ["MessageEvent", "HTMLIFrameElement"]) Object.defineProperty(globalThis, key, { value: w[key], configurable: true, writable: true });
  class FakeObserver {
    constructor(cb: (entries: Array<{ isIntersecting: boolean }>) => void) {
      intersect = cb;
    }
    observe() {}
    disconnect() {}
  }
  w.IntersectionObserver = FakeObserver;
  vi.stubGlobal("IntersectionObserver", FakeObserver);
  vi.stubGlobal("fetch", fetchMock);
  await import("../src/elements/showcase.ts");
});

beforeEach(() => {
  reduced = false;
  webgl = true;
  intersect = null;
  fetchMock.mockReset();
  vi.useFakeTimers();
  Object.defineProperty(globalThis.window.HTMLCanvasElement.prototype, "getContext", { value: () => (webgl ? {} : null), configurable: true });
  Object.defineProperty(globalThis.window.document, "readyState", { value: "complete", configurable: true });
});

afterEach(() => {
  document.body.innerHTML = "";
  vi.useRealTimers();
});

describe("<ulams-showcase>", () => {
  it("keeps the still and never starts the frame under reduced motion", async () => {
    reduced = true;
    const ctx = await mountShowcase();
    await vi.advanceTimersByTimeAsync(10_000);
    expect(ctx.frame.hasAttribute("src")).toBe(false);
    expect(ctx.el.getAttribute("state")).toBe("still");
  });

  it("keeps the still when the package needs WebGL and the browser has none", async () => {
    webgl = false;
    const ctx = await mountShowcase();
    await vi.advanceTimersByTimeAsync(10_000);
    expect(ctx.frame.hasAttribute("src")).toBe(false);
  });

  it("does not load the frame at once: only after load and an idle moment", async () => {
    const ctx = await mountShowcase();
    expect(ctx.frame.hasAttribute("src")).toBe(false);
    await vi.advanceTimersByTimeAsync(1000);
    expect(ctx.frame.getAttribute("src")).toBe(props.src);
  });

  it("starts the package in showcase mode: no chrome, no range, the first step of the loop, no theme secrets", async () => {
    const ctx = await mountShowcase();
    const init = await startFrame(ctx);
    expect(init).toMatchObject({ "ulams-ix": 1, display: "inline", chrome: "none", showcase: true, startStep: "a", reducedMotion: false });
    expect(init).not.toHaveProperty("range");
    expect(String(init!.nonce)).toMatch(/^[0-9a-f]{32}$/);
  });

  it("cycles the loop slowly with goToStep and wraps around", async () => {
    const ctx = await mountShowcase();
    const nonce = String((await startFrame(ctx))!.nonce);
    send(ctx, ready(nonce));
    expect(ctx.el.getAttribute("state")).toBe("playing");
    await vi.advanceTimersByTimeAsync(SHOWCASE_DWELL_MS * 3 + 100);
    expect(sent(ctx).filter((m) => m.type === "goToStep").map((m) => m.step)).toEqual(["b", "c", "a"]);
  });

  it("sends nothing to the server and ignores messages that do not come from its frame", async () => {
    const ctx = await mountShowcase();
    const nonce = String((await startFrame(ctx))!.nonce);
    send(ctx, ready(nonce));
    send(ctx, { "ulams-ix": 1, nonce, type: "complete" });
    send(ctx, { "ulams-ix": 1, nonce, type: "stepChanged", step: "b" });
    await vi.advanceTimersByTimeAsync(10_000);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("pauses the package and the loop when the hero leaves the screen, and resumes it", async () => {
    const ctx = await mountShowcase();
    const nonce = String((await startFrame(ctx))!.nonce);
    send(ctx, ready(nonce));
    intersect!([{ isIntersecting: false }]);
    expect(sent(ctx).at(-1)).toMatchObject({ type: "pause" });
    const before = sent(ctx).length;
    await vi.advanceTimersByTimeAsync(SHOWCASE_DWELL_MS * 2);
    expect(sent(ctx)).toHaveLength(before); // no goToStep while hidden
    intersect!([{ isIntersecting: true }]);
    expect(sent(ctx).at(-1)).toMatchObject({ type: "resume" });
  });

  it("lets the visitor stop and restart the motion (WCAG 2.2.2)", async () => {
    const ctx = await mountShowcase();
    const nonce = String((await startFrame(ctx))!.nonce);
    send(ctx, ready(nonce));
    const toggle = ctx.el.querySelector<HTMLButtonElement>("[data-toggle]")!;
    toggle.click();
    expect(toggle.getAttribute("aria-pressed")).toBe("true");
    expect(sent(ctx).at(-1)).toMatchObject({ type: "pause" });
    toggle.click();
    expect(toggle.getAttribute("aria-pressed")).toBe("false");
    expect(sent(ctx).at(-1)).toMatchObject({ type: "resume" });
  });

  it("falls back to the still, silently, when the package reports an error or never answers", async () => {
    const ctx = await mountShowcase();
    const nonce = String((await startFrame(ctx))!.nonce);
    send(ctx, ready(nonce));
    send(ctx, { "ulams-ix": 1, nonce, type: "error", code: "webgl-unavailable" });
    expect(ctx.el.getAttribute("state")).toBe("still");
    expect(ctx.frame.hasAttribute("src")).toBe(false);

    const slow = await mountShowcase();
    await startFrame(slow);
    await vi.advanceTimersByTimeAsync(11_000);
    expect(slow.el.getAttribute("state")).toBe("still");
    expect(slow.frame.hasAttribute("src")).toBe(false);
  });
});

describe("hero showcase accessibility", () => {
  it("renders without WCAG violations (axe): the picture is hidden, the link and the pause button are named", async () => {
    vi.useRealTimers(); // axe schedules its work with timers
    await mountShowcase();
    const { default: axe } = await import("axe-core");
    const main = document.body.querySelector("main")!;
    const result = await axe.run(main, { rules: { "color-contrast": { enabled: false }, region: { enabled: false } } });
    expect(result.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.html).join(" | ")}`)).toEqual([]);
    expect(main.querySelector(".u-sc__try")?.getAttribute("aria-label")).toBe("Try it: Solar system");
  });
});
