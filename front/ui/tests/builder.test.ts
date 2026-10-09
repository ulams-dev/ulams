// @vitest-environment jsdom
import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it, vi } from "vitest";
import axe from "axe-core";
import { validate } from "../src/schema.ts";
import { builderCatalogue, builderComponentNames, builderManifest } from "../src/builder/catalogue.ts";
import { renderSurface, type FlatComponent, type RenderReport } from "../src/builder/renderer.ts";
import type { A2uiActionOut, BuilderContext } from "../src/builder/components.ts";
import { miniMarkdown } from "../src/builder/dom.ts";
import { builderFixtures } from "./builder-fixtures.ts";

const ctx = (overrides: Partial<BuilderContext> = {}): BuilderContext & { actions: A2uiActionOut[] } => {
  const actions: A2uiActionOut[] = [];
  return { surfaceId: "s1", dispatch: (a) => actions.push(a), actions, ...overrides };
};

const mount = (components: FlatComponent[], c: BuilderContext = ctx(), report?: RenderReport) => {
  document.body.innerHTML = "";
  const main = document.createElement("main");
  main.append(renderSurface(components, c, report));
  document.body.append(main);
  return main;
};

const single = (name: string, props = builderFixtures[name]!) => [{ id: "root", component: name, ...props } as FlatComponent];

describe("builder catalogue", () => {
  it("has a valid fixture for every component", () => {
    for (const name of builderComponentNames) {
      const fixture = builderFixtures[name];
      expect(fixture, name).toBeDefined();
      const result = validate(builderCatalogue[name].props, fixture);
      expect(result.issues, name).toEqual([]);
      expect(typeof builderCatalogue[name].fallback(fixture!)).toBe("string");
    }
  });

  it("matches the manifest the API validates against (no drift)", () => {
    const path = resolve(process.cwd(), "../../api/packages/course-builder/resources/catalogue/manifest.json");
    expect(JSON.parse(readFileSync(path, "utf8"))).toEqual(JSON.parse(JSON.stringify(builderManifest())));
  });

  it("closes every object schema (the server validates with standard JSON Schema)", () => {
    const walk = (schema: Record<string, unknown>, path: string) => {
      if (schema.type === "object") expect(schema.additionalProperties, path).toBe(false);
      for (const child of Object.values((schema.properties ?? {}) as Record<string, Record<string, unknown>>)) walk(child, path);
      if (schema.items) walk(schema.items as Record<string, unknown>, `${path}[]`);
    };
    for (const name of builderComponentNames) walk(builderCatalogue[name].props as Record<string, unknown>, name);
  });
});

