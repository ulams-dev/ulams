/**
 * Machine-readable views of an academy for agents (Phase 7.6): `/llms.txt` (an index in the llmstxt.org
 * format) and `/llms/courses/<id>.md` (the public course page as Markdown). Pure functions over the same
 * public API data the HTML pages use: nothing here is private, nothing is invented, and an academy without
 * public courses still gets a valid file.
 */
import { durationToMinutes, topicKind, type Course, type Lesson, type PublicSettings, type Topic } from "@ulams/sdk";

export interface LlmsAcademy {
  name: string;
  origin: string;
  currency?: string;
  courses: Array<Pick<Course, "id" | "title" | "subtitle" | "summary" | "level" | "language" | "product">>;
}

const PRODUCT_DOCS = "https://ulams-dev.github.io/ulams/";

const oneLine = (text: string | null | undefined, max = 200): string => {
  const flat = (text ?? "").replace(/<[^>]+>/g, " ").replace(/[*_`#>|]/g, "").replace(/\s+/g, " ").trim();
  return flat.length <= max ? flat : `${flat.slice(0, max - 1).replace(/\s+\S*$/, "")}…`;
};

/** Minor units to a plain amount: 8900 -> "89.00". */
export const formatPrice = (minor: number | null | undefined, currency?: string): string => {
  if (minor === null || minor === undefined) return "";
  return `${(minor / 100).toFixed(2)}${currency ? ` ${currency}` : ""}`;
};

const priceLine = (course: Pick<Course, "product">, currency?: string): string => {
  const product = course.product;
  if (!product) return "free";
  if (!product.price) return "free";

  return formatPrice(product.price, currency);
};

export function academyName(settings: PublicSettings | null | undefined, slug: string): string {
  return settings?.global?.companyName?.trim() || slug;
}

/** `/llms.txt` of an academy: what it is, its public courses with links to their Markdown pages. */
export function academyLlmsTxt(academy: LlmsAcademy): string {
  const lines = [
    `# ${academy.name}`,
    "",
    `> ${academy.name} is an academy that runs on ulams, an open, AI-native, headless LMS. This file lists its public courses; every course page is also served as Markdown.`,
    "",
    "## Courses",
    "",
  ];
  if (!academy.courses.length) lines.push("This academy has no public courses yet.");
  for (const course of academy.courses) {
    const facts = [course.level, course.language, priceLine(course, academy.currency)].filter(Boolean).join(", ");
    const about = oneLine(course.subtitle || course.summary);
    lines.push(`- [${course.title}](${academy.origin}/llms/courses/${course.id}.md): ${about ? `${about} ` : ""}(${facts})`);
  }
  lines.push(
    "",
    "## Optional",
    "",
    `- [Course pages as HTML](${academy.origin}/): the same courses with enrolment`,
    `- [ulams documentation for agents](${PRODUCT_DOCS}llms.txt): the API, the CLI, the MCP server and the JSON Schemas of the platform`,
    "",
  );

  return lines.join("\n");
}

/** `/llms.txt` of the platform host (the product site, not an academy). */
export function platformLlmsTxt(origin: string): string {
  return [
    "# ulams",
    "",
    "> ulams is an open, AI-native, headless LMS: courses that stay in sync with their sources, adapt to each learner and cite every generated element.",
    "",
    "## Documentation",
    "",
    `- [llms.txt of the documentation](${PRODUCT_DOCS}llms.txt): an index of every guide as Markdown, with the OpenAPI document and the JSON Schemas`,
    `- [Comparison](${origin}/comparison/): what ulams does against other platforms`,
    `- [FAQ](${origin}/faq/)`,
    "",
    "Each academy serves its own /llms.txt on its own host.",
    "",
  ].join("\n");
}

/** Description text: Markdown stays as it is, simple HTML becomes Markdown, other tags are dropped. */
export function toMarkdown(text: string | null | undefined): string {
  const value = (text ?? "").trim();
  if (!/<\/?[a-z][^>]*>/i.test(value)) return value;

  return value
    .replace(/<\s*br\s*\/?>/gi, "\n")
    .replace(/<h([1-6])[^>]*>([\s\S]*?)<\/h\1>/gi, (_, level: string, inner: string) => `\n\n${"#".repeat(Math.min(6, Number(level) + 1))} ${inner.replace(/<[^>]+>/g, "").trim()}\n\n`)
    .replace(/<a\s[^>]*href="([^"]*)"[^>]*>([\s\S]*?)<\/a>/gi, (_, href: string, inner: string) => `[${inner.replace(/<[^>]+>/g, "").trim()}](${href})`)
    .replace(/<(ul|ol)[^>]*>/gi, "\n")
    .replace(/<li[^>]*>/gi, "- ")
    .replace(/<\/li>/gi, "\n")
    .replace(/<\/(ul|ol)>/gi, "\n\n")
    .replace(/<\/(p|div)>/gi, "\n\n")
    .replace(/<(strong|b)>([\s\S]*?)<\/\1>/gi, "**$2**")
    .replace(/<(em|i)>([\s\S]*?)<\/\1>/gi, "*$2*")
    .replace(/<[^>]+>/g, "")
    .replace(/&nbsp;/g, " ")
    .replace(/&amp;/g, "&")
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">")
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'")
    .replace(/\n{3,}/g, "\n\n")
    .trim();
}

