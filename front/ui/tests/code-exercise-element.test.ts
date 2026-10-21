import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from "vitest";
import { renderDoc, setupDom } from "./astro-render.ts";
import { registry } from "../src/registry.ts";
import { fallbackText, validateDocument, type UiNode } from "../src/render-core.ts";
import { validate } from "../src/schema.ts";
import { codeStrings } from "../src/lib/code-exercise-strings.ts";

import type { RunOutcome, RunnerState } from "../src/lib/code-exercise.ts";

const state = vi.hoisted(() => ({ outcome: null as null | (() => Promise<unknown>), runs: [] as unknown[], onState: null as null | ((s: RunnerState) => void), started: 0, resets: 0 }));

vi.mock("../src/lib/code-exercise.ts", async (original) => {
  const real = await original<typeof import("../src/lib/code-exercise.ts")>();
  class FakeClient {
    constructor(options: { onState?: (s: RunnerState) => void }) {
      state.onState = options.onState ?? null;
    }
    start() {
      state.started++;
      state.onState?.("ready");
    }
    async run(files: unknown) {
      state.runs.push(files);
      return (await state.outcome!()) as RunOutcome;
    }
    reset() {
      state.resets++;
    }
    destroy() {}
  }
  return { ...real, CodeRunnerClient: FakeClient };
});

const FILES = [{ path: "sum.js", content: "export function sum(a, b) {\n  return 0;\n}\n" }, { path: "lib/util.js", content: "\nexport const x = 1;" }];
const TESTS = [{ path: "sum.test.js", content: "\nimport { sum } from './sum.js';\ntest('adds', () => expect(sum(1, 2)).toBe(3));\n" }];
const lesson = (props: Record<string, unknown> = {}): UiNode => ({
  component: "CodeExerciseLesson",
  props: { src: "http://acme.content.localhost/runtime/code-runner/1/runner.html", title: "Sum it", topicId: 7, files: FILES, tests: TESTS, text: "Make `sum` **work**.", ...props },
});

setupDom();
const { default: axe } = await import("axe-core");
beforeAll(async () => {
  await import("../src/elements/code-exercise.ts");
});

