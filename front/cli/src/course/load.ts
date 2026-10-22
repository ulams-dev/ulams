import type { z } from "zod";
import { localImages, markupFindings, questionShape } from "./checks.ts";
import { guessMediaType, indexFolder } from "./folder.ts";
import { missingIds } from "./id-slots.ts";
import { Sink } from "./issues.ts";
import { parseLessonFile, type LessonSource } from "./lesson-file.ts";
import { ASSETS_DIR, COURSE_FILE, FINAL_TEST_FILE, MODULE_FILE, ULAMS_FILE } from "./layout.ts";
import { courseSchema, lessonFrontMatterSchema, moduleSchema, quizSchema, ulamsSchema } from "./schemas.ts";
import { isObj, type AssetLookup, type Files, type Issue, type Obj, type Path, type Pos } from "./types.ts";
import { parseYamlFile, yamlPos, type YamlFile } from "./yaml-io.ts";

/* Folder -> Blueprint v3 document (the `to-blueprint` half of the format), with every problem reported by file and
 * line. Elements without an id get a placeholder id so the rest of the document can still be checked. */

export interface Located {
  file: string;
  pos: Pos;
}

export interface Loaded {
  /** The document; complete enough to check even when issues were found. */
  doc: Obj;
  sink: Sink;
  issues: Issue[];
  /** No error came from the files or the grammar; only then is the assembled document checked against v3.json. */
  structurallySound: boolean;
  /** Where a JSON pointer into `doc` comes from in the folder. */
  locate(pointer: string): Located;
}

type ZodIssue = z.core.$ZodIssue;

const pathText = (path: Path): string => path.reduce<string>((acc, seg) => (typeof seg === "number" ? `${acc}[${seg}]` : acc ? `${acc}.${seg}` : seg), "");

/** Union branches report paths relative to the union: a deeper path means the value had the right shape. */
function branchScore(issues: ZodIssue[]): number {
  return issues.some((i) => i.path.length > 0) ? 2 : issues.some((i) => i.code !== "invalid_type") ? 1 : 0;
}

function reportZod(sink: Sink, yf: YamlFile, issues: ZodIssue[], element: string | undefined): void {
  const where = (path: Path) => ({ file: yf.file, pos: yamlPos(yf, path), ...(element ? { element } : {}) });
  for (const issue of issues) {
    const path = issue.path.filter((p): p is string | number => typeof p !== "symbol");
    if (issue.code === "unrecognized_keys") {
      for (const key of issue.keys) sink.add("UNKNOWN_FIELD", where([...path, key]), `Unknown field "${pathText([...path, key])}".`);
      continue;
    }
    if (issue.code === "invalid_union") {
      const branches = (issue as unknown as { errors?: ZodIssue[][] }).errors ?? [];
      if (branches.length === 0 || (issue as unknown as { note?: string }).note) {
        const disc = (issue as unknown as { discriminator?: string }).discriminator;
        sink.add("SCHEMA_INVALID", where(path), `${pathText(path) || "value"}: ${disc ? `"${disc}" is missing or not one of the known values` : issue.message}`);
        continue;
      }
      const best = [...branches].sort((a, b) => branchScore(b) - branchScore(a))[0] as ZodIssue[];
      reportZod(sink, yf, best.map((i) => ({ ...i, path: [...path, ...i.path] }) as ZodIssue), element);
      continue;
    }
    const last = path[path.length - 1];
    const parent = path[path.length - 2];
    const text = `${pathText(path) || "value"}: ${issue.message}`;
    if (issue.message.includes("received undefined")) sink.add("FIELD_REQUIRED", where(path.slice(0, -1)), `${pathText(path)} is required.`);
    else if (last === "id" || parent === "objectiveIds") sink.add("ID_INVALID", where(path), text);
    else sink.add("SCHEMA_INVALID", where(path), text);
  }
}