describe("renderer", () => {
  it.each(builderComponentNames.filter((n) => n !== "Column"))("renders %s and passes axe", async (name) => {
    const report: RenderReport = { outcomes: {} };
    const main = mount(single(name), ctx(), report);
    expect(report.outcomes.root).toBe("rendered");
    expect(main.querySelector(`[data-component="${name}"]`)).not.toBeNull();
    const result = await axe.run(main, { rules: { "color-contrast": { enabled: false }, region: { enabled: false } } });
    expect(result.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.html).join(" | ")}`)).toEqual([]);
  });

  it("falls back to text for unknown components and invalid props", () => {
    const report: RenderReport = { outcomes: {} };
    const main = mount(
      [
        { id: "root", component: "Column", children: ["a", "b", "c"] },
        { id: "a", component: "Marquee", text: "Unknown widget text" },
        { id: "b", component: "QuizQuestionCard", ...builderFixtures.QuizQuestionCard, type: "essay" },
        { id: "c", component: "Text", text: "Fine" },
      ],
      ctx(),
      report
    );
    expect(report.outcomes).toMatchObject({ a: "fallback", b: "fallback", c: "rendered" });
    expect(main.textContent).toContain("Unknown widget text");
    expect(main.textContent).toContain("How much water for 20 g");
    expect(main.querySelectorAll(".cb-fallback")).toHaveLength(2);
  });

  it("shows a skeleton while required props are still streaming", () => {
    const report: RenderReport = { outcomes: {} };
    const main = mount([{ id: "root", component: "OutlineDiff", versionId: "v" }], ctx(), report);
    expect(report.outcomes.root).toBe("skeleton");
    expect(main.querySelector('[aria-busy="true"]')).not.toBeNull();
  });

  it("never renders markup from content", () => {
    const main = mount(single("LessonPreviewCard"));
    expect(main.querySelector("script")).toBeNull();
    expect(main.textContent).toContain("<script>alert(1)</script>");
    expect(main.querySelector("table th")?.textContent).toBe("A");
    expect(miniMarkdown("[x](javascript:alert(1)) <img src=x onerror=1>")).not.toMatch(/<img|href="javascript/);
  });
});

describe("interactions round-trip as A2UI actions", () => {
  it("chips and Continue send an answer", () => {
    const c = ctx();
    const main = mount(single("ChoiceChips"), c);
    (main.querySelector('[data-value="final"]') as HTMLButtonElement).click();
    main.querySelector("form")!.dispatchEvent(new Event("submit", { cancelable: true }));
    expect(c.actions).toEqual([{ name: "answer", surfaceId: "s1", sourceComponentId: "root", context: { key: "assessments", value: ["quiz", "final"] } }]);
  });

  it("Decide for me sends the question key", () => {
    const c = ctx();
    const main = mount(single("SingleChoice"), c);
    [...main.querySelectorAll("button")].find((b) => b.textContent?.includes("Decide for me"))!.click();
    expect(c.actions[0]).toMatchObject({ name: "decide_for_me", context: { key: "level" } });
  });

  it("the duration control sends both numbers", () => {
    const c = ctx();
    const main = mount(single("DurationSlider"), c);
    (main.querySelector('input[value="120"]') as HTMLInputElement).click();
    main.querySelector("form")!.dispatchEvent(new Event("submit", { cancelable: true }));
    expect(c.actions[0]!.context).toEqual({ key: "duration", value: { totalMinutes: 120, lessonMinutes: 10 } });
  });

  it("the outline sends inline objective edits with the approval", () => {
    const c = ctx();
    const main = mount(single("OutlineDiff"), c);
    (main.querySelector('[aria-label="Edit objective: Explain extraction"]') as HTMLButtonElement).click();
    const input = main.querySelector('input[aria-label="Objective"]') as HTMLInputElement;
    input.value = "Explain extraction in one sentence";
    [...main.querySelectorAll("button")].find((b) => b.textContent === "Save")!.click();
    [...main.querySelectorAll("button")].find((b) => b.textContent?.includes("Approve outline"))!.click();
    expect(c.actions[0]).toMatchObject({ name: "approve_outline", context: { versionId: "01v", edits: [{ objectiveId: "o1", text: "Explain extraction in one sentence" }] } });
  });

  it("request changes sends the comment", () => {
    const c = ctx();
    const main = mount(single("OutlineDiff"), c);
    (main.querySelector("textarea") as HTMLTextAreaElement).value = "Fewer modules";
    [...main.querySelectorAll("button")].find((b) => b.textContent === "Request changes")!.click();
    expect(c.actions[0]).toMatchObject({ name: "reject_outline", context: { comment: "Fewer modules" } });
  });

  it("retry, diff decisions and apply", () => {
    const c = ctx();
    let main = mount(single("GenerationProgress"), c);
    [...main.querySelectorAll("button")].find((b) => b.textContent === "Retry")!.click();
    main = mount(single("DiffView"), c);
    [...main.querySelectorAll("button")].find((b) => b.textContent?.includes("Approve"))!.click();
    [...main.querySelectorAll("button")].find((b) => b.textContent === "Reject")!.click();
    main = mount(single("ApplySummary"), c);
    [...main.querySelectorAll("button")].find((b) => b.textContent?.includes("Apply"))!.click();
    expect(c.actions.map((a) => [a.name, a.context])).toEqual([
      ["retry_step", { stepId: "01step" }],
      ["approve_patch", { versionId: "01p" }],
      ["reject_patch", { versionId: "01p" }],
      ["approve_apply", { versionId: "01c" }],
    ]);
  });

  it("citations, selection and restore call the context", () => {
    const onCitation = vi.fn();
    const onSelect = vi.fn();
    const onRestore = vi.fn();
    let main = mount(single("QuizQuestionCard"), ctx({ onCitation, onSelect }));
    (main.querySelector(".cb-cite") as HTMLButtonElement).click();
    [...main.querySelectorAll("button")].find((b) => b.textContent === "Edit in chat")!.click();
    expect(onCitation).toHaveBeenCalledWith("frg_aaaaaaaaaaa2", "§2 Section 2", expect.anything());
    expect(onSelect).toHaveBeenCalledWith("q1", "How much water for 20 g of coffee at 1:16?");
    main = mount(single("VersionList"), ctx({ onRestore }));
    [...main.querySelectorAll("button")].find((b) => b.textContent === "Restore v2")!.click();
    expect(onRestore).toHaveBeenCalledWith("v2");
  });

  it("version list names a source update and keeps its revision range", () => {
    const main = mount(single("VersionList"));
    const row = [...main.querySelectorAll("li")].find((li) => li.textContent?.includes("v4"))!;
    expect(row.textContent).toContain("Source update r1 → r2: 3 changes");
    const bare = mount(single("VersionList", { versions: [{ id: "v9", number: 9, kind: "update", origin: "ai", status: "approved" }] }));
    expect(bare.textContent).toContain("Source update");
  });

  it("source connection card emits Check now and Upload a new version", () => {
    const c = ctx();
    const main = mount(single("SourceConnectionCard"), c);
    expect(main.textContent).toContain("New version available");
    expect(main.textContent).toContain("Revision 1");
    expect(main.querySelector('[role="alert"]')?.textContent).toContain("could not be read");
    [...main.querySelectorAll("button")].find((b) => b.textContent === "Check now")!.click();
    [...main.querySelectorAll("button")].find((b) => b.textContent === "Upload a new version")!.click();
    expect(c.actions.map((a) => [a.name, a.context])).toEqual([
      ["check_now", { sourceId: "01src" }],
      ["upload_version", { sourceId: "01src" }],
    ]);
    const quiet = mount(single("SourceConnectionCard", { ...builderFixtures.SourceConnectionCard!, canCheck: false }), c);
    expect([...quiet.querySelectorAll("button")].map((b) => b.textContent)).toEqual(["Upload a new version"]);
  });

  it("revision timeline marks the revision in the course, shows counts and selects", () => {
    const c = ctx();
    const main = mount(single("RevisionTimeline"), c);
    const items = [...main.querySelectorAll("li")];
    expect(items[0]!.textContent).toContain("3 changed, 1 removed, 2 added, 1 moved");
    expect(items[0]!.textContent).not.toContain("In your course");
    expect(items[1]!.textContent).toContain("In your course");
    expect(items[0]!.querySelector("button")?.getAttribute("aria-pressed")).toBe("true");
    items[1]!.querySelector("button")!.click();
    expect(c.actions[0]).toMatchObject({ name: "select_revision", context: { revisionId: "01rev1", sourceId: "01src" } });
  });

  it("revision timeline renders links when a revision has an href", () => {
    const props = { ...builderFixtures.RevisionTimeline!, revisions: [{ id: "r", number: 3, status: "failed", error: "Bad file", href: "?revision=r" }] };
    const main = mount(single("RevisionTimeline", props));
    expect(main.querySelector("a")?.getAttribute("href")).toBe("?revision=r");
    expect(main.textContent).toContain("Failed");
    expect(main.textContent).toContain("Bad file");
  });

  it("fragment change shows kind, magnitude, signal text and +/- markers", () => {
    const main = mount(single("FragmentChange"));
    expect(main.textContent).toContain("Changed");
    expect(main.textContent).toContain("Substantive change");
    expect(main.textContent).toContain("a number changed");
    expect(main.querySelector("del")?.textContent).toContain("−15");
    expect(main.querySelector("ins")?.textContent).toContain("+16");
    expect(main.querySelector("del")?.textContent).toContain("removed:");
    const added = mount(single("FragmentChange", { changeId: "8", kind: "added", magnitude: "minor", wordDiff: null, new: { label: "§4 Storage", text: "Keep beans sealed." } }));
    expect(added.textContent).toContain("Added");
    expect(added.textContent).toContain("+ After: Keep beans sealed.");
  });

  describe("update item", () => {
    const item = (over: Record<string, unknown> = {}) => single("UpdateItem", { ...builderFixtures.UpdateItem!, ...over });
    const find = (root: ParentNode, text: string) => [...root.querySelectorAll("button")].find((b) => b.textContent?.includes(text))!;

    it("shows the word diff, reason, warnings, citations and counts without relying on colour", () => {
      const main = mount(item());
      expect(main.querySelector("article")?.getAttribute("aria-labelledby")).toBeTruthy();
      expect(main.querySelector("del")?.textContent).toContain("removed:");
      expect(main.querySelector("ins")?.textContent).toContain("added:");
      expect(main.textContent).toContain("Why: Section 2.3 now recommends 1:16");
      expect(main.textContent).toContain("Answer changed.");
      expect(main.textContent).toContain("Possibly unsupported. the water must be filtered");
      expect(main.textContent).toContain("a number changed");
      expect(main.textContent).toContain("Based on §2.3 Brewing ratios");
      expect(main.textContent).toContain("Asked for changes 1 of 3 times");
      expect(main.querySelector(".cb-cite")?.textContent).toContain("§2 Section 2");
      expect(main.querySelector('[data-update-item] [aria-live="polite"]')).not.toBeNull();
    });

    it("answer may be wrong is worded differently from answer changed", () => {
      const main = mount(item({ answerChanged: false }));
      expect(main.textContent).toContain("Answer may be wrong.");
      expect(main.textContent).not.toContain("Answer changed.");
    });

    it("decisions are aria-pressed toggles that dispatch accept, reject and reset", () => {
      const c = ctx();
      const main = mount(item(), c);
      const accept = find(main, "Accept");
      const reject = find(main, "Reject");
      expect(accept.getAttribute("aria-pressed")).toBe("false");
      accept.click();
      expect(accept.getAttribute("aria-pressed")).toBe("true");
      expect(main.querySelector("[data-update-item]")?.getAttribute("data-status")).toBe("accepted");
      reject.click();
      expect(accept.getAttribute("aria-pressed")).toBe("false");
      expect(reject.getAttribute("aria-pressed")).toBe("true");
      find(main, "Undo my decision").click();
      expect(c.actions.map((a) => a.context.decision)).toEqual(["accept", "reject", "reset"]);
      expect(c.actions[0]!.name).toBe("decide_item");
      expect(c.actions[0]!.context.itemId).toBe("01item1");
      expect(main.querySelector("[data-update-item]")?.getAttribute("data-status")).toBe("pending");
    });

    it("announces the decision politely and moves focus to the next undecided item", () => {
      vi.useFakeTimers();
      try {
        const c = ctx();
        document.body.innerHTML = "";
        const main = document.createElement("main");
        for (const id of ["a", "b"]) main.append(renderSurface([{ id: "root", component: "UpdateItem", ...builderFixtures.UpdateItem!, itemId: id, label: `Item ${id}` } as FlatComponent], c));
        document.body.append(main);
        const [first, second] = [...main.querySelectorAll("[data-update-item]")] as HTMLElement[];
        find(first!, "Accept").click();
        vi.advanceTimersByTime(60);
        expect(first!.querySelector("[aria-live]")?.textContent).toBe("Accepted: Item a.");
        expect(document.activeElement).toBe(second!.querySelector("[data-focus-target]"));
      } finally {
        vi.useRealTimers();
      }
    });

    it("ask for changes opens a labelled field, sends the comment and closes on Escape", () => {
      const c = ctx();
      const main = mount(item(), c);
      const toggle = find(main, "Ask for changes");
      expect(toggle.getAttribute("aria-expanded")).toBe("false");
      toggle.click();
      expect(toggle.getAttribute("aria-expanded")).toBe("true");
      const area = main.querySelector("textarea") as HTMLTextAreaElement;
      expect(main.querySelector(`label[for="${area.id}"]`)?.textContent).toBe("What should change?");
      expect(document.activeElement).toBe(area);
      area.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape", bubbles: true }));
      expect(toggle.getAttribute("aria-expanded")).toBe("false");
      expect(document.activeElement).toBe(toggle);
      toggle.click();
      area.value = "  Keep the second example ";
      main.querySelector("form")!.dispatchEvent(new Event("submit", { cancelable: true }));
      expect(c.actions.at(-1)).toMatchObject({ name: "regenerate_item", context: { itemId: "01item1", comment: "Keep the second example" } });
    });

    it("stops asking after the limit and points to the workspace", () => {
      const main = mount(item({ regenerations: 3 }));
      expect((find(main, "Ask for changes") as HTMLButtonElement).disabled).toBe(true);
      expect(main.textContent).toContain("You asked 3 times. Edit it by hand in the workspace.");
      expect(main.querySelector('a[href="/studio/s/01s/workspace"]')?.textContent).toBe("Open in the workspace");
    });

    it("conflict and stale items explain what to do and cannot be accepted", () => {
      const conflict = mount(item({ status: "conflict" }));
      expect(conflict.querySelector('[role="alert"]')?.textContent).toContain("You edited this element after the analysis");
      expect((find(conflict, "Accept") as HTMLButtonElement).disabled).toBe(true);
      const stale = mount(item({ status: "stale" }));
      expect(stale.textContent).toContain("Edited after the analysis");
      expect(find(stale, "Regenerate against your edit")).toBeTruthy();
    });

    it.each([
      ["citation_remap", "Only the citations change", "Citation update"],
      ["no_change", "AI found no change needed", "No change needed"],
      ["remove", "Accepting removes the element", "Removal"],
      ["manual", "needs an update by hand", "Update by hand"],
      ["uncovered", "no lesson covers yet", "New in the source"],
    ])("presents a %s item", (kind, note, badge) => {
      const main = mount(item({ kind, fields: [], answerCheck: false, answerChanged: false, flags: [], signals: [] }));
      expect(main.textContent).toContain(note);
      expect(main.querySelector(".cb-item-kind")?.textContent).toBe(badge);
    });

    it("read-only items show no decision controls", () => {
      const main = mount(item({ canDecide: false }));
      expect(main.querySelector("[aria-pressed]")).toBeNull();
      expect(main.textContent).toContain("Decisions open when the analysis is finished.");
    });
  });

  it("impact summary states counts and cost, and only shows learner impact when given", () => {
    const main = mount(single("ImpactSummary"));
    expect(main.textContent).toContain("6 elements may need an update across 3 lessons, and 2 quiz answers may now be wrong.");
    expect(main.textContent).toContain("Answers to check");
    expect(main.textContent).toContain("$0.42");
    expect(main.textContent).toContain("$0.13");
    expect(main.textContent).toContain("Learner impact: 12 learners completed lesson 2.");
    const bare = mount(single("ImpactSummary", { elements: 1, answerChecks: 0, uncovered: 0 }));
    expect(bare.textContent).toContain("1 element may need an update.");
    expect(bare.textContent).not.toContain("Learner impact");
    expect(bare.textContent).not.toContain("Estimated cost");
  });

  it("staleness badge puts the state in words", () => {
    expect(mount(single("StalenessBadge")).textContent).toContain("Stale · 3 days");
    expect(mount(single("StalenessBadge")).querySelector("a")?.getAttribute("href")).toBe("/studio/s/01s/updates");
    expect(mount(single("StalenessBadge", { state: "in_sync" })).textContent).toBe("In sync");
    expect(mount(single("StalenessBadge", { state: "dismissed" })).textContent).toBe("Updates dismissed");
    expect(mount(single("StalenessBadge", { state: "stale", days: 1 })).textContent).toContain("Stale · 1 day");
  });

  it("diff states carry text, not only colour", () => {
    const main = mount(single("DiffView"));
    expect(main.querySelector("del")?.textContent).toContain("removed:");
    expect(main.querySelector("ins")?.textContent).toContain("added:");
    expect(main.textContent).toContain("Changed");
  });
});