const AXE = { rules: { "color-contrast": { enabled: false }, region: { enabled: false } } };
const violations = async (root: HTMLElement) => (await axe.run(root, AXE)).violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.html).join(" | ")}`);
async function mount(doc: UiNode): Promise<HTMLElement> {
  document.body.innerHTML = `<main><h1 tabindex="-1">Sum it</h1>${await renderDoc(doc)}</main>`;
  return document.querySelector("main")!;
}
const win = (document.defaultView ?? globalThis) as unknown as Window & typeof globalThis;
const flush = async () => {
  for (let i = 0; i < 8; i++) await Promise.resolve();
  await new Promise((r) => setTimeout(r, 0));
};
/** Hashing the files is asynchronous work outside the microtask queue: wait until the report call has had its time. */
const settle = () => new Promise((r) => setTimeout(r, 80));
const el = (root: HTMLElement) => root.querySelector("ulams-code-exercise") as HTMLElement;
const passed = { kind: "result", result: { runId: 1, status: "passed", tests: [{ name: "adds", status: "pass" }], passed: 1, total: 1, durationMs: 3 } };
const failed = { kind: "result", result: { runId: 1, status: "failed", tests: [{ name: "adds", status: "fail", message: "Expected 3, received 0" }, { name: "other", status: "pass" }], passed: 1, total: 2, durationMs: 3, output: "hello", truncated: true } };

const memory = new Map<string, string>();
// jsdom's window has an opaque origin, which has no localStorage: a small stand-in
Object.defineProperty(win, "localStorage", {
  configurable: true,
  value: { getItem: (k: string) => memory.get(k) ?? null, setItem: (k: string, v: string) => void memory.set(k, v), removeItem: (k: string) => void memory.delete(k), clear: () => memory.clear() },
});

beforeEach(() => {
  state.outcome = async () => passed;
  state.runs = [];
  state.started = 0;
  state.resets = 0;
  win.localStorage.clear();
  vi.stubGlobal("fetch", vi.fn(async () => new Response(JSON.stringify({ success: true, data: { attempt: { attempt_no: 3 }, status: 1 } }), { status: 201 })));
});
afterEach(() => vi.unstubAllGlobals());

describe("CodeExerciseLesson: schema and fallback", () => {
  it("takes a runner address, a title and files and tests, and nothing unknown", () => {
    const props = lesson().props!;
    expect(validate(registry.CodeExerciseLesson.props, props).valid).toBe(true);
    expect(validate(registry.CodeExerciseLesson.props, { ...props, onclick: "x" }).valid).toBe(false);
    expect(validate(registry.CodeExerciseLesson.props, { ...props, files: undefined }).valid).toBe(false);
    expect(validate(registry.CodeExerciseLesson.props, { ...props, language: "python" }).valid).toBe(false);
    expect(validate(registry.CodeExerciseLesson.props, { ...props, timeoutMs: 100 }).valid).toBe(false);
    expect(validate(registry.CodeExerciseLesson.props, { ...props, src: "javascript:alert(1)" }).valid).toBe(false);
    expect(fallbackText(lesson())).toBe("Sum it");
    expect(validateDocument(lesson())).toEqual([]);
  });
});

describe("CodeExerciseLesson renders accessibly", () => {
  it("has a labelled editor per file with the exact starter text, the tests for reading, a sandboxed runner frame, and passes axe", async () => {
    const root = await mount(lesson());
    const editors = [...root.querySelectorAll<HTMLTextAreaElement>("textarea[data-editor]")];
    expect(editors.map((e) => e.dataset.path)).toEqual(["sum.js", "lib/util.js"]);
    // the first newline of a text area is dropped by the parser: the markup carries one more, so the text is exact
    expect(editors.map((e) => e.defaultValue)).toEqual(FILES.map((f) => f.content));
    for (const e of editors) {
      expect(root.querySelector(`label[for="${e.id}"]`)?.textContent?.trim()).toBe(e.dataset.path);
      expect(e.getAttribute("aria-label")).toBe(`Code of ${e.dataset.path}`);
      expect(e.getAttribute("spellcheck")).toBe("false");
    }
    expect(JSON.parse(el(root).dataset.tests!)).toEqual(TESTS);
    expect(root.querySelector(".u-ce__task .u-prose strong")?.textContent).toBe("work");
    const frame = root.querySelector("iframe")!;
    expect(frame.getAttribute("sandbox")).toBe("allow-scripts");
    expect(frame.getAttribute("data-src")).toBe("http://acme.content.localhost/runtime/code-runner/1/runner.html");
    expect(frame.hasAttribute("src")).toBe(false);
    expect(frame.getAttribute("referrerpolicy")).toBe("no-referrer");
    expect(root.querySelector("[data-run]")?.getAttribute("aria-describedby")).toBeTruthy();
    expect(root.textContent).toContain("does not count towards a certificate");
    const found = await violations(root);
    expect(found, found.join("\n")).toEqual([]);
  });

  it("speaks Polish and Chinese when asked", async () => {
    const pl = await mount(lesson({ locale: "pl" }));
    expect(pl.querySelector("[data-run]")?.textContent).toBe("Uruchom testy");
    expect(pl.querySelector("textarea")?.getAttribute("aria-label")).toBe("Kod pliku sum.js");
    const zh = await mount(lesson({ locale: "zh" }));
    expect(zh.querySelector("[data-run]")?.textContent).toBe("运行测试");
  });

  it("has the same keys in every language", () => {
    const en = Object.keys(codeStrings("en")).sort();
    expect(Object.keys(codeStrings("pl")).sort()).toEqual(en);
    expect(Object.keys(codeStrings("zh")).sort()).toEqual(en);
    expect(codeStrings("de")).toBe(codeStrings("en"));
  });
});

describe("<ulams-code-exercise>", () => {
  it("enables Run tests once the runner is ready and runs the current text of every editor", async () => {
    const root = await mount(lesson());
    await flush();
    const run = root.querySelector<HTMLButtonElement>("[data-run]")!;
    expect(el(root).getAttribute("runner")).toBe("ready");
    expect(run.disabled).toBe(false);
    const first = root.querySelector<HTMLTextAreaElement>("textarea")!;
    first.value = "export const sum = (a, b) => a + b;";
    run.click();
    await flush();
    expect(state.runs).toEqual([[{ path: "sum.js", content: "export const sum = (a, b) => a + b;" }, { path: "lib/util.js", content: FILES[1]!.content }]]);
  });

  it("shows the result of each test and a summary, then reports the attempt with the learner's session and announces completion", async () => {
    const completed = vi.fn();
    document.addEventListener("ulams:complete", completed);
    const root = await mount(lesson());
    await flush();
    state.outcome = async () => failed;
    root.querySelector<HTMLButtonElement>("[data-run]")!.click();
    await flush();
    await settle();
    expect(root.querySelector("[data-summary]")?.textContent).toBe("1 of 2 tests passed.");
    const lines = [...root.querySelectorAll("[data-results] li")].map((li) => li.textContent);
    expect(lines[0]).toContain("Failed");
    expect(lines[0]).toContain("adds");
    expect(lines[0]).toContain("Expected 3, received 0");
    expect(lines[1]).toContain("Passed");
    expect(root.querySelector<HTMLDetailsElement>("[data-output]")?.hidden).toBe(false);
    expect(root.querySelector("[data-output] pre")?.textContent).toContain("hello");
    expect(root.querySelector("[data-output] pre")?.textContent).toContain("cut short");

    const call = (fetch as unknown as ReturnType<typeof vi.fn>).mock.calls[0]!;
    expect(call[0]).toBe("/bff/api/code-exercises/topics/7/attempts");
    const body = JSON.parse((call[1] as { body: string }).body) as Record<string, unknown>;
    expect(body.results).toEqual([{ name: "adds", status: "fail", message: "Expected 3, received 0" }, { name: "other", status: "pass" }]);
    expect(body.runner).toBe("browser:1");
    expect(body.files_hash).toMatch(/^[a-f0-9]{64}$/);
    expect(root.querySelector("[data-saved]")?.textContent).toBe("Attempt 3 saved.");
    expect(completed).toHaveBeenCalledOnce();
    expect((completed.mock.calls[0]![0] as CustomEvent).detail).toEqual({ source: "code-exercise" });
    document.removeEventListener("ulams:complete", completed);
  });

  it("says so when the result could not be saved, and never reports in a preview", async () => {
    const root = await mount(lesson());
    await flush();
    (fetch as unknown as ReturnType<typeof vi.fn>).mockResolvedValueOnce(new Response("{}", { status: 500 }));
    root.querySelector<HTMLButtonElement>("[data-run]")!.click();
    await flush();
    await settle();
    expect(root.querySelector("[data-saved]")?.textContent).toContain("could not be saved");

    (fetch as unknown as ReturnType<typeof vi.fn>).mockClear();
    const preview = await mount(lesson({ topicId: undefined }));
    await flush();
    preview.querySelector<HTMLButtonElement>("[data-run]")!.click();
    await flush();
    await settle();
    expect(preview.querySelector("[data-summary]")?.textContent).toBe("All 1 tests passed.");
    expect(fetch).not.toHaveBeenCalled();
  });

  it("reports nothing for a build error, and shows the errors", async () => {
    const root = await mount(lesson());
    await flush();
    state.outcome = async () => ({ kind: "result", result: { runId: 1, status: "error", tests: [], passed: 0, total: 0, durationMs: 1, error: "sum.js:2:10: Unexpected \";\"" } });
    root.querySelector<HTMLButtonElement>("[data-run]")!.click();
    await flush();
    await settle();
    expect(root.querySelector("[data-summary]")?.textContent).toBe("Your code could not be built:");
    expect(root.querySelector("[data-results]")?.textContent).toContain("sum.js:2:10");
    expect(fetch).not.toHaveBeenCalled();
  });

  it("explains a timeout, a stopped run and an unavailable runner, without reporting anything", async () => {
    const root = await mount(lesson());
    await flush();
    state.outcome = async () => ({ kind: "result", result: { runId: 1, status: "timeout", tests: [], passed: 0, total: 0, durationMs: 5000, error: "stopped" } });
    root.querySelector<HTMLButtonElement>("[data-run]")!.click();
    await flush();
    await settle();
    expect(root.querySelector("[data-summary]")?.textContent).toContain("took too long");
    state.outcome = async () => ({ kind: "stopped" });
    root.querySelector<HTMLButtonElement>("[data-run]")!.click();
    await flush();
    await settle();
    expect(root.querySelector("[data-summary]")?.textContent).toBe("The test run was stopped.");
    expect(fetch).not.toHaveBeenCalled();
  });

  it("keeps Run tests disabled and says why when the runner is unavailable", async () => {
    const root = await mount(lesson());
    await flush();
    state.onState?.("unavailable");
    expect(root.querySelector<HTMLButtonElement>("[data-run]")!.disabled).toBe(true);
    expect(root.querySelector("[data-runner-status]")?.textContent).toContain("could not start");
    expect(root.querySelector("textarea")!.disabled).toBe(false);
  });

  it("runs on Ctrl+Enter and Command+Enter in an editor", async () => {
    const root = await mount(lesson());
    await flush();
    const editor = root.querySelector("textarea")!;
    editor.dispatchEvent(new win.KeyboardEvent("keydown", { key: "Enter", ctrlKey: true, bubbles: true, cancelable: true }));
    await flush();
    await settle();
    editor.dispatchEvent(new win.KeyboardEvent("keydown", { key: "Enter", metaKey: true, bubbles: true, cancelable: true }));
    await flush();
    await settle();
    editor.dispatchEvent(new win.KeyboardEvent("keydown", { key: "Enter", bubbles: true, cancelable: true }));
    await flush();
    await settle();
    expect(state.runs).toHaveLength(2);
  });

  it("starts over only after a confirmation, and Tab is never trapped", async () => {
    const root = await mount(lesson());
    await flush();
    const editor = root.querySelector<HTMLTextAreaElement>("textarea")!;
    editor.value = "changed";
    const confirm = root.querySelector<HTMLElement>("[data-reset-confirm]")!;
    expect(confirm.hidden).toBe(true);
    root.querySelector<HTMLButtonElement>("[data-reset]")!.click();
    expect(confirm.hidden).toBe(false);
    root.querySelector<HTMLButtonElement>("[data-reset-keep]")!.click();
    expect(confirm.hidden).toBe(true);
    expect(editor.value).toBe("changed");

    root.querySelector<HTMLButtonElement>("[data-reset]")!.click();
    root.querySelector<HTMLButtonElement>("[data-reset-yes]")!.click();
    expect(editor.value).toBe(FILES[0]!.content);
    expect(state.resets).toBe(1);
    expect(root.querySelector("[data-live]")?.textContent).toBe("Your code is back to the starting point.");
    const tab = new win.KeyboardEvent("keydown", { key: "Tab", bubbles: true, cancelable: true });
    editor.dispatchEvent(tab);
    expect(tab.defaultPrevented).toBe(false);
  });

  it("keeps the learner's edits as a draft and restores them next time, but not after the exercise changed", async () => {
    let root = await mount(lesson());
    await flush();
    const editor = root.querySelector<HTMLTextAreaElement>("textarea")!;
    editor.value = "my work";
    editor.dispatchEvent(new win.Event("input", { bubbles: true }));
    win.dispatchEvent(new win.Event("pagehide"));

    root = await mount(lesson());
    await flush();
    expect(root.querySelector<HTMLTextAreaElement>("textarea")!.value).toBe("my work");
    expect(root.querySelector("[data-live]")?.textContent).toBe("Your earlier changes were restored.");

    root = await mount(lesson({ files: [{ path: "sum.js", content: "a different start" }, FILES[1]] }));
    await flush();
    expect(root.querySelector<HTMLTextAreaElement>("textarea")!.value).toBe("a different start");
  });
});
