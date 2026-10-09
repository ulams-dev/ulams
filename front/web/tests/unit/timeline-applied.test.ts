// @vitest-environment jsdom
import { describe, expect, it, vi } from "vitest";
import { EventType, type AgUiEvent, type BuilderState } from "@ulams/sdk";
import { settleApplied } from "../../src/studio/common.ts";
import { Timeline } from "../../src/studio/timeline.ts";

const session = (patch: Record<string, unknown>) => ({ id: "s1", title: "T", status: "applied", courseId: 1, currentVersionId: "v2", appliedVersionId: "v1", ...patch });
const snapshot = (s: Record<string, unknown>): AgUiEvent => ({ type: EventType.STATE_SNAPSHOT, snapshot: { session: s } }) as AgUiEvent;
const delta = (s: Record<string, unknown>): AgUiEvent => ({ type: EventType.STATE_DELTA, delta: [{ op: "replace", path: "/session", value: s }] }) as AgUiEvent;
const patchSurface = (): AgUiEvent =>
  ({
    type: EventType.ACTIVITY_SNAPSHOT,
    messageId: "m1",
    activityType: "a2ui-surface",
    content: {
      surfaceId: "patch-1",
      kind: "patch",
      messages: [
        { createSurface: { catalogId: "x" } },
        { updateComponents: { components: [{ id: "root", component: "DiffView", versionId: "v2", elementId: "q1", elementLabel: "Q1", reason: "r", status: "approved", changes: [], citations: [] }] } },
      ],
    },
  }) as AgUiEvent;

function mount() {
  document.body.innerHTML = "<div id='c'></div>";
  const container = document.getElementById("c")!;
  return { container, timeline: new Timeline({ container, dispatch: async () => undefined, kinds: ["patch"] }) };
}

describe("approved edits report applied only when the applied version has caught up", () => {
  it("shows 'applying' while appliedVersionId lags currentVersionId, then 'applied' on the delayed delta", () => {
    const { container, timeline } = mount();
    timeline.handle(snapshot(session({})));
    timeline.handle(patchSurface());
    expect(container.textContent).toContain("Approved; applying to the course");
    expect(container.textContent).not.toContain("Approved and applied");

    // the queue catches up later: the stream delivers the new applied version
    timeline.handle(delta(session({ appliedVersionId: "v2" })));
    expect(container.textContent).toContain("Approved and applied");
    expect(container.textContent).not.toContain("applying");
  });

  it("does not trust status alone: a re-apply in flight keeps showing 'applying'", () => {
    const { container, timeline } = mount();
    timeline.handle(snapshot(session({ status: "applying" })));
    timeline.handle(patchSurface());
    expect(container.textContent).toContain("applying to the course");
  });
});

describe("settleApplied", () => {
  const settled = { session: session({ appliedVersionId: "v2" }) } as unknown as BuilderState;
  const lagging = { session: session({}) } as unknown as BuilderState;

  it("returns the stream state without a request when it is already settled", async () => {
    const cb = { sessions: { waitForApplied: vi.fn() } };
    expect(await settleApplied(cb as never, "s1", "v2", () => settled)).toBe(settled);
    expect(cb.sessions.waitForApplied).not.toHaveBeenCalled();
  });

  it("falls back to the session endpoint when the stream state lags", async () => {
    const cb = { sessions: { waitForApplied: vi.fn(async () => settled) } };
    expect(await settleApplied(cb as never, "s1", "v2", () => lagging)).toBe(settled);
    expect(cb.sessions.waitForApplied).toHaveBeenCalledWith("s1", { versionId: "v2" });
  });

  it("returns null when the apply cannot be confirmed", async () => {
    const cb = { sessions: { waitForApplied: vi.fn(async () => Promise.reject(new Error("timeout"))) } };
    expect(await settleApplied(cb as never, "s1", "v2", () => lagging)).toBeNull();
  });
});
