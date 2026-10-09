// @vitest-environment jsdom
import { describe, expect, it, vi } from "vitest";
import axe from "axe-core";
import { validate } from "../src/schema.ts";
import { builderCatalogue } from "../src/builder/catalogue.ts";
import { briefPatch, briefQuestion, EDITABLE_KEYS, renderBriefEditor } from "../src/builder/brief-editor.ts";

const brief = {
  audience: "home baristas",
  level: "beginner",
  totalMinutes: 60,
  lessonMinutes: 10,
  tone: "friendly",
  assessments: { perLessonQuiz: true, finalTest: false, passScore: 70 },
  language: "en",
  pricing: { mode: "paid", amountMinor: 4900, currency: "USD" },
  theme: { preset: "oncall" },
};

describe("brief editor", () => {
  it("builds valid catalogue props for every editable field", () => {
    for (const key of EDITABLE_KEYS) {
      const { component, props } = briefQuestion(key, brief);
      const spec = builderCatalogue[component as keyof typeof builderCatalogue];
      expect(validate(spec.props, props).issues, key).toEqual([]);
    }
  });

  it("turns answers into partial briefs", () => {
    expect(briefPatch("duration", { totalMinutes: 120, lessonMinutes: 15 })).toEqual({ totalMinutes: 120, lessonMinutes: 15 });
    expect(briefPatch("assessments", ["final"])).toEqual({ assessments: { perLessonQuiz: false, finalTest: true } });
    expect(briefPatch("audience", ["home baristas", "cafe staff"])).toEqual({ audience: "home baristas, cafe staff" });
    expect(briefPatch("pricing", { mode: "free" })).toEqual({ pricing: { mode: "free" } });
    expect(briefPatch("tone", ["academic"])).toEqual({ tone: "academic" });
  });

  it("saves through the same control, offers Save and Cancel, and has no WCAG violations", async () => {
    const onSave = vi.fn();
    const onCancel = vi.fn();
    document.body.innerHTML = "<main></main>";
    const el = renderBriefEditor("level", brief, onSave, onCancel);
    document.querySelector("main")!.append(el);
    const labels = [...el.querySelectorAll("button")].map((b) => b.textContent);
    expect(labels).toContain("Save");
    expect(labels).toContain("Cancel");
    expect(labels.join(" ")).not.toMatch(/Decide for me/);
    (el.querySelector('input[value="advanced"]') as HTMLInputElement).click();
    (el.matches("form") ? el : el.querySelector("form")!).dispatchEvent(new Event("submit", { cancelable: true }));
    expect(onSave).toHaveBeenCalledWith({ level: "advanced" });
    [...el.querySelectorAll("button")].find((b) => b.textContent === "Cancel")!.click();
    expect(onCancel).toHaveBeenCalled();
    const result = await axe.run(document.body, { rules: { "color-contrast": { enabled: false } } });
    expect(result.violations.map((v) => v.id)).toEqual([]);
  });
});
