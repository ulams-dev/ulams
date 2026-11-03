import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from "vitest";
import { setupDom } from "./astro-render.ts";
import { renderBasicMarkdown } from "../src/lib/markdown-basic.ts";
import { citationHref, visibleDraft } from "../src/elements/tutor-panel.ts";

const status = { enabled: true, aiEnabled: true, remaining: 29, dailyLimit: 30, attemptActive: false };
const answer = {
  conversationId: "01JCONV00000000000000000AA",
  messageId: "m1",
  answer: "Robusta is **Coffea canephora**.\n\n![x](https://evil.test/p.png) <script>alert(1)</script>",
  citations: [
    { chunkId: "c1", label: "Species · Arabica and Robusta", topicId: 12, fragmentIds: [] },
    { chunkId: "c2", label: "Glossary", topicId: null, fragmentIds: [] },
  ],
  refusal: "none",
  inScope: true,
  remaining: 28,
  label: "AI tutor · answers only from this course",
};
const earlier = [{ id: "01JOLD000000000000000000AA", lastMessageAt: "2026-10-20T09:30:00+00:00", expiresAt: "2027-01-18T09:30:00+00:00" }];

type Route = (init: RequestInit) => { status?: number; body?: unknown; stream?: ReadableStream<Uint8Array> } | "offline";
let routes: Record<string, Route>;
const calls: string[] = [];
const fetchMock = vi.fn(async (input: RequestInfo | URL, init?: RequestInit) => {
  const key = `${init?.method ?? "GET"} ${String(input)}`;
  calls.push(key);
  const route = routes[key];
  if (!route) return new Response(JSON.stringify({ message: "nope" }), { status: 404 });
  const result = route(init ?? {});
  if (result === "offline") throw new TypeError("offline");
  if (result.stream) return new Response(result.stream, { status: result.status ?? 200, headers: { "Content-Type": "text/event-stream" } });
  return new Response(typeof result.body === "string" ? result.body : JSON.stringify(result.body ?? { data: null }), { status: result.status ?? 200 });
});

const flush = async (times = 6) => {
  for (let i = 0; i < times; i++) await new Promise((r) => setTimeout(r, 0));
};

let wide = true;
const modal = vi.fn();

beforeAll(async () => {
  const dom = setupDom();
  const w = dom.window as unknown as Record<string, unknown> & { HTMLDialogElement: { prototype: HTMLDialogElement } };
  w.matchMedia = (query: string) => ({ matches: query.includes("min-width") && wide, addEventListener() {}, removeEventListener() {} });
  Object.defineProperty(globalThis, "matchMedia", { value: w.matchMedia, configurable: true, writable: true });
  // jsdom has no dialog behaviour: open attribute and close event are enough for the panel
  const proto = w.HTMLDialogElement.prototype as unknown as Record<string, unknown> & HTMLDialogElement;
  proto.show = function (this: HTMLDialogElement) {
    this.setAttribute("open", "");
  };
  proto.showModal = function (this: HTMLDialogElement) {
    modal();
    this.setAttribute("open", "");
  };
  proto.close = function (this: HTMLDialogElement) {
    this.removeAttribute("open");
    this.dispatchEvent(new (dom.window as unknown as { Event: typeof Event }).Event("close"));
  };
  vi.stubGlobal("fetch", fetchMock);
  const store = new Map<string, string>();
  vi.stubGlobal("sessionStorage", {
    getItem: (k: string) => store.get(k) ?? null,
    setItem: (k: string, v: string) => void store.set(k, String(v)),
    removeItem: (k: string) => void store.delete(k),
    clear: () => store.clear(),
  });
  await import("../src/elements/tutor.ts");
});

beforeEach(() => {
  wide = true;
  modal.mockClear();
  calls.length = 0;
  routes = {
    "GET /bff/api/tutor/courses/5/status": () => ({ body: { data: status } }),
    "GET /bff/api/tutor/courses/5/conversations": () => ({ body: { data: earlier } }),
    "POST /bff/api/tutor/courses/5/messages": () => ({ body: { data: answer } }),
  };
});

afterEach(() => {
  document.body.innerHTML = "";
  document.body.style.paddingInlineEnd = "";
});

