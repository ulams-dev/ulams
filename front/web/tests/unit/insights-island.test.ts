// @vitest-environment jsdom
import axe from "axe-core";
import { afterEach, beforeAll, describe, expect, it, vi } from "vitest";

const summary = (over: Record<string, unknown> = {}) => ({
  courseId: 5,
  periodDays: 30,
  minGroupSize: 10,
  enrolled: 48,
  smallGroup: false,
  learners: { enrolled: 48, active: 31, completed: 12, completionRate: 0.25 },
  quiz: { attempts: 57, averageScore: 0.68, passRate: 0.61 },
  statuses: { on_track: 36, struggling: 8, at_risk: 4 },
  nudges: { sent: 9, suppressed: 3, opened: 5, recoveryRate: null },
  remediation: { shown: 22, helpful: 14, notHelpful: 3, recoveryRate: 0.64 },
  ...over,
});

const elements = (list: unknown[] | null = null) => ({
  courseId: 5,
  periodDays: 30,
  minGroupSize: 10,
  elements: list ?? [
    { elementKey: "question:3", label: "2.3 Question 3", hidden: false, activeLearners: 29, struggleRate: 0.41, remediationRate: 0.34, recoveryRate: 0.58, topReasons: [{ rule: "failed_attempts", count: 12 }] },
    { elementKey: "topic:14", label: "4.2 Milk texture", hidden: false, activeLearners: 12, struggleRate: 0, remediationRate: 0, recoveryRate: null, topReasons: [] },
    { elementKey: "topic:15", label: "4.3 Rosetta", hidden: true, reason: "not_enough_learners" },
  ],
});

const learners = [
  { userId: 21, name: "Ada Lovelace", status: "struggling", since: "2026-10-18T10:00:00+00:00", reasons: ["3 failed attempts on 2.3 Question 3"] },
  { userId: 22, name: "Grace Hopper", status: "at_risk", since: "2026-10-12T10:00:00+00:00", reasons: ["9 days without activity in this course"] },
];

interface Stub {
  enabled?: boolean;
  summary?: unknown;
  elements?: unknown;
  summaryStatus?: number;
  failSummaryOnce?: boolean;
  learnersStatus?: number;
  timelineStatus?: number;
}

function stubApi(options: Stub = {}) {
  if (typeof AbortSignal.timeout !== "function") (AbortSignal as unknown as { timeout: () => AbortSignal }).timeout = () => new AbortController().signal;
  const calls: string[] = [];
  let failed = false;
  vi.stubGlobal("fetch", async (url: string) => {
    calls.push(url);
    const ok = (data: unknown) => new Response(JSON.stringify({ success: true, data }), { status: 200 });
    const refuse = (status: number) => new Response(JSON.stringify({ message: status === 403 ? "This action is unauthorized." : "Insights are down." }), { status: status });
    const period = Number(new URL(url, "http://x").searchParams.get("period") ?? 30);
    if (url.endsWith("/me")) return ok({ enabled: options.enabled ?? true });
    if (url.includes("/summary")) {
      if (options.failSummaryOnce && !failed) {
        failed = true;
        return refuse(500);
      }
      return options.summaryStatus ? refuse(options.summaryStatus) : ok({ ...((options.summary ?? summary()) as object), periodDays: period });
    }
    if (url.includes("/elements")) return ok(options.elements ?? elements());
    if (url.includes("/learners?") || /\/courses\/5\/learners$/.test(url)) return options.learnersStatus ? refuse(options.learnersStatus) : ok(learners);
    if (url.includes("/timeline")) {
      return options.timelineStatus
        ? refuse(options.timelineStatus)
        : ok([{ at: "2026-10-20T09:00:00+00:00", type: "quiz.answer", element: "2.3 Question 3", value: 0, data: null, source: "gift" }]);
    }
    return new Response("{}", { status: 404 });
  });
  return calls;
}

async function mount(locale = "en"): Promise<HTMLElement> {
  document.body.innerHTML = `<main><div data-studio-insights data-course="5" data-locale="${locale}" data-admin-url="http://coffee.admin.localhost"></div></main><div data-announcer></div>`;
  vi.resetModules();
  // importing the module mounts the island on [data-studio-insights], like the page does
  await import("../../src/studio/insights.ts");
  return document.querySelector<HTMLElement>("[data-studio-insights]")!;
}

