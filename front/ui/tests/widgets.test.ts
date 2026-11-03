import axe from "axe-core";
import { afterEach, beforeAll, describe, expect, it, vi } from "vitest";
import { setupDom } from "./astro-render.ts";

// the element classes extend HTMLElement: load the module once a jsdom window is in place
setupDom();
const { summaryStanding, widgetHref } = await import("../src/elements/widgets.ts");

const envelope = (data: unknown) => ({ ok: true, status: 200, headers: new Headers(), text: async () => JSON.stringify({ success: true, data, meta: {} }) });
const fetchMock = vi.fn();

const courses = [
  { id: 1, title: "Coffee Atlas", summary: "From seed to cup.", level: "Beginner", duration: "6 h" },
  { id: 2, title: "Night sky", summary: null },
];
const rows = (done: number, total: number, firstOpen = 10) =>
  Array.from({ length: total }, (_, i) => ({ id: i, topic_id: firstOpen - done + i, status: i < done ? 1 : 0, seconds: 0 }));
const progress = [
  { course: { id: 1, title: "Coffee Atlas" }, progress: rows(4, 4) },
  { course: { id: 2, title: "Night sky" }, progress: rows(1, 4, 20) },
  { course: { id: 3, title: "Poland in numbers" }, progress: rows(0, 3, 30) },
];

beforeAll(async () => {
  vi.stubGlobal("fetch", fetchMock);
});
afterEach(() => {
  document.body.innerHTML = "";
  fetchMock.mockReset();
});

async function mount(html: string, data: unknown, status = 200) {
  fetchMock.mockResolvedValue(status === 200 ? envelope(data) : { ok: false, status, json: async () => ({}), text: async () => "{}" });
  document.body.innerHTML = `<main>${html}</main>`;
  const el = document.body.querySelector("main")!.firstElementChild as HTMLElement & { reload(): Promise<void> };
  await vi.waitFor(() => expect(el.shadowRoot?.querySelector("[role=status]")).toBeNull());
  return el;
}
const text = (el: HTMLElement) => el.shadowRoot!.textContent ?? "";
const hrefs = (el: HTMLElement) => [...el.shadowRoot!.querySelectorAll("a")].map((a) => a.getAttribute("href"));

describe("helpers", () => {
  it("fills href templates and refuses script URLs", () => {
    expect(widgetHref("/courses/{course}", { course: 7 })).toBe("/courses/7");
    expect(widgetHref("https://x.test/l/{course}/{topic}", { course: 1, topic: 2 })).toBe("https://x.test/l/1/2");
    expect(widgetHref("javascript:alert({course})", { course: 1 })).toBe("#");
    expect(widgetHref("//evil.test/{course}", { course: 1 })).toBe("#");
  });
  it("finds the completion and the first open topic", () => {
    expect(summaryStanding({ progress: rows(1, 4, 20) as never })).toEqual({ percent: 25, done: 1, total: 4, nextTopic: 20 });
    expect(summaryStanding({ progress: [] })).toEqual({ percent: 0, done: 0, total: 0, nextTopic: undefined });
  });
});

