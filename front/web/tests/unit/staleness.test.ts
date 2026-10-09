// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from "vitest";
import type { BlueprintLesson, StaleElement, StalenessSummary } from "@ulams/sdk";
import { agoText, bannerModel, daysSince, freshnessLabel, lessonMarker, markerFor, reviewHref, staleMap, staleNote } from "../../src/studio/staleness.ts";

const S = "01m4fj5zsgfxhzh3cantwevncw";
const P = "01m4fj5zsgfxhzh3cantwevnaa";
const NOW = new Date("2026-10-20T12:00:00Z");

const el = (over: Partial<StaleElement> = {}): StaleElement => ({
  elementId: "b1", status: "pending", type: "block", label: "Ratio", since: "2026-10-17T12:00:00Z", proposalId: P, fragmentIds: ["frg_aaaaaaaaaaa1"], answerCheck: false, ...over,
});
const summary = (over: Partial<StalenessSummary> = {}): StalenessSummary => ({
  state: "stale", since: "2026-10-17T12:00:00Z", days: 3, pendingElements: 2, openProposalId: P, syncedRevision: 1, latestRevision: 2, lastCheckedAt: null, tracked: true, ...over,
});
const lesson = (): BlueprintLesson =>
  ({
    id: "l1", title: "Ratios", minutes: 5, objectives: [{ id: "o1", text: "x", citations: [] }], citations: [], contentType: "richtext", status: "generated", flags: [],
    blocks: [{ id: "b1", kind: "paragraph", markdown: "x", citations: [], objectiveIds: [] }, { id: "b2", kind: "paragraph", markdown: "y", citations: [], objectiveIds: [] }],
    quiz: { id: "z", questions: [{ id: "q1", type: "single", stem: "s", options: [], explanation: "", citations: [], objectiveIds: [] }] },
  }) as BlueprintLesson;

describe("staleness markers", () => {
  it("names each state in words", () => {
    expect(markerFor(el())).toEqual({ kind: "pending", text: "Update pending" });
    expect(markerFor(el({ answerCheck: true }))).toEqual({ kind: "answer", text: "Answer may be wrong" });
    expect(markerFor(el({ status: "source_removed" }))).toEqual({ kind: "removed", text: "Source removed" });
    expect(markerFor(el({ status: "dismissed" }))).toBeNull();
    expect(markerFor(undefined)).toBeNull();
  });

  it("a lesson shows its most serious marker and how many elements are open", () => {
    const stale = staleMap([el({ elementId: "b1" }), el({ elementId: "b2" }), el({ elementId: "q1", type: "question", answerCheck: true }), el({ elementId: "zzz" })]);
    expect(lessonMarker(lesson(), stale)).toEqual({ kind: "answer", text: "Answer may be wrong", count: 3 });
    expect(lessonMarker(lesson(), staleMap([]))).toBeNull();
  });

  it("builds the 'based on' note from the citation labels", () => {
    const labels = { frg_aaaaaaaaaaa1: "§3.2 Ratios" };
    expect(staleNote(el(), labels, NOW)).toBe("Based on §3.2 Ratios, changed 3 days ago");
    expect(staleNote(el({ status: "source_removed" }), labels, NOW)).toBe("Based on §3.2 Ratios, removed from the source");
    expect(staleNote(el({ fragmentIds: [] }), labels, NOW)).toBe("Its source changed 3 days ago");
  });

  it("counts days and words them", () => {
    expect(daysSince("2026-10-20T01:00:00Z", NOW)).toBe(0);
    expect(daysSince("garbage", NOW)).toBeNull();
    expect([agoText(0), agoText(1), agoText(5), agoText(null)]).toEqual(["today", "1 day ago", "5 days ago", "recently"]);
  });
});

describe("freshness", () => {
  it("labels the session list badge", () => {
    expect(freshnessLabel(summary())).toEqual({ state: "stale", text: "Stale · 3 days" });
    expect(freshnessLabel(summary({ days: 1 }))?.text).toBe("Stale · 1 day");
    expect(freshnessLabel(summary({ days: 0 }))?.text).toBe("Stale · today");
    expect(freshnessLabel(summary({ state: "in_sync", days: null }))).toEqual({ state: "in_sync", text: "In sync" });
    expect(freshnessLabel(summary({ state: "dismissed" }))?.text).toBe("Updates dismissed");
    expect(freshnessLabel(summary({ tracked: false }))).toBeNull();
    expect(freshnessLabel(undefined)).toBeNull();
  });

  it("builds the banner and the review link", () => {
    expect(bannerModel(summary())).toMatchObject({ state: "stale", title: "2 elements are out of date with the source" });
    expect(bannerModel(summary())?.detail).toContain("Since 3 days ago");
    expect(bannerModel(summary())?.detail).toContain("revision 1; the latest is 2");
    expect(bannerModel(summary({ pendingElements: 1 }))?.title).toBe("1 element is out of date with the source");
    expect(bannerModel(summary({ state: "dismissed" }))?.title).toBe("You kept the earlier version");
    expect(bannerModel(summary({ state: "in_sync" }))).toBeNull();
    expect(bannerModel(summary({ tracked: false }))).toBeNull();
    expect(reviewHref(S, summary())).toBe(`/studio/s/${S}/updates/${P}`);
    expect(reviewHref(S, summary({ openProposalId: null }))).toBe(`/studio/s/${S}/updates`);
  });
});

