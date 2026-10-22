/**
 * The contrast report of the admin theme screen: WCAG 2.2 ratios of an accent colour on a preset's own
 * backgrounds, with what exactly fails and the nearest shade that passes. The admin refuses to save an
 * accent while `failures` is not empty; the learner site adjusts a weak accent on its own
 * (`front/web/src/lib/accent.ts`), so the report tells the admin what they get *before* that happens.
 */
import { contrast, adjustAccent, parseHex, toHex, type Rgb } from "./contrast.ts";
import { THEME_PRESETS, type TenantThemeName } from "./presets.ts";

export interface ReportCheck {
  id: "accent-text-page" | "accent-text-card" | "accent-ui-page" | "on-accent" | "preset-text" | "preset-muted";
  label: string;
  /** The two colours compared, as hex. */
  foreground: string;
  background: string;
  ratio: number;
  required: number;
  /** WCAG 2.2 success criterion the requirement comes from. */
  criterion: string;
  pass: boolean;
  /** A failing check with `blocking` stops the save; the preset's own rows only inform. */
  blocking: boolean;
  /** One sentence for the screen: what was measured and, on a failure, what is missing. */
  message: string;
}

export interface ContrastReport {
  /** False when the accent is not a six digit hex colour: there is nothing to measure. */
  valid: boolean;
  checks: ReportCheck[];
  failures: ReportCheck[];
  /** The nearest accent that passes every blocking check, or null when the accent already does (or is invalid). */
  suggestion: string | null;
}

const round = (n: number) => Math.round(n * 100) / 100;
const WHITE: Rgb = [255, 255, 255];
const BLACK: Rgb = [0, 0, 0];

export const TEXT_RATIO = 4.5;
export const NON_TEXT_RATIO = 3;

function check(c: Omit<ReportCheck, "ratio" | "pass" | "message"> & { a: Rgb; b: Rgb; subject: string; place: string }): ReportCheck {
  const ratio = round(contrast(c.a, c.b));
  const pass = ratio >= c.required;
  const { a: _a, b: _b, subject, place, ...rest } = c;
  const message = pass
    ? `${subject} on ${place}: ${ratio}:1 (needs ${c.required}:1, WCAG ${c.criterion}).`
    : `${subject} on ${place} is ${ratio}:1, below the ${c.required}:1 that WCAG 2.2 AA (${c.criterion}) asks for.`;
  return { ...rest, ratio, pass, message };
}

export function contrastReport(theme: TenantThemeName, accent: unknown): ContrastReport {
  const preset = THEME_PRESETS[theme];
  const checks: ReportCheck[] = [];
  const bg = parseHex(preset.bg)!;
  const card = parseHex(preset.card)!;
  checks.push(
    check({ id: "preset-text", label: "Body text on the page", foreground: preset.text, background: preset.bg, required: TEXT_RATIO, criterion: "1.4.3", blocking: false, a: parseHex(preset.text)!, b: bg, subject: "Body text", place: "the page background" }),
    check({ id: "preset-muted", label: "Secondary text on the page", foreground: preset.muted, background: preset.bg, required: TEXT_RATIO, criterion: "1.4.3", blocking: false, a: parseHex(preset.muted)!, b: bg, subject: "Secondary text", place: "the page background" })
  );
  const rgb = parseHex(accent);
  if (!rgb) return { valid: false, checks, failures: [], suggestion: null };
  const hex = toHex(rgb);
  const onAccent = contrast(rgb, WHITE) >= contrast(rgb, BLACK) ? WHITE : BLACK;
  checks.unshift(
    check({ id: "accent-text-page", label: "Accent as text or link on the page", foreground: hex, background: preset.bg, required: TEXT_RATIO, criterion: "1.4.3", blocking: true, a: rgb, b: bg, subject: `Accent ${hex} as text`, place: "the page background" }),
    check({ id: "accent-text-card", label: "Accent as text or link on a card", foreground: hex, background: preset.card, required: TEXT_RATIO, criterion: "1.4.3", blocking: true, a: rgb, b: card, subject: `Accent ${hex} as text`, place: "a card" }),
    check({ id: "accent-ui-page", label: "Accent as border, icon or focus ring", foreground: hex, background: preset.bg, required: NON_TEXT_RATIO, criterion: "1.4.11", blocking: true, a: rgb, b: bg, subject: `Accent ${hex} as a border or icon`, place: "the page background" }),
    check({ id: "on-accent", label: "Text on an accent button", foreground: toHex(onAccent), background: hex, required: TEXT_RATIO, criterion: "1.4.3", blocking: true, a: onAccent, b: rgb, subject: `${onAccent === WHITE ? "White" : "Black"} text`, place: `an ${hex} button` })
  );
  const failures = checks.filter((c) => c.blocking && !c.pass);
  // 5:1 on the page leaves room for the slightly darker card tone, as the learner site does
  const adjusted = failures.length > 0 ? adjustAccent(hex, preset.bg, 5) : null;
  const suggestion = adjusted && adjusted.adjusted && contrast(parseHex(adjusted.value)!, card) >= TEXT_RATIO ? adjusted.value : null;
  return { valid: true, checks, failures, suggestion };
}

/** Accent as the admin screen saves it: lower-case six digit hex, or null. */
export const normaliseAccent = (value: unknown): string | null => {
  const rgb = parseHex(value);
  return rgb ? toHex(rgb) : null;
};
