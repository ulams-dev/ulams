// @vitest-environment jsdom
import axe from "axe-core";
import { afterEach, beforeAll, describe, expect, it, vi } from "vitest";
import { payload } from "./compare-fixture.ts";

const versionRow = (number: number, over: Record<string, unknown> = {}) => ({
  id: `01v${String(number).padStart(23, "0")}`, number, kind: "author", origin: "author", status: "approved", reason: null, variantId: null, releaseId: null, releaseNumber: null,
  createdAt: "2026-10-20T09:00:00+00:00", decidedAt: null, ...over,
});
const versionsOf = (rows: Array<Record<string, unknown>>, variants: unknown[] = []) => ({
  sessionId: "01s0000000000000000000abcd", variantId: null, courseId: 5, baseCourseId: 5, versions: rows, variants, total: rows.length, page: 1, perPage: 200,
});
const TWO = [versionRow(6, { releaseId: `01r${"3".padStart(23, "0")}`, releaseNumber: 3 }), versionRow(2, { releaseId: `01r${"1".padStart(23, "0")}`, releaseNumber: 1 })];

type Answer = { status?: number; body: unknown };
const ok = (data: unknown): Answer => ({ body: { success: true, data, message: "OK" } });

function stubApi(answer: (url: string) => Answer) {
  if (typeof AbortSignal.timeout !== "function") (AbortSignal as unknown as { timeout: () => AbortSignal }).timeout = () => new AbortController().signal;
  const calls: string[] = [];
  vi.stubGlobal("fetch", async (url: string) => {
    calls.push(url);
    const a = answer(url);
    return new Response(JSON.stringify(a.body), { status: a.status ?? 200 });
  });
  return calls;
}

const FROM = "01v00000000000000000000002";
const TO = "01v00000000000000000000006";

async function mount(opts: { locale?: string; from?: string; to?: string } = {}): Promise<HTMLElement> {
  const { locale = "en", from = FROM, to = TO } = opts;
  document.body.innerHTML = `<main><div data-studio-compare data-course="5" data-locale="${locale}" data-from="${from}" data-to="${to}"></div></main><div data-announcer></div>`;
  vi.resetModules();
  await import("../../src/studio/compare.ts");
  return document.querySelector<HTMLElement>("[data-studio-compare]")!;
}