const KIND_LABEL: Record<string, string> = {
  richtext: "reading", video: "video", audio: "audio", image: "image", pdf: "PDF", oembed: "embed", h5p: "interactive (H5P)",
  scorm: "SCORM", liascript: "interactive (LiaScript)", interactive: "interactive", code_exercise: "code exercise", layout: "page", lti: "external tool (LTI)",
  cmi5: "tracked (cmi5)", quiz: "quiz", project: "project",
};

const topicLine = (topic: Topic): string => {
  const kind = topicKind(topic.topicable_type);
  const label = KIND_LABEL[kind];
  const minutes = durationToMinutes(topic.duration);
  const facts = [label, minutes ? `${minutes} min` : "", topic.preview ? "free preview" : ""].filter(Boolean).join(", ");

  return `- ${topic.title}${facts ? ` (${facts})` : ""}`;
};

const lessonBlock = (lesson: Lesson, depth: number): string[] => {
  const out = [`${"#".repeat(Math.min(6, depth))} ${lesson.title}`, ""];
  if (lesson.summary) out.push(oneLine(lesson.summary, 400), "");
  const topics = [...(lesson.topics ?? [])].filter((t) => t.active !== false).sort((a, b) => (a.order ?? 0) - (b.order ?? 0));
  if (topics.length) out.push(...topics.map(topicLine), "");
  for (const child of lesson.lessons ?? []) out.push(...lessonBlock(child, depth + 1));

  return out;
};

/** The public page of a course as Markdown: what it is, who teaches it, the syllabus, the price. */
export function courseMarkdown(course: Course, academy: Pick<LlmsAcademy, "name" | "origin" | "currency">): string {
  const authors = (course.authors?.length ? course.authors : course.author ? [course.author] : []).map((a) => `${a.first_name} ${a.last_name}`.trim()).filter(Boolean);
  const facts: Array<[string, string]> = [
    ["Academy", academy.name],
    ["Page", `${academy.origin}/courses/${course.id}`],
    ["Level", course.level ?? ""],
    ["Language", course.language ?? ""],
    ["Duration", course.duration ?? (course.hours_to_complete ? `${course.hours_to_complete} h` : "")],
    ["Audience", course.target_group ?? ""],
    ["Teachers", authors.join(", ")],
    ["Tags", (course.tags ?? []).map((t) => (typeof t === "string" ? t : (t as { title?: string }).title ?? "")).filter(Boolean).join(", ")],
    ["Price", priceLine(course, academy.currency)],
  ];
  const lines = [`# ${course.title}`, ""];
  const lead = course.subtitle || course.summary;
  if (lead) lines.push(`> ${oneLine(lead, 400)}`, "");
  lines.push(...facts.filter(([, v]) => v).map(([k, v]) => `- ${k}: ${v}`), "");
  const description = toMarkdown(course.description);
  if (description) lines.push("## About", "", description, "");
  const lessons = [...(course.lessons ?? [])].filter((l) => l.active !== false).sort((a, b) => (a.order ?? 0) - (b.order ?? 0));
  if (lessons.length) {
    lines.push("## Syllabus", "");
    for (const lesson of lessons) lines.push(...lessonBlock(lesson, 3));
  }

  return `${lines.join("\n").replace(/\n{3,}/g, "\n\n").trim()}\n`;
}
