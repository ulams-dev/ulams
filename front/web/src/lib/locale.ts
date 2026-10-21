/**
 * The language of the learner's interface strings (ADR 0106). A lesson is read in the language of its course,
 * so the strings around the course content (the "Extra help for you" card, later the tutor and the lesson
 * view) follow the course language: `pl` or `zh` when the course language starts with that code, else `en`.
 * A course without a language falls back to the tenant default; account pages use the tenant default.
 */
import type { UiLocale } from "@ulams/ui/i18n";

const SUPPORTED: UiLocale[] = ["pl", "zh"];
/** Courses may name the language instead of giving its code. */
const NAMES: Record<string, UiLocale> = { polish: "pl", polski: "pl", chinese: "zh" };

const normalise = (language: string | null | undefined): UiLocale | null => {
  const code = (language ?? "").trim().toLowerCase();
  if (code === "") return null;
  return NAMES[code] ?? SUPPORTED.find((l) => code === l || code.startsWith(`${l}-`) || code.startsWith(`${l}_`)) ?? "en";
};

export function learnerLocale(courseLanguage?: string | null, tenantDefault?: string | null): UiLocale {
  return normalise(courseLanguage) ?? normalise(tenantDefault) ?? "en";
}

/** The language of the account pages: the tenant default (`global.language` of the public settings), else English. */
export const accountLocale = (settings: { global?: Record<string, unknown> } | null | undefined): UiLocale =>
  learnerLocale(null, typeof settings?.global?.language === "string" ? settings.global.language : null);