async function mountTutor(attrs = 'course-id="5" topic-id="12"') {
  document.body.innerHTML = `<main><ulams-tutor ${attrs}></ulams-tutor></main>`;
  const el = document.querySelector("ulams-tutor") as HTMLElement;
  await flush();
  const root = el.shadowRoot!;
  const launcher = root.querySelector<HTMLButtonElement>("button.launcher");
  return { el, root, launcher };
}

async function openPanel() {
  const t = await mountTutor();
  t.launcher!.click();
  await flush(12);
  const panelRoot = (document.querySelector("[data-tutor-panel]") as HTMLElement).shadowRoot!;
  const q = <T extends Element>(sel: string) => panelRoot.querySelector(sel) as T;
  const ask = async (text: string) => {
    const field = q<HTMLTextAreaElement>("textarea");
    field.value = text;
    q<HTMLFormElement>("form").dispatchEvent(new window.Event("submit", { cancelable: true }));
    await flush(12);
  };
  return { ...t, panelRoot, q, ask };
}

describe("<ulams-tutor> launcher", () => {
  it("shows the launcher only when the tutor is on", async () => {
    const on = await mountTutor();
    expect(on.el.hidden).toBe(false);
    expect(on.launcher?.textContent).toContain("Ask the course tutor");
    expect(on.launcher?.getAttribute("aria-haspopup")).toBe("dialog");

    routes["GET /bff/api/tutor/courses/5/status"] = () => ({ body: { data: { ...status, enabled: false } } });
    expect((await mountTutor()).launcher).toBeNull();
    routes["GET /bff/api/tutor/courses/5/status"] = () => ({ body: { data: { ...status, aiEnabled: false } } });
    expect((await mountTutor()).launcher).toBeNull();
  });

  it("stays out of the way when the learner may not attend or the API is down", async () => {
    routes["GET /bff/api/tutor/courses/5/status"] = () => ({ status: 403, body: { message: "no" } });
    const denied = await mountTutor();
    expect(denied.el.hidden).toBe(true);
    routes["GET /bff/api/tutor/courses/5/status"] = () => "offline";
    expect((await mountTutor()).el.hidden).toBe(true);
    expect((await mountTutor("")).el.hidden).toBe(true);
  });

  it("talks to the tenant API with the token when api-url is set", async () => {
    routes["GET https://acme.test/api/tutor/courses/5/status"] = () => ({ body: { data: status } });
    const t = await mountTutor('course-id="5" api-url="https://acme.test" token="tok"');
    expect(t.launcher).not.toBeNull();
    expect(calls).toContain("GET https://acme.test/api/tutor/courses/5/status");
    expect((fetchMock.mock.calls.at(-1)![1]!.headers as Record<string, string>).Authorization).toBe("Bearer tok");
  });

  it("uses the page language for its strings", async () => {
    document.documentElement.lang = "pl";
    try {
      expect((await mountTutor()).launcher?.textContent).toContain("Zapytaj korepetytora kursu");
    } finally {
      document.documentElement.lang = "en";
    }
  });
});

