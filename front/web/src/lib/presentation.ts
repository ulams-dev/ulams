/**
 * The adaptive lesson view (Learner Insights presentation profile, M-UI4). The API derives a suggestion from
 * the learner's progress when the tenant has adaptive presentation on, and keeps the learner's own choices on
 * top; this module reads it for a lesson page, validates the "Lesson view" form and applies the profile to the
 * lesson document with catalogue components only (no model call). Every failure means the default view.
 */
import { createInsightsClient, type Presentation, type PresentationChange, type PresentationState, type Tenant } from "@ulams/sdk";
import { splitMarkdownSections } from "@ulams/ui/markdown";
import type { UiLocale } from "@ulams/ui/i18n";
import type { UiNode } from "@ulams/ui/render-core";
import { tenantFetch } from "./api-transport.ts";

export const PRESENTATION_TIMEOUT_MS = 1_500;

export const DEFAULT_PRESENTATION: Presentation = { density: "comfortable", chunking: "whole", navigation: "overview", hints: 0 };

const OPTIONS = {
  density: ["comfortable", "compact"],
  chunking: ["whole", "steps"],
  navigation: ["overview", "linear"],
  hints: [0, 1, 2, 3],
} as const;

const client = (tenant: Tenant, token: string, locale?: UiLocale) =>
  createInsightsClient({ baseUrl: tenant.apiUrl, fetch: tenantFetch(tenant), token, timeoutMs: PRESENTATION_TIMEOUT_MS, ...(locale ? { headers: { "X-Locale": locale } } : {}) });

const isOption = <K extends keyof Presentation>(key: K, value: unknown): value is Presentation[K] => (OPTIONS[key] as readonly unknown[]).includes(value);

/** Keeps only what the page understands; anything else falls back to the default of that setting. */
function clean(value: Partial<Presentation> | null | undefined): Presentation {
  const out: Presentation = { ...DEFAULT_PRESENTATION };
  const v = (value ?? {}) as Record<string, unknown>;
  if (isOption("density", v.density)) out.density = v.density;
  if (isOption("chunking", v.chunking)) out.chunking = v.chunking;
  if (isOption("navigation", v.navigation)) out.navigation = v.navigation;
  if (isOption("hints", v.hints)) out.hints = v.hints;
  return out;
}

/** The learner's presentation for a course, or `null` when the module is off, the API is older or the call fails. */
export async function getPresentation(tenant: Tenant, token: string, courseId: number, locale?: UiLocale): Promise<PresentationState | null> {
  try {
    const state = await client(tenant, token, locale).presentation(courseId);
    if (!state || typeof state !== "object" || !state.profile) return null;
    return {
      adaptive: state.adaptive === true,
      profile: clean(state.profile),
      derived: clean(state.derived),
      overrides: Object.fromEntries(Object.entries(state.overrides ?? {}).filter(([k, v]) => k in OPTIONS && isOption(k as keyof Presentation, v))) as Partial<Presentation>,
      why: Array.isArray(state.why) ? state.why.filter((w): w is string => typeof w === "string").slice(0, 6) : [],
    };
  } catch {
    return null;
  }
}

/** What the page applies: the profile, or the default view without the module. */
export const profileOf = (state: PresentationState | null): Presentation => state?.profile ?? DEFAULT_PRESENTATION;

export const isDefault = (p: Presentation): boolean => (Object.keys(DEFAULT_PRESENTATION) as Array<keyof Presentation>).every((k) => p[k] === DEFAULT_PRESENTATION[k]);

/** The suggestion is on: the tenant derives it and it differs from the default view. */
export const hasSuggestion = (state: PresentationState | null): boolean => Boolean(state?.adaptive) && !isDefault(state!.derived);

export type FormAction = { kind: "save"; change: PresentationChange } | { kind: "reset" } | { kind: "whole" };

/**
 * Reads the Lesson view form. `save` sends the four groups; a group left out of the post is not changed;
 * `reset` removes every choice of the learner; `whole` is the one-click "Show the whole lesson".
 */
export function parsePresentationForm(form: FormData): FormAction | null {
  const action = String(form.get("action") ?? "");
  if (action === "reset") return { kind: "reset" };
  if (action === "whole") return { kind: "whole" };
  if (action !== "save") return null;
  const change: PresentationChange = {};
  const density = form.get("density");
  if (isOption("density", density)) change.density = density;
  const chunking = form.get("chunking");
  if (isOption("chunking", chunking)) change.chunking = chunking;
  const navigation = form.get("navigation");
  if (isOption("navigation", navigation)) change.navigation = navigation;
  const hints = Number(form.get("hints"));
  if (form.get("hints") !== null && form.get("hints") !== "" && isOption("hints", hints)) change.hints = hints;
  return Object.keys(change).length > 0 ? { kind: "save", change } : null;
}

export const RESET_CHANGE: PresentationChange = { density: null, chunking: null, navigation: null, hints: null };

export async function savePresentationChange(tenant: Tenant, token: string, courseId: number, action: FormAction): Promise<boolean> {
  const change: PresentationChange = action.kind === "reset" ? RESET_CHANGE : action.kind === "whole" ? { chunking: "whole" } : action.change;
  try {
    return (await client(tenant, token).savePresentation(courseId, change)) !== null;
  } catch {
    return false;
  }
}

/** Where the form returns to: the course page or one of its lessons (never an open redirect). */
export function safeReturn(next: unknown, courseId: number): string {
  const fallback = `/learn/${courseId}`;
  if (typeof next !== "string") return fallback;
  const path = next.split(/[?#]/)[0]!;
  return new RegExp(`^/(learn/${courseId}(/\\d+)?|courses/${courseId})$`).test(path) ? path : fallback;
}

/** A reading's Prose, as parts when the learner wants them and the text has 2 to 12 H2/H3 sections; else whole. */
export function proseNode(markdown: string, props: { dropCap?: boolean; size?: "sm" | "md" | "lg" }, presentation: Presentation, locale: UiLocale): UiNode {
  if (presentation.chunking === "steps") {
    const { intro, sections } = splitMarkdownSections(markdown);
    if (sections.length >= 2 && sections.length <= 12) {
      return {
        component: "ProseSteps",
        props: { ...(intro ? { intro } : {}), sections: sections.map((s) => ({ heading: s.heading, level: s.level, markdown: s.body })), ...(props.size ? { size: props.size } : {}), locale },
      };
    }
  }
  return { component: "Prose", props: { markdown, ...props } };
}

/** `hints=N` on every practice activity of the lesson. */
export function withInitialHints(nodes: UiNode[], hints: number): UiNode[] {
  if (hints <= 0) return nodes;
  return nodes.map((node) => (node.component === "PracticeActivity" ? { ...node, props: { ...node.props, initialHints: hints } } : node));
}
