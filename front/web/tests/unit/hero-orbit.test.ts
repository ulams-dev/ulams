// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { validateDocument, type UiNode } from "@ulams/ui/render-core";
import { landingDocs } from "../../src/lib/docs.ts";
import { applyLandingStatus } from "../../src/lib/landing-status.ts";
import { comparisonModel } from "../../src/lib/comparison.ts";
import { workflowsModel } from "../../src/lib/workflows.ts";

const platform = landingDocs.platform!;
const hero = (doc: UiNode): UiNode =>
  doc
    .children!.find((c) => c.component === "Main")!
    .children!.find((c) => c.component === "Hero")!;
/** Anchor ids on the landing: explicit ids plus the defaults of Chips and Showcase. */
function anchors(doc: UiNode): Set<string> {
  const ids = new Set<string>();
  const walk = (n: UiNode) => {
    const id = n.props?.id as string | undefined;
    if (id) ids.add(id);
    else if (n.component === "Chips") ids.add("standards");
    else if (n.component === "Showcase") ids.add("demos");
    n.children?.forEach(walk);
  };
  walk(doc);
  return ids;
}

describe("platform product hero", () => {
  it("is valid against the catalogue", () => {
    const data = {
      demos: [],
      comparison: comparisonModel(),
      workflows: workflowsModel("actual"),
    };
    expect(
      validateDocument(platform, data).filter((p) => p.component === "Hero"),
    ).toEqual([]);
  });

  it("puts one policy lifecycle in the hero and marks unbuilt compliance features honestly", () => {
    expect(hero(platform).props).not.toHaveProperty("capabilities");
    expect(hero(platform).props!.diff).toMatchObject({
      title: "Privacy policy · v1 → v2",
      journey: [
        { status: "available" }, { status: "coming" }, { status: "available" },
        { status: "coming" }, { status: "available" }, { status: "coming" },
      ],
      visual: { chapters: ["Source", "Teams", "Learning", "Oversight", "Change", "Renewal"] },
    });
    expect(hero(platform).props!.primaryCta).toMatchObject({ href: "#demos" });
    expect(hero(platform).props!.secondaryCta).toMatchObject({ href: "#self-host" });
  });

  it("keeps the detailed comparison on its own route", () => {
    const sections = platform.children!.find((n) => n.component === "Main")!.children!.map((n) => n.component);
    expect(sections.filter((name) => name === "Hero")).toHaveLength(1);
    expect(sections).not.toContain("LivingCourseStory");
    expect(sections).not.toContain("ComparisonTable");
    const header = platform.children!.find((n) => n.component === "SiteHeader")!;
    expect((header.props!.links as Array<{ href: string }>)[1]!.href).toBe("/comparison/");
  });

  it("keeps every header and footer anchor on the page", () => {
    const ids = anchors(platform);
    for (const chrome of [
      platform.children!.find((n) => n.component === "SiteHeader")!,
      platform.children!.find((n) => n.component === "SiteFooter")!,
    ]) {
      for (const link of chrome.props!.links as Array<{ href: string }>) {
        if (link.href.startsWith("#")) expect(ids.has(link.href.slice(1)), link.href).toBe(true);
      }
    }
  });

  it("still validates in final and actual display modes", () => {
    const data = { demos: [], comparison: comparisonModel(), workflows: workflowsModel("actual") };
    for (const mode of ["actual", "final"] as const) {
      expect(validateDocument(applyLandingStatus(platform, mode), data).filter((p) => p.component === "Hero")).toEqual([]);
    }
  });
});

describe("<ulams-orbit>", () => {
  beforeEach(() => {
    vi.useFakeTimers();
    vi.stubGlobal("matchMedia", () => ({ matches: false }));
    vi.stubGlobal(
      "IntersectionObserver",
      class {
        constructor(cb: (e: Array<{ isIntersecting: boolean }>) => void) {
          cb([{ isIntersecting: true }]);
        }
        observe() {}
      },
    );
    document.body.innerHTML = `<ulams-orbit>
      ${[0, 1, 2].map((i) => `<li data-cap="${i}" class="${i ? "" : "is-on"}"><a href="#x${i}">Card ${i}</a></li>`).join("")}
      ${[0, 1, 2].map((i) => `<span data-cap-text="${i}" ${i ? "hidden" : ""}>Caption ${i}</span>`).join("")}
      <button data-pause aria-pressed="false"></button>
    </ulams-orbit>`;
  });
  afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
    document.body.innerHTML = "";
  });

  const load = async () => {
    await import("@ulams/ui/elements/orbit.ts");
    const el = document.querySelector("ulams-orbit")!;
    // the module may already be registered from an earlier test: connect a fresh element
    const fresh = el.cloneNode(true) as HTMLElement;
    el.replaceWith(fresh);
    return fresh;
  };
  const on = (el: Element) =>
    [...el.querySelectorAll("[data-cap]")].findIndex((c) =>
      c.classList.contains("is-on"),
    );
  const shown = (el: Element) =>
    [...el.querySelectorAll<HTMLElement>("[data-cap-text]")]
      .filter((c) => !c.hidden)
      .map((c) => c.textContent);

  it("highlights the next card and caption every few seconds", async () => {
    const el = await load();
    expect(on(el)).toBe(0);
    vi.advanceTimersByTime(4300);
    expect(on(el)).toBe(1);
    expect(shown(el)).toEqual(["Caption 1"]);
    vi.advanceTimersByTime(8400);
    expect(on(el)).toBe(0);
  });

  it("hover pauses the cycle and shows that card's caption; leaving resumes", async () => {
    const el = await load();
    el.querySelector("[data-cap='2'] a")!.dispatchEvent(
      new MouseEvent("mouseover", { bubbles: true }),
    );
    expect(el.hasAttribute("data-hold")).toBe(true);
    expect(shown(el)).toEqual(["Caption 2"]);
    vi.advanceTimersByTime(13000);
    expect(on(el)).toBe(2);
    el.querySelector("[data-cap='2'] a")!.dispatchEvent(
      new MouseEvent("mouseout", { bubbles: true }),
    );
    expect(el.hasAttribute("data-hold")).toBe(false);
    vi.advanceTimersByTime(4300);
    expect(on(el)).toBe(0);
  });

  it("keyboard focus does the same, and the Pause button stops the cycle", async () => {
    const el = await load();
    el.querySelector("[data-cap='1'] a")!.dispatchEvent(
      new FocusEvent("focusin", { bubbles: true }),
    );
    expect(shown(el)).toEqual(["Caption 1"]);
    el.querySelector("[data-cap='1'] a")!.dispatchEvent(
      new FocusEvent("focusout", { bubbles: true }),
    );
    el.querySelector<HTMLButtonElement>("[data-pause]")!.click();
    expect(el.hasAttribute("data-paused")).toBe(true);
    expect(el.querySelector("[data-pause]")!.getAttribute("aria-pressed")).toBe(
      "true",
    );
    vi.advanceTimersByTime(20000);
    expect(on(el)).toBe(1);
  });
});