const violations = async (root: HTMLElement) =>
  (await axe.run(root, { rules: { "color-contrast": { enabled: false }, region: { enabled: false } } })).violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.html).join(" | ")}`);

const api = (compare: () => Answer = () => ok(payload())) => (url: string): Answer => (url.includes("/compare") ? compare() : ok(versionsOf(TWO)));
const loaded = (root: HTMLElement) => vi.waitFor(() => expect(root.querySelector('[role="tree"]')).not.toBeNull(), { timeout: 4000 });
const node = (root: HTMLElement, id: string) => root.querySelector<HTMLElement>(`[data-node="${id}"]`)!;
const key = (el: HTMLElement, k: string) => el.dispatchEvent(new KeyboardEvent("keydown", { key: k, bubbles: true, cancelable: true }));
const detail = (root: HTMLElement) => root.querySelector<HTMLElement>("[data-detail]")!;
const active = () => document.activeElement as HTMLElement;

beforeAll(async () => {
  document.body.innerHTML = "<main></main>";
  await axe.run(document.body, { rules: { "color-contrast": { enabled: false }, region: { enabled: false } } });
  await import("../../src/studio/compare.ts");
}, 60_000);

afterEach(() => vi.unstubAllGlobals());

describe("compare island", () => {
  it("asks for the versions of every branch and the comparison, and shows the summary, learner impact, quiz impact and tree", async () => {
    const calls = stubApi(api());
    const root = await mount();
    await loaded(root);

    expect(calls[0]).toBe("/studio/api/course-versions/courses/5/versions?branch=all&perPage=200");
    expect(calls[1]).toBe(`/studio/api/course-versions/courses/5/compare?from=${FROM}&to=${TO}`);
    expect(root.querySelector(".st-cmp-sides")!.textContent).toMatch(/From: Version 2 \(release #1\).*To: Version 6 \(release #3\)/);
    const figures = [...root.querySelectorAll(".cb-figures")].map((dl) => dl.textContent);
    expect(figures[0]).toContain("Elements added1");
    expect(figures[0]).toContain("Citations repointed to a newer source revision1");
    expect(figures[1]).toContain("Lessons that show an “Updated” notice1");
    expect(figures[1]).toContain("Lessons removed0");
    const quiz = [...root.querySelectorAll(".st-cmp-quiz-list li")].map((li) => li.textContent);
    expect(quiz).toEqual(["Lesson 1.1 › Question 1: answer key changed", "Lesson 1.1 › Question 2: wording changed"]);
    expect(root.querySelector("[data-count]")!.textContent).toBe("Showing 10 of 10 changed elements");
    await vi.waitFor(() => expect(document.querySelector("[data-announcer]")!.textContent).toBe("Comparison loaded: changed elements: 10."));
    expect(await violations(root)).toEqual([]);
  });

  it("marks every change with a symbol and a word, never colour alone", async () => {
    stubApi(api());
    const root = await mount();
    await loaded(root);

    const badges = (id: string) => [...node(root, id).firstElementChild!.querySelectorAll(".cb-badge")].map((b) => b.textContent!.trim());
    expect(badges("les_3")).toEqual(["+ Added"]);
    expect(badges("blk_old")).toEqual(["− Removed"]);
    expect(badges("les_1")).toEqual(["~ Changed"]);
    expect(badges("les_2")).toEqual(["→ Moved"]);
    expect(badges("les_5")).toEqual(["↕ Reordered"]);
    expect(badges("qz_1")).toEqual([]);
    expect(node(root, "les_1").getAttribute("aria-labelledby")).toBe(node(root, "les_1").querySelector(".st-cmp-row")!.id);
    expect(node(root, "les_1").querySelector(".st-cmp-row")!.textContent).toContain("Lesson 1.1: Brewing basics");
  });

  it("selects an element with Enter, moves the focus to its details and shows the word-level changes", async () => {
    stubApi(api());
    const root = await mount();
    await loaded(root);

    key(node(root, "blk_1"), "Enter");

    const heading = detail(root).querySelector("h3")!;
    expect(active()).toBe(heading);
    expect(heading.textContent).toContain("Block 1");
    expect(node(root, "blk_1").getAttribute("aria-selected")).toBe("true");
    expect(detail(root).textContent).toContain("Module 1 › Lesson 1.1");
    const del = detail(root).querySelector("del")!;
    const ins = detail(root).querySelector("ins")!;
    expect(del.textContent).toContain("removed: ");
    expect(del.textContent).toContain("Heat");
    expect(ins.textContent).toContain("added: ");
    expect(ins.textContent).toContain("Warm");
    expect(detail(root).querySelector(".st-cmp-sides-grid")).toBeNull();
    expect(await violations(root)).toEqual([]);
  });

  it("shows the same change side by side on request and keeps the choice visible as pressed", async () => {
    stubApi(api());
    const root = await mount();
    await loaded(root);
    key(node(root, "blk_1"), "Enter");

    root.querySelector<HTMLButtonElement>('[data-view="side"]')!.click();

    expect(root.querySelector('[data-view="side"]')!.getAttribute("aria-pressed")).toBe("true");
    expect(root.querySelector('[data-view="inline"]')!.getAttribute("aria-pressed")).toBe("false");
    const sides = [...detail(root).querySelectorAll(".st-cmp-side")];
    expect(sides.map((s) => s.getAttribute("aria-label"))).toEqual(["Before", "After"]);
    expect(sides[0]!.querySelector("del")!.textContent).toContain("Heat");
    expect(sides[0]!.querySelector("ins")).toBeNull();
    expect(sides[1]!.querySelector("ins")!.textContent).toContain("Warm");
    expect(sides[1]!.querySelector("del")).toBeNull();
    expect(active()).toBe(root.querySelector('[data-view="side"]'));
    expect(await violations(root)).toEqual([]);
  });

  it("shows an added element as content, a removed one as content, a move and a reorder as a sentence", async () => {
    stubApi(api());
    const root = await mount();
    await loaded(root);

    key(node(root, "les_3"), "Enter");
    expect(detail(root).textContent).toContain("Content of the new element");
    expect(detail(root).querySelector("ins, del")).toBeNull();
    expect(detail(root).textContent).toContain("Choosing a kettle");

    key(node(root, "blk_old"), "Enter");
    expect(detail(root).textContent).toContain("Content of the removed element");
    expect(detail(root).textContent).toContain("Use a cheap blade grinder.");

    key(node(root, "les_2"), "Enter");
    expect(detail(root).querySelector(".st-cmp-move")!.textContent).toContain("Moved from Module 1 to Module 2.");

    key(node(root, "les_5"), "Enter");
    expect(detail(root).querySelector(".st-cmp-move")!.textContent).toContain("Moved from position 3 to position 1 among its siblings.");
  });

  it("shows numbers and flags as before and after, and a very long field as changed lines", async () => {
    stubApi(api());
    const root = await mount();
    await loaded(root);

    key(node(root, "les_1"), "Enter");
    const minutes = detail(root).querySelector('[data-field="minutes"]')!;
    expect(minutes.textContent).toContain("Before");
    expect(minutes.querySelector("del")!.textContent).toContain("5");
    expect(minutes.querySelector("ins")!.textContent).toContain("6");

    key(node(root, "blk_big"), "Enter");
    const lines = detail(root).querySelector('[data-field="markdown"]')!;
    expect(lines.textContent).toContain("very long, so only the changed lines are shown");
    expect(lines.textContent).toContain("old line");
    expect(lines.textContent).toContain("12 more changed lines are not shown.");
    expect(lines.querySelectorAll(".st-cmp-line-added")).toHaveLength(2);
    expect(await violations(root)).toEqual([]);
  });

  it("shows the citations that changed with the passages, before and after for a repointed one", async () => {
    stubApi(api());
    const root = await mount();
    await loaded(root);

    key(node(root, "les_1"), "Enter");

    const panel = detail(root).querySelector('[data-panel="citations"]')!;
    expect(panel.querySelector("h4")!.textContent).toBe("Citations");
    expect(panel.querySelector('[data-citation="added"]')!.textContent).toContain("Water between 92 and 96 degrees.");
    expect(panel.querySelector('[data-citation="removed"]')!.textContent).toContain("Boiling water is fine.");
    const repointed = panel.querySelector('[data-citation="repointed"]')!;
    expect(repointed.textContent).toContain("Citation repointed to a newer source revision");
    expect(repointed.textContent).toContain("The passage before");
    expect(repointed.textContent).toContain("Use water at 90 degrees.");
    expect(repointed.textContent).toContain("source revision 2");
    expect(repointed.textContent).toContain("Use water at 95 degrees.");
    expect(repointed.textContent).toContain("source revision 3");
    expect(detail(root).querySelector('[data-field="citations"]')).toBeNull();
    expect(await violations(root)).toEqual([]);
  });

  it("shows the quiz impact of a question whose answer key changed, with the consequence for learners", async () => {
    stubApi(api());
    const root = await mount();
    await loaded(root);

    root.querySelector<HTMLButtonElement>('[data-quiz-jump="q_1"]')!.click();

    expect(active()).toBe(detail(root).querySelector("h3"));
    expect(detail(root).querySelector("h3")!.textContent).toContain("Question 1");
    expect(detail(root).textContent).toContain("This element did not change itself");
    const panel = detail(root).querySelector('[data-panel="quiz"]')!;
    expect(panel.textContent).toContain("Correct beforePressure");
    expect(panel.textContent).toContain("Correct afterTemperature");
    expect(panel.querySelector('[role="note"]')!.textContent).toContain("re-attempt notice and one extra attempt");
    expect(await violations(root)).toEqual([]);

    root.querySelector<HTMLButtonElement>('[data-quiz-jump="q_2"]')!.click();
    expect(detail(root).querySelector('[data-panel="quiz"] .cb-change-text ins')!.textContent).toContain("water");
  });

  it("filters by kind and type, announces the count and clears the filters", async () => {
    stubApi(api());
    const root = await mount();
    await loaded(root);
    const filter = (selector: string) => root.querySelector<HTMLButtonElement>(selector)!;

    filter('[data-filter-kind="moved"]').click();
    expect(root.querySelector("[data-count]")!.textContent).toBe("Showing 1 of 10 changed elements");
    expect(filter('[data-filter-kind="moved"]').getAttribute("aria-pressed")).toBe("true");
    expect(active()).toBe(filter('[data-filter-kind="moved"]'));
    expect(node(root, "les_2")).not.toBeNull();
    expect(root.querySelector('[data-node="les_3"]')).toBeNull();
    await vi.waitFor(() => expect(document.querySelector("[data-announcer]")!.textContent).toBe("Showing 1 of 10 changed elements."));

    filter('[data-filter-type="option"]').click();
    expect(root.querySelector('[data-state="no-match"]')!.textContent).toBe("No changed element matches these filters.");
    expect(await violations(root)).toEqual([]);

    root.querySelector<HTMLButtonElement>("[data-filter-clear]")!.click();
    expect(root.querySelector("[data-count]")!.textContent).toBe("Showing 10 of 10 changed elements");
    expect(root.querySelector("[data-filter-clear]")).toBeNull();
    expect(root.querySelector('[role="tree"]')).not.toBeNull();
  });

  it("is a tree with one tab stop and arrow-key navigation", async () => {
    stubApi(api());
    const root = await mount();
    await loaded(root);
    const tree = root.querySelector('[role="tree"]')!;
    expect(tree.getAttribute("aria-label")).toBe("Changed elements, as a tree");
    expect(root.querySelectorAll('[role="treeitem"][tabindex="0"]')).toHaveLength(1);
    const first = node(root, "mod_1");
    expect(first.getAttribute("aria-level")).toBe("1");
    expect(first.getAttribute("aria-expanded")).toBe("true");
    expect(node(root, "les_1").getAttribute("aria-level")).toBe("2");

    first.focus();
    key(first, "ArrowDown");
    expect(active()).toBe(node(root, "les_1"));
    expect(root.querySelectorAll('[role="treeitem"][tabindex="0"]')).toHaveLength(1);
    key(active(), "ArrowUp");
    expect(active()).toBe(first);
    key(first, "End");
    expect(active().dataset.node).toBe("les_5");
    key(active(), "Home");
    expect(active()).toBe(first);

    key(first, "ArrowLeft"); // collapse Module 1
    expect(node(root, "mod_1").getAttribute("aria-expanded")).toBe("false");
    expect(node(root, "mod_1").querySelector('[role="group"]')!.hasAttribute("hidden")).toBe(true);
    key(active(), "ArrowDown");
    expect(active().dataset.node).toBe("mod_2");
    key(node(root, "mod_1"), "ArrowRight"); // expand again
    expect(node(root, "mod_1").getAttribute("aria-expanded")).toBe("true");
    key(active(), "ArrowRight"); // into the first child
    expect(active().dataset.node).toBe("les_1");
    key(active(), "ArrowLeft"); // collapses the open lesson
    expect(node(root, "les_1").getAttribute("aria-expanded")).toBe("false");
    key(active(), "ArrowLeft"); // then back to the parent
    expect(active().dataset.node).toBe("mod_1");
    expect(await violations(root)).toEqual([]);
  });

  it("lets Back return the focus to the element in the tree", async () => {
    stubApi(api());
    const root = await mount();
    await loaded(root);
    key(node(root, "blk_1"), " ");

    detail(root).querySelector<HTMLButtonElement>("[data-back]")!.click();

    expect(active()).toBe(node(root, "blk_1"));
  });

  it("says when two versions have the same content", async () => {
    stubApi(api(() => ok(payload({ changes: [], quiz: [] }))));
    const root = await mount();
    await vi.waitFor(() => expect(root.querySelector('[data-state="no-change"]')).not.toBeNull(), { timeout: 4000 });

    expect(root.textContent).toContain("These two versions have the same content");
    expect(root.querySelector('[role="tree"]')).toBeNull();
    expect(await violations(root)).toEqual([]);
  });

  it("says when the same version is on both sides", async () => {
    const same = payload();
    stubApi(api(() => ok({ ...same, to: { ...same.to, versionId: same.from.versionId } })));
    const root = await mount();
    await vi.waitFor(() => expect(root.textContent).toContain("The same version is on both sides"), { timeout: 4000 });
  });

  it("offers the pair picker with the chosen versions selected, and a swap link", async () => {
    stubApi(api());
    const root = await mount();
    await loaded(root);

    const [from, to] = [...root.querySelectorAll<HTMLSelectElement>("form select[name]")];
    expect(from!.labels![0]!.textContent).toBe("From (older)");
    expect(to!.labels![0]!.textContent).toBe("To (newer)");
    expect(from!.value).toBe(FROM);
    expect(to!.value).toBe(TO);
    expect(from!.options[0]!.textContent).toMatch(/^Version 6 · release #3 · /);
    const form = root.querySelector<HTMLFormElement>(".st-cmp-form")!;
    expect(form.method).toBe("get");
    expect(Object.fromEntries(new FormData(form).entries())).toEqual({ from: FROM, to: TO, lang: "en" });
    expect(root.querySelector<HTMLAnchorElement>("a.cb-btn")!.getAttribute("href")).toBe(`/studio/courses/5/compare?from=${TO}&to=${FROM}&lang=en`);
  });

  it("asks for two versions when none are chosen and suggests the latest two releases", async () => {
    const calls = stubApi(api());
    const root = await mount({ from: "", to: "" });
    await vi.waitFor(() => expect(root.querySelector("form")).not.toBeNull(), { timeout: 4000 });

    expect(calls).toHaveLength(1);
    expect(root.textContent).toContain("Pick two versions and choose Compare.");
    const [from, to] = [...root.querySelectorAll<HTMLSelectElement>("form select[name]")];
    expect(to!.value).toBe(TO);
    expect(from!.value).toBe(FROM);
    expect(await violations(root)).toEqual([]);
  });

  it("compares the base with a variant through a plain form that carries the latest version of each", async () => {
    const base = [versionRow(7, { releaseId: "r3", releaseNumber: 3 }), versionRow(5, { releaseId: "r2", releaseNumber: 2 })];
    const variantRows = [versionRow(8, { variantId: "01w00000000000000000000009", releaseId: "rv1", releaseNumber: 1 })];
    const variants = [
      { id: "01w00000000000000000000009", key: "expert", label: "Expert", level: "expert", role: null, status: "approved", courseId: 9 },
      { id: "01w0000000000000000000000a", key: "beginner", label: "Beginner", level: "beginner", role: null, status: "draft", courseId: null },
    ];
    stubApi(() => ok(versionsOf([...variantRows, ...base], variants)));
    const root = await mount({ from: "", to: "" });
    await vi.waitFor(() => expect(root.querySelector("[data-variant-form]")).not.toBeNull(), { timeout: 4000 });
    const form = root.querySelector<HTMLFormElement>("[data-variant-form]")!;

    expect(form.querySelector("label")!.textContent).toBe("Compare the base course with a variant");
    expect(Object.fromEntries(new FormData(form).entries())).toEqual({ from: base[0]!.id, to: variantRows[0]!.id, lang: "en" });
    expect(await violations(root)).toEqual([]);

    // a variant without a version: the submit is refused and the author told why
    const select = form.querySelector("select")!;
    select.value = "01w0000000000000000000000a";
    select.dispatchEvent(new Event("change"));
    expect((form.querySelector('input[name="from"]') as HTMLInputElement).value).toBe("");
    const submit = new Event("submit", { cancelable: true });
    form.dispatchEvent(submit);
    expect(submit.defaultPrevented).toBe(true);
    expect(form.querySelector('[role="alert"]')!.hasAttribute("hidden")).toBe(false);
    expect(form.querySelector('[role="alert"]')!.textContent).toBe("That variant has no version to compare yet.");
  });

  it("explains a pair that is not part of the course", async () => {
    stubApi(api(() => ({ status: 404, body: { message: "That version or release is not part of this course." } })));
    const root = await mount();
    await vi.waitFor(() => expect(root.querySelector('[data-state="not-found"]')).not.toBeNull(), { timeout: 4000 });

    expect(root.textContent).toContain("One of these versions or releases is not part of this course.");
    expect(root.querySelector("form")).not.toBeNull();
  });

  it("explains a course that was not made with the builder, another author's course and an empty course", async () => {
    stubApi(() => ({ status: 404, body: { message: "x" } }));
    let root = await mount();
    await vi.waitFor(() => expect(root.querySelector('[data-state="not-built"]')).not.toBeNull(), { timeout: 4000 });
    expect(root.textContent).toContain("Adopt the course to track its versions.");

    stubApi(() => ({ status: 403, body: { message: "no" } }));
    root = await mount();
    await vi.waitFor(() => expect(root.querySelector('[data-state="forbidden"]')).not.toBeNull(), { timeout: 4000 });
    expect(root.querySelector('[role="alert"]')!.textContent).toContain("belongs to another author");
    expect(await violations(root)).toEqual([]);

    stubApi(() => ok(versionsOf([])));
    root = await mount();
    await vi.waitFor(() => expect(root.querySelector('[data-state="empty"]')).not.toBeNull(), { timeout: 4000 });
    expect(root.textContent).toContain("This course has no versions yet.");
  });

  it("offers a retry after an error that recovers", async () => {
    let failed = false;
    stubApi(api(() => {
      if (!failed) {
        failed = true;
        return { status: 500, body: { message: "down" } };
      }
      return ok(payload());
    }));
    const root = await mount();
    await vi.waitFor(() => expect(root.querySelector('[role="alert"]')).not.toBeNull(), { timeout: 4000 });
    expect(root.querySelector('[role="alert"]')!.textContent).toBe("The comparison could not be loaded.");
    expect(await violations(root)).toEqual([]);

    root.querySelector<HTMLButtonElement>("[data-state=error] button")!.click();
    await loaded(root);
  });

  it.each([
    ["pl", "Wpływ na quizy", "Dodano", "Lekcja 1.1", /usunięto: /],
    ["zh", "对测验的影响", "新增", "课时 1.1", /删除：/],
  ])("speaks %s", async (locale, quizTitle, added, lesson, removed) => {
    stubApi(api());
    const root = await mount({ locale });
    await loaded(root);

    expect([...root.querySelectorAll("h2")].map((h) => h.textContent)).toContain(quizTitle);
    expect(node(root, "les_3").textContent).toContain(added);
    expect(node(root, "les_1").textContent).toContain(lesson);
    key(node(root, "blk_1"), "Enter");
    expect(detail(root).querySelector("del")!.textContent).toMatch(removed);
    expect(await violations(root)).toEqual([]);
  });
});
