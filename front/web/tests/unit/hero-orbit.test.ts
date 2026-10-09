// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { validateDocument, type UiNode } from "@ulams/ui/render-core";
import { landingDocs } from "../../src/lib/docs.ts";
import { applyLandingStatus } from "../../src/lib/landing-status.ts";
import { comparisonModel } from "../../src/lib/comparison.ts";
import { workflowsModel } from "../../src/lib/workflows.ts";

interface Capability {
  icon: string;
  label: string;
  caption: string;
  href: string;
  ring: number;
  angle?: number;
  status?: string;
}

const platform = landingDocs.platform!;
const hero = (doc: UiNode): UiNode =>
  doc
    .children!.find((c) => c.component === "Main")!
    .children!.find((c) => c.component === "Hero")!;
const caps = (doc: UiNode) =>
  (hero(doc).props!.capabilities as Capability[]) ?? [];

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

describe("hero capability orbit data", () => {
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

  it("lists the ten capabilities with short labels and one-line captions", () => {
    const list = caps(platform);
    expect(list).toHaveLength(10);
    for (const c of list) {
      expect(c.label.length).toBeLessThanOrEqual(32);
      expect(
        c.label.split(/\s+/).filter((w) => /\w/.test(w)).length,
      ).toBeLessThanOrEqual(5);
      expect(c.caption.length).toBeGreaterThan(20);
      expect(c.caption.length).toBeLessThanOrEqual(90);
      expect([1, 2, 3]).toContain(c.ring);
    }
    expect(new Set(list.map((c) => c.label)).size).toBe(10);
    for (const needle of [
      "Living Course",
      "AI course builder",
      "Preview",
      "insights",
      "CLI",
      "Claude",
      "Site per customer",
      "H5P",
      "Commerce",
      "Self-hosted",
    ]) {
      expect(
        list.some((c) => c.label.includes(needle)),
        needle,
      ).toBe(true);
    }
  });

  it("links every card to a section that exists on the page", () => {
    const ids = anchors(platform);
    for (const c of caps(platform)) {
      expect(c.href.startsWith("#"), c.label).toBe(true);
      expect(ids.has(c.href.slice(1)), `${c.label} -> ${c.href}`).toBe(true);
    }
  });

  it("start angles leave no two cards of one orbit closer than 25 degrees", () => {
    for (const ring of [1, 2, 3]) {
      const a = caps(platform)
        .filter((c) => c.ring === ring)
        .map((c) => c.angle ?? 0)
        .sort((x, y) => x - y);
      for (let i = 1; i < a.length; i++)
        expect((a[i] ?? 0) - (a[i - 1] ?? 0)).toBeGreaterThan(25);
    }
  });

  it("final mode shows everything as available, actual mode keeps the Coming markers", () => {
    expect(caps(platform).some((c) => c.status === "coming")).toBe(true);
    expect(
      caps(applyLandingStatus(platform, "final")).some((c) => c.status),
    ).toBe(false);
    const actual = caps(applyLandingStatus(platform, "actual"));
    expect(
      actual.filter((c) => c.status === "coming").map((c) => c.label),
    ).toEqual(
      expect.arrayContaining([
        "Living Course",
        "Commerce",
        "CLI · MCP · REST API",
        "Claude & Claude Code",
      ]),
    );
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