describe("<ulams-catalogue>", () => {
  it("lists the courses with links, asks the API directly with api-url and honours the limit", async () => {
    const el = await mount('<ulams-catalogue api-url="https://acme.test" limit="2"></ulams-catalogue>', courses);
    expect(text(el)).toContain("Coffee Atlas");
    expect(text(el)).toContain("Beginner · 6 h");
    expect(hrefs(el)).toEqual(["/courses/1", "/courses/2"]);
    const url = String(fetchMock.mock.calls[0]![0]);
    expect(url).toBe("https://acme.test/api/courses?per_page=2");
  });
  it("uses the page's own /bff by default and a custom href template", async () => {
    const el = await mount('<ulams-catalogue course-href="/c/{course}"></ulams-catalogue>', courses);
    expect(String(fetchMock.mock.calls[0]![0])).toMatch(/^\/bff\/api\/courses/);
    expect(hrefs(el)).toEqual(["/c/1", "/c/2"]);
  });
  it("says so when there is nothing to list", async () => {
    expect(text(await mount("<ulams-catalogue></ulams-catalogue>", []))).toContain("No courses are published yet.");
  });
  it("never turns API text into markup", async () => {
    const el = await mount("<ulams-catalogue></ulams-catalogue>", [{ id: 1, title: "<img src=x onerror=alert(1)>" }]);
    expect(el.shadowRoot!.querySelector("img")).toBeNull();
    expect(text(el)).toContain("<img src=x");
  });
  it("shows an alert with a retry link when the API fails, and recovers", async () => {
    const el = await mount("<ulams-catalogue></ulams-catalogue>", null, 500);
    expect(el.shadowRoot!.querySelector("[role=alert]")?.textContent).toContain("could not be loaded");
    fetchMock.mockResolvedValue(envelope(courses));
    (el.shadowRoot!.querySelector(".retry") as HTMLElement).click();
    await vi.waitFor(() => expect(text(el)).toContain("Coffee Atlas"));
  });
});

describe("<ulams-my-courses>", () => {
  it("shows progress and a Start, Continue or Review link per course, with the learner token", async () => {
    const el = await mount('<ulams-my-courses api-url="https://acme.test" token="t0k"></ulams-my-courses>', progress);
    const bars = [...el.shadowRoot!.querySelectorAll("[role=progressbar]")].map((b) => b.getAttribute("aria-valuenow"));
    expect(bars).toEqual(["100", "25", "0"]);
    const buttons = [...el.shadowRoot!.querySelectorAll("a.button")].map((a) => [a.textContent, a.getAttribute("href")]);
    expect(buttons).toEqual([
      ["Review", "/courses/1"],
      ["Continue", "/learn/2/20"],
      ["Start", "/learn/3/30"],
    ]);
    const headers = fetchMock.mock.calls[0]![1].headers as Record<string, string>;
    expect(headers.Authorization).toBe("Bearer t0k");
    expect(text(el)).toContain("25% · 1/4 topics");
  });
  it("has accessible names on every bar and link button", async () => {
    const el = await mount("<ulams-my-courses></ulams-my-courses>", progress);
    for (const bar of el.shadowRoot!.querySelectorAll("[role=progressbar]")) expect(bar.getAttribute("aria-label")).toMatch(/^Progress in /);
    expect(el.shadowRoot!.querySelector("a.button")?.getAttribute("aria-label")).toBe("Review: Coffee Atlas");
  });
  it("is empty for a learner without courses", async () => {
    expect(text(await mount("<ulams-my-courses></ulams-my-courses>", []))).toContain("No courses yet.");
  });
});

describe("<ulams-continue>", () => {
  it("picks the course in progress, then the first unfinished one", async () => {
    const el = await mount("<ulams-continue></ulams-continue>", progress);
    expect(text(el)).toContain("Night sky");
    expect(text(el)).toContain("Pick up where you left off");
    expect(hrefs(el)).toEqual(["/learn/2/20"]);
    const fresh = await mount("<ulams-continue></ulams-continue>", [progress[0], progress[2]]);
    expect(text(fresh)).toContain("Poland in numbers");
    expect(text(fresh)).toContain("Start your course");
  });
  it("says when everything is finished", async () => {
    expect(text(await mount("<ulams-continue></ulams-continue>", [progress[0]]))).toContain("finished all your courses");
  });
});

describe("accessibility (axe, structure rules)", () => {
  it.each([
    ["<ulams-catalogue></ulams-catalogue>", courses],
    ["<ulams-my-courses></ulams-my-courses>", progress],
    ["<ulams-continue></ulams-continue>", progress],
  ])("%s has no axe violations", async (html, data) => {
    const el = await mount(html, data);
    const results = await axe.run(el, { rules: { "color-contrast": { enabled: false }, region: { enabled: false } } });
    expect(results.violations.map((v) => `${v.id}: ${v.nodes[0]?.html}`)).toEqual([]);
  });
});
