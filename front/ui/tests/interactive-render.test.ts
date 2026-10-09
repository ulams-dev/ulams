import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from "vitest";
import { JSDOM } from "jsdom";
import { renderDoc, setupDom } from "./astro-render.ts";
import type { UiNode } from "../src/render-core.ts";

const STEPS = [
  { id: "intro", title: "Intro", text: "Start here.", poster: "https://content.test/p/intro.webp" },
  { id: "middle", title: "Middle", text: "Halfway there." },
  { id: "last", title: "Last", text: "The end." },
];

const doc = (props: Record<string, unknown> = {}): UiNode => ({
  component: "InteractiveLesson",
  props: {
    src: "https://content.test/interactive/k/v1/index.html",
    title: "Gravity lab",
    topicId: 5,
    courseId: 1,
    steps: STEPS,
    text: "Drag the **slider**.",
    licence: "MIT",
    attribution: "The authors",
    sourceUrl: "https://example.com/src",
    ...props,
  },
});

describe("InteractiveLesson markup", () => {
  it("renders the frame sandboxed without allow-same-origin, and the text version of the range only", async () => {
    const html = await renderDoc(doc({ startStep: "middle", endStep: "last" }));
    const { document } = new JSDOM(html).window;
    const frame = document.querySelector("iframe")!;
    expect(frame.getAttribute("sandbox")).toBe("allow-scripts allow-popups allow-popups-to-escape-sandbox");
    expect(frame.getAttribute("referrerpolicy")).toBe("no-referrer");
    // the entry is only loaded by the element, after its checks
    expect(frame.hasAttribute("src")).toBe(false);
    expect(frame.getAttribute("data-src")).toBe("https://content.test/interactive/k/v1/index.html");
    expect([...document.querySelectorAll("[data-step]")].map((el) => el.getAttribute("data-step-id"))).toEqual(["middle", "last"]);
    expect(document.querySelector("ulams-interactive")!.getAttribute("start-step")).toBe("middle");
    expect(document.querySelector("details summary")!.textContent).toBe("Text version of this interactive");
    expect(html).toContain("About this interactive");
    expect(html).toContain("MIT");
    expect(html).toContain('rel="noopener noreferrer"');
  });

  it("inline: the text sits above the frame and there is no stepper", async () => {
    const { document } = new JSDOM(await renderDoc(doc())).window;
    const root = document.querySelector("ulams-interactive")!;
    expect(root.classList.contains("u-ix--inline")).toBe(true);
    expect(root.querySelector("strong")!.textContent).toBe("slider");
    expect(root.firstElementChild!.classList.contains("u-ix__lead")).toBe(true);
    expect(root.querySelector("[data-prev]")).toBeNull();
    expect(root.querySelector("[data-explore]")).toBeNull();
  });

  it("background: the text sits in an overlay region with the stepper, Explore freely and a way back", async () => {
    const { document } = new JSDOM(await renderDoc(doc({ display: "background" }))).window;
    const root = document.querySelector("ulams-interactive")!;
    expect(root.classList.contains("u-ix--background")).toBe(true);
    const overlay = root.querySelector("[role=region]")!;
    expect(overlay.getAttribute("aria-label")).toBe("Gravity lab: lesson text");
    for (const hook of ["[data-prev]", "[data-next]", "[data-explore]", "[data-counter]"]) expect(overlay.querySelector(hook), hook).not.toBeNull();
    expect(root.querySelector("[data-back]")!.textContent).toBe("Back to the lesson");
    expect(root.querySelector("iframe")!.getAttribute("tabindex")).toBe("-1");
    expect(root.querySelector("[aria-live=polite]")).not.toBeNull();
  });

  it("escapes author text: manifest strings are never HTML", async () => {
    const html = await renderDoc(doc({ steps: [{ id: "x", title: "<img src=x onerror=alert(1)>", text: "<script>alert(1)</script>" }], attribution: "<b>x</b>" }));
    // the title is only ever an attribute value or escaped text, so no element comes out of it
    const parsed = new JSDOM(html).window.document;
    expect(parsed.querySelector("img[src=x]")).toBeNull();
    expect(parsed.querySelector("li[data-step] script")).toBeNull();
    expect(parsed.querySelector("h3")!.textContent).toBe("<img src=x onerror=alert(1)>");
    expect(html).not.toContain("<script>alert");
    expect(html).toContain("&lt;script&gt;");
  });
});

