import { ApiError, type Blueprint, type BlueprintLesson, type BlueprintQuestion, type BlueprintVersion, type BuilderState, type Course, type CourseBuilderClient, type Lesson, type Topic } from "@ulams/sdk";
import type { CourseLinks } from "./view-model.ts";

/**
 * The studio's interactive preview shows a Course Blueprint version the way a learner will see it
 * (ADR 0071). The blueprint is mapped to the same `Course` shape the learner pages read, so the
 * course page and the lesson player render it with the components they already use, and every
 * element keeps the blueprint id the element chat addresses (`data-blueprint-id`).
 */
const RICHTEXT = "Ulams\\TopicTypes\\Models\\TopicContent\\RichText";
const GIFT = "Ulams\\TopicTypeGift\\Models\\GiftQuiz";

export type PreviewPageKind = "lesson" | "quiz" | "final";

/** One learner page of the preview: a lesson, the quiz after it, or the final test. */
export interface PreviewPage {
  /** URL segment (the blueprint id of the lesson, the quiz or the final test). */
  key: string;
  /** Synthetic topic id used by the player's program tree. */
  topicId: number;
  kind: PreviewPageKind;
  title: string;
  label: string;
  lesson?: BlueprintLesson;
  questions: BlueprintQuestion[];
}

export interface BlueprintPreview {
  course: Course;
  pages: PreviewPage[];
  byKey: Map<string, PreviewPage>;
  byTopic: Map<number, PreviewPage>;
}

export const LANDING_KEY = "landing";

const minutes = (n: number) => `${Math.max(0, Math.round(n))} min`;

export function buildPreview(doc: Blueprint, options: { level?: string | null } = {}): BlueprintPreview {
  const pages: PreviewPage[] = [];
  let topicId = 100;
  const topic = (page: Omit<PreviewPage, "topicId">, lessonId: number, order: number, duration: number, type: string): Topic => {
    const id = topicId++;
    pages.push({ ...page, topicId: id });
    return { id, title: page.title, lesson_id: lessonId, order, topicable_type: type, topicable: { id, value: "" }, duration: minutes(duration), summary: page.lesson?.summary ?? null };
  };

  const lessons: Lesson[] = doc.modules.map((module, mi) => {
    const lessonId = 10 + mi;
    const topics: Topic[] = [];
    module.lessons.forEach((lesson, li) => {
      topics.push(topic({ key: lesson.id, kind: "lesson", title: lesson.title, label: `Lesson ${mi + 1}.${li + 1}`, lesson, questions: [] }, lessonId, topics.length + 1, lesson.minutes, RICHTEXT));
      const questions = lesson.quiz?.questions ?? [];
      if (lesson.quiz && questions.length) {
        topics.push(topic({ key: lesson.quiz.id, kind: "quiz", title: `Quiz: ${lesson.title}`, label: `Lesson ${mi + 1}.${li + 1} › quiz`, lesson, questions }, lessonId, topics.length + 1, Math.max(2, questions.length), GIFT));
      }
    });
    return { id: lessonId, title: module.title, summary: module.summary ?? null, order: mi + 1, topics };
  });
  const finalTest = doc.finalTest;
  if (finalTest && finalTest.questions.length) {
    lessons.push({
      id: 99,
      title: "Final test",
      summary: "Questions across the whole course.",
      order: doc.modules.length + 1,
      topics: [topic({ key: finalTest.id, kind: "final", title: "Final test", label: "Final test", questions: finalTest.questions }, 99, 1, Math.max(2, finalTest.questions.length), GIFT)],
    });
  }

  const total = doc.modules.flatMap((m) => m.lessons).reduce((sum, l) => sum + l.minutes, 0);
  const course: Course = {
    id: 1,
    title: doc.course.title,
    subtitle: doc.course.subtitle ?? null,
    summary: doc.course.subtitle ?? null,
    description: doc.course.description ?? null,
    language: doc.course.language,
    level: options.level ?? null,
    duration: minutes(total),
    status: "draft",
    authors: [],
    lessons,
    fields: null,
  };
  return { course, pages, byKey: new Map(pages.map((p) => [p.key, p])), byTopic: new Map(pages.map((p) => [p.topicId, p])) };
}

export const previewBase = (sessionId: string): string => `/studio/s/${sessionId}/preview`;
export const previewPageHref = (sessionId: string, key?: string): string => (key ? `${previewBase(sessionId)}/${key}` : previewBase(sessionId));
export const framePageHref = (sessionId: string, key?: string): string => `/studio/s/${sessionId}/frame${key ? `/${key}` : ""}`;

