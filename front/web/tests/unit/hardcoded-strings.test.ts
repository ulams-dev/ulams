import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";

/**
 * No new English (P5R-1): the visible text of the learner pages and the catalogue components they render comes
 * from a strings table (`@ulams/ui/player-strings`, `account-strings`, `training-strings`, `i18n`), never from a
 * literal in the markup. The check reads the Astro templates (frontmatter, script and style removed, every
 * `{expression}` removed) and fails on a text node or a `title`, `aria-label`, `alt` or `placeholder` attribute
 * that still holds words; for the browser elements it fails on a literal given to `textContent`, `say`,
 * `announce` or as the text child of `h(...)`.
 */
const root = (path: string) => fileURLToPath(new URL(`../../../${path}`, import.meta.url));
const read = (path: string) => readFileSync(root(path), "utf8");

const PAGES = [
  "web/src/components/LessonPlayer.astro",
  "web/src/pages/learn/[courseId]/finish.astro",
  "web/src/pages/courses/[id].astro",
  "web/src/pages/account/index.astro",
  "web/src/pages/login.astro",
  "ui/src/components/CourseHeader.astro",
  "ui/src/components/Syllabus.astro",
  "ui/src/components/PurchaseCard.astro",
  "ui/src/components/QuizRunner.astro",
  "ui/src/components/VideoPlayer.astro",
  "ui/src/components/AudioPlayer.astro",
  "ui/src/components/PdfViewer.astro",
  "ui/src/components/PackageFrame.astro",
  "ui/src/components/LiaScriptLesson.astro",
  "ui/src/components/ActivityCard.astro",
  "ui/src/components/UpdateNotice.astro",
  "ui/src/components/CourseUpdates.astro",
  "ui/src/components/ReattemptNotice.astro",
  "ui/src/components/PendingUpdateNotice.astro",
];
const ELEMENTS = ["ui/src/elements/quiz.ts", "ui/src/elements/progress.ts", "ui/src/elements/notice.ts", "ui/src/elements/video.ts", "ui/src/elements/h5p.ts"];

/** Words that are the same in every language (names, commands, environment variables). */
const ALLOWED = [/^ulams$/i, /^ULAMS_(TOKEN|URL)=?$/, /^printf/, /^MOD$/];

/** Removes the first balanced `{...}` expressions; what is left is literal markup. */
function withoutExpressions(source: string): string {
  let out = "";
  let depth = 0;
  for (const char of source) {
    if (char === "{") depth++;
    else if (char === "}" && depth > 0) depth--;
    else if (depth === 0) out += char;
  }
  return out;
}

function template(source: string): string {
  const body = source.replace(/^---[\s\S]*?\n---/, "").replace(/<script[\s\S]*?<\/script>/g, "").replace(/<style[\s\S]*?<\/style>/g, "");
  return withoutExpressions(body.replace(/<!--[\s\S]*?-->/g, ""));
}

const hasWords = (text: string) => /[\p{L}]{2,}/u.test(text.replace(/&\w+;/g, " "));

function literals(source: string): string[] {
  const html = template(source);
  const found: string[] = [];
  for (const m of html.matchAll(/>([^<>]+)</g)) {
    const text = m[1]!.trim();
    if (text && hasWords(text)) found.push(`text "${text}"`);
  }
  for (const m of html.matchAll(/\s(title|aria-label|alt|placeholder)="([^"]*)"/g)) {
    if (hasWords(m[2]!)) found.push(`${m[1]}="${m[2]}"`);
  }
  return found.filter((f) => !ALLOWED.some((a) => a.test(f.replace(/^text "|"$/g, ""))));
}

function elementLiterals(source: string): string[] {
  const code = source.replace(/\/\*[\s\S]*?\*\//g, "").replace(/^\s*\/\/.*$/gm, "");
  const found: string[] = [];
  for (const m of code.matchAll(/textContent\s*=\s*(?:`[^`$]*`|"[^"]*")/g)) if (hasWords(m[0])) found.push(m[0]);
  for (const m of code.matchAll(/\b(?:say|announce)\(\s*"[^"]*[A-Za-z][^"]*"/g)) found.push(m[0]);
  for (const m of code.matchAll(/,\s*"([A-Z][a-z][^"]*)"\s*\)/g)) found.push(m[0]);
  return found;
}

describe("learner pages have no literal visible text", () => {
  it.each(PAGES)("%s", (file) => {
    expect(literals(read(file)), `${file} has text that is not from a strings table`).toEqual([]);
  });

  it.each(ELEMENTS)("%s", (file) => {
    expect(elementLiterals(read(file)), `${file} has text that is not from a strings table`).toEqual([]);
  });

  it("the check itself finds a literal", () => {
    expect(literals("---\nconst a = 1;\n---\n<p>Hello <b>{name}</b></p><button aria-label=\"Close\">{x}</button>")).toEqual(['text "Hello"', 'aria-label="Close"']);
    expect(literals("---\n---\n<p>{t.hello}</p><a title={t.x}>{t.y}</a><script>const a = 'Text'</script>")).toEqual([]);
    expect(elementLiterals('this.say("Saved."); el.textContent = "Done"; h("p", {}, "Try again")')).toHaveLength(3);
  });
});
