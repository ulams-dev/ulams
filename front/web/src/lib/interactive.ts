/**
 * Interactive topics (ADR 0086) for the lesson player: the launch calls (server side, with the learner's
 * token) and the catalogue node for the topic. The frame never gets the token; progress is reported by the
 * page through the BFF (see front/ui/src/elements/interactive.ts).
 */
import type { Course, Tenant } from "@ulams/sdk";
import type { UiNode } from "@ulams/ui/render-core";

type Localised = Record<string, string>;

export interface InteractiveManifest {
  title: Localised;
  steps: Array<{ id: string; title: Localised; text: Localised; poster?: string }>;
  capabilities: { reducedMotion?: boolean; [key: string]: unknown };
  requires: string[];
  locales: string[];
  defaultLocale: string;
  licence: string;
  attribution?: string | null;
  source?: { url: string; ref?: string } | null;
}

export interface InteractiveLaunch {
  url: string;
  version: number;
  manifest: InteractiveManifest;
  topic: {
    start_step: string | null;
    end_step: string | null;
    completion_rule: string;
    pass_score: number | null;
    display: "inline" | "background";
    height: number;
    text: string | null;
  };
}

export type InteractiveResult = InteractiveLaunch | { error: string };

const FAILED = "This interactive cannot be opened right now.";

/** Starts an Interactive topic: the entry file on the tenant content origin plus the manifest's steps. */
export async function interactiveLaunch(tenant: Tenant, token: string, topicId: number): Promise<InteractiveResult> {
  try {
    const response = await fetch(`${tenant.apiUrl}/api/interactive/launches/${topicId}`, {
      method: "POST",
      headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
      signal: AbortSignal.timeout(8000),
    });
    const body = (await response.json().catch(() => null)) as { data?: InteractiveLaunch; message?: string } | null;
    if (response.ok && typeof body?.data?.url === "string") return body.data;
    return { error: body?.message ?? FAILED };
  } catch {
    return { error: FAILED };
  }
}

/**
 * Author preview of a package version through the admin preview endpoint (nothing is tracked). The
 * topic's own settings come from the topic, since the endpoint knows only the package.
 */
export async function interactivePreview(
  tenant: Tenant,
  token: string,
  topic: { value: number; version?: number | null; follow_latest?: boolean; start_step?: string | null; end_step?: string | null; completion_rule?: string; pass_score?: number | null; display?: string; height?: number; text?: string | null }
): Promise<InteractiveResult> {
  try {
    const query = topic.version && !topic.follow_latest ? `?version=${topic.version}` : "";
    const response = await fetch(`${tenant.apiUrl}/api/admin/interactive/${topic.value}/preview${query}`, {
      headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
      signal: AbortSignal.timeout(8000),
    });
    const body = (await response.json().catch(() => null)) as { data?: { url?: string; version?: number; manifest?: InteractiveManifest }; message?: string } | null;
    const data = body?.data;
    if (!response.ok || typeof data?.url !== "string" || !data.manifest) return { error: body?.message ?? FAILED };
    const base = data.url.replace(/[^/]*$/, "");
    return {
      url: data.url,
      version: data.version ?? 1,
      manifest: { ...data.manifest, steps: data.manifest.steps.map((s) => (s.poster ? { ...s, poster: base + s.poster } : s)) },
      topic: {
        start_step: topic.start_step ?? null,
        end_step: topic.end_step ?? null,
        completion_rule: topic.completion_rule ?? "on_range_end",
        pass_score: topic.pass_score ?? null,
        display: topic.display === "background" ? "background" : "inline",
        height: topic.height ?? 640,
        text: topic.text ?? null,
      },
    };
  } catch {
    return { error: FAILED };
  }
}

/** The locale the texts are shown in: the course language when the package has it, else its default. */
export function pickLocale(manifest: Pick<InteractiveManifest, "locales" | "defaultLocale">, courseLanguage?: string | null): string {
  const wanted = (courseLanguage ?? "").toLowerCase().slice(0, 2);
  return manifest.locales.includes(wanted) ? wanted : manifest.defaultLocale;
}

const pick = (value: Localised | undefined, locale: string, fallback: string): string => value?.[locale] ?? value?.[fallback] ?? Object.values(value ?? {})[0] ?? "";

/** Whether the lesson page switches to the full-bleed layout (the package is the page background). */
export const isImmersive = (result: InteractiveResult | null | undefined): boolean => Boolean(result && "url" in result && result.topic.display === "background");

/** The catalogue node of an Interactive topic; an error becomes a Callout (the lesson text still shows). */
export function interactiveNode(result: InteractiveResult | null, input: { title: string; course: Course; topicId: number; preview: boolean }): UiNode {
  if (!result || "error" in result) {
    return {
      component: "Callout",
      props: { tone: "warning", title: input.title, text: result && "error" in result ? result.error : "This interactive opens once you are enrolled." },
    };
  }
  const m = result.manifest;
  const locale = pickLocale(m, input.course.language);
  return {
    component: "InteractiveLesson",
    props: {
      src: result.url,
      title: input.title,
      ...(input.preview ? {} : { topicId: input.topicId, courseId: input.course.id }),
      display: result.topic.display,
      height: result.topic.height,
      ...(result.topic.start_step ? { startStep: result.topic.start_step } : {}),
      ...(result.topic.end_step ? { endStep: result.topic.end_step } : {}),
      steps: m.steps.map((s) => ({
        id: s.id,
        title: pick(s.title, locale, m.defaultLocale),
        text: pick(s.text, locale, m.defaultLocale),
        ...(s.poster ? { poster: s.poster } : {}),
      })),
      ...(result.topic.text ? { text: result.topic.text } : {}),
      requires: m.requires,
      reducedMotionSupported: m.capabilities.reducedMotion === true,
      locale,
      licence: m.licence,
      ...(m.attribution ? { attribution: m.attribution } : {}),
      ...(m.source?.url ? { sourceUrl: m.source.url } : {}),
    },
  };
}