describe("<ulams-tutor> panel", () => {
  it("opens a non-modal side sheet on a wide screen, focuses the question field and returns focus on Escape", async () => {
    const p = await openPanel();
    const dialog = p.q<HTMLDialogElement>("dialog");
    expect(dialog.hasAttribute("open")).toBe(true);
    expect(modal).not.toHaveBeenCalled();
    expect(p.launcher!.getAttribute("aria-expanded")).toBe("true");
    expect(p.panelRoot.activeElement).toBe(p.q("textarea"));
    expect(p.q("[data-quota]").textContent).toBe("29 of 30 questions left today");
    expect(document.body.style.paddingInlineEnd).toBe("420px");

    dialog.dispatchEvent(new window.KeyboardEvent("keydown", { key: "Escape", bubbles: true }));
    expect(dialog.hasAttribute("open")).toBe(false);
    expect(p.root.activeElement).toBe(p.launcher);
    expect(p.launcher!.getAttribute("aria-expanded")).toBe("false");
    expect(document.body.style.paddingInlineEnd).toBe("");
  });

  it("opens full screen as a modal on a phone", async () => {
    wide = false;
    await openPanel();
    expect(modal).toHaveBeenCalledTimes(1);
    expect(document.body.style.paddingInlineEnd).toBe("");
  });

  it("answers with the AI label, safe markdown and citations that link to the lesson", async () => {
    const p = await openPanel();
    await p.ask("What is Robusta?");
    const sent = JSON.parse(String(fetchMock.mock.calls.find((c) => String(c[0]).endsWith("/messages"))![1]!.body));
    expect(sent).toEqual({ text: "What is Robusta?", topicId: 12 });

    const log = p.q<HTMLElement>("[data-log]");
    expect(log.getAttribute("role")).toBe("log");
    expect(log.getAttribute("aria-live")).toBe("polite");
    const msg = log.querySelector(".msg.tutor")!;
    expect(msg.querySelector(".ai")?.textContent).toBe("AI tutor · answers only from this course");
    expect(msg.querySelector("strong")?.textContent).toBe("Coffea canephora");
    expect(msg.innerHTML).not.toContain("<script");
    expect(msg.querySelector("img")).toBeNull();
    const links = [...msg.querySelectorAll(".sources a")].map((a) => [a.textContent, a.getAttribute("href")]);
    expect(links).toEqual([["Species · Arabica and Robusta", "/learn/5/12"]]);
    expect(msg.querySelector(".sources")?.textContent).toContain("Glossary");
    expect(log.querySelector(".msg.learner")?.textContent).toContain("What is Robusta?");
    // question and answer arrive together, the draft state is gone and the counter moved
    expect(p.q("[data-quota]").textContent).toBe("28 of 30 questions left today");
    expect(p.q<HTMLTextAreaElement>("textarea").value).toBe("");
    expect(log.hasAttribute("aria-busy")).toBe(false);

    // a follow-up joins the same conversation
    await p.ask("And Arabica?");
    const second = JSON.parse(String(fetchMock.mock.calls.filter((c) => String(c[0]).endsWith("/messages")).at(-1)![1]!.body));
    expect(second.conversationId).toBe(answer.conversationId);
  });

  it("says in plain words when a question is declined", async () => {
    routes["POST /bff/api/tutor/courses/5/messages"] = () => ({
      body: { data: { ...answer, answer: "I can't help with quiz answers while a quiz attempt is open.", citations: [], refusal: "attempt_active", inScope: false } },
    });
    const p = await openPanel();
    await p.ask("Which option is right?");
    const msg = p.q(".msg.tutor");
    expect(msg.querySelector(".note")?.textContent).toBe("A quiz is in progress, so the tutor did not answer this.");
    expect(msg.querySelector(".md")).toBeNull();
    expect(msg.querySelector(".sources")).toBeNull();

    routes["POST /bff/api/tutor/courses/5/messages"] = () => ({ body: { data: { ...answer, answer: "That is about football.", citations: [], refusal: "out_of_scope", inScope: false } } });
    await p.ask("Who won the world cup?");
    const last = [...p.panelRoot.querySelectorAll(".msg.tutor")].at(-1)!;
    expect(last.querySelector(".md")?.textContent).toContain("football");
    expect(last.querySelector(".note")?.textContent).toContain("outside the course");
  });

  it("tells the learner up front that a quiz in progress limits the tutor", async () => {
    routes["GET /bff/api/tutor/courses/5/status"] = () => ({ body: { data: { ...status, attemptActive: true } } });
    const p = await openPanel();
    const note = p.q<HTMLElement>(".note[role=note]");
    expect(note.hidden).toBe(false);
    expect(note.textContent).toContain("will not help with quiz answers");
  });

  it("explains the daily limit with a way back to the lesson and keeps the question when the network fails", async () => {
    routes["POST /bff/api/tutor/courses/5/messages"] = () => ({ status: 429, body: { message: "You have used today's questions.", reason: "limit" } });
    const p = await openPanel();
    await p.ask("One more?");
    const error = p.q<HTMLElement>(".error");
    expect(error.hidden).toBe(false);
    expect(error.getAttribute("role")).toBe("alert");
    expect(error.textContent).toContain("Try again tomorrow");
    expect(p.q<HTMLButtonElement>("button[type=submit]").disabled).toBe(true);
    [...error.querySelectorAll("button")].find((b) => b.textContent === "Back to the lesson")!.click();
    expect(p.q<HTMLDialogElement>("dialog").hasAttribute("open")).toBe(false);

    routes["POST /bff/api/tutor/courses/5/messages"] = () => "offline";
    const q = await openPanel();
    await q.ask("Are you there?");
    expect(q.q<HTMLElement>(".error").textContent).toContain("Could not reach the tutor");
    expect(q.q<HTMLTextAreaElement>("textarea").value).toBe("Are you there?");
    routes["POST /bff/api/tutor/courses/5/messages"] = () => ({ body: { data: answer } });
    [...q.q<HTMLElement>(".error").querySelectorAll("button")].find((b) => b.textContent === "Try again")!.click();
    await flush(12);
    expect(q.q(".msg.tutor .md")).not.toBeNull();
  });

  it("says the tutor is unavailable on 503", async () => {
    routes["POST /bff/api/tutor/courses/5/messages"] = () => ({ status: 503, body: { message: "off", reason: "disabled" } });
    const p = await openPanel();
    await p.ask("Hello there");
    expect(p.q(".error").textContent).toContain("The tutor is not available right now");
  });

  it("lists earlier conversations, opens one and deletes one only after a confirmation", async () => {
    const old = earlier[0]!;
    routes[`GET /bff/api/tutor/conversations/${old.id}`] = () => ({
      body: {
        data: {
          id: old.id,
          courseId: 5,
          expiresAt: old.expiresAt,
          messages: [
            { id: "a", role: "learner", text: "Earlier question", citations: [], refusal: null, at: old.lastMessageAt },
            { id: "b", role: "tutor", text: "Earlier **answer**", citations: [{ chunkId: "c1", label: "Roasting", topicId: 7, fragmentIds: [] }], refusal: "none", at: old.lastMessageAt },
          ],
        },
      },
    });
    routes[`DELETE /bff/api/tutor/conversations/${old.id}`] = () => ({ body: { data: { deleted: true } } });
    routes["POST /bff/api/tutor/courses/5/messages"] = () => ({ body: { data: { ...answer, conversationId: old.id } } });
    const p = await openPanel();
    const item = p.q<HTMLElement>("[data-conversation]");
    expect(item.textContent).toContain("Deleted automatically on");

    [...item.querySelectorAll("button")].find((b) => b.textContent === "Open")!.click();
    await flush(10);
    expect(p.q("[data-log]").textContent).toContain("Earlier question");
    expect(p.q("[data-log] .md strong")?.textContent).toBe("answer");
    expect(p.q("[data-log] .sources a")?.getAttribute("href")).toBe("/learn/5/7");

    // a follow-up goes to that conversation
    await p.ask("Next question");
    expect(JSON.parse(String(fetchMock.mock.calls.filter((c) => String(c[0]).endsWith("/messages")).at(-1)![1]!.body)).conversationId).toBe(old.id);

    [...item.querySelectorAll("button")].find((b) => b.textContent === "Delete")!.click();
    expect(calls.some((c) => c.startsWith("DELETE"))).toBe(false);
    expect(item.textContent).toContain("Delete this conversation for good?");
    [...item.querySelectorAll("button")].find((b) => b.textContent === "Keep it")!.click();
    expect(calls.some((c) => c.startsWith("DELETE"))).toBe(false);
    [...item.querySelectorAll("button")].find((b) => b.textContent === "Delete")!.click();
    [...item.querySelectorAll("button")].find((b) => b.textContent === "Yes, delete it")!.click();
    await flush(10);
    expect(calls).toContain(`DELETE /bff/api/tutor/conversations/${old.id}`);
    expect(p.q("[data-conversation]")).toBeNull();
    expect(p.q("[role=status]").textContent).toBe("Conversation deleted.");
    // the open conversation was the deleted one: the panel starts fresh
    expect(p.q("[data-log]").textContent).not.toContain("Earlier question");
  });

  it("counts characters and caps the question at 1000", async () => {
    const p = await openPanel();
    const field = p.q<HTMLTextAreaElement>("textarea");
    expect(field.getAttribute("maxlength")).toBe("1000");
    field.value = "abcde";
    field.dispatchEvent(new window.Event("input"));
    expect(p.q("#tutor-counter").textContent).toBe("5 of 1000 characters");
    await p.ask("x");
    expect(calls.some((c) => c.startsWith("POST"))).toBe(false);
  });
});

