// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from "vitest";
import axe from "axe-core";
import type { ProposalDetail, ProposalItem, ProposalSummary } from "@ulams/sdk";
import { detail, item, question } from "./updates-fixtures.ts";

const S = "01m4fj5zsgfxhzh3cantwevncw";
const P = "01m4fj5zsgfxhzh3cantwevnaa";

const { mountReview } = await import("../../src/studio/updates-review.ts");

/** The page waits on timers and a fake API; give a busy machine room. */
const wait = <T>(assertion: () => T): Promise<T> => vi.waitFor(assertion, { timeout: 5000, interval: 20 });

const counts = { changed: 2, moved: 0, removed: 0, added: 0, trivial: 1, minor: 1, substantive: 1, total: 3 };
const rev = (n: number) => ({ id: `r${n}`, sourceId: "src", number: n, origin: "upload", originRef: null, trigger: "manual", triggeredBy: 1, status: "ingested", fragmentCount: 3, tokens: 9, title: null, name: "x.md", files: null, counts, error: null, detectedAt: null, synced: n === 1, latest: n === 2 });
const frag = (text: string) => ({ fragmentId: "frg_aaaaaaaaaaa1", label: "§3.2 Ratios", section: "3.2", headingPath: ["Ratios"], file: null, text, pages: null });
const change = (id: number, magnitude: string) => ({ id, kind: "changed", magnitude, similarity: 0.9, signals: [], wordDiff: [["=", "Use "], ["-", "15"], ["+", "16"]], old: frag("Use 15"), new: frag("Use 16") });

interface Server {
  detail: ProposalDetail;
  calls: Array<{ method: string; path: string; body: unknown }>;
  getCount: number;
  /** Called for POST apply; return a status and body. */
  apply: (body: { overwrite?: boolean }) => { status: number; body: unknown };
  analyse: (body: { confirmEstimate?: boolean }) => { status: number; body: unknown };
  aiEnabled: boolean;
  /** Called on every GET of the proposal, to move a running state forward. */
  onGet?: (s: Server) => void;
  /** Serve an open event stream (otherwise the endpoint answers 404 and the page polls). */
  sse?: boolean;
  events?: ReadableStreamDefaultController<Uint8Array>;
}

const summary = (d: ProposalDetail): ProposalSummary => {
  const decisions: Record<string, number> = {};
  for (const i of d.items) decisions[i.status] = (decisions[i.status] ?? 0) + 1;
  const { groups: _g, items: _i, steps: _s, ...rest } = d;
  return { ...rest, decisions };
};

