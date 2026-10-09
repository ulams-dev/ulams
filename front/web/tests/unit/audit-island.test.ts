// @vitest-environment jsdom
import axe from "axe-core";
import { afterEach, describe, expect, it, vi } from "vitest";

const S = "01m4fj5zsgfxhzh3cantwevncw";

const entry = (id: number, over: Record<string, unknown> = {}) => ({
  id,
  at: "2026-10-20T10:15:00+00:00",
  action: id % 2 ? "item.accepted" : "proposal.applied",
  actor: { type: "user", id: 3, name: "Ada Lovelace", onBehalfOf: null },
  subject: { type: "proposal", id: "01p" },
  sourceId: "01src",
  revisionId: "01rev2",
  originRef: "upload",
  versionFrom: "v3",
  versionTo: "v4",
  aiCallIds: [41],
  data: { reason: `Reason ${id}` },
  hash: `hash${id}`,
  prevHash: `hash${id - 1}`,
  ...over,
});

interface Stub {
  total?: number;
  verdict?: Record<string, unknown>;
  failList?: boolean;
}

function stubApi(options: Stub = {}) {
  if (typeof AbortSignal.timeout !== "function") (AbortSignal as unknown as { timeout: () => AbortSignal }).timeout = () => new AbortController().signal;
  const calls: string[] = [];
  const total = options.total ?? 30;
  vi.stubGlobal("fetch", async (url: string) => {
    calls.push(url);
    const ok = (data: unknown) => new Response(JSON.stringify({ success: true, data }), { status: 200 });
    if (url.includes("/audit/verify")) return ok(options.verdict ?? { ok: true, checked: total, brokenId: null, reason: null });
    if (url.includes("/audit?") || url.endsWith("/audit")) {
      if (options.failList) return new Response(JSON.stringify({ message: "Audit is down." }), { status: 500 });
      const query = new URL(url, "http://x").searchParams;
      const page = Number(query.get("page") ?? 1);
      const perPage = Number(query.get("perPage") ?? 25);
      const count = Math.max(0, Math.min(perPage, total - (page - 1) * perPage));
      return ok({ entries: Array.from({ length: count }, (_, i) => entry(total - (page - 1) * perPage - i)), total, page, perPage });
    }
    return new Response("{}", { status: 404 });
  });
  return calls;
}

async function mount(): Promise<HTMLElement> {
  document.body.innerHTML = `<main data-studio-audit data-session="${S}"></main><div data-announcer></div>`;
  vi.resetModules();
  const { mountAudit } = await import("../../src/studio/audit.ts");
  const root = document.querySelector<HTMLElement>("[data-studio-audit]")!;
  mountAudit(root);
  return root;
}

const violations = async (root: HTMLElement) =>
  (await axe.run(root, { rules: { "color-contrast": { enabled: false }, region: { enabled: false } } })).violations.map(
    (v) => `${v.id}: ${v.nodes.map((n) => n.html).join(" | ")}`
  );

const button = (root: HTMLElement, text: string) => [...root.querySelectorAll("button")].find((b) => b.textContent?.startsWith(text))!;

afterEach(() => vi.unstubAllGlobals());

