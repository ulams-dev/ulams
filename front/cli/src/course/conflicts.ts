import { parse as parseYaml } from "yaml";
import { parseLessonFile, serializeDirective, FenceTracker } from "./lesson-file.ts";
import { Sink } from "./issues.ts";
import { DOC, type Conflict, type ConflictKind } from "./merge.ts";
import { unifiedDiff } from "./textdiff.ts";
import { isObj, type Obj } from "./types.ts";
import { stringifyValue } from "./yaml-io.ts";

/* Conflict files (plan 7.5): `.ulams/conflicts/<element id>.md` shows the element as the base, the folder and the server
 * have it, in the folder's own syntax (a block as a directive, everything else as YAML), with a unified diff and a
 * "Resolution" section the author may edit. `ulams course resolve <id> --take file` reads that section back. */

export const REMOVED = "(removed)";

export const conflictFileName = (id: string): string => (id === DOC ? "_root" : id);

const KIND_TEXT: Record<ConflictKind, string> = {
  "edit-edit": "Both sides changed this element differently.",
  "add-add": "Both sides added an element with this id, with different content.",
  "move-move": "Both sides moved this element to different places.",
  "removed-ours": "You removed this element (with everything in it) while the server changed it or something in it.",
  "removed-theirs": "The server removed this element (with everything in it) while you changed it or something in it.",
  slot: "Both sides put a different element into the single slot this one belongs in.",
  orphan: "The element's parent is gone after the merge, so it has no place to go.",
};

export const kindText = (kind: ConflictKind): string => KIND_TEXT[kind];

const fenceFor = (text: string): string => {
  let longest = 3;
  for (const m of text.matchAll(/~{3,}/g)) longest = Math.max(longest, m[0].length + 1);
  return "~".repeat(longest);
};

const withIdFirst = (own: Obj, id: string): Obj => ({ id, ...Object.fromEntries(Object.entries(own).filter(([k]) => k !== "id")) });

/** An element's own data as text in the folder's syntax. */
export function renderElement(type: string, id: string, own: Obj | null): string {
  if (own === null) return REMOVED;
  if (type === "block") {
    return serializeDirective({
      kind: String(own.kind),
      id,
      cite: Array.isArray(own.citations) ? (own.citations as string[]) : [],
      ...(typeof own.origin === "string" ? { origin: own.origin } : {}),
      objectives: Array.isArray(own.objectiveIds) ? (own.objectiveIds as string[]) : [],
      markdown: String(own.markdown ?? ""),
    });
  }
  return stringifyValue(type === "pages" || id === DOC ? own : withIdFirst(own, id), { keys: ["id"] }).trimEnd();
}

/** The inverse of `renderElement`. Throws a sentence the author can act on. */
export function parseElement(type: string, id: string, text: string): Obj | null {
  const body = text.trim();
  if (body === REMOVED) return null;
  if (type === "block") {
    const sink = new Sink();
    const lesson = parseLessonFile("conflict", `---\n---\n\n${body}\n`, sink);
    const nodes = lesson.nodes;
    const errors = sink.issues.filter((i) => i.severity === "error");
    if (errors.length > 0) throw new Error(`The block in the Resolution section is not valid: ${errors[0]?.message ?? "unknown error"}`);
    const node = nodes[0];
    if (nodes.length !== 1 || !node || node.type !== "directive") throw new Error("The Resolution section must hold exactly one block directive (:::kind ... :::), or the word (removed).");
    return {
      id: node.id ?? id,
      kind: node.kind,
      markdown: node.markdown,
      citations: node.cite ?? [],
      objectiveIds: node.objectives ?? [],
      ...(node.origin ? { origin: node.origin } : {}),
    };
  }
  let value: unknown;
  try {
    value = parseYaml(body);
  } catch (e) {
    throw new Error(`The Resolution section is not valid YAML: ${(e as Error).message.split("\n")[0]}`, { cause: e });
  }
  if (!isObj(value)) throw new Error("The Resolution section must hold the element as YAML (key: value lines), or the word (removed).");
  return value;
}

export interface ConflictMeta {
  element: string;
  type: string;
  kind: ConflictKind;
  /** The version the folder was based on when the conflict arose. */
  base: { versionId: string; versionNumber: number };
  /** The server's version the folder was merged with (the folder's base now). */
  remote: { versionId: string; versionNumber: number };
}