/** Links of the program tree and the course page inside the preview: they open the studio preview pages. */
export function previewLinks(sessionId: string, preview: BlueprintPreview): CourseLinks {
  const first = preview.pages[0];
  return {
    learn: () => previewPageHref(sessionId, first?.key),
    topic: (_course, topicId) => previewPageHref(sessionId, preview.byTopic.get(topicId)?.key),
  };
}

export type ElementType = "course" | "module" | "lesson" | "block" | "quiz" | "question";

export interface FoundElement {
  type: ElementType;
  label: string;
  /** Whether the element chat can change it (the API patches course, module, lesson, block and question). */
  editable: boolean;
  node: Record<string, unknown>;
}

/** Finds an element of the blueprint by id, with the label the studio uses ("Lesson 1.2 › Q1"). */
export function findElement(doc: Blueprint, id: string): FoundElement | null {
  if (doc.course.id === id) return { type: "course", label: "Course", editable: true, node: doc.course as never };
  for (const [mi, module] of doc.modules.entries()) {
    if (module.id === id) return { type: "module", label: `Module ${mi + 1}`, editable: true, node: module as never };
    for (const [li, lesson] of module.lessons.entries()) {
      const label = `Lesson ${mi + 1}.${li + 1}`;
      if (lesson.id === id) return { type: "lesson", label, editable: true, node: lesson as never };
      const block = lesson.blocks.findIndex((b) => b.id === id);
      if (block >= 0) return { type: "block", label: `${label} › block ${block + 1}`, editable: true, node: lesson.blocks[block] as never };
      if (lesson.quiz?.id === id) return { type: "quiz", label: `${label} › quiz`, editable: false, node: lesson.quiz as never };
      const question = (lesson.quiz?.questions ?? []).findIndex((q) => q.id === id);
      if (question >= 0) return { type: "question", label: `${label} › Q${question + 1}`, editable: true, node: lesson.quiz!.questions[question] as never };
    }
  }
  const final = doc.finalTest;
  if (final?.id === id) return { type: "quiz", label: "Final test", editable: false, node: final as never };
  const question = (final?.questions ?? []).findIndex((q) => q.id === id);
  if (final && question >= 0) return { type: "question", label: `Final test › Q${question + 1}`, editable: true, node: final.questions[question] as never };
  return null;
}

/** Every source fragment id cited anywhere inside an element, in order of first appearance. */
export function elementCitations(node: unknown): string[] {
  const found = new Set<string>();
  const walk = (value: unknown): void => {
    if (typeof value === "string") {
      if (/^frg_[a-z2-7]{12}$/.test(value)) found.add(value);
    } else if (Array.isArray(value)) value.forEach(walk);
    else if (value && typeof value === "object") Object.values(value).forEach(walk);
  };
  walk(node);
  return [...found];
}

/** The ids of every element the preview marks, as a stable string: when it changes the page is rebuilt. */
export function elementIds(doc: Blueprint): string[] {
  const ids: string[] = [doc.course.id];
  for (const module of doc.modules) {
    ids.push(module.id);
    for (const lesson of module.lessons) {
      ids.push(lesson.id, ...lesson.blocks.map((b) => b.id), ...(lesson.quiz?.questions ?? []).map((q) => q.id));
    }
  }
  if (doc.finalTest) ids.push(...doc.finalTest.questions.map((q) => q.id));
  return ids;
}

/** The labels of the landing sections the preview shows, keyed by the catalogue component. */
export const LANDING_LABEL: Record<string, string> = {
  Hero: "Landing › headline",
  FeatureList: "Landing › outcomes",
  Syllabus: "Landing › syllabus",
  Faq: "Landing › questions",
};

type SessionSource = Pick<CourseBuilderClient, "sessions" | "versions">;

export interface SessionPreviewSource {
  state: BuilderState;
  /** The version being discussed: null before the lessons exist (only the outline). */
  version: BlueprintVersion | null;
}

/** Version kinds that carry lesson content (the outline alone has nothing to preview). */
const CONTENT_KINDS = new Set(["content", "patch", "author", "restore"]);

/**
 * The studio session and its current blueprint version, loaded with the author's token. Null when the
 * API does not show it to this author (not their session, another tenant, expired token): the
 * session policy and the tenant API decide, the preview adds no rule of its own.
 */
export async function loadSessionPreview(cb: SessionSource, sessionId: string): Promise<SessionPreviewSource | null> {
  try {
    const state = await cb.sessions.get(sessionId);
    const versionId = state.session.currentVersionId;
    if (!versionId) return { state, version: null };
    const version = await cb.versions.get(versionId);
    return { state, version: CONTENT_KINDS.has(version.kind) ? version : null };
  } catch (error) {
    if (error instanceof ApiError && [401, 403, 404].includes(error.status)) return null;
    throw error;
  }
}
