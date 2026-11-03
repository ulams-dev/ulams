import { pad, slugify } from "./ids.ts";
import { serializeDirective, serializeLesson, normalizeMarkdown } from "./lesson-file.ts";
import { BLUEPRINT_VERSION, COURSE_FILE, COURSE_SPEC, FINAL_TEST_FILE, FORMAT_VERSION, LESSON_SPEC, MODULES_DIR, MODULE_FILE, MODULE_SPEC, QUIZ_SPEC, ULAMS_FILE, ULAMS_SPEC } from "./layout.ts";
import { isObj, type Files, type Obj } from "./types.ts";
import { stringifyValue } from "./yaml-io.ts";

/* Blueprint v3 document -> course folder files (the `from-blueprint` half of the format). `meta` is tool room that
 * the files do not carry; everything else survives a round trip through `loadCourse`. */

export interface FolderBinding {
  course?: { id?: number; session?: string };
  profile?: string;
  lint?: { uncitedAuthorBlocks: "warn" | "error" | "off" };
}

const copy = (o: Obj, drop: string[]): Obj => Object.fromEntries(Object.entries(o).filter(([k, v]) => !drop.includes(k) && v !== undefined));

/** Removes `meta` from elements (never from H5P parameters, which are the library's own data). */
function noMeta(value: unknown): unknown {
  if (Array.isArray(value)) return value.map(noMeta);
  if (!isObj(value)) return value;
  const out: Obj = {};
  for (const [k, v] of Object.entries(value)) {
    if (k === "meta") continue;
    out[k] = k === "data" ? v : noMeta(v);
  }
  return out;
}

function question(q: Obj): Obj {
  const out = copy(noMeta(q) as Obj, []);
  if (Array.isArray(out.citations) && out.citations.length === 0) delete out.citations;
  if (Array.isArray(out.objectiveIds) && out.objectiveIds.length === 0) delete out.objectiveIds;
  out.options = (out.options as Obj[]).map((o) => (o.correct === false ? copy(o, ["correct"]) : o));
  return out;
}

function quiz(q: Obj): Obj {
  const out = copy(noMeta(q) as Obj, []);
  out.questions = ((out.questions as Obj[] | undefined) ?? []).map(question);
  return out;
}

function interaction(i: Obj): Obj {
  const out = copy(noMeta(i) as Obj, []);
  if (Array.isArray(out.citations) && out.citations.length === 0) delete out.citations;
  if (Array.isArray(out.objectiveIds) && out.objectiveIds.length === 0) delete out.objectiveIds;
  return out;
}

function lessonFrontMatter(lesson: Obj): Obj {
  const out = copy(noMeta(lesson) as Obj, ["blocks", "quiz"]);
  if (out.contentType === "richtext") delete out.contentType;
  if (out.status === "generated") delete out.status;
  if (Array.isArray(out.citations) && out.citations.length === 0) delete out.citations;
  if (Array.isArray(out.flags) && out.flags.length === 0) delete out.flags;
  out.objectives = ((out.objectives as Obj[] | undefined) ?? []).map((o) => (Array.isArray(o.citations) && o.citations.length === 0 ? copy(o, ["citations"]) : o));
  if (out.selfChecks !== undefined) out.selfChecks = (out.selfChecks as Obj[]).map(question);
  if (out.interaction === null) delete out.interaction;
  else if (isObj(out.interaction)) out.interaction = interaction(out.interaction);
  return out;
}

function block(b: Obj): string {
  const cite = Array.isArray(b.citations) ? (b.citations as string[]) : [];
  const objectives = Array.isArray(b.objectiveIds) ? (b.objectiveIds as string[]) : [];
  return serializeDirective({
    kind: String(b.kind),
    id: String(b.id),
    cite,
    ...(typeof b.origin === "string" ? { origin: b.origin } : {}),
    objectives,
    markdown: normalizeMarkdown(String(b.markdown)),
  });
}

export function folderFromBlueprint(doc: Obj, binding: FolderBinding = {}): Files {
  const files: Files = new Map();
  files.set(
    ULAMS_FILE,
    stringifyValue(
      {
        format: FORMAT_VERSION,
        blueprint: BLUEPRINT_VERSION,
        ...(binding.profile ? { profile: binding.profile } : {}),
        ...(binding.course ? { course: binding.course } : {}),
        ...(binding.lint ? { lint: binding.lint } : {}),
      },
      ULAMS_SPEC
    )
  );

  const course = copy(noMeta(doc.course) as Obj, []);
  course.objectives = ((course.objectives as Obj[] | undefined) ?? []).map((o) => (Array.isArray(o.citations) && o.citations.length === 0 ? copy(o, ["citations"]) : o));
  if (Array.isArray(course.faq)) course.faq = (course.faq as Obj[]).map((f) => (Array.isArray(f.citations) && f.citations.length === 0 ? copy(f, ["citations"]) : f));
  if (isObj(doc.pages) && Object.keys(doc.pages).length > 0) course.pages = doc.pages;
  if (Array.isArray(doc.sources) && doc.sources.length > 0) course.sources = doc.sources;
  files.set(COURSE_FILE, stringifyValue(course, COURSE_SPEC));

  ((doc.modules as Obj[] | undefined) ?? []).forEach((m, mi) => {
    const dir = `${MODULES_DIR}/${pad(mi + 1)}-${slugify(String(m.key ?? m.title))}`;
    files.set(`${dir}/${MODULE_FILE}`, stringifyValue(copy(noMeta(m) as Obj, ["lessons"]), MODULE_SPEC));
    ((m.lessons as Obj[] | undefined) ?? []).forEach((l, li) => {
      const stem = `${pad(li + 1)}-${slugify(String(l.key ?? l.title))}`;
      files.set(`${dir}/${stem}.md`, serializeLesson(stringifyValue(lessonFrontMatter(l), LESSON_SPEC), ((l.blocks as Obj[] | undefined) ?? []).map(block)));
      if (isObj(l.quiz)) files.set(`${dir}/${stem}.quiz.yaml`, stringifyValue(quiz(l.quiz), QUIZ_SPEC));
    });
  });
  if (isObj(doc.finalTest)) files.set(FINAL_TEST_FILE, stringifyValue(quiz(doc.finalTest), QUIZ_SPEC));
  return files;
}

/** What the files can represent of a document: no `meta`, normalised Markdown, no null interaction. Round-trip tests compare these. */
export function normalizeDocument(doc: Obj): Obj {
  const walk = (value: unknown, key?: string): unknown => {
    if (Array.isArray(value)) return value.map((v) => walk(v));
    if (!isObj(value)) return value;
    const out: Obj = {};
    for (const [k, v] of Object.entries(value)) {
      if (k === "meta") continue;
      if (k === "interaction" && v === null) continue;
      if (k === "markdown" && typeof v === "string" && key !== "data") out[k] = normalizeMarkdown(v);
      else out[k] = k === "data" ? v : walk(v, k);
    }
    return out;
  };
  return walk(doc) as Obj;
}
