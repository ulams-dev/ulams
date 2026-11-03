import { isMap } from "yaml";
import { guessMediaType, indexFolder, orderOf } from "./folder.ts";
import { missingIds, type FileKind } from "./id-slots.ts";
import { pad } from "./ids.ts";
import { Sink } from "./issues.ts";
import { parseLessonFile, serializeDirective, serializeLesson } from "./lesson-file.ts";
import { ASSETS_DIR, COURSE_FILE, COURSE_SPEC, FINAL_TEST_FILE, LESSON_SPEC, MODULES_DIR, MODULE_SPEC, QUIZ_SPEC, ULAMS_FILE, ULAMS_SPEC } from "./layout.ts";
import { isObj, type AssetLookup, type Files, type Issue } from "./types.ts";
import { parseYamlFile, settle, type KeySpec, type YamlFile } from "./yaml-io.ts";

/* `ulams course fmt`: canonical key order, ids minted for elements that lack one, loose Markdown wrapped in author
 * paragraphs, directives rewritten the way the writer prints them, numeric prefixes renumbered, asset hashes filled.
 * YAML comments survive. The result is the plan; nothing is written here. */

export interface FmtPlan {
  /** Module folders to rename (applied first). */
  dirRenames: Array<{ from: string; to: string }>;
  /** Files to rename inside the (already renamed) folders: from -> to. */
  fileRenames: Map<string, string>;
  /** New content of text files, keyed by their final path; only files that change. */
  writes: Map<string, string>;
  /** Problems that stop formatting (syntax errors). When non-empty nothing else is set. */
  blocking: Issue[];
}

export interface FmtOptions {
  mint: () => string;
  assets: AssetLookup;
}

function fixIds(yf: YamlFile, kind: FileKind, mint: () => string): void {
  for (const path of missingIds(kind, yf.data)) yf.doc.setIn([...path, "id"], mint());
}

function fixAsset(yf: YamlFile, assets: AssetLookup): void {
  const asset = yf.doc.getIn(["asset"]);
  const raw = yf.doc.toJS() as Record<string, unknown>;
  const current = raw.asset;
  if (asset === undefined || current === undefined) return;
  const given = typeof current === "string" ? { path: current } : isObj(current) ? (current as { path?: string; mediaType?: string }) : null;
  if (!given || typeof given.path !== "string") return;
  const info = assets(`${ASSETS_DIR}/${given.path}`);
  if (info === null) return;
  yf.doc.setIn(["asset"], yf.doc.createNode({ path: given.path, sha256: info.sha256, bytes: info.bytes, mediaType: given.mediaType ?? guessMediaType(given.path) }));
}

function formatYamlFile(path: string, text: string, spec: KeySpec, kind: FileKind, o: FmtOptions, sink: Sink): string | null {
  const yf = parseYamlFile(path, text, sink);
  if (!yf) return null;
  if (!isMap(yf.doc.contents)) {
    sink.add("SCHEMA_INVALID", { file: path }, "The file must be a YAML mapping.");
    return null;
  }
  fixIds(yf, kind, o.mint);
  return settle(yf.doc, spec);
}

function formatLesson(path: string, text: string, o: FmtOptions, sink: Sink): string | null {
  const before = sink.errors;
  const src = parseLessonFile(path, text, sink);
  if (sink.errors > before || !src.frontMatter) return null;
  const yf = src.frontMatter;
  if (!isMap(yf.doc.contents)) {
    sink.add("SCHEMA_INVALID", { file: path, pos: { line: 2, column: 1 } }, "The front matter must be a YAML mapping.");
    return null;
  }
  fixIds(yf, "lesson", o.mint);
  fixAsset(yf, o.assets);
  const frontMatter = settle(yf.doc, LESSON_SPEC);
  const blocks = src.nodes.map((n) =>
    n.type === "loose"
      ? serializeDirective({ kind: "paragraph", id: o.mint(), origin: "author", markdown: n.markdown })
      : serializeDirective({ kind: n.kind, id: n.id ?? o.mint(), ...(n.cite ? { cite: n.cite } : {}), ...(n.origin ? { origin: n.origin } : {}), ...(n.objectives ? { objectives: n.objectives } : {}), markdown: n.markdown })
  );
  return serializeLesson(frontMatter, blocks);
}