describe("audit trail island", () => {
  it("shows the chain as verified, the first page, the range and the export links", async () => {
    const calls = stubApi();
    const root = await mount();
    await vi.waitFor(() => expect(root.querySelectorAll("tr.cb-audit-row")).toHaveLength(25));
    await vi.waitFor(() => expect(root.querySelector("[data-chain]")?.textContent).toContain("Chain verified: 30 entries checked"));
    expect(root.querySelector("[data-range]")?.textContent).toBe("Showing 1 to 25 of 30 entries. Page 1 of 2.");
    expect(root.querySelector("tr.cb-audit-row")?.textContent).toContain("Ada Lovelace");
    expect(root.querySelector<HTMLAnchorElement>("[data-export=csv]")?.getAttribute("href")).toBe(`/studio/api/living-course/sessions/${S}/audit/export?format=csv`);
    expect(root.querySelector<HTMLAnchorElement>("[data-export=json]")?.getAttribute("href")).toBe(`/studio/api/living-course/sessions/${S}/audit/export?format=json`);
    expect(calls.some((c) => c.includes(`/sessions/${S}/audit?page=1&perPage=25`))).toBe(true);
    expect(await violations(root)).toEqual([]);
  });

  it("pages forward and back, disabling the buttons at the ends and announcing the page", async () => {
    stubApi();
    const root = await mount();
    await vi.waitFor(() => expect(root.querySelectorAll("tr.cb-audit-row")).toHaveLength(25));
    const next = button(root, "Next page");
    const previous = button(root, "Previous page");
    expect(previous.disabled).toBe(true);
    expect(next.disabled).toBe(false);
    next.click();
    await vi.waitFor(() => expect(root.querySelectorAll("tr.cb-audit-row")).toHaveLength(5));
    expect(root.querySelector("[data-range]")?.textContent).toBe("Showing 26 to 30 of 30 entries. Page 2 of 2.");
    expect(next.disabled).toBe(true);
    expect(previous.disabled).toBe(false);
    await vi.waitFor(() => expect(document.querySelector("[data-announcer]")?.textContent).toContain("Page 2"));
    previous.click();
    await vi.waitFor(() => expect(root.querySelectorAll("tr.cb-audit-row")).toHaveLength(25));
  });

  it("filters by action group, who and dates, and carries them to the exports", async () => {
    const calls = stubApi();
    const root = await mount();
    await vi.waitFor(() => expect(root.querySelectorAll("tr.cb-audit-row")).toHaveLength(25));
    const set = (name: string, value: string) => {
      const el = root.querySelector<HTMLInputElement | HTMLSelectElement>(`[name=${name}]`)!;
      el.value = value;
      el.dispatchEvent(new Event("change"));
    };
    set("group", "proposal.");
    set("actorType", "user");
    set("from", "2026-10-01");
    set("to", "2026-10-31");
    root.querySelector("form")!.dispatchEvent(new Event("submit", { cancelable: true }));
    await vi.waitFor(() => expect(calls.some((c) => c.includes("action=proposal.") && c.includes("actorType=user") && c.includes("page=1"))).toBe(true));
    const last = calls.filter((c) => c.includes("/audit?")).at(-1)!;
    expect(decodeURIComponent(last.replace(/\+/g, " "))).toContain("from=2026-10-01 00:00:00");
    expect(decodeURIComponent(last.replace(/\+/g, " "))).toContain("to=2026-10-31 23:59:59");
    const csv = root.querySelector<HTMLAnchorElement>("[data-export=csv]")!.getAttribute("href")!;
    expect(csv).toContain("format=csv");
    expect(csv).toContain("action=proposal.");
    expect(csv).toContain("actorType=user");
    expect(csv).not.toContain("page=");
  });

  it("refuses an end date before the start date without calling the API", async () => {
    const calls = stubApi();
    const root = await mount();
    await vi.waitFor(() => expect(root.querySelectorAll("tr.cb-audit-row")).toHaveLength(25));
    const before = calls.length;
    const from = root.querySelector<HTMLInputElement>("[name=from]")!;
    const to = root.querySelector<HTMLInputElement>("[name=to]")!;
    from.value = "2026-10-09";
    from.dispatchEvent(new Event("change"));
    to.value = "2026-10-01";
    to.dispatchEvent(new Event("change"));
    root.querySelector("form")!.dispatchEvent(new Event("submit", { cancelable: true }));
    expect(root.querySelector("[data-form-error]")?.textContent).toBe("The end date is before the start date.");
    expect(calls.length).toBe(before);
  });

  it("opens the full record of an entry", async () => {
    stubApi();
    const root = await mount();
    await vi.waitFor(() => expect(root.querySelectorAll("tr.cb-audit-row")).toHaveLength(25));
    const toggle = root.querySelector<HTMLButtonElement>("tr[data-entry='30'] button")!;
    toggle.click();
    const detail = root.querySelector<HTMLElement>(`#${toggle.getAttribute("aria-controls")}`)!;
    expect(detail.hidden).toBe(false);
    expect(detail.textContent).toContain("hash30");
    expect(detail.textContent).toContain("01rev2");
    expect(detail.textContent).toContain("v3 to v4");
    expect(await violations(root)).toEqual([]);
  });

  it("names the first broken entry when the chain does not verify", async () => {
    stubApi({ verdict: { ok: false, checked: 9, brokenId: 9, reason: "hash does not match the row" } });
    const root = await mount();
    await vi.waitFor(() => expect(root.querySelector("[data-chain]")?.textContent).toContain("Chain broken at entry 9: hash does not match the row."));
    expect(root.querySelector("[data-chain]")?.getAttribute("role")).toBe("alert");
    expect(root.querySelector<HTMLElement>("[data-chain]")?.dataset.state).toBe("broken");
  });

  it("shows the empty states, with and without filters", async () => {
    stubApi({ total: 0 });
    const root = await mount();
    await vi.waitFor(() => expect(root.querySelector(".st-empty")?.textContent).toContain("Nothing has been recorded for this course yet."));
    expect(root.querySelector(".st-pager")?.hasAttribute("hidden")).toBe(true);
    const group = root.querySelector<HTMLSelectElement>("[name=group]")!;
    group.value = "notice.";
    group.dispatchEvent(new Event("change"));
    root.querySelector("form")!.dispatchEvent(new Event("submit", { cancelable: true }));
    await vi.waitFor(() => expect(root.querySelector(".st-empty")?.textContent).toContain("No entries match these filters."));
    expect(await violations(root)).toEqual([]);
  });

  it("shows the API message and a retry when the trail cannot be loaded", async () => {
    stubApi({ failList: true });
    const root = await mount();
    await vi.waitFor(() => expect(root.querySelector("[data-table] [role=alert]")?.textContent).toContain("Audit is down."));
    expect(button(root, "Try again")).toBeDefined();
    expect(await violations(root)).toEqual([]);
  });
});
