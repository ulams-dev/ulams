/**
 * "Created with AI assistance and reviewed by the author" (AI content labelling, Phase 4.3): shown in the
 * lesson footer for a topic that was applied from a builder session. The API marks such topics with
 * `ai_assisted` in the program; the tenant switches the label off with the public setting
 * `ai.label_generated_courses` of `GET /api/config` (on when it is absent).
 */
import type { Topic } from "@ulams/sdk";

/** The public configuration, grouped by the first part of each key. */
type PublicConfigLike = Record<string, unknown>;

const OFF = new Set(["false", "0", "off", "no"]);

export function labelGeneratedCourses(config: PublicConfigLike | null | undefined): boolean {
  const ai = config?.ai;
  if (!ai || typeof ai !== "object") return true;
  const value = (ai as Record<string, unknown>).label_generated_courses;
  if (value === false || value === 0) return false;
  return !(typeof value === "string" && OFF.has(value.trim().toLowerCase()));
}

export const showAiAssistedLabel = (topic: Pick<Topic, "ai_assisted">, config: PublicConfigLike | null | undefined): boolean =>
  topic.ai_assisted === true && labelGeneratedCourses(config);
