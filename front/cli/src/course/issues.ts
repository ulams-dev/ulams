import type { Issue, Pos, Severity } from "./types.ts";

interface Entry {
  severity: Severity;
  title: string;
  hint: string;
  /** `ulams course fmt` and `validate --fix` repair it. */
  fixable?: boolean;
}

/** Every issue code `ulams course validate` can report. The docs page lists the same table (a test keeps them in step). */
export const CATALOG = {
  FOLDER_NOT_COURSE: { severity: "error", title: "The folder has no ulams.yaml or course.yaml", hint: "Run the command inside a course folder, or pass its path." },
  YAML_SYNTAX: { severity: "error", title: "A YAML file or front matter is not valid YAML", hint: "Fix the YAML syntax at the reported position." },
  SCHEMA_INVALID: { severity: "error", title: "A field has the wrong type or an out-of-range value", hint: "Check the field against the file schema (docs: course folder format)." },
  FIELD_REQUIRED: { severity: "error", title: "A required field is missing", hint: "Add the field." },
  UNKNOWN_FIELD: { severity: "error", title: "A field the format does not define", hint: "Remove it or fix the spelling." },
  FRONT_MATTER_MISSING: { severity: "error", title: "A lesson file has no YAML front matter", hint: "Start the file with a --- fenced block holding at least title and minutes." },
  FRONT_MATTER_UNCLOSED: { severity: "error", title: "The front matter is never closed", hint: "Close it with a line holding only ---." },
  DIRECTIVE_UNCLOSED: { severity: "error", title: "A directive is never closed", hint: "Add a line holding only the closing colons (same count as the opening line)." },
  DIRECTIVE_STRAY_CLOSER: { severity: "error", title: "A closing fence without an open directive", hint: "Remove it, or add the opening :::kind line." },
  DIRECTIVE_SYNTAX: { severity: "error", title: "A line starting with ::: is not a valid directive line", hint: "Write `:::kind` or `:::kind{#id cite=\"frg_...\"}`." },
  DIRECTIVE_NESTED: { severity: "error", title: "A directive opens inside another directive", hint: "Close the first one, or give the outer directive a longer fence (::::)." },
  DIRECTIVE_KIND_UNKNOWN: { severity: "error", title: "Unknown block kind", hint: "Use paragraph, callout, steps, code, table or example." },
  DIRECTIVE_ATTR_UNKNOWN: { severity: "error", title: "Unknown directive attribute", hint: "Use #id, cite, origin or objectives." },
  DIRECTIVE_ATTR_INVALID: { severity: "error", title: "A directive attribute has an invalid value", hint: "Ids are 26 lowercase letters and digits; cite takes frg_ ids; origin is ai or author." },
  DIRECTIVE_EMPTY: { severity: "error", title: "A directive has no content", hint: "Write the block's Markdown between the fences, or remove the directive." },
  LOOSE_MARKDOWN: { severity: "info", title: "Markdown outside a directive", hint: "Run `ulams course fmt` to wrap it in author paragraphs.", fixable: true },
  ID_MISSING: { severity: "error", title: "An element has no id", hint: "Run `ulams course fmt` to mint ids.", fixable: true },
  ID_INVALID: { severity: "error", title: "An id is not 26 lowercase letters and digits", hint: "Use a lowercase ULID; quote it in YAML when it is all digits." },
  ID_DUPLICATE: { severity: "error", title: "Two elements share an id", hint: "Give one of them a new id (delete the id and run `ulams course fmt`)." },
  KEY_DUPLICATE: { severity: "error", title: "Two siblings share a key", hint: "Keys are unique among the modules of a course and among the lessons of a module." },
  OBJECTIVE_UNKNOWN: { severity: "error", title: "An objective id that does not exist where it is used", hint: "A lesson lists ids from course.yaml; a block lists ids of its own lesson." },
  CITATION_MISSING: { severity: "error", title: "An AI-written element cites no source", hint: "Cite at least one fragment, or set origin: author if you wrote it yourself." },
  UNCITED_AUTHOR_BLOCK: { severity: "warning", title: "An author-written block has no sources", hint: "Living Course cannot keep an uncited block up to date. Add cite=\"frg_...\" when a source exists, or set lint.uncitedAuthorBlocks in ulams.yaml." },
  MARKUP_HTML: { severity: "error", title: "Raw HTML outside code", hint: "Use plain text or Markdown." },
  MARKUP_SCRIPT_LINK: { severity: "error", title: "A javascript:, data: or vbscript: link", hint: "Use an https link." },
  MARKUP_REMOTE_IMAGE: { severity: "error", title: "An image from outside the course", hint: "Put the image in assets/ and reference it relatively." },
  IMAGE_OUTSIDE_ASSETS: { severity: "error", title: "An image path that does not resolve inside assets/", hint: "Use `![alt](../../assets/name.png)` from a lesson file." },
  IMAGE_MISSING: { severity: "error", title: "An image file that does not exist", hint: "Add the file under assets/ or fix the path." },
  ASSET_MISSING: { severity: "error", title: "A lesson asset that does not exist", hint: "Add the file under assets/ or fix asset.path." },
  ASSET_HASH_MISMATCH: { severity: "error", title: "An asset's sha256 or size does not match the file", hint: "Run `ulams course fmt` to refresh them.", fixable: true },
  QUESTION_SHAPE: { severity: "error", title: "A question breaks the rules of its type", hint: "single: 3+ options, one correct. multiple: 3+, 2+ correct, one wrong. truefalse: True and False. short: every option is an accepted answer." },
  QUIZ_ORPHAN: { severity: "error", title: "A quiz file without a lesson file", hint: "Name it after its lesson: 01-install.md pairs with 01-install.quiz.yaml." },
  ORDER_PREFIX_MISSING: { severity: "warning", title: "A module folder or lesson file without a numeric prefix", hint: "Run `ulams course fmt` to number them.", fixable: true },
  UNKNOWN_FILE: { severity: "warning", title: "A file the format does not use", hint: "Lessons are .md, quizzes .quiz.yaml, modules module.yaml." },
  BLUEPRINT_SCHEMA: { severity: "error", title: "The assembled course breaks the Blueprint v3 schema", hint: "See the message for the JSON path; the course folder maps to a Blueprint v3 document." },
} as const satisfies Record<string, Entry>;