const settle = async (root: HTMLElement, selector = "section") => {
  await vi.waitFor(() => expect(root.querySelector(selector)).not.toBeNull(), { timeout: 4000 });
};

const violations = async (root: HTMLElement) =>
  (await axe.run(root, { rules: { "color-contrast": { enabled: false }, region: { enabled: false } } })).violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.html).join(" | ")}`);

const text = (root: HTMLElement) => root.textContent ?? "";

beforeAll(async () => {
  document.body.innerHTML = "<main></main>";
  await axe.run(document.body, { rules: { "color-contrast": { enabled: false }, region: { enabled: false } } });
  await import("../../src/studio/insights.ts");
}, 60_000);

afterEach(() => vi.unstubAllGlobals());

describe("insights island", () => {
  it("shows the privacy notice, the figures, the confusing sections and the individuals, and passes axe", async () => {
    const calls = stubApi();
    const root = await mount();
    await vi.waitFor(() => expect(root.querySelector("[data-activity]")).not.toBeNull(), { timeout: 4000 });

    expect(text(root)).toContain("Groups smaller than 10 learners are hidden");
    expect(calls.some((c) => c.endsWith("/studio/api/learner-insights/courses/5/summary?period=30"))).toBe(true);
    expect([...root.querySelectorAll("h2")].map((h) => h.textContent)).toEqual([
      "Privacy",
      "Progress and completion",
      "Quiz performance",
      "Who needs support",
      "Sections that confuse people",
      "Personal help and nudges",
      "Learners who are struggling or at risk",
    ]);
    const terms = [...root.querySelectorAll("dt")].map((d) => d.textContent);
    expect(terms).toContain("Completion rate");
    expect(root.querySelector("dd")!.textContent).toBe("48");

    const confusing = root.querySelector('[data-row="question:3"]')!;
    expect(confusing.textContent).toContain("41%");
    expect(confusing.textContent).toContain("Repeated failed attempts (12)");
    expect(root.querySelector('[data-row="topic:14"]')!.textContent).toContain("Not enough data");
    // a withheld section is said to be withheld and carries no figure
    const withheld = root.querySelector('[data-row="topic:15"]')!;
    expect(withheld.textContent).toContain("Hidden: fewer than 10 learners");
    expect(withheld.querySelector(".cb-rate")).toBeNull();
    // every bar is decorative, its number is in the text next to it
    expect([...root.querySelectorAll(".cb-rate-bar")].every((b) => b.getAttribute("aria-hidden") === "true")).toBe(true);
    expect(root.querySelector('[data-row="21"]')!.textContent).toContain("Struggling");

    expect(await violations(root)).toEqual([]);
  });

  it("reloads for another period and announces it", async () => {
    const calls = stubApi();
    const root = await mount();
    await settle(root);
    const select = root.querySelector<HTMLSelectElement>("select")!;
    expect(select.labels![0]!.textContent).toBe("Period");

    select.value = "7";
    select.dispatchEvent(new Event("change"));
    await vi.waitFor(() => expect(calls.some((c) => c.endsWith("/summary?period=7"))).toBe(true));
    await vi.waitFor(() => expect(document.querySelector("[data-announcer]")!.textContent).toBe("Insights updated: Last 7 days."));
    expect(calls.some((c) => c.endsWith("/elements?period=7"))).toBe(true);
  });

  it("explains a group below the minimum and shows no figures", async () => {
    stubApi({ summary: { courseId: 5, periodDays: 30, minGroupSize: 10, enrolled: 6, smallGroup: true } });
    const root = await mount();
    await settle(root, '[data-state="small-group"]');

    expect(text(root)).toContain("Not enough learners yet: 6 enrolled");
    expect(text(root)).toContain("at least 10 learners");
    expect(root.querySelector("dl")).toBeNull();
    expect(root.querySelector("table")).toBeNull();
    expect(await violations(root)).toEqual([]);
  });

  it("says so when Learner Insights is off for the academy, with the link to the settings", async () => {
    const calls = stubApi({ enabled: false });
    const root = await mount();
    await settle(root, '[data-state="module-off"]');

    expect(text(root)).toContain("Learner Insights is off for this academy");
    expect(root.querySelector<HTMLAnchorElement>("a")!.href).toBe("http://coffee.admin.localhost/");
    expect(root.querySelector("table")).toBeNull();
    expect(calls.some((c) => c.includes("/learners"))).toBe(false);
  });

  it("explains a refusal without offering a retry", async () => {
    stubApi({ summaryStatus: 403 });
    const root = await mount();
    await settle(root, '[role="alert"]');

    expect(text(root)).toContain("You can see insights for courses you author");
    expect(root.querySelector("button")).toBeNull();
  });

  it("offers a retry after an error and recovers", async () => {
    stubApi({ failSummaryOnce: true });
    const root = await mount();
    await settle(root, '[role="alert"]');
    expect(text(root)).toContain("The insights could not be loaded.");

    [...root.querySelectorAll("button")].find((b) => b.textContent === "Try again")!.click();
    await settle(root, "section");
    expect(text(root)).toContain("Progress and completion");
  });

  it("says why the list of individuals is hidden when the permission is missing", async () => {
    stubApi({ learnersStatus: 403 });
    const root = await mount();
    await vi.waitFor(() => expect(text(root)).toContain("Individual learners are visible only to people with the Learner Insights individuals permission"), { timeout: 4000 });
    expect(root.querySelector("[data-row='21']")).toBeNull();
    // the aggregates are still there
    expect(text(root)).toContain("Who needs support");
  });

  it("opens one learner's activity, moves focus to it, and reports a refusal", async () => {
    stubApi();
    const root = await mount();
    await vi.waitFor(() => expect(root.querySelector("[data-activity]")).not.toBeNull(), { timeout: 4000 });
    const button = [...root.querySelectorAll<HTMLButtonElement>("button")].find((b) => b.textContent!.startsWith("Show activity"))!;
    expect(button.getAttribute("aria-expanded")).toBe("false");
    expect(button.textContent).toContain("Ada Lovelace");

    button.click();
    await vi.waitFor(() => expect(root.querySelector("[data-activity] table")).not.toBeNull());
    const panel = root.querySelector("[data-activity]")!;
    expect(panel.querySelector("h3")!.textContent).toBe("Activity of Ada Lovelace");
    expect(document.activeElement).toBe(panel.querySelector("h3"));
    expect(panel.textContent).toContain("Answered a question");
    expect(button.getAttribute("aria-expanded")).toBe("true");
    expect(await violations(root)).toEqual([]);
  });

  it("only administrators can open an activity", async () => {
    stubApi({ timelineStatus: 403 });
    const root = await mount();
    await vi.waitFor(() => expect(root.querySelector("[data-activity]")).not.toBeNull(), { timeout: 4000 });
    [...root.querySelectorAll<HTMLButtonElement>("button")].find((b) => b.textContent!.startsWith("Show activity"))!.click();
    await vi.waitFor(() => expect(root.querySelector("[data-activity]")!.textContent).toContain("Only administrators can open the activity of a learner."));
  });

  it("has an empty state for a period without activity", async () => {
    stubApi({ summary: summary({ quiz: { attempts: 0, averageScore: null, passRate: null } }), elements: elements([]) });
    const root = await mount();
    await vi.waitFor(() => expect(text(root)).toContain("No quiz attempts were finished in this period."), { timeout: 4000 });
    expect(text(root)).toContain("No section had learner activity in this period.");
    expect(await violations(root)).toEqual([]);
  });

  it.each([
    ["pl", "Fragmenty, które sprawiają trudność", "Grupy mniejsze niż 10 kursantów są ukryte"],
    ["zh", "让人困惑的部分", "少于 10 名学员的群体会被隐藏"],
  ])("renders in %s", async (locale, heading, privacy) => {
    stubApi();
    const root = await mount(locale);
    await vi.waitFor(() => expect(root.querySelector("[data-activity]")).not.toBeNull(), { timeout: 4000 });
    expect(text(root)).toContain(heading);
    expect(text(root)).toContain(privacy);
    expect(root.querySelector("select")!.labels![0]!.textContent).toBe(locale === "pl" ? "Okres" : "时间范围");
    expect(await violations(root)).toEqual([]);
  });
});
