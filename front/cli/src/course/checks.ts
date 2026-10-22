/* Deterministic checks ported from api/packages/course-builder/src/Blueprint/Checks.php. The messages are the same
 * strings; api/packages/course-builder/tests/fixtures/checks/*.json holds inputs and expected messages and is run by
 * Vitest (tests/course/checks.test.ts) so a drift between the two implementations shows up in CI. */

const blank = (s: string) => s.replace(/[^\n]/g, " ");

export interface MarkupFinding {
  code: "MARKUP_HTML" | "MARKUP_SCRIPT_LINK" | "MARKUP_REMOTE_IMAGE";
  message: string;
  /** 0-based line inside `text` where the problem starts. */
  line: number;
}

/** Raw HTML outside code (fenced or inline), `javascript:` links and remote images. */
export function markupFindings(text: string, where: string): MarkupFinding[] {
  // Code is blanked, not removed, so match offsets still point at the original lines.
  let stripped = text.replace(/```[\s\S]*?```|~~~[\s\S]*?~~~/g, blank);
  stripped = stripped.replace(/`[^`\n]*`/g, blank);
  const lineOf = (index: number) => stripped.slice(0, index).split("\n").length - 1;
  const out: MarkupFinding[] = [];
  const html = /<\s*\/?\s*[a-zA-Z][a-zA-Z0-9-]*(\s[^<>]*)?\/?>/.exec(stripped);
  if (html) out.push({ code: "MARKUP_HTML", message: `${where}: contains HTML; use plain text or Markdown`, line: lineOf(html.index) });
  const script = /\]\(\s*(javascript|data|vbscript):/i.exec(stripped) ?? /\bjavascript:/i.exec(stripped);
  if (script) out.push({ code: "MARKUP_SCRIPT_LINK", message: `${where}: contains a script link`, line: lineOf(script.index) });
  const remote = /!\[[^\]]*\]\(\s*https?:/i.exec(stripped);
  if (remote) out.push({ code: "MARKUP_REMOTE_IMAGE", message: `${where}: images from outside the course are not allowed`, line: lineOf(remote.index) });
  return out;
}

export function markup(text: string, where: string): string[] {
  return markupFindings(text, where).map((f) => f.message);
}

/** Local image references outside code: `![alt](path)`; remote ones are reported by `markupFindings`. */
export function localImages(text: string): Array<{ path: string; line: number }> {
  const stripped = text.replace(/```[\s\S]*?```|~~~[\s\S]*?~~~/g, blank).replace(/`[^`\n]*`/g, blank);
  const out: Array<{ path: string; line: number }> = [];
  for (const m of stripped.matchAll(/!\[[^\]]*\]\(\s*([^)\s]+)[^)]*\)/g)) {
    const path = m[1] as string;
    if (/^[a-z][a-z0-9+.-]*:/i.test(path)) continue;
    out.push({ path, line: stripped.slice(0, m.index).split("\n").length - 1 });
  }
  return out;
}

export interface QuestionLike {
  type?: unknown;
  options?: Array<{ text?: unknown; correct?: unknown }>;
}

/** Shape rules per question type. */
export function questionShape(q: QuestionLike, where: string): string[] {
  const options = q.options ?? [];
  const correct = options.filter((o) => Boolean(o.correct)).length;
  const texts = options.map((o) => String(o.text ?? "").trim().toLowerCase());
  const out: Array<string | null> = [];
  switch (q.type) {
    case "single":
      out.push(
        options.length < 3 ? `${where}: a single-choice question needs at least 3 options` : null,
        correct !== 1 ? `${where}: a single-choice question needs exactly one correct option` : null,
        new Set(texts).size !== texts.length ? `${where}: options repeat` : null
      );
      break;
    case "multiple":
      out.push(
        options.length < 3 ? `${where}: a multiple-choice question needs at least 3 options` : null,
        correct < 2 ? `${where}: a multiple-answer question needs at least 2 correct options` : null,
        correct === options.length ? `${where}: at least one option must be wrong` : null
      );
      break;
    case "truefalse":
      out.push(
        options.length !== 2 || texts.some((t) => t !== "true" && t !== "false") ? `${where}: true/false options must be exactly True and False` : null,
        correct !== 1 ? `${where}: exactly one of True/False is correct` : null
      );
      break;
    case "short":
      out.push(correct !== options.length || options.length === 0 ? `${where}: every short-answer option is an accepted answer (correct: true)` : null);
      break;
    default:
      out.push(`${where}: unknown question type`);
  }
  return out.filter((m): m is string => m !== null);
}