function stub(initial: ProposalDetail, over: Partial<Server> = {}): Server {
  if (typeof AbortSignal.timeout !== "function") (AbortSignal as unknown as { timeout: () => AbortSignal }).timeout = () => new AbortController().signal;
  const server: Server = { detail: initial, calls: [], getCount: 0, aiEnabled: true, apply: () => ({ status: 202, body: { data: { runId: "run2", proposal: summary(initial) } } }), analyse: () => ({ status: 202, body: { data: { state: "started", estimateMicroUsd: 420000, runId: "run1", message: null } } }), ...over };
  const setItem = (id: string, patch: Partial<ProposalItem>): ProposalItem => {
    const next = server.detail.items.map((i) => (i.id === id ? { ...i, ...patch } : i));
    server.detail = { ...server.detail, items: next, groups: server.detail.groups.map((g) => ({ ...g, items: g.items.map((i) => next.find((n) => n.id === i.id)!) })) };
    return next.find((i) => i.id === id)!;
  };
  vi.stubGlobal("fetch", async (url: string, init?: RequestInit) => {
    const method = init?.method ?? "GET";
    const path = url.replace(/^.*\/studio\/api/, "").replace("/living-course", "");
    const body = typeof init?.body === "string" ? JSON.parse(init.body) : undefined;
    server.calls.push({ method, path, body });
    const json = (data: unknown, status = 200) => new Response(JSON.stringify({ success: true, data }), { status });
    if (path === `/sessions/${S}` && method === "GET") return json({ session: { id: S, status: "applied" }, aiEnabled: server.aiEnabled });
    if (path.endsWith("/events")) {
      if (!server.sse) return new Response("{}", { status: 404 });
      return new Response(new ReadableStream<Uint8Array>({ start: (c) => (server.events = c) }), { status: 200, headers: { "Content-Type": "text/event-stream" } });
    }
    if (path === `/proposals/${P}` && method === "GET") {
      server.getCount += 1;
      server.onGet?.(server);
      return json(server.detail);
    }
    const decide = /^\/proposals\/[^/]+\/items\/([^/]+)\/(accept|reject|reset)$/.exec(path);
    if (decide) {
      const status = { accept: "accepted", reject: "rejected", reset: "pending" }[decide[2]!] as ProposalItem["status"];
      const row = setItem(decide[1]!, { status });
      return json({ item: row, proposal: summary(server.detail) });
    }
    const regen = /^\/proposals\/[^/]+\/items\/([^/]+)\/regenerate$/.exec(path);
    if (regen) {
      const current = server.detail.items.find((i) => i.id === regen[1])!;
      const row = setItem(regen[1]!, { regenerations: current.regenerations + 1, reason: "A new version.", status: "pending" });
      return json({ item: row, proposal: summary(server.detail) });
    }
    if (path.endsWith("/accept-all")) {
      let n = 0;
      for (const i of server.detail.items) {
        if (i.status !== "pending" || !["update", "no_change", "remove"].includes(i.kind)) continue;
        setItem(i.id, { status: "accepted" });
        n++;
      }
      return json({ accepted: n, proposal: summary(server.detail) });
    }
    if (path.endsWith("/reject") && path.startsWith("/proposals")) {
      server.detail = { ...server.detail, status: "rejected" };
      return json(summary(server.detail));
    }
    if (path.endsWith("/apply")) {
      const result = server.apply(body ?? {});
      return new Response(JSON.stringify(result.body), { status: result.status });
    }
    if (path.endsWith("/analyse")) {
      const result = server.analyse(body ?? {});
      return new Response(JSON.stringify(result.body), { status: result.status });
    }
    if (/^\/runs\/[^/]+\/steps\/[^/]+\/retry$/.test(path)) return json({ stepId: "s2" });
    if (path.startsWith("/revisions/r2/changes")) return json({ from: rev(1), to: rev(2), counts, changes: [change(1, "minor"), change(2, "trivial")] });
    return new Response("{}", { status: 404 });
  });
  return server;
}

function mount(pollMs = 20): HTMLElement {
  document.body.innerHTML = `<main><div data-studio-review data-session="${S}" data-proposal="${P}"></div></main><div data-announcer></div>`;
  const root = document.querySelector<HTMLElement>("[data-studio-review]")!;
  mountReview(root, { pollMs });
  return root;
}

const button = (root: ParentNode, text: string): HTMLButtonElement => {
  const found = [...root.querySelectorAll("button")].find((b) => b.textContent?.includes(text));
  if (!found) throw new Error(`no button "${text}" in ${(root as HTMLElement).innerHTML?.slice(0, 600)}`);
  return found;
};
const ready = (root: HTMLElement) => wait(() => expect(root.querySelector("[data-items] article"), root.innerHTML.slice(0, 500)).not.toBeNull());
const item3 = () => [item(), question(), item({ id: "i3", kind: "citation_remap", status: "accepted", elementId: "b2", label: "Lesson 1.1 › Paragraph 2" })];

afterEach(() => vi.unstubAllGlobals());

