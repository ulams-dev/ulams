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

  describe("comparing", () => {
    const versionsOf = (rows: Array<Record<string, unknown>>, variants: unknown[] = []) => ({
      sessionId: "01s0000000000000000000abcd", variantId: null, courseId: 5, baseCourseId: 5, versions: rows, variants, total: rows.length, page: 1, perPage: 200,
    });
    const version = (number: number, over: Record<string, unknown> = {}) => ({
      id: `01v${String(number).padStart(23, "0")}`, number, kind: "author", origin: "author", status: "approved", reason: null, variantId: null, releaseId: null, releaseNumber: null,
      createdAt: "2026-10-20T09:00:00+00:00", decidedAt: null, ...over,
    });

    it("links every release but the first to a comparison with the previous release", async () => {
      stubApi(() => ok(pageOf([release(2, "chat", { previousReleaseId: release(1, "generate").id }), release(1, "generate")])));
      const root = await mount();
      await vi.waitFor(() => expect(root.querySelector("table")).not.toBeNull(), { timeout: 4000 });

      const links = [...root.querySelectorAll<HTMLAnchorElement>("a.st-ver-link")].filter((a) => a.href.includes("from="));
      expect(links).toHaveLength(1);
      expect(links[0]!.getAttribute("href")).toBe(`/studio/courses/5/compare?from=${release(1, "generate").id}&to=${release(2, "chat").id}&lang=en`);
      expect(links[0]!.getAttribute("aria-label")).toBe("Compare release 2 with the previous release");
      expect(root.querySelectorAll("tbody tr")[1]!.textContent).toContain("First release");
      expect(root.querySelector('a[href="/studio/courses/5/compare?lang=en"]')!.textContent).toBe("Open the compare screen");
      expect(await violations(root)).toEqual([]);
    });

    it("ticks two versions, refuses a third, and sends them to the compare page older first", async () => {
      stubApi(() => ok(pageOf([release(3, "chat"), release(2, "restore"), release(1, "generate")])));
      const root = await mount();
      await vi.waitFor(() => expect(root.querySelector("table")).not.toBeNull(), { timeout: 4000 });
      const boxes = () => [...root.querySelectorAll<HTMLInputElement>("input[data-pick]")];
      const go = root.querySelector<HTMLButtonElement>("[data-compare-selected]")!;
      expect(go.disabled).toBe(true);
      expect(root.querySelector("[data-pick-count]")!.textContent).toBe("Selected for comparison: 0 of 2");
      expect(boxes()[0]!.getAttribute("aria-label")).toBe("Select release 3 to compare");

      boxes()[0]!.click(); // release 3, version 4
      boxes()[2]!.click(); // release 1, version 2
      expect(go.disabled).toBe(false);
      expect(boxes()[1]!.disabled).toBe(true);
      expect(root.querySelector("[data-pick-count]")!.textContent).toContain("Two are selected. Clear one to choose another.");
      expect(await violations(root)).toEqual([]);

      // a plain GET form to the compare page: older version as `from`, newer as `to`, whichever was ticked first
      const form = root.querySelector<HTMLFormElement>("[data-compare-form]")!;
      expect(form.getAttribute("action")).toBe("/studio/courses/5/compare");
      expect(form.method).toBe("get");
      expect(Object.fromEntries(new FormData(form).entries())).toEqual({ from: release(1, "generate").versionId, to: release(3, "chat").versionId, lang: "en" });

      root.querySelector<HTMLButtonElement>("[data-clear-picks]")!.click();
      expect(boxes().every((b) => !b.checked && !b.disabled)).toBe(true);
      expect(go.disabled).toBe(true);
    });

    it("shows every version on request, with the state, the release and the branch of variants, and keeps the selection in step", async () => {
      const all = versionsOf(
        [version(7, { status: "proposed" }), version(6, { releaseId: release(3, "chat").id, releaseNumber: 3 }), version(5, { status: "rejected" }), version(4, { variantId: "01w00000000000000000000009", status: "approved" })],
        [{ id: "01w00000000000000000000009", key: "expert", label: "Expert", level: "expert", role: null, status: "approved", courseId: 9 }]
      );
      const calls = stubApi((url) => (url.includes("/versions") ? ok(all) : ok(pageOf([release(3, "chat")]))));
      const root = await mount();
      await vi.waitFor(() => expect(root.querySelector("table")).not.toBeNull(), { timeout: 4000 });
      expect(root.querySelector("[data-all-versions]")).toBeNull();

      root.querySelector<HTMLInputElement>("[data-show-all]")!.click();
      await vi.waitFor(() => expect(root.querySelector("[data-all-versions] table")).not.toBeNull(), { timeout: 4000 });

      expect(calls.at(-1)).toBe("/studio/api/course-versions/courses/5/versions?branch=all&perPage=200");
      const rows = [...root.querySelectorAll("[data-all-versions] tbody tr")];
      expect(rows).toHaveLength(4);
      expect(rows[0]!.textContent).toContain("Proposed, not applied");
      expect(rows[0]!.textContent).toContain("Never released");
      expect(rows[1]!.textContent).toContain("Release 3");
      expect(rows[2]!.textContent).toContain("Rejected");
      expect(rows[3]!.textContent).toContain("Expert");
      expect(rows[0]!.textContent).toContain("Base course");
      expect(document.activeElement).toBe(root.querySelector("[data-show-all]"));
      expect(await violations(root)).toEqual([]);

      // version 6 is in both tables: ticking it in one ticks it in the other
      root.querySelector<HTMLInputElement>(`[data-all-versions] input[data-pick="${version(6).id}"]`)!.click();
      expect(root.querySelector<HTMLInputElement>(`input[data-pick="${release(3, "chat").versionId}"]`)).not.toBeNull();
    });

    it("says so when the full list cannot be loaded and keeps the releases", async () => {
      stubApi((url) => (url.includes("/versions") ? { status: 500, body: { message: "down" } } : ok(pageOf([release(1, "generate")]))));
      const root = await mount();
      await vi.waitFor(() => expect(root.querySelector("table")).not.toBeNull(), { timeout: 4000 });

      root.querySelector<HTMLInputElement>("[data-show-all]")!.click();
      await vi.waitFor(() => expect(root.querySelector('[data-state="all-error"]')).not.toBeNull(), { timeout: 4000 });

      expect(root.querySelector('[data-state="all-error"] [role="alert"]')!.textContent).toBe("All versions could not be loaded.");
      expect(root.querySelector("tbody tr")).not.toBeNull();
    });
  });
});
