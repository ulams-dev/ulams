import { tenantFetch } from "./api-transport.ts";
/**
 * Code exercise topics (ADR 0170) for the lesson player: the launch call (server side, with the learner's
 * token) or the author's preview, and the catalogue node of the topic. The runner frame never gets the
 * token; a finished run is reported by the page through the BFF (see front/ui/src/elements/code-exercise.ts).
 */
import type { Course, Tenant } from "@ulams/sdk";
import type { UiNode } from "@ulams/ui/render-core";

export interface ExerciseFileData {
  path: string;
  content: string;
}

export interface CodeExerciseLaunch {
  runner: { url: string; version: number };
  exercise: {
    language: "javascript" | "typescript";
    files: ExerciseFileData[];
    tests: ExerciseFileData[];
    timeout_ms: number;
    completion_rule: "on_pass" | "on_attempt";
    pass_score: number;
    text: string | null;
  };
  /** Absent in a preview. */
  attempts?: { count: number; best_score: number | null; last: unknown } | null;
}

export type CodeExerciseResult = CodeExerciseLaunch | { error: string };

const FAILED = "This exercise cannot be opened right now.";

async function call(tenant: Tenant, url: string, init: RequestInit): Promise<CodeExerciseResult> {
  try {
    const response = await tenantFetch(tenant)(url, { ...init, signal: AbortSignal.timeout(8000) });
    const body = (await response.json().catch(() => null)) as { data?: CodeExerciseLaunch; message?: string } | null;
    if (response.ok && typeof body?.data?.runner?.url === "string" && Array.isArray(body.data.exercise?.files)) return body.data;
    return { error: body?.message ?? FAILED };
  } catch {
    return { error: FAILED };
  }
}

/** Opens a code exercise for a learner: the runner's address on the tenant content origin plus the exercise. */
export const codeExerciseLaunch = (tenant: Tenant, token: string, topicId: number): Promise<CodeExerciseResult> =>
  call(tenant, `${tenant.apiUrl}/api/code-exercises/launches/${topicId}`, { method: "POST", headers: { Accept: "application/json", Authorization: `Bearer ${token}` } });

/** The author's preview of a topic (nothing is tracked). */
export const codeExercisePreview = (tenant: Tenant, token: string, topicId: number): Promise<CodeExerciseResult> =>
  call(tenant, `${tenant.apiUrl}/api/admin/code-exercises/topics/${topicId}/preview`, { headers: { Accept: "application/json", Authorization: `Bearer ${token}` } });

/** The catalogue node of a code exercise; an error becomes a Callout (the topic's own text still shows). */
export function codeExerciseNode(result: CodeExerciseResult | null, input: { title: string; course: Course; topicId: number; preview: boolean; locale?: string }): UiNode {
  if (!result || "error" in result) {
    return {
      component: "Callout",
      props: { tone: "warning", title: input.title, text: result && "error" in result ? result.error : "This exercise opens once you are enrolled." },
    };
  }
  const e = result.exercise;
  return {
    component: "CodeExerciseLesson",
    props: {
      src: result.runner.url,
      title: input.title,
      ...(input.preview ? {} : { topicId: input.topicId }),
      language: e.language,
      files: e.files,
      tests: e.tests,
      timeoutMs: e.timeout_ms,
      passScore: e.pass_score,
      completionRule: e.completion_rule,
      ...(e.text ? { text: e.text } : {}),
      ...(input.locale ? { locale: input.locale } : {}),
      ...(result.attempts && result.attempts.count > 0 ? { attempts: { count: result.attempts.count, best_score: result.attempts.best_score ?? 0 } } : {}),
    },
  };
}
