/** Learning formats (topic types) as shown on the landing pages. */
export type FormatKey =
  | "video"
  | "audio"
  | "reading"
  | "image"
  | "pdf"
  | "embed"
  | "interactive"
  | "scorm"
  | "tracked"
  | "quiz"
  | "project";

export const FORMAT_LABELS: Record<FormatKey, string> = {
  video: "Video",
  audio: "Audio",
  reading: "Reading",
  image: "Image",
  pdf: "PDF",
  embed: "Embed",
  interactive: "Interactive",
  scorm: "SCORM",
  tracked: "Tracked activity",
  quiz: "Quiz",
  project: "Project",
};

/** Maps an API `topicable_type` (e.g. `…\TopicContent\Video`) to a format. */
export function formatFromTopicType(type: string | null | undefined): FormatKey | null {
  if (!type) return null;
  const name = type.split("\\").pop()?.toLowerCase() ?? "";
  if (name.includes("video")) return "video";
  if (name.includes("audio")) return "audio";
  if (name.includes("richtext")) return "reading";
  if (name.includes("image")) return "image";
  if (name.includes("pdf")) return "pdf";
  if (name.includes("oembed")) return "embed";
  if (name.includes("h5p")) return "interactive";
  if (name.includes("scorm")) return "scorm";
  if (name.includes("cmi5")) return "tracked";
  if (name.includes("gift") || name.includes("quiz")) return "quiz";
  if (name.includes("project")) return "project";
  return null;
}
