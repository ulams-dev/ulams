/**
 * The editable Course Brief: each row of the brief panel opens the same catalogue control the
 * interview used. `briefQuestion` builds that control's props from the current brief (options are
 * fixed lists here; the interview's source-specific suggestions are not needed to edit), and
 * `briefPatch` turns the control's answer into the partial brief `PUT …/brief` expects.
 */
import { renderSurface, type FlatComponent } from "./renderer.ts";
import type { A2uiActionOut, BuilderContext } from "./components.ts";
import { THEME_PRESETS } from "../theme/presets.ts";

export type BriefKey = "audience" | "level" | "duration" | "tone" | "assessments" | "language" | "pricing" | "theme";
export const EDITABLE_KEYS: ReadonlyArray<BriefKey> = ["audience", "level", "duration", "tone", "assessments", "language", "pricing", "theme"];

type Brief = Record<string, any>; // eslint-disable-line @typescript-eslint/no-explicit-any

const LANGUAGES: Array<[string, string]> = [["en", "English"], ["pl", "Polski"], ["de", "Deutsch"], ["es", "Español"], ["fr", "Français"], ["it", "Italiano"], ["pt", "Português"], ["nl", "Nederlands"], ["uk", "Українська"], ["cs", "Čeština"]];
const LEVELS = ["beginner", "intermediate", "advanced"];
const TONES = ["friendly", "professional", "playful", "academic"];
const cap = (v: string): string => v.charAt(0).toUpperCase() + v.slice(1);

export const isEditable = (key: string): key is BriefKey => (EDITABLE_KEYS as ReadonlyArray<string>).includes(key);

export function briefQuestion(key: BriefKey, brief: Brief): { component: string; props: Record<string, unknown> } {
  const base = { questionKey: key, status: "open", why: "You can change this at any time." };
  switch (key) {
    case "audience":
      return { component: "ChoiceChips", props: { ...base, label: "Who is this course for?", options: [], multiple: false, allowCustom: true, defaultValue: [String(brief.audience ?? "")].filter(Boolean) } };
    case "level":
      return { component: "SingleChoice", props: { ...base, label: "What level is it?", options: LEVELS.map((v) => ({ value: v, label: cap(v) })), value: brief.level, defaultValue: String(brief.level ?? "beginner") } };
    case "tone":
      return { component: "ChoiceChips", props: { ...base, label: "Which tone?", options: TONES.map((v) => ({ value: v, label: cap(v) })), multiple: false, value: [brief.tone].filter(Boolean), defaultValue: [String(brief.tone ?? "friendly")] } };
    case "duration": {
      const value = { totalMinutes: Number(brief.totalMinutes ?? 60), lessonMinutes: Number(brief.lessonMinutes ?? 10) };
      return { component: "DurationSlider", props: { ...base, label: "How long should it be?", totalOptions: [...new Set([15, 30, 60, 120, 240, value.totalMinutes])].sort((a, b) => a - b).slice(0, 6), lessonOptions: [5, 10, 15, 20], value, defaultValue: value } };
    }
    case "assessments": {
      const chosen = [brief.assessments?.perLessonQuiz ? "quiz" : null, brief.assessments?.finalTest ? "final" : null].filter(Boolean) as string[];
      return { component: "ChoiceChips", props: { ...base, label: "Which assessments?", options: [{ value: "quiz", label: "Quiz after each lesson" }, { value: "final", label: "Final test" }], multiple: true, value: chosen, defaultValue: chosen } };
    }
    case "language":
      return { component: "LanguagePicker", props: { ...base, label: "Which language?", options: LANGUAGES.map(([value, label]) => ({ value, label })), value: brief.language, defaultValue: String(brief.language ?? "en") } };
    case "pricing":
      return { component: "PriceInput", props: { ...base, label: "Free or paid?", currency: String(brief.pricing?.currency ?? "USD"), value: brief.pricing ?? { mode: "free" }, defaultValue: { mode: "free" } } };
    case "theme":
      return { component: "ThemePicker", props: { ...base, label: "Which look should the site have?", presets: Object.entries(THEME_PRESETS).map(([value, p]) => ({ value, label: p.label })), value: brief.theme ?? { preset: "coffee" }, defaultValue: { preset: "coffee" } } };
  }
}

/** The partial brief for `PUT …/brief` from a control's answer. */
export function briefPatch(key: BriefKey, value: unknown): Brief {
  switch (key) {
    case "audience":
      return { audience: String(Array.isArray(value) ? value.join(", ") : value).slice(0, 200) };
    case "level":
    case "tone":
    case "language":
      return { [key]: String(Array.isArray(value) ? value[0] : value) };
    case "duration":
      return { totalMinutes: Number((value as Brief).totalMinutes), lessonMinutes: Number((value as Brief).lessonMinutes) };
    case "assessments": {
      const list = Array.isArray(value) ? value : [];
      return { assessments: { perLessonQuiz: list.includes("quiz"), finalTest: list.includes("final") } };
    }
    case "pricing":
    case "theme":
      return { [key]: value };
  }
}

/**
 * Renders the control for one brief field. `onSave` receives the partial brief; "Decide for me" is
 * not offered here (an edit is always an explicit choice), so the card's default action is ignored.
 */
export function renderBriefEditor(key: BriefKey, brief: Brief, onSave: (patch: Brief) => void, onCancel: () => void): HTMLElement {
  const { component, props } = briefQuestion(key, brief);
  const ctx: BuilderContext = {
    surfaceId: "brief",
    dispatch: (action: A2uiActionOut) => {
      if (action.name === "answer") onSave(briefPatch(key, action.context.value));
    },
  };
  const surface = renderSurface([{ id: "root", component, ...props } as FlatComponent], ctx);
  const cancel = document.createElement("button");
  cancel.type = "button";
  cancel.className = "cb-btn cb-btn-ghost";
  cancel.textContent = "Cancel";
  cancel.addEventListener("click", onCancel);
  surface.querySelectorAll(".cb-actions .cb-btn-ghost").forEach((button) => button.remove()); // "Decide for me"
  const submit = surface.querySelector(".cb-actions .cb-btn-primary");
  if (submit) submit.textContent = "Save";
  surface.querySelector(".cb-actions")?.append(cancel);
  return surface;
}
