import { experimental_AstroContainer as AstroContainer } from "astro/container";
import { JSDOM } from "jsdom";
import { describe, expect, it } from "vitest";
import Render from "../src/Render.astro";
import type { UiNode } from "../src/render-core.ts";

// The components render on the server (Astro container, node); the HTML is then mounted in one
// jsdom document so axe-core can check it. axe reads the DOM globals when it loads.
const dom = new JSDOM("<!doctype html><html lang='en'><body></body></html>", { pretendToBeVisual: true });
for (const key of ["window", "document", "Node", "Element", "HTMLElement", "NodeList", "getComputedStyle"] as const) {
  const value = key === "getComputedStyle" ? dom.window.getComputedStyle.bind(dom.window) : (dom.window as unknown as Record<string, unknown>)[key];
  Object.defineProperty(globalThis, key, { value, configurable: true, writable: true });
}
const { default: axe } = await import("axe-core");

/** Renders a catalogue document to HTML with the Astro container and mounts it for axe. */
async function mount(doc: UiNode): Promise<HTMLElement> {
  const container = await AstroContainer.create();
  const html = await container.renderToString(Render as never, { props: { doc } });
  document.body.innerHTML = `<main>${html}</main>`;
  return document.body.querySelector("main")!;
}

const AXE = { rules: { "color-contrast": { enabled: false }, region: { enabled: false } } };
const violations = async (root: HTMLElement) =>
  (await axe.run(root, AXE)).violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.html).join(" | ")}`);

describe("Living Course notices render accessibly", () => {
  it("UpdateNotice: a labelled region with the date, the note and a keyboard-reachable button", async () => {
    const root = await mount({ component: "UpdateNotice", props: { noticeId: 7, date: "2026-10-09T10:00:00Z", message: "The ratio is now 1:16." } });
    const region = root.querySelector("section[aria-labelledby]")!;
    const title = root.querySelector(`#${region.getAttribute("aria-labelledby")}`)!;
    expect(title.textContent).toBe("Updated since you completed it, 9 October 2026.");
    expect(region.textContent).toContain("What changed: The ratio is now 1:16.");
    const button = root.querySelector<HTMLButtonElement>("ulams-notice[notice-id='7'] button[data-dismiss]")!;
    expect(button.textContent?.trim()).toBe("Mark as reviewed");
    expect(button.type).toBe("button");
    expect(root.querySelector("[role=status]")).not.toBeNull();
    expect(await violations(root)).toEqual([]);
  });

  it("UpdateNotice: the wording for a lesson that was started, no button without an id, text is escaped", async () => {
    const root = await mount({ component: "UpdateNotice", props: { started: true, date: "2026-10-09T10:00:00Z", message: "<b>bold</b>" } });
    expect(root.textContent).toContain("This lesson was updated after you started it, 9 October 2026.");
    expect(root.querySelector("button")).toBeNull();
    expect(root.querySelector("b")).toBeNull();
    expect(root.textContent).toContain("<b>bold</b>");
    expect(await violations(root)).toEqual([]);
  });

  it("ReattemptNotice: says the score stays and links to the quiz", async () => {
    const root = await mount({ component: "ReattemptNotice", props: { quizHref: "#quiz" } });
    expect(root.textContent).toContain("One question was corrected.");
    expect(root.textContent).toContain("Your previous score stays on record. Retake it to update your result.");
    expect(root.querySelector("a")?.getAttribute("href")).toBe("#quiz");
    expect(root.querySelector("button")).toBeNull();
    expect(await violations(root)).toEqual([]);
  });

  it("PendingUpdateNotice: the opt-in marker in words", async () => {
    const root = await mount({ component: "PendingUpdateNotice", props: { since: "2026-10-01T00:00:00Z" } });
    expect(root.textContent).toContain("The source of this lesson changed on 1 October 2026; an update is under review.");
    expect(await violations(root)).toEqual([]);
  });

  it("CourseUpdates: every status is a sentence, lessons link where they still exist", async () => {
    const root = await mount({
      component: "CourseUpdates",
      props: {
        updated: [{ title: "Grinding", href: "/learn/5/10", date: "2026-10-09T10:00:00Z" }],
        extended: [{ title: "Cupping", href: "/learn/5/12" }],
        retired: [{ title: "Old roast chart", date: "2026-10-08T10:00:00Z" }],
        pending: [{ title: "Brewing", href: "/learn/5/11", since: "2026-10-01T00:00:00Z" }],
      },
    });
    const text = root.textContent ?? "";
    expect(text).toContain("Updated since you completed it, 9 October 2026");
    expect(text).toContain("New since you finished");
    expect(text).toContain("Retired lesson, no longer part of the course (8 October 2026)");
    expect(text).toContain("an update is under review");
    expect([...root.querySelectorAll("a")].map((a) => a.getAttribute("href"))).toEqual(["/learn/5/10", "/learn/5/12", "/learn/5/11"]);
    expect(root.querySelector("a[href*='Old']")).toBeNull();
    expect(root.querySelector("section")?.getAttribute("aria-labelledby")).toBeTruthy();
    expect(await violations(root)).toEqual([]);
  });

  it("CourseUpdates renders nothing when nothing changed", async () => {
    const root = await mount({ component: "CourseUpdates", props: {} });
    expect(root.querySelector("section")).toBeNull();
  });
});
