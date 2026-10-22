import { ASSETS_DIR, COURSE_FILE, FINAL_TEST_FILE, MODULE_FILE, MODULES_DIR, ULAMS_FILE } from "./layout.ts";
import type { Files } from "./types.ts";

export interface Ordered {
  /** Numeric prefix, or null when the name has none. */
  prefix: number | null;
  /** The name without prefix and extension. */
  slug: string;
}

export function orderOf(name: string): Ordered {
  const m = /^(\d+)-(.*)$/.exec(name);
  return m ? { prefix: Number(m[1]), slug: m[2] as string } : { prefix: null, slug: name };
}

export function compareNames(a: string, b: string): number {
  const x = orderOf(a).prefix ?? Number.POSITIVE_INFINITY;
  const y = orderOf(b).prefix ?? Number.POSITIVE_INFINITY;
  return x === y ? a.localeCompare(b) : x < y ? -1 : 1;
}

export interface LessonFiles {
  name: string;
  path: string;
  quizPath: string | null;
  quizName: string;
}
export interface ModuleFiles {
  dir: string;
  path: string;
  modulePath: string | null;
  lessons: LessonFiles[];
  /** Quiz files without a lesson file. */
  orphanQuizzes: string[];
}
export interface FolderIndex {
  modules: ModuleFiles[];
  /** Files under modules/ the format does not use. */
  unknown: string[];
}

const LESSON = /\.md$/;
const QUIZ = /\.quiz\.yaml$/;

/** Groups the files of modules/ into modules and lessons, in order. Paths are relative to the folder root. */
export function indexFolder(files: Files): FolderIndex {
  const byDir = new Map<string, string[]>();
  const unknown: string[] = [];
  for (const path of files.keys()) {
    if (!path.startsWith(`${MODULES_DIR}/`)) continue;
    const parts = path.split("/");
    if (parts.length !== 3) {
      unknown.push(path);
      continue;
    }
    const list = byDir.get(parts[1] as string) ?? [];
    list.push(parts[2] as string);
    byDir.set(parts[1] as string, list);
  }
  const modules: ModuleFiles[] = [];
  for (const dir of [...byDir.keys()].sort(compareNames)) {
    const names = byDir.get(dir) as string[];
    const base = `${MODULES_DIR}/${dir}`;
    const lessonNames = names.filter((n) => LESSON.test(n)).sort(compareNames);
    const quizNames = new Set(names.filter((n) => QUIZ.test(n)));
    const lessons = lessonNames.map((name): LessonFiles => {
      const quizName = name.replace(LESSON, ".quiz.yaml");
      return { name, path: `${base}/${name}`, quizPath: quizNames.has(quizName) ? `${base}/${quizName}` : null, quizName };
    });
    const paired = new Set(lessons.map((l) => l.quizName));
    for (const n of names) if (n !== MODULE_FILE && !LESSON.test(n) && !QUIZ.test(n)) unknown.push(`${base}/${n}`);
    modules.push({
      dir,
      path: base,
      modulePath: names.includes(MODULE_FILE) ? `${base}/${MODULE_FILE}` : null,
      lessons,
      orphanQuizzes: [...quizNames].filter((q) => !paired.has(q)).sort().map((q) => `${base}/${q}`),
    });
  }
  return { modules, unknown: unknown.sort() };
}

/** Text files that belong to the course folder (the rest, such as assets and sources, are not read as text). */
export const isTextFile = (path: string): boolean =>
  path === ULAMS_FILE || path === COURSE_FILE || path === FINAL_TEST_FILE || (path.startsWith(`${MODULES_DIR}/`) && (path.endsWith(".md") || path.endsWith(".yaml")));

export const isAssetPath = (path: string): boolean => path.startsWith(`${ASSETS_DIR}/`);

export const MEDIA_TYPES: Record<string, string> = {
  zip: "application/zip",
  pdf: "application/pdf",
  mp4: "video/mp4",
  webm: "video/webm",
  mp3: "audio/mpeg",
  png: "image/png",
  jpg: "image/jpeg",
  jpeg: "image/jpeg",
  gif: "image/gif",
  svg: "image/svg+xml",
  webp: "image/webp",
  txt: "text/plain",
  csv: "text/csv",
  json: "application/json",
};

export function guessMediaType(path: string): string {
  const ext = path.split(".").pop()?.toLowerCase() ?? "";
  return MEDIA_TYPES[ext] ?? "application/octet-stream";
}
