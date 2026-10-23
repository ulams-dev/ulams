// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from "vitest";

const ID = "01arz3ndektsv4rrffq69g5fav";
const ID2 = "01arz3ndektsv4rrffq69g5fb0";
const F1 = "frg_aaaaaaaaaaaa";

const detail = (over: Record<string, unknown> = {}) => ({
  id: ID,
  name: "Northwind",
  scope: { type: "tenant", id: null },
  status: "suggested",
  sourceId: "s1",
  warnings: ["components.disallowed named Marquee, which is not in the catalogue, and it was dropped"],
  createdBy: 1,
  approvedBy: null,
  approvedAt: null,
  createdAt: "2026-10-22T09:30:00+00:00",
  profile: {
    schemaVersion: 1,
    theme: { preset: "coffee", accent: "#1d6f5c", citations: [F1] },
    voice: { person: "you", formality: 3, citations: [F1] },
    terminology: [{ use: "learner", avoid: ["student"], citations: [F1] }, { use: "lesson", avoid: [], citations: [] }],
    banned: { blocking: false, phrases: [{ phrase: "simply", citations: [F1] }] },
  },
  current: null,
  diff: [
    { path: "voice.person", change: "added", before: null, after: "you", citations: [F1] },
    { path: "terminology.learner", change: "added", before: null, after: ["student"], citations: [F1] },
  ],
  fragments: { [F1]: { label: "§1 Voice and terms", preview: "Write to the learner as you. Use learner, not student." } },
  ...over,
});

interface Call {
  method: string;
  path: string;
  body: unknown;
  form?: FormData;
}

function stubApi(options: { approve?: number; profiles?: unknown[]; detail?: Record<string, unknown> } = {}) {
  const calls: Call[] = [];
  let list = options.profiles ?? [detail()];
  vi.stubGlobal("fetch", async (url: string, init?: RequestInit) => {
    const method = init?.method ?? "GET";
    if (url.startsWith("/studio/api/living-course/")) {
      const body = typeof init?.body === "string" ? JSON.parse(init.body) : {};
      calls.push({ method, path: url, body });
      const wrap = (data: unknown, status = 200) => new Response(JSON.stringify({ success: true, data }), { status });
      if (url.endsWith("/01stylesession000000000002/style-proposals")) return new Response(JSON.stringify({ success: false, code: "nothing_to_restyle", message: "Nothing in this course departs from the style profile." }), { status: 422 });
      return body.confirmEstimate ? wrap({ state: "started", proposal: { id: "01styleproposal000000000001", kind: "style" } }, 201) : wrap({ state: "estimate", elements: 4, findings: 6, skipped: 1, estimateMicroUsd: 120000 });
    }
    const path = url.replace("/studio/api/style-profiles", "") || "/";
    const form = init?.body instanceof FormData ? init.body : undefined;
    calls.push({ method, path, body: typeof init?.body === "string" ? JSON.parse(init.body) : undefined, ...(form ? { form } : {}) });
    const ok = (data: unknown, status = 200) => new Response(JSON.stringify({ success: true, data, message: "OK" }), { status });
    if (method === "GET" && path === "/") return ok(list);
    if (method === "POST" && path === "/") {
      const body = typeof init?.body === "string" ? JSON.parse(init.body) : null;
      const created = detail({ id: ID2, status: "suggested", name: body?.name ?? (form?.get("name") as string | null) ?? "From file", warnings: [] });
      list = [created, ...list];
      return ok(created, 201);
    }
    if (method === "GET" && path === `/${ID}`) return ok(detail(options.detail));
    if (method === "GET" && path === `/${ID2}`) return ok(detail({ id: ID2, name: "From file" }));
    if (method === "PATCH" && path === `/${ID}`) return ok(detail({ name: (JSON.parse(String(init?.body)) as { name: string }).name, warnings: ["voice.formality must be a whole number from 1 to 5 and was dropped"] }));
    if (method === "POST" && path === `/${ID}/approve`) {
      if (options.approve === 403) return new Response(JSON.stringify({ success: false, message: "Only an admin approves a style profile." }), { status: 403 });
      list = [detail({ status: "approved", approvedAt: "2026-10-22T10:00:00+00:00" })];
      return ok(detail({ status: "approved", approvedAt: "2026-10-22T10:00:00+00:00", diff: [] }));
    }
    if (method === "GET" && path.startsWith(`/${ID}/check`)) {
      const course = Number(new URL(`http://x${path}`).searchParams.get("course"));
      if (course === 15) return ok({ profile: { id: ID, name: "Northwind", status: "approved" }, course: { id: 15, title: "Brewing", sessionId: "01stylesession000000000002" }, blocking: [], warnings: [], hints: [], counts: { blocking: 0, warnings: 0, hints: 0 }, blocksPublishing: false });
      if (course === 16) return ok({ profile: { id: ID, name: "Northwind", status: "approved" }, course: { id: 16, title: "Brewing", sessionId: "01stylesession000000000001" }, blocking: [], warnings: [], hints: [], counts: { blocking: 0, warnings: 0, hints: 0 }, blocksPublishing: false });
      if (course === 14) return new Response(JSON.stringify({ success: false, message: "That course was not made with the Course Builder." }), { status: 404 });
      const clean = course === 13;
      return ok({
        profile: { id: ID, name: "Northwind", status: "suggested" }, course: { id: course, title: "Brewing" },
        blocking: clean ? [] : [{ code: "style_banned", rule: "banned", severity: "blocking", message: "“Grinding”: “simply” is a banned phrase of the style guide (style guide §1 Voice and terms)", quote: "…Simply grind…", citations: [{ id: F1, label: "§1 Voice and terms" }] }],
        warnings: [], hints: clean ? [] : [{ code: "style_reading_level", rule: "reading_level", severity: "hint", message: "“Grinding” reads at about grade 11", citations: [] }],
        counts: { blocking: clean ? 0 : 1, warnings: 0, hints: clean ? 0 : 1 }, blocksPublishing: !clean,
      });
    }
    if (method === "DELETE" && path === `/${ID}`) {
      list = [];
      return ok({ id: ID });
    }
    return new Response("{}", { status: 404 });
  });
  return calls;
}

