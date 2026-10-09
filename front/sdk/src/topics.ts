import type { Course, Lesson, Topic, TopicKind, TopicProgress } from "./types.ts";

const KIND_BY_CLASS: Record<string, TopicKind> = {
  richtext: "richtext",
  video: "video",
  audio: "audio",
  image: "image",
  pdf: "pdf",
  oembed: "oembed",
  h5p: "h5p",
  scormsco: "scorm",
  liascripttopic: "liascript",
  cmi5au: "cmi5",
  giftquiz: "quiz",
  project: "project",
};

/** `Ulams\TopicTypes\Models\TopicContent\Video` → `video`. */
export function topicKind(topicableType: string | null | undefined): TopicKind {
  if (!topicableType) return "unknown";
  const name = (topicableType.split("\\").pop() ?? "").toLowerCase();
  return KIND_BY_CLASS[name] ?? "unknown";
}

/** "4 min", "1 h 20 min", "2 h", "00:12:00", "12" → minutes (0 when unknown). */
export function durationToMinutes(value: string | number | null | undefined): number {
  if (value === null || value === undefined || value === "") return 0;
  if (typeof value === "number") return Math.max(0, Math.round(value));
  const clock = value.trim().match(/^(\d+):(\d{1,2})(?::(\d{1,2}))?$/);
  if (clock) {
    const [, a, b, c] = clock;
    return c !== undefined ? Number(a) * 60 + Number(b) + Math.round(Number(c) / 60) : Number(a) * 60 + Number(b);
  }
  const hours = value.match(/(\d+(?:[.,]\d+)?)\s*h/i);
  const minutes = value.match(/(\d+)\s*min/i);
  if (hours || minutes) {
    return Math.round((hours ? parseFloat(hours[1]!.replace(",", ".")) * 60 : 0) + (minutes ? Number(minutes[1]) : 0));
  }
  const n = parseInt(value, 10);
  return Number.isNaN(n) ? 0 : n;
}

/** Lessons in program order, nested sub-lessons flattened after their parent. */
export function flattenLessons(course: Pick<Course, "lessons">): Lesson[] {
  const out: Lesson[] = [];
  const walk = (lessons: Lesson[] | undefined) => {
    for (const lesson of [...(lessons ?? [])].sort((a, b) => (a.order ?? 0) - (b.order ?? 0))) {
      out.push(lesson);
      walk(lesson.lessons);
    }
  };
  walk(course.lessons);
  return out;
}

/** All topics in program order. */
export function flattenTopics(course: Pick<Course, "lessons">): Topic[] {
  return flattenLessons(course).flatMap((lesson) =>
    [...(lesson.topics ?? [])].sort((a, b) => (a.order ?? 0) - (b.order ?? 0))
  );
}

export interface TopicNeighbours {
  index: number;
  previous: Topic | null;
  next: Topic | null;
  total: number;
}

export function topicNeighbours(course: Pick<Course, "lessons">, topicId: number): TopicNeighbours {
  const topics = flattenTopics(course);
  const index = topics.findIndex((t) => t.id === topicId);
  return {
    index,
    previous: index > 0 ? topics[index - 1]! : null,
    next: index >= 0 && index < topics.length - 1 ? topics[index + 1]! : null,
    total: topics.length,
  };
}

/** Share of completed topics, 0–100. */
export function completionPercent(course: Pick<Course, "lessons">, progress: TopicProgress[]): number {
  const topics = flattenTopics(course);
  if (topics.length === 0) return 0;
  const done = new Set(progress.filter((p) => p.status === 1).map((p) => p.topic_id));
  return Math.round((topics.filter((t) => done.has(t.id)).length / topics.length) * 100);
}

/** First topic that is not complete, else the first topic. */
export function resumeTopic(course: Pick<Course, "lessons">, progress: TopicProgress[]): Topic | null {
  const topics = flattenTopics(course);
  const done = new Set(progress.filter((p) => p.status === 1).map((p) => p.topic_id));
  return topics.find((t) => !done.has(t.id)) ?? topics[0] ?? null;
}