describe("renderBasicMarkdown and citationHref", () => {
  it("escapes HTML, drops images and unsafe links, keeps safe links", () => {
    const html = renderBasicMarkdown('<img src=x onerror=1> [a](javascript:x) [b](https://example.com) ![alt text](https://evil.test/p.png)');
    expect(html).not.toContain("<img");
    expect(html).not.toContain("javascript:");
    expect(html).toContain("&lt;img");
    expect(html).toContain('href="https://example.com"');
    expect(html).toContain("alt text");
    expect(renderBasicMarkdown("Ratio $x^2$")).toContain("$x^2$");
    expect(renderBasicMarkdown(null)).toBe("");
  });

  it("fills only relative and http(s) templates", () => {
    expect(citationHref("/learn/{course}/{topic}", 5, 12)).toBe("/learn/5/12");
    expect(citationHref("https://acme.test/c/{course}/t/{topic}", 5, 12)).toBe("https://acme.test/c/5/t/12");
    expect(citationHref("javascript:alert({course})", 5, 12)).toBe("#");
    expect(citationHref("//evil.test/{topic}", 5, 12)).toBe("#");
  });
});

describe("tutor strings", () => {
  it("has every string in English, Polish and Chinese and falls back to English", async () => {
    const { tutorStrings } = await import("../src/lib/tutor-strings.ts");
    const en = tutorStrings("en");
    for (const lang of ["pl", "zh", "pl-PL", "zh-Hans"]) {
      const other = tutorStrings(lang);
      expect(other).not.toBe(en);
      expect(Object.keys(other).sort()).toEqual(Object.keys(en).sort());
      for (const [key, value] of Object.entries(other)) expect(value, `${lang}.${key}`).not.toBe("");
    }
    expect(tutorStrings("de")).toBe(en);
    expect(tutorStrings(undefined)).toBe(en);
  });
});