export function planFormat(files: Files, o: FmtOptions): FmtPlan {
  const sink = new Sink();
  const dirRenames: FmtPlan["dirRenames"] = [];
  const fileRenames: FmtPlan["fileRenames"] = new Map();
  const writes: FmtPlan["writes"] = new Map();

  const rewrite = (oldPath: string, finalPath: string, next: string | null) => {
    if (next === null) return;
    if (next !== files.get(oldPath)) writes.set(finalPath, next);
  };

  for (const [path, spec, kind] of [[ULAMS_FILE, ULAMS_SPEC, "ulams"], [COURSE_FILE, COURSE_SPEC, "course"], [FINAL_TEST_FILE, QUIZ_SPEC, "quiz"]] as const) {
    const text = files.get(path);
    if (text !== undefined) rewrite(path, path, formatYamlFile(path, text, spec, kind, o, sink));
  }

  const index = indexFolder(files);
  const moduleWidth = Math.max(2, String(index.modules.length).length);
  index.modules.forEach((mod, mi) => {
    const newDir = `${pad(mi + 1, moduleWidth)}-${orderOf(mod.dir).slug}`;
    const finalBase = `${MODULES_DIR}/${newDir}`;
    if (finalBase !== mod.path) dirRenames.push({ from: mod.path, to: finalBase });
    // Paths inside are read from the old location and written to the final one.
    if (mod.modulePath) rewrite(mod.modulePath, `${finalBase}/module.yaml`, formatYamlFile(mod.modulePath, files.get(mod.modulePath) as string, MODULE_SPEC, "module", o, sink));
    const lessonWidth = Math.max(2, String(mod.lessons.length).length);
    mod.lessons.forEach((lesson, li) => {
      const stem = `${pad(li + 1, lessonWidth)}-${orderOf(lesson.name.replace(/\.md$/, "")).slug}`;
      const finalLesson = `${finalBase}/${stem}.md`;
      const afterDirRename = `${finalBase}/${lesson.name}`;
      if (afterDirRename !== finalLesson) fileRenames.set(afterDirRename, finalLesson);
      rewrite(lesson.path, finalLesson, formatLesson(lesson.path, files.get(lesson.path) as string, o, sink));
      if (lesson.quizPath) {
        const finalQuiz = `${finalBase}/${stem}.quiz.yaml`;
        const quizAfterDir = `${finalBase}/${lesson.quizName}`;
        if (quizAfterDir !== finalQuiz) fileRenames.set(quizAfterDir, finalQuiz);
        rewrite(lesson.quizPath, finalQuiz, formatYamlFile(lesson.quizPath, files.get(lesson.quizPath) as string, QUIZ_SPEC, "quiz", o, sink));
      }
    });
  });

  const blocking = sink.issues.filter((i) => i.severity === "error");
  if (blocking.length > 0) return { dirRenames: [], fileRenames: new Map(), writes: new Map(), blocking: sink.sorted().filter((i) => i.severity === "error") };
  return { dirRenames, fileRenames, writes, blocking: [] };
}

/** The final path of an existing file under a plan's renames. */
export function finalPath(path: string, plan: FmtPlan): string {
  let p = path;
  for (const r of plan.dirRenames) {
    if (p.startsWith(`${r.from}/`)) {
      p = r.to + p.slice(r.from.length);
      break;
    }
  }
  return plan.fileRenames.get(p) ?? p;
}

/** Applies a plan to an in-memory folder (tests, and the base of `--check`). */
export function applyPlan(files: Files, plan: FmtPlan): Files {
  const out: Files = new Map();
  for (const [path, text] of files) out.set(finalPath(path, plan), text);
  for (const [path, text] of plan.writes) out.set(path, text);
  return out;
}

/** Final paths of text files the plan creates or changes (what `fmt` reports). */
export function changedPaths(files: Files, plan: FmtPlan): string[] {
  const moved = [...files.keys()].filter((p) => finalPath(p, plan) !== p && files.get(p) !== "").map((p) => finalPath(p, plan));
  return [...new Set([...plan.writes.keys(), ...moved])].sort();
}

export function formatFiles(files: Files, o: FmtOptions): { files: Files; plan: FmtPlan } {
  const plan = planFormat(files, o);
  return { files: plan.blocking.length ? files : applyPlan(files, plan), plan };
}
