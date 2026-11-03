import { existsSync, mkdirSync, readFileSync, readdirSync, rmSync, rmdirSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { CliError } from "../errors.ts";
import { orderOf } from "./folder.ts";
import { folderFromBlueprint } from "./from-blueprint.ts";
import { COURSE_FILE, FINAL_TEST_FILE, MODULES_DIR, ULAMS_FILE } from "./layout.ts";
import { pad } from "./ids.ts";
import { parseBase, serializeBase, type BaseFile } from "./sync.ts";
import type { Files, Obj } from "./types.ts";

/* The files `init`, `pull` and `push` keep next to the course: `.ulams/base.json` (committed) and
 * `.ulams/proposal.json` (local: the version a push proposed and nobody has approved yet). */

export const STATE_DIR = ".ulams";
export const BASE_FILE = `${STATE_DIR}/base.json`;
export const PROPOSAL_FILE = `${STATE_DIR}/proposal.json`;
export const CONFLICTS_DIR = `${STATE_DIR}/conflicts`;
const GITIGNORE = `${STATE_DIR}/.gitignore`;

const NOT_INITIALISED = `Run \`ulams course init --from-course <id>\` to bind this folder to a course.`;

export function readBase(root: string): BaseFile {
  const path = join(root, BASE_FILE);
  if (!existsSync(path)) throw new CliError("INPUT_INVALID", `${BASE_FILE} is missing, so this folder is not bound to a course yet.`, { hint: NOT_INITIALISED });
  const parsed = parseBase(readFileSync(path, "utf8"));
  if ("error" in parsed) throw new CliError("INPUT_INVALID", `${BASE_FILE} ${parsed.error}.`, { hint: "Restore it from Git, or pull the course into a fresh folder with `ulams course init --from-course <id>`." });
  return parsed.base;
}

export const hasBase = (root: string): boolean => existsSync(join(root, BASE_FILE));

function write(root: string, rel: string, text: string): void {
  mkdirSync(dirname(join(root, rel)), { recursive: true });
  writeFileSync(join(root, rel), text);
}

export function writeBase(root: string, base: BaseFile): void {
  write(root, BASE_FILE, serializeBase(base));
  // the proposal is personal (a version waiting for someone's approval); the base is shared through Git
  if (!existsSync(join(root, GITIGNORE))) write(root, GITIGNORE, "proposal.json\n");
}

export interface Proposal {
  versionId: string;
  versionNumber: number;
  baseVersionId: string;
  documentHash: string;
  message: string | null;
  counts: { added: number; changed: number; removed: number };
  pushedAt: string;
  /** The server merged the push with newer changes: the version is not what the folder holds, so approving it must not move the base. */
  merged?: boolean;
  /** `absorb`: the version reads admin edits back from the LMS (`course absorb-drift`); absent means a push of the folder. */
  kind?: "absorb";
  /** `type:element` keys of the LMS entities the absorbed version took edits from. */
  absorbed?: string[];
}

export function readProposal(root: string): Proposal | null {
  const path = join(root, PROPOSAL_FILE);
  if (!existsSync(path)) return null;
  try {
    const p = JSON.parse(readFileSync(path, "utf8")) as Partial<Proposal>;
    return typeof p.versionId === "string" && typeof p.baseVersionId === "string" ? (p as Proposal) : null;
  } catch {
    return null;
  }
}

export function writeProposal(root: string, p: Proposal): void {
  write(root, PROPOSAL_FILE, `${JSON.stringify(p, null, 2)}\n`);
}

export function clearProposal(root: string): void {
  rmSync(join(root, PROPOSAL_FILE), { force: true });
}

export const conflictPath = (root: string, name: string): string => join(root, CONFLICTS_DIR, `${name}.md`);

export function writeConflict(root: string, name: string, text: string): void {
  write(root, `${CONFLICTS_DIR}/${name}.md`, text);
}

export function readConflictText(root: string, name: string): string | null {
  const path = conflictPath(root, name);
  return existsSync(path) ? readFileSync(path, "utf8") : null;
}

export function removeConflict(root: string, name: string): void {
  rmSync(conflictPath(root, name), { force: true });
  try {
    rmdirSync(join(root, CONFLICTS_DIR));
  } catch {
    /* other conflicts are still open */
  }
}

export function listConflicts(root: string): string[] {
  try {
    return readdirSync(join(root, CONFLICTS_DIR))
      .filter((n) => n.endsWith(".md"))
      .map((n) => n.slice(0, -3))
      .sort();
  } catch {
    return [];
  }
}

/** The course folder's own text files: what `pull` may rewrite or delete. Everything else (assets, sources, notes) is the author's. */
export const isManaged = (path: string): boolean => path === COURSE_FILE || path === FINAL_TEST_FILE || (path.startsWith(`${MODULES_DIR}/`) && /\.(md|yaml)$/.test(path));

const topLevelId = (text: string): string | null => /^id:\s*["']?([0-9a-z]{26})["']?\s*$/m.exec(text)?.[1] ?? null;
const frontMatter = (text: string): string => /^---\r?\n([\s\S]*?)\r?\n---/.exec(text)?.[1] ?? "";

interface Slots {
  modules: Map<string, string>;
  lessons: Map<string, string>;
}

/** Id -> the slug (name without number prefix) the author's files use now. */
function existingSlugs(files: Files): Slots {
  const slots: Slots = { modules: new Map(), lessons: new Map() };
  for (const [path, text] of files) {
    const m = /^modules\/([^/]+)\/([^/]+)$/.exec(path);
    if (!m) continue;
    if (m[2] === "module.yaml") {
      const id = topLevelId(text);
      if (id) slots.modules.set(id, orderOf(m[1] as string).slug);
    } else if (m[2]?.endsWith(".md")) {
      const id = topLevelId(frontMatter(text));
      if (id) slots.lessons.set(id, orderOf((m[2] as string).replace(/\.md$/, "")).slug);
    }
  }
  return slots;
}

/**
 * Renames freshly generated files to the names the author already uses, keeping the position prefix of the new order:
 * a lesson the author renamed to `02-pods-and-nodes.md` stays that way after a pull, and a new module in second place
 * moves the others down by one, as `course fmt` would.
 */
export function keepAuthorNames(generated: Files, existing: Files): Files {
  const slots = existingSlugs(existing);
  const moduleDir = new Map<string, string>();
  for (const [path, text] of generated) {
    const m = /^(modules\/[^/]+)\/module\.yaml$/.exec(path);
    const id = m ? topLevelId(text) : null;
    if (m && id) {
      const prefix = /^modules\/(\d+)-/.exec(m[1] as string)?.[1] ?? pad(0);
      const slug = slots.modules.get(id);
      moduleDir.set(m[1] as string, slug ? `${MODULES_DIR}/${prefix}-${slug}` : (m[1] as string));
    }
  }
  const renameLesson = new Map<string, string>();
  for (const [path, text] of generated) {
    const m = /^(modules\/[^/]+)\/(\d+)-[^/]+\.md$/.exec(path);
    const id = m ? topLevelId(frontMatter(text)) : null;
    if (m && id) {
      const slug = slots.lessons.get(id);
      if (slug) renameLesson.set(path.replace(/\.md$/, ""), `${m[2]}-${slug}`);
    }
  }
  const out: Files = new Map();
  for (const [path, text] of generated) {
    const m = /^(modules\/[^/]+)\/([^/]+)$/.exec(path);
    if (!m) {
      out.set(path, text);
      continue;
    }
    const dir = moduleDir.get(m[1] as string) ?? (m[1] as string);
    const stem = /^(.*?)(\.quiz\.yaml|\.md)$/.exec(m[2] as string);
    const renamed = stem ? renameLesson.get(`${m[1]}/${stem[1]}`) : undefined;
    out.set(`${dir}/${stem && renamed ? `${renamed}${stem[2]}` : m[2]}`, text);
  }
  return out;
}

export interface Materialised {
  written: string[];
  updated: string[];
  removed: string[];
  unchanged: number;
}

/** What writing `files` over the folder would do to the managed text files. */
export function planMaterialise(existing: Files, files: Files): Materialised {
  const out: Materialised = { written: [], updated: [], removed: [], unchanged: 0 };
  for (const [path, text] of files) {
    const before = existing.get(path);
    if (before === undefined) out.written.push(path);
    else if (before !== text) out.updated.push(path);
    else out.unchanged++;
  }
  for (const path of existing.keys()) if (isManaged(path) && !files.has(path)) out.removed.push(path);
  for (const list of [out.written, out.updated, out.removed]) list.sort();
  return out;
}

/** Writes the files, removes managed files that are gone from the course and empties module folders left behind. */
export function materialise(root: string, existing: Files, files: Files): Materialised {
  const plan = planMaterialise(existing, files);
  for (const path of plan.removed) rmSync(join(root, path), { force: true });
  for (const path of [...plan.written, ...plan.updated]) write(root, path, files.get(path) as string);
  const dirs = new Set(plan.removed.map((p) => dirname(p)).filter((d) => d !== "."));
  for (const dir of dirs) {
    try {
      rmdirSync(join(root, dir));
    } catch {
      /* not empty: the author keeps other files in it */
    }
  }
  return plan;
}

/**
 * The files after a change to the document, leaving untouched every file whose content did not change: `before` and
 * `after` are written the canonical way, and a file that is the same in both keeps the author's own text (comments,
 * spacing). Files that did change are written canonically.
 */
export function filesAfterChange(existing: Files, before: Obj, after: Obj): Files {
  const canonical = (doc: Obj): Files => {
    const generated = folderFromBlueprint(doc);
    generated.delete(ULAMS_FILE);
    return keepAuthorNames(generated, existing);
  };
  const was = canonical(before);
  const out: Files = new Map();
  for (const [path, text] of canonical(after)) {
    const original = existing.get(path);
    out.set(path, original !== undefined && was.get(path) === text ? original : text);
  }
  return out;
}
