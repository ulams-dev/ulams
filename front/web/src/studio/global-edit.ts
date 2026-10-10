/** Whole-course edits (L2-15): the choices of the form, and the text shown for an estimate. Pure, so it is unit tested. */
export type GlobalKind = "translate" | "change_level" | "change_tone" | "custom";

export const KIND_LABEL: Record<GlobalKind, string> = {
  translate: "Translate the course",
  change_level: "Change the level",
  change_tone: "Change the tone",
  custom: "Something else",
};

const LANGUAGES: Array<[string, string]> = [["en", "English"], ["pl", "Polish"], ["de", "German"], ["fr", "French"], ["es", "Spanish"], ["it", "Italian"], ["pt", "Portuguese"], ["nl", "Dutch"], ["cs", "Czech"], ["uk", "Ukrainian"]];
const LEVELS: Array<[string, string]> = [["beginner", "Beginner"], ["intermediate", "Intermediate"], ["advanced", "Advanced"]];
const TONES: Array<[string, string]> = [["friendly", "Friendly"], ["professional", "Professional"], ["playful", "Playful"], ["academic", "Academic"]];

/** The options of the value select for a kind (none for a free instruction, which has a text box). */
export function valueChoices(kind: GlobalKind): Array<[string, string]> {
  return kind === "translate" ? LANGUAGES : kind === "change_level" ? LEVELS : kind === "change_tone" ? TONES : [];
}

export function usd(micro: number): string {
  return `$${(micro / 1_000_000).toFixed(2)}`;
}

/** "12 model calls, about $0.84" for the estimate line. */
export function estimateText(steps: number, estimateMicroUsd: number): string {
  return `${steps} model call${steps === 1 ? "" : "s"}, about ${usd(estimateMicroUsd)}`;
}
