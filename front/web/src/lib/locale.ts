/**
 * The language of the learner's interface strings (ADR 0106). A lesson is read in the language of its course,
 * so the strings around the course content (the "Extra help for you" card, later the tutor and the lesson
 * view) follow the course language: `pl` or `zh` when the course language starts with that code, else `en`.
 * A course without a language falls back to the tenant default; account pages use the tenant default.
 */
import type { UiLocale } from "@ulams/ui/i18n";

const SUPPORTED: UiLocale[] = ["pl", "zh"];
/** Courses may name the language instead of giving its code. */
const NAMES: Record<string, UiLocale> = { polish: "pl", polski: "pl", chinese: "zh", english: "en" };

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
  learnerLocale(null, tenantLanguage(settings));

/** The tenant default language of the public settings (`global.language`), when it is a string. */
export const tenantLanguage = (settings: { global?: Record<string, unknown> } | null | undefined): string | null =>
  typeof settings?.global?.language === "string" ? settings.global.language : null;

/**
 * The language of a learner page (course, lesson, finish): the course language, else the tenant default, else
 * English (ADR 0106, amended: the lesson player's strings are translated, so `<html lang>` follows it).
 */
export const learnerPageLocale = (courseLanguage: string | null | undefined, settings: { global?: Record<string, unknown> } | null | undefined): UiLocale =>
  learnerLocale(courseLanguage, tenantLanguage(settings));

/**
 * The language tag to put on content that is not in the interface language: a course in German on an English
 * interface gets `lang="de"` on its title and text. Undefined when the course has no usable language tag or
 * its language is the interface language.
 */
export function contentLang(courseLanguage: string | null | undefined, interfaceLocale: UiLocale): string | undefined {
  const raw = (courseLanguage ?? "").trim();
  if (raw === "") return undefined;
  const named = NAMES[raw.toLowerCase()];
  const tag = named ?? raw.replace(/_/g, "-");
  if (!/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*$/.test(tag)) return undefined;
  return tag.toLowerCase().split("-")[0] === interfaceLocale ? undefined : tag;
}