function section(title: string, type: string, id: string, own: Obj | null): string[] {
  const text = renderElement(type, id, own);
  const fence = fenceFor(text);
  return [`## ${title}`, "", `${fence}ulams-element`, text, fence, ""];
}

/** The text of `.ulams/conflicts/<id>.md`. */
export function renderConflict(c: Conflict, meta: ConflictMeta, label: string): string {
  const id = c.id;
  const base = renderElement(c.type, id, c.base);
  const ours = renderElement(c.type, id, c.ours);
  const theirs = renderElement(c.type, id, c.theirs);
  const diff = unifiedDiff(ours, theirs, `yours (folder)`, `server (version ${meta.remote.versionNumber})`);
  // what the author starts from: the folder's version, or the server's when the folder has none
  const start = c.ours !== null ? c.ours : c.kind === "removed-ours" ? null : c.theirs;
  const out = [
    "---",
    stringifyValue({ element: meta.element, type: meta.type, kind: meta.kind, base: meta.base, remote: meta.remote }, { keys: ["element", "type", "kind", "base", "remote"] }).trimEnd(),
    "---",
    "",
    `# Conflict: ${label}`,
    "",
    `${kindText(c.kind)} Element ${id}.`,
    "",
    "Your folder holds your version of this element (or has none when you removed it). Nothing is sent to the server until every conflict is resolved.",
    "",
    "Resolve it one of three ways, then `ulams course push`:",
    "",
    `- keep yours: \`ulams course resolve ${id} --take local\``,
    `- take the server's: \`ulams course resolve ${id} --take remote\``,
    `- combine them: edit the Resolution section below, then \`ulams course resolve ${id} --take file\``,
    "",
    ...(c.held.length > 0 ? [`Resolving this element also settles ${c.held.length} element${c.held.length === 1 ? "" : "s"} below it.`, ""] : []),
    ...(c.fields.length > 0 ? [`Fields that differ: ${c.fields.map((f) => f.field).join(", ")}.`, ""] : []),
    ...section(`Base (version ${meta.base.versionNumber})`, c.type, id, c.base),
    ...section("Yours (folder)", c.type, id, c.ours),
    ...section(`Server (version ${meta.remote.versionNumber})`, c.type, id, c.theirs),
    "## Difference (yours to the server's)",
    "",
    diff === "" ? "(none: the two sides are identical apart from where the element sits)" : "```diff",
    ...(diff === "" ? [] : [diff, "```"]),
    "",
    ...section("Resolution", c.type, id, start),
  ];
  void base;
  return `${out.join("\n").trimEnd()}\n`;
}

/** Reads `.ulams/conflicts/<id>.md` back: its meta and the text of the Resolution section. */
export function readConflict(text: string): { meta: ConflictMeta; resolution: string } {
  const lines = text.replace(/\r\n?/g, "\n").split("\n");
  if (lines[0]?.trimEnd() !== "---") throw new Error("The conflict file lost its front matter.");
  const end = lines.indexOf("---", 1);
  if (end < 0) throw new Error("The conflict file's front matter is not closed.");
  const meta = parseYaml(lines.slice(1, end).join("\n")) as Partial<ConflictMeta> | null;
  if (!meta || typeof meta.element !== "string" || typeof meta.type !== "string" || typeof meta.kind !== "string") throw new Error("The conflict file's front matter is incomplete; do not edit it.");

  let heading = -1;
  const fence = new FenceTracker();
  for (let i = end + 1; i < lines.length; i++) {
    const line = lines[i] as string;
    if (fence.feed(line)) continue;
    if (line.trimEnd() === "## Resolution") heading = i;
  }
  if (heading < 0) throw new Error('The conflict file has no "## Resolution" section.');
  let open = -1;
  let closer = "";
  for (let i = heading + 1; i < lines.length; i++) {
    const m = /^(~{3,})ulams-element\s*$/.exec(lines[i] as string);
    if (m) {
      open = i;
      closer = m[1] as string;
      break;
    }
  }
  if (open < 0) throw new Error('The "## Resolution" section lost its ~~~ulams-element fence.');
  let close = -1;
  for (let i = open + 1; i < lines.length; i++) {
    if ((lines[i] as string).trimEnd() === closer) close = i;
  }
  if (close < 0) throw new Error(`The Resolution fence is not closed (expected a line holding only ${closer}).`);
  return { meta: meta as ConflictMeta, resolution: lines.slice(open + 1, close).join("\n") };
}