async function mount(locale = "en", adminUrl = "http://coffee.admin.localhost"): Promise<HTMLElement> {
  document.body.innerHTML = `<div data-studio-style data-locale="${locale}" data-admin-url="${adminUrl}"></div><div data-announcer></div>`;
  vi.resetModules();
  const { mountStyle } = await import("../../src/studio/style.ts");
  const root = document.querySelector<HTMLElement>("[data-studio-style]")!;
  mountStyle(root);
  await vi.waitFor(() => expect(root.querySelector("[data-review]"), root.innerHTML).not.toBeNull());
  return root;
}

const button = (root: HTMLElement, text: string | RegExp) => [...root.querySelectorAll("button")].find((b) => (typeof text === "string" ? b.textContent === text : text.test(b.textContent ?? "")))!;

afterEach(() => vi.unstubAllGlobals());

describe("style page island", () => {
  it("lists the profiles and opens one with values, citations, dropped values and the changes", async () => {
    stubApi();
    const root = await mount();
    expect(root.querySelector("table caption")?.textContent).toBe("Style profiles of this academy, newest first");
    expect(root.querySelector("tbody tr")?.textContent).toContain("Suggested, not approved");

    root.querySelector<HTMLButtonElement>("[data-review]")!.click();
    await vi.waitFor(() => expect(root.querySelector("[data-values]")).not.toBeNull());
    expect(document.activeElement?.tagName).toBe("H2");
    expect(root.querySelector("[data-warnings]")?.textContent).toContain("Marquee");
    const change = root.querySelector("[data-change='terminology.learner']")!;
    expect(change.textContent).toContain("Added");
    expect(change.querySelector("[aria-hidden='true']")?.textContent).toBe("+");
    expect(root.querySelector("[data-value='terminology.lesson']")?.textContent).toContain("Your own value, no passage cited");
    expect(root.querySelector("[data-value='voice.person']")?.textContent).toContain("Person: you");
  });

  it("shows the passage behind a citation and toggles it", async () => {
    stubApi();
    const root = await mount();
    root.querySelector<HTMLButtonElement>("[data-review]")!.click();
    await vi.waitFor(() => expect(root.querySelector("[data-values]")).not.toBeNull());
    const cite = root.querySelector<HTMLButtonElement>("[data-value='voice.person'] [data-cite]")!;
    const passage = document.getElementById(cite.getAttribute("aria-controls")!)!;
    expect(passage.hidden).toBe(true);
    cite.click();
    expect(passage.hidden).toBe(false);
    expect(passage.textContent).toContain("Write to the learner as you");
    expect(cite.getAttribute("aria-expanded")).toBe("true");
    expect(cite.getAttribute("aria-label")).toBe("Hide the passage §1 Voice and terms");
    cite.click();
    expect(passage.hidden).toBe(true);
  });

  it("hands the theme to the admin theme screen as a link, applying nothing itself", async () => {
    const calls = stubApi();
    const root = await mount();
    root.querySelector<HTMLButtonElement>("[data-review]")!.click();
    await vi.waitFor(() => expect(root.querySelector("[data-theme]")).not.toBeNull());
    const link = root.querySelector<HTMLAnchorElement>("[data-theme] a")!;
    expect(link.href).toBe(`http://coffee.admin.localhost/configuration/settings/theme_settings?styleProfile=${ID}`);
    expect(calls.filter((c) => c.method !== "GET")).toEqual([]);

    const none = await mount("en", "");
    none.querySelector<HTMLButtonElement>("[data-review]")!.click();
    await vi.waitFor(() => expect(none.querySelector("[data-theme]")).not.toBeNull());
    expect(none.querySelector("[data-theme] a")).toBeNull();
    expect(none.querySelector("[data-theme]")?.textContent).toContain("not known here");
  });

  it("uploads a style guide as multipart, announces it and opens the proposal", async () => {
    const calls = stubApi();
    const root = await mount();
    const input = root.querySelector<HTMLInputElement>("input[type=file]")!;
    const file = new File(["# Guide"], "guide.md", { type: "text/markdown" });
    Object.defineProperty(input, "files", { value: [file], configurable: true });
    root.querySelector<HTMLInputElement>("input[name=name]")!.value = "Northwind v2";
    root.querySelector<HTMLFormElement>("[data-upload]")!.dispatchEvent(new Event("submit", { cancelable: true }));
    await vi.waitFor(() => expect(root.querySelector("[data-detail] h2")?.textContent).toBe("Style profile: Northwind v2"));
    const post = calls.find((c) => c.method === "POST")!;
    expect(post.form?.get("file")).toBeInstanceOf(File);
    expect(post.form?.get("name")).toBe("Northwind v2");
    expect(document.querySelector("[data-announcer]")?.textContent).toBeDefined();
    expect(root.querySelector("[data-status]")?.textContent).toBe("The style guide was read. Review the proposal below.");
  });

  it("asks for a file first", async () => {
    const calls = stubApi();
    const root = await mount();
    root.querySelector<HTMLFormElement>("[data-upload]")!.dispatchEvent(new Event("submit", { cancelable: true }));
    expect(root.querySelector("[data-alert]")?.textContent).toBe("Choose a style guide file first.");
    expect(calls.filter((c) => c.method === "POST")).toEqual([]);
  });

  it("removes a value, saves the voice fields and shows what the API dropped", async () => {
    const calls = stubApi();
    const root = await mount();
    root.querySelector<HTMLButtonElement>("[data-review]")!.click();
    await vi.waitFor(() => expect(root.querySelector("[data-edit]")).not.toBeNull());
    expect(root.querySelector("[data-remove='terminology.lesson']")?.getAttribute("aria-label")).toBe("Remove lesson");
    root.querySelector<HTMLButtonElement>("[data-remove='terminology.lesson']")!.click();
    expect(root.querySelector("[data-value='terminology.lesson']")).toBeNull();

    root.querySelector<HTMLSelectElement>("[data-edit] select")!.value = "we";
    root.querySelector<HTMLInputElement>("[data-edit] input[type=number]")!.value = "9";
    root.querySelector<HTMLFormElement>("[data-edit]")!.dispatchEvent(new Event("submit", { cancelable: true }));
    await vi.waitFor(() => expect(calls.some((c) => c.method === "PATCH")).toBe(true));
    const patch = calls.find((c) => c.method === "PATCH")!.body as { profile: { voice: { person: string; formality: number }; terminology: Array<{ use: string }> } };
    expect(patch.profile.voice).toMatchObject({ person: "we", formality: 9 });
    expect(patch.profile.terminology.map((x) => x.use)).toEqual(["learner"]);
    await vi.waitFor(() => expect(root.querySelector("[data-warnings]")?.textContent).toContain("voice.formality"));
    expect(root.querySelector("[data-status]")?.textContent).toBe("The profile was saved.");
  });

  it("refuses invalid JSON in the advanced editor without calling the API", async () => {
    const calls = stubApi();
    const root = await mount();
    root.querySelector<HTMLButtonElement>("[data-review]")!.click();
    await vi.waitFor(() => expect(root.querySelector("[data-edit]")).not.toBeNull());
    root.querySelector<HTMLDetailsElement>("details")!.open = true;
    root.querySelector<HTMLTextAreaElement>("textarea")!.value = "{ nope";
    root.querySelector<HTMLFormElement>("[data-edit]")!.dispatchEvent(new Event("submit", { cancelable: true }));
    expect(root.querySelector("[data-alert]")?.textContent).toBe("That is not valid JSON.");
    expect(calls.filter((c) => c.method === "PATCH")).toEqual([]);
  });

  it("approves a suggestion and shows it as approved and read-only with a way to revise it", async () => {
    const calls = stubApi();
    const root = await mount();
    root.querySelector<HTMLButtonElement>("[data-review]")!.click();
    await vi.waitFor(() => expect(root.querySelector("[data-approve]")).not.toBeNull());
    root.querySelector<HTMLButtonElement>("[data-approve]")!.click();
    await vi.waitFor(() => expect(root.querySelector("[data-revise]")).not.toBeNull());
    expect(calls.some((c) => c.method === "POST" && c.path === `/${ID}/approve`)).toBe(true);
    expect(root.querySelector("[data-status]")?.textContent).toBe("Approved. This is now the academy's style profile.");
    expect(root.querySelector("[data-edit]")).toBeNull();
    expect(root.querySelector("[data-remove]")).toBeNull();
    expect(root.querySelector("[data-detail]")?.textContent).toContain("create a revision");
  });

  it("says who may approve when the API refuses with 403", async () => {
    stubApi({ approve: 403 });
    const root = await mount();
    root.querySelector<HTMLButtonElement>("[data-review]")!.click();
    await vi.waitFor(() => expect(root.querySelector("[data-approve]")).not.toBeNull());
    root.querySelector<HTMLButtonElement>("[data-approve]")!.click();
    await vi.waitFor(() => expect(root.querySelector("[data-alert]")?.textContent).toContain("Only an admin approves a style profile"));
    expect(root.querySelector<HTMLElement>("[data-alert]")!.hidden).toBe(false);
  });

  it("deletes a suggestion only after the second step", async () => {
    const calls = stubApi();
    const root = await mount();
    root.querySelector<HTMLButtonElement>("[data-review]")!.click();
    await vi.waitFor(() => expect(root.querySelector("[data-delete]")).not.toBeNull());
    root.querySelector<HTMLButtonElement>("[data-delete]")!.click();
    expect(calls.filter((c) => c.method === "DELETE")).toEqual([]);
    expect(document.activeElement?.textContent).toBe("Yes, delete it");
    button(root, "Keep it").click();
    expect(root.querySelector("[data-delete]")).not.toBeNull();
    root.querySelector<HTMLButtonElement>("[data-delete]")!.click();
    root.querySelector<HTMLButtonElement>("[data-delete-confirm]")!.click();
    await vi.waitFor(() => expect(root.querySelector("[data-empty]")).not.toBeNull());
    expect(calls.filter((c) => c.method === "DELETE")).toHaveLength(1);
  });

  it("shows an empty list and an error with retry", async () => {
    stubApi({ profiles: [] });
    document.body.innerHTML = `<div data-studio-style data-locale="pl" data-admin-url=""></div><div data-announcer></div>`;
    vi.resetModules();
    const { mountStyle } = await import("../../src/studio/style.ts");
    const root = document.querySelector<HTMLElement>("[data-studio-style]")!;
    mountStyle(root);
    await vi.waitFor(() => expect(root.querySelector("[data-empty]")).not.toBeNull());
    expect(root.querySelector("[data-empty]")?.textContent).toBe("Nie ma jeszcze profili stylu. Prześlij przewodnik stylu powyżej.");

    vi.stubGlobal("fetch", async () => new Response(JSON.stringify({ success: false, message: "boom" }), { status: 500 }));
    vi.resetModules();
    const again = await import("../../src/studio/style.ts");
    const second = document.createElement("div");
    second.dataset.locale = "en";
    document.body.append(second);
    again.mountStyle(second);
    await vi.waitFor(() => expect(second.querySelector("[data-state='error']")).not.toBeNull());
    expect(second.querySelector("[data-state='error'] [role='alert']")?.textContent).toBe("The style profiles could not be loaded.");
  });

  it("checks a course against the profile and shows blocking items and hints with their passages", async () => {
    const calls = stubApi();
    const root = await mount();
    root.querySelector<HTMLButtonElement>("[data-review]")!.click();
    await vi.waitFor(() => expect(root.querySelector("[data-check]")).not.toBeNull());

    root.querySelector<HTMLFormElement>("[data-check]")!.dispatchEvent(new Event("submit", { cancelable: true }));
    expect(root.querySelector("[data-check-result]")?.textContent).toBe("Enter the id of a course first.");
    expect(calls.some((c) => c.path.includes("/check"))).toBe(false);

    root.querySelector<HTMLInputElement>("[data-check] input")!.value = "12";
    root.querySelector<HTMLFormElement>("[data-check]")!.dispatchEvent(new Event("submit", { cancelable: true }));
    await vi.waitFor(() => expect(root.querySelector("[data-check-summary]")).not.toBeNull());
    expect(calls.some((c) => c.method === "GET" && c.path === `/${ID}/check?course=12`)).toBe(true);
    expect(root.querySelector("[data-check-summary]")?.textContent).toBe("Check finished. Blocking: 1. Warnings: 0. Hints: 1.");
    expect(root.querySelector("[data-blocks]")?.textContent).toContain("cannot be published");
    expect(root.querySelector("[data-findings='blocking']")?.textContent).toContain("Blocks publishing: 1");
    expect(root.querySelector("[data-findings='blocking'] [data-code='style_banned']")?.textContent).toContain("“simply” is a banned phrase");
    expect(root.querySelector("[data-findings='warnings']")).toBeNull();
    expect(root.querySelector("[data-findings='hints']")?.textContent).toContain("Hints (approximate, never blocking): 1");
    const cite = root.querySelector<HTMLButtonElement>("[data-findings='blocking'] [data-cite]")!;
    cite.click();
    expect(document.getElementById(cite.getAttribute("aria-controls")!)?.textContent).toContain("Write to the learner as you");
    expect(calls.filter((c) => c.method !== "GET")).toEqual([]);

    root.querySelector<HTMLInputElement>("[data-check] input")!.value = "13";
    root.querySelector<HTMLFormElement>("[data-check]")!.dispatchEvent(new Event("submit", { cancelable: true }));
    await vi.waitFor(() => expect(root.querySelector("[data-check-result]")?.textContent).toContain("Nothing to report"));
    expect(root.querySelector("[data-blocks]")).toBeNull();

    root.querySelector<HTMLInputElement>("[data-check] input")!.value = "14";
    root.querySelector<HTMLFormElement>("[data-check]")!.dispatchEvent(new Event("submit", { cancelable: true }));
    await vi.waitFor(() => expect(root.querySelector("[data-check-result] [role='alert']")?.textContent).toBe("That course was not found, or it was not made with the Course Builder."));
  });

  it("offers a restyle only for an approved profile: preview, cost, then the proposal on a second click", async () => {
    const calls = stubApi({ detail: { status: "approved", approvedAt: "2026-10-22T10:00:00+00:00", diff: [] } });
    const root = await mount();
    root.querySelector<HTMLButtonElement>("[data-review]")!.click();
    await vi.waitFor(() => expect(root.querySelector("[data-restyle]")).not.toBeNull());
    expect(root.querySelector("[data-restyle]")?.textContent).toContain("never automatic");

    root.querySelector<HTMLInputElement>("[data-restyle] input")!.value = "16";
    root.querySelector<HTMLFormElement>("[data-restyle]")!.dispatchEvent(new Event("submit", { cancelable: true }));
    await vi.waitFor(() => expect(root.querySelector("[data-restyle-preview]")).not.toBeNull());
    expect(root.querySelector("[data-restyle-preview]")?.textContent).toBe("A restyle would rewrite 4 elements to fix 6 findings. Estimated cost: $0.12.");
    expect(root.querySelector("[data-restyle-result]")?.textContent).toContain("1 flagged elements are left for you to edit by hand");
    // nothing was created by the preview
    expect(calls.filter((c) => c.path.includes("style-proposals") && (c.body as { confirmEstimate?: boolean })?.confirmEstimate)).toEqual([]);

    root.querySelector<HTMLButtonElement>("[data-restyle-create]")!.click();
    await vi.waitFor(() => expect(root.querySelector("[data-restyle-done]")).not.toBeNull());
    expect(calls.some((c) => c.path === "/studio/api/living-course/sessions/01stylesession000000000001/style-proposals" && (c.body as { confirmEstimate?: boolean }).confirmEstimate === true)).toBe(true);
    expect(root.querySelector<HTMLAnchorElement>("[data-restyle-link]")?.getAttribute("href")).toBe("/studio/s/01stylesession000000000001/updates/01styleproposal000000000001?lang=en");

    root.querySelector<HTMLInputElement>("[data-restyle] input")!.value = "15";
    root.querySelector<HTMLFormElement>("[data-restyle]")!.dispatchEvent(new Event("submit", { cancelable: true }));
    await vi.waitFor(() => expect(root.querySelector("[data-restyle-result] [role='alert']")?.textContent).toBe("Nothing in this course departs from the style profile."));
  });

  it("does not offer a restyle for a suggestion", async () => {
    stubApi();
    const root = await mount();
    root.querySelector<HTMLButtonElement>("[data-review]")!.click();
    await vi.waitFor(() => expect(root.querySelector("[data-check]")).not.toBeNull());
    expect(root.querySelector("[data-restyle]")).toBeNull();
  });
});
