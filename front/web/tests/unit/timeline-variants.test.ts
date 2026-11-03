// @vitest-environment jsdom
import { describe, expect, it, vi } from "vitest";
import { EventType, type AgUiEvent } from "@ulams/sdk";
import { Timeline } from "../../src/studio/timeline.ts";

const surface = (surfaceId: string, kind: string, components: Record<string, unknown>[]): AgUiEvent =>
  ({
    type: EventType.ACTIVITY_SNAPSHOT,
    messageId: surfaceId,
    activityType: "a2ui-surface",
    content: { surfaceId, kind, messages: [{ createSurface: { catalogId: "x" } }, { updateComponents: { components } }] },
  }) as AgUiEvent;

const review = (status = "proposed") =>
  surface("variant-v1", "variant", [
    {
      id: "root",
      component: "VariantReview",
      versionId: "v1",
      variantKey: "expert",
      label: "Expert",
      status,
      baseNumber: 3,
      summary: { lessons: 3, kept: 1, changed: 1, added: 1, removed: 1, citations: 4 },
      changes: [{ path: "l1.title", label: "Lesson 1.1 · Title", kind: "changed", before: "Basics", after: "Basics in depth" }],
    },
  ]);

describe("the studio workspace shows the variants of a course (ADR 0192)", () => {
  function mount(dispatch = vi.fn(async () => undefined)) {
    document.body.innerHTML = "<div id='c'></div>";
    const container = document.getElementById("c")!;
    // the kinds the workspace asks for (src/studio/workspace.ts)
    const timeline = new Timeline({ container, dispatch, kinds: ["patch", "apply", "variants", "variant_outline", "variant_progress", "variant"] });
    return { container, timeline, dispatch };
  }

  it("renders the variant review and sends the decision as an action", () => {
    const { container, timeline, dispatch } = mount();
    timeline.handle(review());
    expect(container.querySelector(".st-surface-variant")).not.toBeNull();
    expect(container.textContent).toContain("Variant for Expert");
    expect(container.textContent).toContain("1 skipped");
    [...container.querySelectorAll("button")].find((b) => b.textContent?.includes("Create the Expert course"))!.click();
    expect(dispatch).toHaveBeenCalledWith(expect.objectContaining({ name: "approve_variant", surfaceId: "variant-v1", context: { versionId: "v1" } }));
  });

  it("replaces the review in place when the server sends the decision", () => {
    const { container, timeline } = mount();
    timeline.handle(review());
    timeline.handle(review("approved"));
    expect(container.querySelectorAll("[data-surface='variant-v1']")).toHaveLength(1);
    expect(container.textContent).toContain("Created as its own course");
    expect(container.querySelector("button")).toBeNull();
  });

  it("shows the outline and the progress of a variant but not the base outline", () => {
    const { container, timeline } = mount();
    timeline.handle(surface("outline-v0", "outline", [{ id: "root", component: "Text", text: "Base outline" }]));
    timeline.handle(surface("outline-v2", "variant_outline", [{ id: "root", component: "Text", text: "Expert outline" }]));
    timeline.handle(surface("progress-r1", "variant_progress", [{ id: "root", component: "Text", text: "Writing" }]));
    expect(container.textContent).not.toContain("Base outline");
    expect(container.textContent).toContain("Expert outline");
    expect(container.textContent).toContain("Writing");
    expect(container.querySelector("[data-surface='outline-v2']")!.getAttribute("data-label")).toBe("Proposed outline of a variant");
  });
});