describe("update review: ready", () => {
  it("shows the summary, the source changes and the items per lesson", async () => {
    stub(detail(item3(), { counts: { items: 3, elements: 2, remaps: 1, uncovered: 0, major: 1, answerChecks: 1, groups: 1, source: counts } }));
    const root = mount();
    await ready(root);
    expect(root.querySelector("[data-header]")?.textContent).toContain("Source update: revision 1 → 2");
    expect(root.querySelector("[data-header]")?.textContent).toContain("2 changed");
    expect(root.querySelector('[data-component="ImpactSummary"]')?.textContent).toContain("2 elements may need an update across 1 lesson");
    expect(root.querySelector("[data-group] h3")?.textContent).toBe("Lesson 1.1: Ratios");
    expect(root.querySelectorAll("[data-update-item]")).toHaveLength(3);
    await wait(() => expect(root.querySelectorAll("[data-source-changes] article")).toHaveLength(1));
    expect(root.querySelector("[data-source-changes] del")?.textContent).toContain("removed:");
    expect(button(root, "Show 1 cosmetic change")).toBeTruthy();
    expect(root.querySelector("[data-apply-summary]")?.textContent).toBe("1 accepted · 2 undecided · 0 rejected");
    expect(button(root, "Apply 1 accepted change").disabled).toBe(false);
    expect(root.querySelector("[data-extension='learner-impact']")).not.toBeNull();
  });

  it("is accessible", async () => {
    stub(detail(item3()));
    const root = mount();
    await ready(root);
    const result = await axe.run(root, { rules: { "color-contrast": { enabled: false }, region: { enabled: false } } });
    expect(result.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.html.slice(0, 120)).join(" | ")}`)).toEqual([]);
  });

  it("saves a decision, updates the apply bar and moves focus to the next undecided item", async () => {
    const s = stub(detail(item3()));
    const root = mount();
    await ready(root);
    const first = root.querySelector<HTMLElement>('[data-update-item="i1"]')!;
    button(first, "Accept").click();
    await wait(() => expect(root.querySelector("[data-apply-summary]")?.textContent).toBe("2 accepted · 1 undecided · 0 rejected"));
    expect(s.calls.some((c) => c.method === "POST" && c.path === `/proposals/${P}/items/i1/accept`)).toBe(true);
    expect(document.activeElement).toBe(root.querySelector('[data-update-item="i2"] [data-focus-target]'));
    expect(button(root, "Apply 2 accepted changes")).toBeTruthy();
    expect(root.querySelector("[data-group-progress]")?.textContent).toBe("1 of 2 decided");
    expect(first.getAttribute("data-status")).toBe("accepted");
  });

  it("puts the item back when saving a decision fails", async () => {
    const s = stub(detail(item3()));
    await Promise.resolve();
    const original = globalThis.fetch;
    vi.stubGlobal("fetch", async (url: string, init?: RequestInit) =>
      /items\/i1\/reject/.test(url) ? new Response(JSON.stringify({ message: "This proposal is already settled." }), { status: 409 }) : original(url, init));
    const root = mount();
    await ready(root);
    button(root.querySelector<HTMLElement>('[data-update-item="i1"]')!, "Reject").click();
    await wait(() => expect(root.querySelector("[data-notices] [role='alert']")?.textContent).toContain("already settled"));
    expect(root.querySelector('[data-update-item="i1"]')?.getAttribute("data-status")).toBe("pending");
    expect(s.detail.items[0]!.status).toBe("pending");
  });

  it("asks for changes with a comment and shows the regenerated item", async () => {
    const s = stub(detail(item3()));
    const root = mount();
    await ready(root);
    const first = root.querySelector<HTMLElement>('[data-update-item="i1"]')!;
    button(first, "Ask for changes").click();
    const area = first.querySelector("textarea")!;
    area.value = "Keep the metric units";
    first.querySelector("form")!.dispatchEvent(new Event("submit", { cancelable: true }));
    await wait(() => expect(root.querySelector('[data-update-item="i1"]')?.textContent).toContain("A new version."));
    expect(s.calls.find((c) => c.path.endsWith("/items/i1/regenerate"))?.body).toEqual({ comment: "Keep the metric units" });
    expect(root.querySelector('[data-update-item="i1"]')?.textContent).toContain("Asked for changes 1 of 3 times");
  });

  it("accept all accepts every undecided item and reloads", async () => {
    const s = stub(detail(item3()));
    const root = mount();
    await ready(root);
    button(root, "Accept all").click();
    await wait(() => expect(root.querySelector("[data-apply-summary]")?.textContent).toBe("3 accepted · 0 undecided · 0 rejected"));
    expect(s.calls.some((c) => c.path.endsWith("/accept-all"))).toBe(true);
    expect(button(root, "Apply 3 accepted changes")).toBeTruthy();
  });

  it("reject all explains the consequence first, then rejects and shows the kept state", async () => {
    const s = stub(detail(item3()));
    const root = mount();
    await ready(root);
    button(root, "Reject all").click();
    const panel = root.querySelector("[data-confirm]")!;
    expect(panel.textContent).toContain("marks source revision 2 as reviewed");
    expect(s.calls.some((c) => c.path === `/proposals/${P}/reject`)).toBe(false);
    button(panel, "Cancel").click();
    expect(root.querySelector("[data-confirm]")).toBeNull();
    button(root, "Reject all").click();
    button(root.querySelector("[data-confirm]")!, "Reject and keep my course").click();
    await wait(() => expect(root.querySelector("[data-state]")?.textContent).toContain("You kept the earlier version"));
    expect(root.querySelector("[data-apply-bar]")?.hasAttribute("hidden")).toBe(true);
    expect(root.querySelector("[data-accept-all]")).toBeNull();
  });
});

describe("update review: apply", () => {
  it("applies, follows the progress and ends on the done state with a link to the workspace", async () => {
    const s = stub(detail(item3()));
    let finish = false;
    s.onGet = (srv) => {
      if (srv.detail.status === "applying" && finish) srv.detail = { ...srv.detail, status: "applied", resultVersionId: "v9" };
    };
    s.apply = () => {
      s.detail = { ...s.detail, status: "applying" };
      return { status: 202, body: { data: { runId: "run2", proposal: summary(s.detail) } } };
    };
    const root = mount();
    await ready(root);
    button(root, "Apply 1 accepted change").click();
    await wait(() => expect(root.querySelector("[data-state]")?.textContent).toContain("Applying the accepted changes"));
    finish = true;
    await wait(() => expect(root.querySelector("[data-done]")).not.toBeNull());
    expect(root.querySelector("[data-done] a")?.getAttribute("href")).toBe(`/studio/s/${S}/workspace`);
    expect(root.querySelector("[data-apply-bar]")?.hasAttribute("hidden")).toBe(true);
    expect(root.querySelector('[data-update-item] [aria-pressed]')).toBeNull();
  });

  it("shows conflicts, marks the items and explains what to do", async () => {
    const s = stub(detail(item3()));
    s.apply = () => ({
      status: 409,
      body: { success: false, code: "conflicts", message: "You edited some elements.", data: { conflicts: [{ ...item3()[0]!, status: "conflict" }] } },
    });
    const root = mount();
    await ready(root);
    button(root, "Apply 1 accepted change").click();
    await wait(() => expect(root.querySelector('[data-update-item="i1"]')?.getAttribute("data-status")).toBe("conflict"));
    expect(root.querySelector("[data-notices]")?.textContent).toContain("1 element was edited after the analysis");
    expect(root.querySelector('[data-update-item="i1"]')?.textContent).toContain("You edited this element after the analysis");
    expect((button(root.querySelector<HTMLElement>('[data-update-item="i1"]')!, "Accept") as HTMLButtonElement).disabled).toBe(true);
  });

  it("asks before overwriting edits made in the admin", async () => {
    const s = stub(detail(item3()));
    const bodies: Array<{ overwrite?: boolean }> = [];
    s.apply = (body) => {
      bodies.push(body);
      if (!body.overwrite) return { status: 409, body: { success: false, code: "admin_edits", message: "edited", data: { drift: ["Lesson 1.1", "Q2"] } } };
      s.detail = { ...s.detail, status: "applying" };
      return { status: 202, body: { data: { runId: "run2", proposal: summary(s.detail) } } };
    };
    const root = mount();
    await ready(root);
    button(root, "Apply 1 accepted change").click();
    await wait(() => expect(root.querySelector("[data-apply-bar] [data-confirm]")).not.toBeNull());
    const panel = root.querySelector("[data-apply-bar] [data-confirm]")!;
    expect(panel.textContent).toContain("Lesson 1.1, Q2 were edited in the admin");
    expect(bodies).toEqual([{ overwrite: false }]);
    button(panel, "Overwrite and apply").click();
    await wait(() => expect(bodies).toEqual([{ overwrite: false }, { overwrite: true }]));
    await wait(() => expect(root.querySelector("[data-state]")?.textContent).toContain("Applying the accepted changes"));
  });

  it("keeps Apply disabled when nothing is accepted", async () => {
    stub(detail([item(), question()]));
    const root = mount();
    await ready(root);
    expect(button(root, "Apply accepted changes").disabled).toBe(true);
  });
});

describe("update review: analysis states", () => {
  it("awaiting analysis offers the estimate button with a confirmation", async () => {
    const s = stub(detail([item({ kind: "manual", after: null })], { status: "awaiting_analysis", costMicroUsd: null }));
    const bodies: unknown[] = [];
    s.analyse = (body) => {
      bodies.push(body);
      s.detail = { ...s.detail, status: "analysing" };
      return { status: 202, body: { data: { state: "started", estimateMicroUsd: 420000, runId: "run1", message: null } } };
    };
    const root = mount();
    await wait(() => expect(root.querySelector("[data-awaiting]")).not.toBeNull());
    expect(button(root, "Analyse (about $0.42)")).toBeTruthy();
    expect(root.querySelector("[data-apply-bar]")?.hasAttribute("hidden")).toBe(true);
    expect(root.querySelector('[data-update-item]')?.textContent).toContain("Decisions open when the analysis is finished.");
    button(root, "Analyse (about $0.42)").click();
    const panel = root.querySelector("[data-confirm]")!;
    expect(panel.textContent).toContain("for about $0.42");
    expect(bodies).toEqual([]);
    button(panel, "Start the analysis").click();
    await wait(() => expect(bodies).toEqual([{ confirmEstimate: true }]));
    await wait(() => expect(root.querySelector("[data-analysing]")).not.toBeNull());
  });

  it("analysing shows progress per group, from the event stream, and follows the proposal when it is ready", async () => {
    const s = stub(detail(item3(), { status: "analysing", steps: [{ id: "s1", groupKey: "lesson:l1", status: "running", error: null }] }), { sse: true });
    const root = mount(10_000);
    await wait(() => expect(root.querySelector("[data-analysing]")).not.toBeNull());
    expect(root.querySelector("[data-progress-text]")?.textContent).toBe("0 of 1 lesson analysed · $0.13 so far");
    expect(root.querySelector('[data-step="lesson:l1"]')?.textContent).toContain("Lesson 1.1: Ratios");
    await wait(() => expect(s.events).toBeDefined());
    const send = (name: string, value: Record<string, unknown>) =>
      s.events!.enqueue(new TextEncoder().encode(`id: ${Math.random()}\ndata: ${JSON.stringify({ type: "CUSTOM", name, value })}\n\n`));
    send("update_analysis", { proposalId: P, total: 4, done: 2, failed: 0, costMicroUsd: 200000 });
    await wait(() => expect(root.querySelector("[data-progress-text]")?.textContent).toBe("2 of 4 lessons analysed · $0.20 so far"));
    send("update_analysis", { proposalId: "other", total: 9, done: 9, failed: 0, costMicroUsd: 1 });
    s.detail = { ...s.detail, status: "ready", steps: [{ id: "s1", groupKey: "lesson:l1", status: "done", error: null }] };
    send("update_proposal", { proposalId: P });
    await wait(() => expect(root.querySelector("[data-analysing]")).toBeNull());
    expect(button(root, "Apply 1 accepted change")).toBeTruthy();
  });

  it("falls back to polling every few seconds when the event stream is not available", async () => {
    const s = stub(detail(item3(), { status: "analysing", steps: [{ id: "s1", groupKey: "lesson:l1", status: "running", error: null }] }));
    let finish = false;
    s.onGet = (srv) => {
      if (finish) srv.detail = { ...srv.detail, status: "ready" };
    };
    const root = mount(15);
    await wait(() => expect(root.querySelector("[data-analysing]")).not.toBeNull());
    // with no event stream the page keeps asking while the analysis runs
    await wait(() => expect(s.getCount).toBeGreaterThanOrEqual(4));
    expect(root.querySelector("[data-analysing]")).not.toBeNull();
    finish = true;
    await wait(() => expect(root.querySelector("[data-analysing]")).toBeNull());
    expect(button(root, "Apply 1 accepted change")).toBeTruthy();
  });

  it("partial failure lists the failed group with a retry that restarts only that step", async () => {
    const s = stub(detail(item3(), {
      status: "ready",
      counts: { items: 3, elements: 2, remaps: 1, uncovered: 0, major: 1, answerChecks: 1, groups: 2, failedGroups: ["lesson:l2"] },
      steps: [{ id: "s1", groupKey: "lesson:l1", status: "done", error: null }, { id: "s2", groupKey: "lesson:l2", status: "failed", error: "The model timed out." }],
    }));
    s.onGet = () => undefined;
    const root = mount();
    await ready(root);
    const panel = root.querySelector("[data-failed-groups]")!;
    expect(panel.textContent).toContain("The analysis failed for 1 group");
    expect(panel.textContent).toContain("The model timed out.");
    s.detail = { ...s.detail, status: "analysing" };
    button(panel, "Retry this group").click();
    await wait(() => expect(s.calls.some((c) => c.method === "POST" && c.path === "/runs/run1/steps/s2/retry")).toBe(true));
    await wait(() => expect(root.querySelector("[data-analysing]")).not.toBeNull());
  });

  it("budget blocked explains the manual path", async () => {
    stub(detail([item({ kind: "manual", after: null })], { status: "budget_blocked" }));
    const root = mount();
    await wait(() => expect(root.querySelector("[data-state]")?.textContent).toContain("The AI budget is used up"));
    expect(root.querySelector('[data-state] a[href$="/workspace"]')?.textContent).toBe("Update by hand in the workspace");
    expect(button(root, "Try the analysis again")).toBeTruthy();
    expect(root.querySelector('[data-update-item]')?.textContent).toContain("needs an update by hand");
    expect(root.querySelector('[data-update-item] a[href$="/workspace"]')?.textContent).toBe("Open in the workspace");
  });

  it("with AI disabled the items are manual, linked to the workspace, and nothing offers the analysis", async () => {
    stub(detail([item({ kind: "manual", after: null })], { status: "awaiting_analysis" }), { aiEnabled: false });
    const root = mount();
    await wait(() => expect(root.querySelector("[data-state]")?.textContent).toContain("AI analysis is off"));
    expect([...root.querySelectorAll("button")].some((b) => b.textContent?.includes("Analyse"))).toBe(false);
    expect([...root.querySelectorAll("button")].some((b) => b.textContent?.includes("Ask for changes"))).toBe(false);
    expect(button(root, "Mark as handled")).toBeTruthy();
  });

  it("a 503 from analyse switches the page to the manual path", async () => {
    const s = stub(detail([item({ kind: "manual", after: null })], { status: "awaiting_analysis" }));
    s.analyse = () => ({ status: 503, body: { success: false, code: "ai_disabled", message: "AI analysis is disabled on this installation." } });
    const root = mount();
    await wait(() => expect(root.querySelector("[data-awaiting]")).not.toBeNull());
    button(root, "Analyse (about $0.42)").click();
    button(root.querySelector("[data-confirm]")!, "Start the analysis").click();
    await wait(() => expect(root.querySelector("[data-state]")?.textContent).toContain("AI analysis is off"));
    expect(root.querySelector("[data-notices] [role='alert']")?.textContent).toContain("AI is disabled");
  });

  it("shows the newer-revision banner and the applied and no-impact states", async () => {
    stub(detail(item3(), { counts: { items: 3, elements: 2, remaps: 1, uncovered: 0, major: 1, answerChecks: 1, groups: 1, newerRevision: 3 } }));
    let root = mount();
    await ready(root);
    expect(root.querySelector("[data-state]")?.textContent).toContain("A newer source revision exists (revision 3)");
    stub(detail([], { status: "no_impact", groups: [] }));
    root = mount();
    await wait(() => expect(root.querySelector("[data-state]")?.textContent).toContain("does not affect your course"));
  });
});