/** Validates `yf.data` against a schema; null (after reporting) when it does not conform. */
function validateFile<T extends z.ZodType>(schema: T, yf: YamlFile, sink: Sink): z.output<T> | null {
  const result = schema.safeParse(yf.data ?? null);
  if (result.success) return result.data;
  reportZod(sink, yf, result.error.issues, isObj(yf.data) && typeof yf.data.id === "string" ? yf.data.id : undefined);
  return null;
}

function reportMissingIds(sink: Sink, yf: YamlFile, kind: Parameters<typeof missingIds>[0]): void {
  for (const path of missingIds(kind, yf.data)) {
    const label = path.length === 0 ? "The file" : pathText(path);
    sink.add("ID_MISSING", { file: yf.file, pos: yamlPos(yf, path) }, `${label} has no id.`);
  }
}

/** Sets a placeholder id where the parsed data lacks one, so the document is checkable. */
function fillIds(kind: Parameters<typeof missingIds>[0], data: Obj, ph: () => string): Obj {
  for (const path of missingIds(kind, data)) {
    let node: unknown = data;
    for (const seg of path) node = (node as Record<string | number, unknown>)[seg];
    if (isObj(node)) node.id = ph();
  }
  return data;
}

interface LessonLoc {
  file: string;
  fm: YamlFile | null;
  blocks: Array<{ pos: Pos; columns: Record<string, number> }>;
  quiz: YamlFile | null;
}
interface ModuleLoc {
  file: string;
  yf: YamlFile | null;
  lessons: LessonLoc[];
}

const MARKUP_TEXT = {
  MARKUP_HTML: "The text contains HTML; use plain text or Markdown.",
  MARKUP_SCRIPT_LINK: "The text contains a script link.",
  MARKUP_REMOTE_IMAGE: "The text has an image from outside the course; images are files under assets/.",
} as const;

const sentence = (message: string): string => {
  const text = message.replace(/^: /, "");
  return `${text.charAt(0).toUpperCase()}${text.slice(1)}.`;
};

const rejectEmpty = (o: Obj): Obj => Object.fromEntries(Object.entries(o).filter(([, v]) => v !== undefined));

/** Errors after which the assembled document would mislead the Blueprint check. */
const STRUCTURAL = /^(YAML_SYNTAX|SCHEMA_INVALID|FIELD_REQUIRED|UNKNOWN_FIELD|FRONT_MATTER_|DIRECTIVE_|FOLDER_NOT_COURSE|ID_INVALID)/;

