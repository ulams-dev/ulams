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

  it("diff states carry text, not only colour", () => {
    const main = mount(single("DiffView"));
    expect(main.querySelector("del")?.textContent).toContain("removed:");
    expect(main.querySelector("ins")?.textContent).toContain("added:");
    expect(main.textContent).toContain("Changed");
  });
});