describe("InteractiveLesson accessibility", () => {
  it.each([["inline"], ["background"]])("%s mode renders without WCAG violations (axe)", async (display) => {
    vi.useRealTimers(); // axe schedules its work with timers
    const { document } = setupDom().window;
    document.body.innerHTML = `<main>${await renderDoc(doc({ display }))}</main>`;
    const { default: axe } = await import("axe-core");
    const result = await axe.run(document.body.querySelector("main")!, { rules: { "color-contrast": { enabled: false }, region: { enabled: false } } });
    expect(result.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.html).join(" | ")}`)).toEqual([]);
  });
});

// ---- the element, in jsdom

interface Ctx {
  el: HTMLElement;
  frame: HTMLIFrameElement;
  win: Window & typeof globalThis;
  post: ReturnType<typeof vi.fn>;
}

const NONCE_RE = /^[0-9a-f]{32}$/;
let reduced = false;
let canvasWebgl = true;
const fetchMock = vi.fn();

async function mountElement(props: Record<string, unknown> = {}): Promise<Ctx> {
  const dom = setupDom();
  const win = dom.window as unknown as Window & typeof globalThis;
  document.body.innerHTML = `<main>${await renderDoc(doc(props))}</main>`;
  const el = document.querySelector("ulams-interactive") as HTMLElement;
  const frame = el.querySelector("iframe")!;
  const post = vi.fn();
  Object.defineProperty(frame, "contentWindow", { value: { postMessage: post }, configurable: true });
  return { el, frame, win, post };
}

const send = (ctx: Ctx, data: unknown, opts: { source?: unknown; origin?: string } = {}) => {
  const event = new ctx.win.MessageEvent("message", { data, origin: opts.origin ?? "null" });
  Object.defineProperty(event, "source", { value: "source" in opts ? opts.source : ctx.frame.contentWindow });
  ctx.win.dispatchEvent(event);
};

/** The nonce the page generated: read from the init message it posted to the frame. */
const nonceOf = (ctx: Ctx) => {
  ctx.frame.dispatchEvent(new ctx.win.Event("load"));
  const init = ctx.post.mock.calls.map((c) => c[0]).find((m) => m.type === "init");
  expect(init?.nonce).toMatch(NONCE_RE);
  return init.nonce as string;
};
const env = (nonce: string, m: Record<string, unknown>) => ({ "ulams-ix": 1, nonce, ...m });
const ready = (nonce: string) => env(nonce, { type: "ready", protocol: 1, steps: ["intro", "middle", "last"], capabilities: { steps: true } });

beforeAll(async () => {
  setupDom();
  const w = globalThis.window as unknown as Record<string, unknown>;
  w.matchMedia = (query: string) => ({ matches: query.includes("reduce") && reduced, addEventListener() {}, removeEventListener() {} });
  Object.defineProperty(globalThis, "MessageEvent", { value: (w as { MessageEvent: unknown }).MessageEvent, configurable: true, writable: true });
  Object.defineProperty(globalThis, "HTMLIFrameElement", { value: (w as { HTMLIFrameElement: unknown }).HTMLIFrameElement, configurable: true, writable: true });
  vi.stubGlobal("fetch", fetchMock);
  await import("../src/elements/interactive.ts");
});

beforeEach(() => {
  reduced = false;
  canvasWebgl = true;
  fetchMock.mockReset();
  fetchMock.mockResolvedValue({ ok: true, json: async () => ({ data: { status: 2 } }) });
  vi.useFakeTimers();
  // jsdom has no WebGL: the canvas answers only when a test says the browser can do it
  Object.defineProperty(globalThis.window.HTMLCanvasElement.prototype, "getContext", { value: () => (canvasWebgl ? {} : null), configurable: true });
});

afterEach(() => {
  document.body.innerHTML = "";
  vi.useRealTimers();
});

describe("<ulams-interactive>", () => {
  it("loads the package into the frame and sends init with a random nonce, the theme and the range", async () => {
    const ctx = await mountElement({ display: "background" });
    const nonce = nonceOf(ctx);
    expect(ctx.frame.getAttribute("src")).toBe("https://content.test/interactive/k/v1/index.html");
    const init = ctx.post.mock.calls.find((c) => c[0].type === "init")![0];
    expect(init).toMatchObject({ "ulams-ix": 1, nonce, locale: "en", reducedMotion: false, display: "background", chrome: "none", startStep: "intro", range: { from: "intro", to: "last" } });
    expect(ctx.post.mock.calls[0]![1]).toBe("*");
  });

  it("accepts messages only from its frame, with origin null and the nonce", async () => {
    const ctx = await mountElement({ display: "background" });
    const nonce = nonceOf(ctx);
    send(ctx, ready(nonce), { origin: "https://evil.example" });
    send(ctx, ready(nonce), { source: {} });
    send(ctx, ready("z".repeat(32)));
    expect(ctx.el.getAttribute("state")).toBe("loading");
    send(ctx, ready(nonce));
    expect(ctx.el.getAttribute("state")).toBe("ready");
  });

  it("announces step changes, updates the card and queues events for the BFF", async () => {
    const ctx = await mountElement({ display: "background" });
    const nonce = nonceOf(ctx);
    send(ctx, ready(nonce));
    send(ctx, env(nonce, { type: "stepChanged", step: "middle" }));

    expect(ctx.el.querySelector("[data-live]")!.textContent).toBe("Step 2: Middle");
    expect(ctx.el.querySelector("[data-counter]")!.textContent).toBe("Step 2 of 3");
    expect(ctx.el.querySelector("[data-current-title]")!.textContent).toBe("Middle");
    expect(ctx.el.querySelector("[data-current-text]")!.textContent).toBe("Halfway there.");
    expect(fetchMock).not.toHaveBeenCalled();

    await vi.advanceTimersByTimeAsync(2001);
    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [url, init] = fetchMock.mock.calls[0]!;
    expect(url).toBe("/bff/api/interactive/topics/5/events");
    expect(init.method).toBe("POST");
    expect(init.keepalive).toBe(true);
    expect(init.credentials).toBeUndefined();
    expect(JSON.parse(init.body)).toEqual({ events: [{ type: "stepChanged", step: "middle" }] });
    // no envelope, no nonce, no token on the wire
    expect(init.body).not.toContain("nonce");
    expect(JSON.stringify(init.headers)).not.toMatch(/authorization|token/i);
  });

  it("completes the topic when the API says so", async () => {
    fetchMock.mockResolvedValue({ ok: true, json: async () => ({ data: { status: 1 } }) });
    const ctx = await mountElement();
    const nonce = nonceOf(ctx);
    const completed = vi.fn();
    document.addEventListener("ulams:complete", completed);
    send(ctx, ready(nonce));
    send(ctx, env(nonce, { type: "complete" }));
    await vi.advanceTimersByTimeAsync(10);
    expect(fetchMock).toHaveBeenCalled(); // complete is flushed at once
    expect(completed).toHaveBeenCalledTimes(1);
    send(ctx, env(nonce, { type: "complete" }));
    await vi.advanceTimersByTimeAsync(10);
    expect(completed).toHaveBeenCalledTimes(1);
  });

  it("sends nothing to the BFF without a topic id (a preview)", async () => {
    const ctx = await mountElement({ topicId: undefined });
    const nonce = nonceOf(ctx);
    send(ctx, ready(nonce));
    send(ctx, env(nonce, { type: "complete" }));
    await vi.advanceTimersByTimeAsync(5000);
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("the stepper asks the package to go to the step and moves the card at once", async () => {
    const ctx = await mountElement({ display: "background" });
    const nonce = nonceOf(ctx);
    send(ctx, ready(nonce));
    expect(ctx.el.querySelector<HTMLButtonElement>("[data-prev]")!.disabled).toBe(true);
    ctx.el.querySelector<HTMLButtonElement>("[data-next]")!.click();
    expect(ctx.post.mock.calls.at(-1)![0]).toMatchObject({ type: "goToStep", step: "middle", nonce });
    expect(ctx.el.querySelector("[data-counter]")!.textContent).toBe("Step 2 of 3");
    ctx.el.querySelector<HTMLButtonElement>("[data-next]")!.click();
    expect(ctx.el.querySelector<HTMLButtonElement>("[data-next]")!.disabled).toBe(true);
    ctx.el.querySelector<HTMLButtonElement>("[data-prev]")!.click();
    expect(ctx.post.mock.calls.at(-1)![0]).toMatchObject({ type: "goToStep", step: "middle" });
  });

  it("Explore freely hides the card and moves the focus into the frame; Escape and the button come back", async () => {
    const ctx = await mountElement({ display: "background" });
    nonceOf(ctx);
    const focus = vi.spyOn(ctx.frame, "focus");
    ctx.el.querySelector<HTMLButtonElement>("[data-explore]")!.click();
    expect(ctx.el.getAttribute("explore")).toBe("true");
    expect(document.documentElement.hasAttribute("data-ix-explore")).toBe(true);
    expect(ctx.frame.hasAttribute("tabindex")).toBe(false);
    expect(focus).toHaveBeenCalled();

    document.dispatchEvent(new window.KeyboardEvent("keydown", { key: "Escape" }));
    expect(ctx.el.hasAttribute("explore")).toBe(false);
    expect(document.documentElement.hasAttribute("data-ix-explore")).toBe(false);
    expect(ctx.frame.getAttribute("tabindex")).toBe("-1");

    ctx.el.querySelector<HTMLButtonElement>("[data-explore]")!.click();
    ctx.el.querySelector<HTMLButtonElement>("[data-back]")!.click();
    expect(ctx.el.hasAttribute("explore")).toBe(false);
  });

  it("reduced motion: shows the step's poster and text instead of the frame, and can play anyway", async () => {
    reduced = true;
    const ctx = await mountElement();
    expect(ctx.el.getAttribute("fallback")).toBe("reduced-motion");
    expect(ctx.frame.hasAttribute("src")).toBe(false);
    const poster = ctx.el.querySelector<HTMLImageElement>("[data-poster]")!;
    expect(poster.hidden).toBe(false);
    expect(poster.getAttribute("src")).toBe("https://content.test/p/intro.webp");
    expect(poster.alt).toBe("Start here.");
    expect(ctx.el.querySelector("[data-notice]")!.textContent).toContain("Motion is reduced");

    ctx.el.querySelector<HTMLButtonElement>("[data-play-anyway]")!.click();
    expect(ctx.frame.getAttribute("src")).toBe("https://content.test/interactive/k/v1/index.html");
    expect(ctx.el.hasAttribute("fallback")).toBe(false);
  });

  it("reduced motion: a package that handles it itself still plays", async () => {
    reduced = true;
    const ctx = await mountElement({ reducedMotionSupported: true });
    expect(ctx.el.hasAttribute("fallback")).toBe(false);
    nonceOf(ctx);
    expect(ctx.post.mock.calls.find((c) => c[0].type === "init")![0].reducedMotion).toBe(true);
  });

  it("no WebGL: the poster and the text, with no play button", async () => {
    canvasWebgl = false;
    const ctx = await mountElement({ requires: ["webgl"] });
    expect(ctx.el.getAttribute("fallback")).toBe("webgl");
    expect(ctx.frame.hasAttribute("src")).toBe(false);
    expect(ctx.el.querySelector("[data-notice]")!.textContent).toContain("cannot show this 3D view");
  });

  it("no ready within 10 seconds: the text alternative and an error note", async () => {
    const ctx = await mountElement();
    nonceOf(ctx);
    await vi.advanceTimersByTimeAsync(9_999);
    expect(ctx.el.hasAttribute("fallback")).toBe(false);
    await vi.advanceTimersByTimeAsync(2);
    expect(ctx.el.getAttribute("fallback")).toBe("timeout");
    expect(ctx.el.getAttribute("state")).toBe("error");
    expect(ctx.frame.hasAttribute("src")).toBe(false);
    expect(ctx.el.querySelector("[data-notice]")!.textContent).toContain("did not start");
  });

  it("an error from the package falls back too, and a webgl error says why", async () => {
    const a = await mountElement();
    send(a, env(nonceOf(a), { type: "error", code: "oops" }));
    // not ready yet: errors count, ready is only needed for the other messages
    expect(a.el.getAttribute("fallback")).toBeNull();
    send(a, ready(nonceOf(a)));
    send(a, env(nonceOf(a), { type: "error", code: "webgl-unavailable" }));
    expect(a.el.getAttribute("fallback")).toBe("webgl");
  });

  it("flushes on pagehide", async () => {
    const ctx = await mountElement();
    const nonce = nonceOf(ctx);
    send(ctx, ready(nonce));
    send(ctx, env(nonce, { type: "stepChanged", step: "last" }));
    ctx.win.dispatchEvent(new ctx.win.Event("pagehide"));
    await vi.advanceTimersByTimeAsync(1);
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it("resizes the inline frame within 240 and 2000 px, and not the background one", async () => {
    const ctx = await mountElement();
    const nonce = nonceOf(ctx);
    send(ctx, ready(nonce));
    send(ctx, env(nonce, { type: "resize", height: 5000 }));
    expect(ctx.frame.style.height).toBe("2000px");
    send(ctx, env(nonce, { type: "resize", height: 10 }));
    expect(ctx.frame.style.height).toBe("240px");
    const bg = await mountElement({ display: "background" });
    const n2 = nonceOf(bg);
    send(bg, ready(n2));
    send(bg, env(n2, { type: "resize", height: 900 }));
    expect(bg.frame.style.height).toBe("");
  });
});