export type IssueCode = keyof typeof CATALOG;
export type UncitedLevel = "warn" | "error" | "off";

export interface Where {
  file: string;
  pos?: Pos;
  element?: string;
}

/** Collects issues; every issue carries the file and a 1-based line and column. */
export class Sink {
  readonly issues: Issue[] = [];
  private readonly seen = new Set<string>();

  constructor(public uncited: UncitedLevel = "warn") {}

  add(code: IssueCode, where: Where, message: string, severity?: Severity): void {
    const entry: Entry = CATALOG[code];
    let level = severity ?? entry.severity;
    if (code === "UNCITED_AUTHOR_BLOCK") {
      if (this.uncited === "off") return;
      level = this.uncited === "error" ? "error" : "warning";
    }
    const line = where.pos?.line ?? 1;
    const column = where.pos?.column ?? 1;
    const key = `${code}|${where.file}|${line}|${column}|${message}`;
    if (this.seen.has(key)) return;
    this.seen.add(key);
    this.issues.push({
      code,
      severity: level,
      file: where.file,
      line,
      column,
      ...(where.element ? { element: where.element } : {}),
      message,
      hint: entry.hint,
    });
  }

  get errors(): number {
    return this.issues.filter((i) => i.severity === "error").length;
  }
  get warnings(): number {
    return this.issues.filter((i) => i.severity === "warning").length;
  }

  /** Stable order: file, line, column, code. */
  sorted(): Issue[] {
    return [...this.issues].sort((a, b) => a.file.localeCompare(b.file) || a.line - b.line || a.column - b.column || a.code.localeCompare(b.code));
  }
}

export function summarize(issues: Issue[]): { errors: number; warnings: number; infos: number } {
  return {
    errors: issues.filter((i) => i.severity === "error").length,
    warnings: issues.filter((i) => i.severity === "warning").length,
    infos: issues.filter((i) => i.severity === "info").length,
  };
}