describe("<ulams-tutor> streamed answers", () => {
  const run = { conversationId: answer.conversationId, runId: "01jrun00000000000000000ok", questionId: "q1" };
  const EVENTS = `GET /bff/api/tutor/runs/${run.runId}/events`;
  const enc = new TextEncoder();
  const frame = (id: number, event: Record<string, unknown>) => enc.encode(`id: ${id}\ndata: ${JSON.stringify(event)}\n\n`);

  /** An event stream the test feeds by hand. */
  function controlled() {
    let controller!: ReadableStreamDefaultController<Uint8Array>;
    const stream = new ReadableStream<Uint8Array>({ start: (c) => (controller = c) });
    return { stream, send: (id: number, event: Record<string, unknown>) => controller.enqueue(frame(id, event)), end: () => controller.close() };
  }

  beforeEach(() => {
    sessionStorage.clear();
    routes["GET /bff/api/tutor/courses/5/status"] = () => ({ body: { data: { ...status, streaming: true } } });
    routes["POST /bff/api/tutor/courses/5/messages"] = () => ({ status: 202, body: { data: run } });
  });

  it("shows a busy draft outside the live log, then the validated answer replaces it and is announced once", async () => {
    const feed = controlled();
    routes[EVENTS] = () => ({ stream: feed.stream });
    const p = await openPanel();
    void p.ask("What is Robusta?");
    await flush(8);
    const sent = JSON.parse(String(fetchMock.mock.calls.filter((c) => String(c[0]).endsWith("/messages")).at(-1)![1]!.body));
    expect(sent).toEqual({ text: "What is Robusta?", topicId: 12, stream: true });

    feed.send(1, { type: "RUN_STARTED" });
    feed.send(2, { type: "TEXT_MESSAGE_CONTENT", delta: "Draft sentence one. " });
    await flush(12);
    const draft = p.q<HTMLElement>("[data-draft]");
    expect(draft.hidden).toBe(false);
    expect(draft.textContent).toBe("Draft sentence one. ");
    expect(draft.getAttribute("aria-busy")).toBe("true");
    expect(draft.closest('[role="log"]')).toBeNull();
    expect(p.q("[data-log]").getAttribute("aria-busy")).toBe("true");
    expect(p.q("[data-log]").querySelector(".msg.tutor")).toBeNull();
    // the running answer is remembered for a reload
    expect(JSON.parse(sessionStorage.getItem("ulams-tutor-run:5")!).run.runId).toBe(run.runId);

    feed.send(3, { type: "CUSTOM", name: "tutor.final", value: { ...answer, answer: "The checked answer.", questionId: "q1" } });
    feed.send(4, { type: "RUN_FINISHED" });
    feed.end();
    await flush(14);
    const log = p.q<HTMLElement>("[data-log]");
    expect(log.querySelectorAll(".msg.tutor")).toHaveLength(1);
    expect(log.querySelector(".msg.tutor")!.textContent).toContain("The checked answer.");
    expect(log.textContent).not.toContain("Draft sentence one");
    expect(log.querySelector(".msg.tutor .sources a")?.getAttribute("href")).toBe("/learn/5/12");
    expect(p.q<HTMLElement>(".pending").hidden).toBe(true);
    expect(log.hasAttribute("aria-busy")).toBe(false);
    expect(sessionStorage.getItem("ulams-tutor-run:5")).toBeNull();
    expect(p.q("[data-quota]").textContent).toBe("28 of 30 questions left today");
  });

  it("shows a failed run in words with a retry that keeps the question", async () => {
    routes[EVENTS] = () => ({ body: `id: 1\ndata: ${JSON.stringify({ type: "RUN_ERROR", code: "error", message: "x" })}\n\n` });
    const p = await openPanel();
    await p.ask("What is Robusta?");
    await flush(10);
    expect(p.q<HTMLElement>(".error").hidden).toBe(false);
    expect(p.q(".error").textContent).toContain("Something went wrong. Your question is kept.");
    expect(p.q(".error button")?.textContent).toBe("Try again");
    expect(p.q("[data-log]").querySelector(".msg.tutor")).toBeNull();
  });

  it("says that the tutor is still answering on a second question (409)", async () => {
    routes["POST /bff/api/tutor/courses/5/messages"] = () => ({ status: 409, body: { message: "busy", reason: "busy" } });
    const p = await openPanel();
    await p.ask("And more?");
    expect(p.q(".error").textContent).toContain("still answering your last question");
  });

  it("falls back to the plain request when the operator switched streaming off", async () => {
    routes["GET /bff/api/tutor/courses/5/status"] = () => ({ body: { data: { ...status, streaming: false } } });
    routes["POST /bff/api/tutor/courses/5/messages"] = () => ({ body: { data: answer } });
    const p = await openPanel();
    await p.ask("What is Robusta?");
    const sent = JSON.parse(String(fetchMock.mock.calls.filter((c) => String(c[0]).endsWith("/messages")).at(-1)![1]!.body));
    expect(sent.stream).toBeUndefined();
    expect(p.q("[data-log]").querySelector(".msg.tutor")).not.toBeNull();
    expect(calls.some((c) => c.includes("/runs/"))).toBe(false);
  });

  it("resumes a run after a reload when the panel opens", async () => {
    sessionStorage.setItem("ulams-tutor-run:5", JSON.stringify({ run, text: "What is Robusta?" }));
    routes[EVENTS] = () => ({
      body: [
        frameText(1, { type: "TEXT_MESSAGE_CONTENT", delta: "Part. " }),
        frameText(2, { type: "CUSTOM", name: "tutor.final", value: { ...answer, answer: "Resumed answer.", questionId: "q1" } }),
      ].join(""),
    });
    const p = await openPanel();
    await flush(14);
    const log = p.q<HTMLElement>("[data-log]");
    expect(log.querySelector(".msg.learner")?.textContent).toContain("What is Robusta?");
    expect(log.querySelector(".msg.tutor")?.textContent).toContain("Resumed answer.");
    expect(sessionStorage.getItem("ulams-tutor-run:5")).toBeNull();
  });

  it("ignores a saved run when streaming is off", async () => {
    sessionStorage.setItem("ulams-tutor-run:5", JSON.stringify({ run, text: "Q" }));
    routes["GET /bff/api/tutor/courses/5/status"] = () => ({ body: { data: { ...status, streaming: false } } });
    await openPanel();
    expect(sessionStorage.getItem("ulams-tutor-run:5")).toBeNull();
    expect(calls.some((c) => c.includes("/runs/"))).toBe(false);
  });

  it("holds back an unfinished sentence under reduced motion", () => {
    expect(visibleDraft("One. Two is half", false)).toBe("One. Two is half");
    expect(visibleDraft("One. Two is half", true)).toBe("One. ");
    expect(visibleDraft("Only a half sentence", true)).toBe("");
    expect(visibleDraft("Done!", true)).toBe("Done!");
    expect(visibleDraft("Is it (really?) so", true)).toBe("Is it (really?) ");
  });
});

const frameText = (id: number, event: Record<string, unknown>) => `id: ${id}\ndata: ${JSON.stringify(event)}\n\n`;
