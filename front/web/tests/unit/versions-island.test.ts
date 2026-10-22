// @vitest-environment jsdom
import axe from "axe-core";
import { afterEach, beforeAll, describe, expect, it, vi } from "vitest";

const summary = (over: Record<string, unknown> = {}) => ({
  first: false,
  counts: { added: 0, changed: 1, removed: 0 },
  lessons: { added: 0, changed: 1, removed: 0 },
  questions: { added: 0, changed: 0, removed: 0 },
  citationsChanged: 0,
  answerKeysChanged: 0,
  ...over,
});

const release = (number: number, trigger: string, over: Record<string, unknown> = {}) => ({
  id: `01r${String(number).padStart(23, "0")}`,
  number,
  trigger,
  versionId: `01v${String(number).padStart(23, "0")}`,
  versionNumber: number + 1,
  reason: null,
  previousReleaseId: null,
  courseId: 5,
  approvedBy: { id: 7, name: "Jane Doe" },
  sourceRevisions: null,
  summary: summary(),
  releasedAt: "2026-10-20T09:00:00+00:00",
  ...over,
});

const pageOf = (releases: unknown[], over: Record<string, unknown> = {}) => ({ sessionId: "01s0000000000000000000abcd", courseId: 5, releases, total: releases.length, page: 1, perPage: 25, ...over });

type Answer = { status?: number; body: unknown };

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

const ok = (data: unknown): Answer => ({ body: { success: true, data, message: "OK" } });

async function mount(locale = "en"): Promise<HTMLElement> {
  document.body.innerHTML = `<main><div data-studio-versions data-course="5" data-locale="${locale}"></div></main><div data-announcer></div>`;
  vi.resetModules();
  // importing the module mounts the island on [data-studio-versions], like the page does
  await import("../../src/studio/versions.ts");
  return document.querySelector<HTMLElement>("[data-studio-versions]")!;
}