describe("workspace island", () => {
  afterEach(() => vi.unstubAllGlobals());

  it("marks stale elements in the tree and the preview and shows the banner", async () => {
    if (typeof AbortSignal.timeout !== "function") (AbortSignal as unknown as { timeout: () => AbortSignal }).timeout = () => new AbortController().signal;
    const doc = {
      schemaVersion: 1, sources: [], pages: {},
      course: { id: "c", title: "Coffee", language: "en", objectives: [] },
      modules: [{ id: "m1", title: "Brewing", lessons: [{ ...lesson(), blocks: [{ id: "b1", kind: "paragraph", markdown: "Use 15 g.", citations: ["frg_aaaaaaaaaaa1"], objectiveIds: [] }] }] }],
      finalTest: null,
    };
    const state = { session: { id: S, title: "Coffee", status: "applied", courseId: 1, currentVersionId: "v1", appliedVersionId: "v1" }, canUndo: false, canRedo: false, cost: null };
    vi.stubGlobal("fetch", async (url: string) => {
      const ok = (data: unknown) => new Response(JSON.stringify({ success: true, data }), { status: 200 });
      if (url.endsWith("/events")) {
        const frame = `id: 1\ndata: ${JSON.stringify({ type: "STATE_SNAPSHOT", snapshot: state })}\n\n`;
        return new Response(new ReadableStream({ start: (c) => c.enqueue(new TextEncoder().encode(frame)) }), { status: 200, headers: { "Content-Type": "text/event-stream" } });
      }
      if (url.endsWith(`/sessions/${S}`)) return ok(state);
      if (url.endsWith("/versions/v1")) return ok({ id: "v1", number: 1, kind: "content", origin: "ai", status: "approved", document: doc, fragments: { frg_aaaaaaaaaaa1: "§3.2 Ratios" }, createdAt: "2026-10-01T00:00:00Z" });
      if (url.endsWith(`/sessions/${S}/versions`)) return ok({ currentVersionId: "v1", appliedVersionId: "v1", versions: [] });
      if (url.endsWith(`/sessions/${S}/staleness`)) {
        return ok({ summary: summary({ pendingElements: 2 }), elements: [el({ elementId: "b1", since: new Date(Date.now() - 3 * 86_400_000).toISOString() }), el({ elementId: "q1", type: "question", answerCheck: true })] });
      }
      return new Response("{}", { status: 404 });
    });
    document.body.innerHTML = `<div data-studio-workspace data-session="${S}">
      <div data-tree></div><div data-stale-banner hidden></div><div data-preview></div><div data-chat></div><p data-scope></p>
      <form data-composer><textarea></textarea></form><button data-undo></button><button data-redo></button><div data-history></div><div data-apply></div>
    </div><div data-announcer></div>`;
    vi.resetModules();
    const { mountWorkspace } = await import("../../src/studio/workspace.ts");
    const root = document.querySelector<HTMLElement>("[data-studio-workspace]")!;
    mountWorkspace(root);
    await vi.waitFor(() => expect(root.querySelector("[data-stale-banner]:not([hidden])"), root.innerHTML).not.toBeNull());
    const banner = root.querySelector("[data-stale-banner]")!;
    expect(banner.textContent).toContain("2 elements are out of date with the source");
    expect(banner.querySelector("a")?.getAttribute("href")).toBe(`/studio/s/${S}/updates/${P}`);
    // the state arrives over the event stream, then the version loads: tree and preview carry the markers
    await vi.waitFor(() => expect(root.querySelector("[data-tree] [data-marker]"), root.innerHTML).not.toBeNull());
    const tree = root.querySelector("[data-tree]")!;
    expect(tree.textContent).toContain("Answer may be wrong");
    expect(tree.querySelector('[data-marker="answer"]')?.textContent).toContain("Answer may be wrong (2)");
    await vi.waitFor(() => expect(root.querySelector('[data-preview] [data-stale-note="b1"]')).not.toBeNull());
    const note = root.querySelector('[data-stale-note="b1"]')!;
    expect(note.textContent).toContain("Update pending");
    expect(note.textContent).toContain("Based on §3.2 Ratios, changed 3 days ago");
    expect(root.querySelector("[data-block='b1']")?.classList.contains("st-stale")).toBe(true);
  });
});