export function loadCourse(files: Files, assets: AssetLookup): Loaded {
  const sink = new Sink();
  let counter = 0;
  const ph = () => String(++counter).padStart(26, "0");
  const ids = new Map<string, Located[]>();
  const register = (id: unknown, file: string, pos: Pos) => {
    if (typeof id !== "string") return;
    const list = ids.get(id) ?? [];
    list.push({ file, pos });
    ids.set(id, list);
  };

  // ulams.yaml
  let ulamsYf: YamlFile | null = null;
  let ulams: z.output<typeof ulamsSchema> | null = null;
  const ulamsText = files.get(ULAMS_FILE);
  if (ulamsText === undefined) sink.add("FOLDER_NOT_COURSE", { file: ULAMS_FILE }, "There is no ulams.yaml here; this is not a course folder.");
  else {
    ulamsYf = parseYamlFile(ULAMS_FILE, ulamsText, sink);
    if (ulamsYf) ulams = validateFile(ulamsSchema, ulamsYf, sink);
  }
  if (ulams?.lint) sink.uncited = ulams.lint.uncitedAuthorBlocks;

  // course.yaml
  let courseYf: YamlFile | null = null;
  let courseData: z.output<typeof courseSchema> | null = null;
  const courseText = files.get(COURSE_FILE);
  if (courseText === undefined) sink.add("FOLDER_NOT_COURSE", { file: COURSE_FILE }, "There is no course.yaml here; this is not a course folder.");
  else {
    courseYf = parseYamlFile(COURSE_FILE, courseText, sink);
    if (courseYf) {
      reportMissingIds(sink, courseYf, "course");
      courseData = validateFile(courseSchema, courseYf, sink);
    }
  }
  let courseObj: Obj = { id: ph(), title: "Untitled", language: "en", objectives: [] };
  let sources: unknown[] = [];
  let pages: Obj = {};
  if (courseData && courseYf) {
    const { sources: s, pages: p, language, ...rest } = courseData;
    const filled = fillIds("course", rest as Obj, ph);
    register(filled.id, COURSE_FILE, yamlPos(courseYf, ["id"]));
    (filled.objectives as Obj[]).forEach((o, i) => register(o.id, COURSE_FILE, yamlPos(courseYf as YamlFile, ["objectives", i, "id"])));
    const lang = language ?? ulams?.language;
    if (!lang) sink.add("FIELD_REQUIRED", { file: COURSE_FILE, pos: yamlPos(courseYf, []) }, "language: required (two lowercase letters, in course.yaml or ulams.yaml).");
    courseObj = { ...filled, language: lang ?? "en" };
    sources = s ?? [];
    pages = (p ?? {}) as Obj;
  }

  // modules
  const index = indexFolder(files);
  for (const path of index.unknown) sink.add("UNKNOWN_FILE", { file: path }, `${path} is not used by the course folder format.`);
  const moduleLocs: ModuleLoc[] = [];
  const modules: Obj[] = [];
  const moduleKeys = new Map<string, Located>();

  for (const mod of index.modules) {
    if (!/^\d+-/.test(mod.dir)) sink.add("ORDER_PREFIX_MISSING", { file: mod.modulePath ?? mod.path }, `The folder ${mod.dir} has no numeric prefix, so its place in the course is its name order.`);
    const loc: ModuleLoc = { file: mod.modulePath ?? `${mod.path}/${MODULE_FILE}`, yf: null, lessons: [] };
    moduleLocs.push(loc);
    let moduleObj: Obj = { id: ph(), title: mod.dir };
    if (mod.modulePath === null) sink.add("FIELD_REQUIRED", { file: loc.file }, `${mod.path}/${MODULE_FILE} is missing.`);
    else {
      const yf = parseYamlFile(mod.modulePath, files.get(mod.modulePath) as string, sink);
      loc.yf = yf;
      if (yf) {
        reportMissingIds(sink, yf, "module");
        const data = validateFile(moduleSchema, yf, sink);
        if (data) {
          moduleObj = fillIds("module", rejectEmpty(data as Obj), ph);
          register(moduleObj.id, yf.file, yamlPos(yf, ["id"]));
          if (typeof moduleObj.key === "string") {
            const prior = moduleKeys.get(moduleObj.key);
            if (prior) sink.add("KEY_DUPLICATE", { file: yf.file, pos: yamlPos(yf, ["key"]) }, `The key "${moduleObj.key}" is already used by ${prior.file}.`);
            else moduleKeys.set(moduleObj.key, { file: yf.file, pos: yamlPos(yf, ["key"]) });
          }
        }
      }
    }
    for (const orphan of mod.orphanQuizzes) sink.add("QUIZ_ORPHAN", { file: orphan }, `${orphan} has no lesson file ${orphan.replace(/\.quiz\.yaml$/, ".md")}.`);

    const lessons: Obj[] = [];
    const lessonKeys = new Map<string, string>();
    for (const lf of mod.lessons) {
      if (!/^\d+-/.test(lf.name)) sink.add("ORDER_PREFIX_MISSING", { file: lf.path }, `The file ${lf.name} has no numeric prefix, so its place in the module is its name order.`);
      const src = parseLessonFile(lf.path, files.get(lf.path) as string, sink);
      const lloc: LessonLoc = { file: lf.path, fm: src.frontMatter, blocks: [], quiz: null };
      loc.lessons.push(lloc);
      lessons.push(buildLesson(src, lloc, lf.quizPath, lessonKeys));
    }
    modules.push({ ...moduleObj, lessons });
  }

  // final test
  let finalTest: Obj | null = null;
  let finalYf: YamlFile | null = null;
  const finalText = files.get(FINAL_TEST_FILE);
  if (finalText !== undefined) {
    finalYf = parseYamlFile(FINAL_TEST_FILE, finalText, sink);
    if (finalYf) finalTest = buildQuiz(finalYf, "Final test");
  }

  function buildQuiz(yf: YamlFile, where: string): Obj | null {
    reportMissingIds(sink, yf, "quiz");
    const data = validateFile(quizSchema, yf, sink);
    if (!data) return null;
    const quiz = fillIds("quiz", rejectEmpty(data as Obj), ph);
    register(quiz.id, yf.file, yamlPos(yf, ["id"]));
    (quiz.questions as Obj[]).forEach((q, i) => {
      register(q.id, yf.file, yamlPos(yf, ["questions", i, "id"]));
      (q.options as Obj[]).forEach((o, j) => register(o.id, yf.file, yamlPos(yf, ["questions", i, "options", j, "id"])));
      for (const message of questionShape(q, "")) sink.add("QUESTION_SHAPE", { file: yf.file, pos: yamlPos(yf, ["questions", i]), element: String(q.id) }, `${where} question ${i + 1}: ${sentence(message)}`);
      citationRule(q, yf.file, yamlPos(yf, ["questions", i]), `Question ${i + 1}`);
    });
    return quiz;
  }

  function citationRule(node: Obj, file: string, pos: Pos, label: string): void {
    if (node.origin !== "author" && Array.isArray(node.citations) && node.citations.length === 0) {
      sink.add("CITATION_MISSING", { file, pos, element: String(node.id) }, `${label} cites no source and is not marked origin: author.`);
    }
  }

  function buildLesson(src: LessonSource, lloc: LessonLoc, quizPath: string | null, lessonKeys: Map<string, string>): Obj {
    const file = src.file;
    let lessonSound = false;
    let lesson: Obj = { id: ph(), title: file, minutes: 1, objectives: [], citations: [], contentType: "richtext", status: "generated", flags: [] };
    const fm = src.frontMatter;
    if (fm) {
      reportMissingIds(sink, fm, "lesson");
      const data = validateFile(lessonFrontMatterSchema, fm, sink);
      if (data) {
        lessonSound = true;
        lesson = fillIds("lesson", rejectEmpty(data as Obj), ph);
        register(lesson.id, file, yamlPos(fm, ["id"]));
        (lesson.objectives as Obj[]).forEach((o, i) => register(o.id, file, yamlPos(fm, ["objectives", i, "id"])));
        ((lesson.selfChecks as Obj[] | undefined) ?? []).forEach((q, i) => {
          register(q.id, file, yamlPos(fm, ["selfChecks", i, "id"]));
          (q.options as Obj[]).forEach((o, j) => register(o.id, file, yamlPos(fm, ["selfChecks", i, "options", j, "id"])));
          citationRule(q, file, yamlPos(fm, ["selfChecks", i]), `Self-check ${i + 1}`);
        });
        if (isObj(lesson.interaction)) {
          register(lesson.interaction.id, file, yamlPos(fm, ["interaction", "id"]));
          citationRule(lesson.interaction, file, yamlPos(fm, ["interaction"]), "The interaction");
        }
        citationRule(lesson, file, yamlPos(fm, []), "The lesson");
        if (typeof lesson.key === "string") {
          const prior = lessonKeys.get(lesson.key);
          if (prior) sink.add("KEY_DUPLICATE", { file, pos: yamlPos(fm, ["key"]) }, `The key "${lesson.key}" is already used by ${prior}.`);
          else lessonKeys.set(lesson.key, file);
        }
        const asset = expandAsset(lesson.asset, file, fm);
        if (asset === undefined) delete lesson.asset;
        else lesson.asset = asset;
      }
    }
    const lessonId = String(lesson.id);
    const objectiveIds = new Set(((lesson.objectives as Obj[]) ?? []).map((o) => String(o.id)));

    // blocks
    const blocks: Obj[] = [];
    for (const node of src.nodes) {
      if (node.type === "loose") {
        sink.add("LOOSE_MARKDOWN", { file, pos: { line: node.line, column: 1 }, element: lessonId }, "Markdown outside a directive becomes an author paragraph.");
        lloc.blocks.push({ pos: { line: node.line, column: 1 }, columns: {} });
        blocks.push({ id: ph(), kind: "paragraph", markdown: node.markdown, citations: [], objectiveIds: [], origin: "author" });
        checkBlockText(node.markdown, file, node.line, lessonId);
        continue;
      }
      lloc.blocks.push({ pos: { line: node.line, column: 1 }, columns: node.columns });
      if (node.id === undefined && !node.invalid) sink.add("ID_MISSING", { file, pos: { line: node.line, column: 1 }, element: lessonId }, "This block has no #id.");
      const id = node.id ?? ph();
      if (node.id) register(id, file, { line: node.line, column: node.columns.id ?? 1 });
      const block: Obj = { id, kind: node.kind, markdown: node.markdown, citations: node.cite ?? [], objectiveIds: node.objectives ?? [], ...(node.origin ? { origin: node.origin } : {}) };
      blocks.push(block);
      for (const oid of node.objectives ?? []) {
        if (lessonSound && !objectiveIds.has(oid)) sink.add("OBJECTIVE_UNKNOWN", { file, pos: { line: node.line, column: node.columns.objectives ?? 1 }, element: id }, `The objective ${oid} is not an objective of this lesson.`);
      }
      if (node.invalid) {
        checkBlockText(node.markdown, file, node.contentLine, id);
        continue;
      }
      if (node.origin !== "author" && (node.cite ?? []).length === 0) {
        sink.add("CITATION_MISSING", { file, pos: { line: node.line, column: 1 }, element: id }, "This block cites no source and is not marked origin=author.");
      }
      if (node.origin === "author" && (node.cite ?? []).length === 0) {
        sink.add("UNCITED_AUTHOR_BLOCK", { file, pos: { line: node.line, column: 1 }, element: id }, "This author-written block cites no source.");
      }
      checkBlockText(node.markdown, file, node.contentLine, id);
    }

    // quiz
    let quiz: Obj | null = null;
    if (quizPath !== null) {
      const yf = parseYamlFile(quizPath, files.get(quizPath) as string, sink);
      lloc.quiz = yf;
      if (yf) quiz = buildQuiz(yf, "Quiz");
    }
    return { ...lesson, blocks, quiz };
  }

  function checkBlockText(markdown: string, file: string, firstLine: number, element: string): void {
    for (const f of markupFindings(markdown, "")) {
      sink.add(f.code, { file, pos: { line: firstLine + f.line, column: 1 }, element }, MARKUP_TEXT[f.code]);
    }
    for (const img of localImages(markdown)) {
      const pos = { line: firstLine + img.line, column: 1 };
      const lessonDir = file.split("/").slice(0, -1);
      const resolved = normalizePath([...lessonDir, ...img.path.split("#")[0]!.split("?")[0]!.split("/")]);
      if (resolved === null || !resolved.startsWith(`${ASSETS_DIR}/`)) sink.add("IMAGE_OUTSIDE_ASSETS", { file, pos, element }, `The image ${img.path} does not resolve to a file inside ${ASSETS_DIR}/.`);
      else if (assets(resolved) === null) sink.add("IMAGE_MISSING", { file, pos, element }, `The image ${resolved} does not exist.`);
    }
  }

  function expandAsset(asset: unknown, file: string, fm: YamlFile): unknown {
    if (asset === undefined) return undefined;
    const given = typeof asset === "string" ? { path: asset } : (asset as { path: string; sha256?: string; bytes?: number; mediaType?: string });
    const pos = yamlPos(fm, ["asset"]);
    const info = assets(`${ASSETS_DIR}/${given.path}`);
    if (info === null) {
      sink.add("ASSET_MISSING", { file, pos }, `The asset ${ASSETS_DIR}/${given.path} does not exist.`);
      return { path: given.path, sha256: given.sha256 ?? "0".repeat(64), bytes: given.bytes ?? 0, mediaType: given.mediaType ?? guessMediaType(given.path) };
    }
    if ((given.sha256 !== undefined && given.sha256 !== info.sha256) || (given.bytes !== undefined && given.bytes !== info.bytes)) {
      sink.add("ASSET_HASH_MISMATCH", { file, pos }, `${ASSETS_DIR}/${given.path} has sha256 ${info.sha256.slice(0, 12)}... and ${info.bytes} bytes, but the lesson says otherwise.`);
    }
    return { path: given.path, sha256: info.sha256, bytes: info.bytes, mediaType: given.mediaType ?? guessMediaType(given.path) };
  }

  // duplicated ids (the server checks the same: one id, one element)
  for (const [id, places] of ids) {
    if (places.length < 2) continue;
    for (const place of places.slice(1)) sink.add("ID_DUPLICATE", { file: place.file, pos: place.pos, element: id }, `The id ${id} is already used at ${(places[0] as Located).file}:${(places[0] as Located).pos.line}.`);
  }

  const doc: Obj = {
    schemaVersion: 3,
    sources,
    course: courseObj,
    modules,
    finalTest,
    pages,
  };

  const locate = (pointer: string): Located => {
    const seg = pointer.split("/").slice(1).map((s) => s.replace(/~1/g, "/").replace(/~0/g, "~")).map((s) => (/^\d+$/.test(s) ? Number(s) : s));
    const at = (yf: YamlFile | null, fallback: string, path: Path): Located => (yf ? { file: yf.file, pos: yamlPos(yf, path) } : { file: fallback, pos: { line: 1, column: 1 } });
    const head = seg[0];
    if (head === undefined) return at(ulamsYf, ULAMS_FILE, []);
    if (head === "schemaVersion") return at(ulamsYf, ULAMS_FILE, ["blueprint"]);
    if (head === "course") return at(courseYf, COURSE_FILE, seg.slice(1));
    if (head === "sources" || head === "pages") return at(courseYf, COURSE_FILE, seg);
    if (head === "finalTest") return at(finalYf, FINAL_TEST_FILE, seg.slice(1));
    if (head === "modules" && typeof seg[1] === "number") {
      const m = moduleLocs[seg[1]];
      if (!m) return at(courseYf, COURSE_FILE, []);
      if (seg[2] !== "lessons" || typeof seg[3] !== "number") return at(m.yf, m.file, seg.slice(2));
      const l = m.lessons[seg[3]];
      if (!l) return at(m.yf, m.file, []);
      const rest = seg.slice(4);
      if (rest[0] === "blocks" && typeof rest[1] === "number") {
        const b = l.blocks[rest[1]];
        if (!b) return { file: l.file, pos: { line: 1, column: 1 } };
        const attr = ({ citations: "cite", objectiveIds: "objectives", id: "id", origin: "origin" } as Record<string, string>)[String(rest[2])];
        return { file: l.file, pos: { line: b.pos.line, column: (attr ? b.columns[attr] : undefined) ?? b.pos.column } };
      }
      if (rest[0] === "quiz") return l.quiz ? at(l.quiz, l.file, rest.slice(1)) : at(l.fm, l.file, []);
      return at(l.fm, l.file, rest);
    }
    return at(courseYf, COURSE_FILE, []);
  };

  const issues = sink.sorted();
  const structurallySound = !issues.some((i) => i.severity === "error" && STRUCTURAL.test(i.code));
  return { doc, sink, issues, structurallySound, locate };
}

/** `a/b/../c` -> `a/c`; null when it climbs out of the folder. */
export function normalizePath(parts: string[]): string | null {
  const out: string[] = [];
  for (const p of parts) {
    if (p === "" || p === ".") continue;
    if (p === "..") {
      if (out.length === 0) return null;
      out.pop();
    } else out.push(p);
  }
  return out.join("/");
}