const violations = async (root: HTMLElement) =>
  (await axe.run(root, { rules: { "color-contrast": { enabled: false }, region: { enabled: false } } })).violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.html).join(" | ")}`);

beforeAll(async () => {
  document.body.innerHTML = "<main></main>";
  await axe.run(document.body, { rules: { "color-contrast": { enabled: false }, region: { enabled: false } } });
  await import("../../src/studio/versions.ts");
}, 60_000);

afterEach(() => vi.unstubAllGlobals());

const full = () => pageOf([
  release(3, "chat", { summary: summary({ counts: { added: 0, changed: 2, removed: 0 }, lessons: { added: 0, changed: 1, removed: 0 }, answerKeysChanged: 1 }) }),
  release(2, "restore", { approvedBy: null }),
  release(1, "generate", { summary: summary({ first: true, counts: { added: 20, changed: 0, removed: 0 }, lessons: { added: 4, changed: 0, removed: 0 } }) }),
]);

describe("versions island", () => {
  it("lists the releases newest first as a table with text for every fact, and passes axe", async () => {
    const calls = stubApi(() => ok(full()));
    const root = await mount();
    await vi.waitFor(() => expect(root.querySelector("table")).not.toBeNull(), { timeout: 4000 });

    expect(calls[0]).toBe("/studio/api/course-versions/courses/5/releases?page=1&perPage=25");
    expect(root.querySelector("h2")!.textContent).toBe("Releases, newest first");
    const rows = [...root.querySelectorAll("tbody tr")];
    expect(rows.map((r) => r.querySelector("th")!.textContent)).toEqual(["#3", "#2", "#1"]);
    expect(rows[0]!.textContent).toContain("Changed in the element chat");
    expect(rows[0]!.textContent).toContain("Jane Doe");
    expect(rows[0]!.textContent).toContain("Quiz answer keys changed: 1");
    expect(rows[1]!.textContent).toContain("Not recorded");
    expect(rows[2]!.textContent).toContain("First release (lessons: 4)");
    expect(root.querySelector("caption")!.textContent).toBe("Releases of this course, newest first");
    expect(root.querySelector('[role="region"]')!.getAttribute("tabindex")).toBe("0");
    expect(root.querySelector("[data-page-status]")!.textContent).toBe("Page 1 of 1 (releases: 3)");
    expect(root.querySelector<HTMLButtonElement>('[data-pager="newer"]')!.disabled).toBe(true);
    expect(root.querySelector<HTMLButtonElement>('[data-pager="older"]')!.disabled).toBe(true);

    expect(await violations(root)).toEqual([]);
  });

  it("pages with buttons, announces the page and moves the focus to the list heading", async () => {
    const calls = stubApi((url) => (url.includes("page=2") ? ok(pageOf([release(1, "generate")], { total: 26, page: 2 })) : ok(pageOf([release(26, "author")], { total: 26 }))));
    const root = await mount();
    await vi.waitFor(() => expect(root.querySelector("table")).not.toBeNull(), { timeout: 4000 });
    expect(root.querySelector("[data-page-status]")!.textContent).toBe("Page 1 of 2 (releases: 26)");

    root.querySelector<HTMLButtonElement>('[data-pager="older"]')!.click();
    await vi.waitFor(() => expect(root.querySelector("[data-page-status]")!.textContent).toBe("Page 2 of 2 (releases: 26)"), { timeout: 4000 });

    expect(calls[1]).toBe("/studio/api/course-versions/courses/5/releases?page=2&perPage=25");
    expect(document.activeElement).toBe(root.querySelector("h2"));
    await vi.waitFor(() => expect(document.querySelector("[data-announcer]")!.textContent).toBe("Releases updated: page 2 of 2."));
    expect(root.querySelector<HTMLButtonElement>('[data-pager="older"]')!.disabled).toBe(true);
    expect(root.querySelector<HTMLButtonElement>('[data-pager="newer"]')!.disabled).toBe(false);
    expect(await violations(root)).toEqual([]);
  });

  it("says so when the course has not been applied", async () => {
    stubApi(() => ok(pageOf([], { total: 0 })));
    const root = await mount();
    await vi.waitFor(() => expect(root.querySelector('[data-state="empty"]')).not.toBeNull(), { timeout: 4000 });

    expect(root.textContent).toContain("No releases yet: the course has not been applied.");
    expect(root.querySelector("table")).toBeNull();
    expect(await violations(root)).toEqual([]);
  });

  it("explains a course that was not made with the builder", async () => {
    stubApi(() => ({ status: 404, body: { message: "This course was not made with the Course Builder." } }));
    const root = await mount();
    await vi.waitFor(() => expect(root.querySelector('[data-state="not-built"]')).not.toBeNull(), { timeout: 4000 });

    expect(root.textContent).toContain("Adopt the course to track its versions.");
    expect(root.querySelector("button")).toBeNull();
  });

  it("explains another author's course", async () => {
    stubApi(() => ({ status: 403, body: { message: "no" } }));
    const root = await mount();
    await vi.waitFor(() => expect(root.querySelector('[data-state="forbidden"]')).not.toBeNull(), { timeout: 4000 });

    expect(root.querySelector('[role="alert"]')!.textContent).toContain("belongs to another author");
    expect(await violations(root)).toEqual([]);
  });

  it("offers a retry after an error that recovers", async () => {
    let failed = false;
    stubApi(() => {
      if (!failed) {
        failed = true;
        return { status: 500, body: { message: "down" } };
      }
      return ok(full());
    });
    const root = await mount();
    await vi.waitFor(() => expect(root.querySelector('[role="alert"]')).not.toBeNull(), { timeout: 4000 });
    expect(root.querySelector('[role="alert"]')!.textContent).toBe("The releases could not be loaded.");

    root.querySelector<HTMLButtonElement>("button")!.click();
    await vi.waitFor(() => expect(root.querySelector("table")).not.toBeNull(), { timeout: 4000 });
  });

  it.each([
    ["pl", "Wydania, od najnowszego", "Edycja autora"],
    ["zh", "发布版本，最新的在前", "作者编辑"],
  ])("speaks %s", async (locale, heading, trigger) => {
    stubApi(() => ok(pageOf([release(2, "author")])));
    const root = await mount(locale);
    await vi.waitFor(() => expect(root.querySelector("table")).not.toBeNull(), { timeout: 4000 });

    expect(root.querySelector("h2")!.textContent).toBe(heading);
    expect(root.textContent).toContain(trigger);
    expect(await violations(root)).toEqual([]);
  });
});
