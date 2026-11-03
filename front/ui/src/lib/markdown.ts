/**
 * Markdown to HTML for course content (server only). Course content is untrusted input:
 * raw HTML in the source is escaped, links and images must use safe schemes, and math
 * ($$…$$ and $…$) is rendered by KaTeX as MathML (no client CSS or fonts needed).
 */
import { Marked } from "marked";
import katex from "katex";
import { baseRenderer, escapeHtml } from "./markdown-basic.ts";

const math = (tex: string, displayMode: boolean): string => {
  try {
    return katex.renderToString(tex, { displayMode, output: "mathml", throwOnError: false, trust: false, strict: "ignore" });
  } catch {
    return `<code>${escapeHtml(tex)}</code>`;
  }
};

const marked = new Marked({
  gfm: true,
  breaks: false,
  extensions: [
    {
      name: "blockMath",
      level: "block",
      start: (src: string) => src.match(/\$\$/)?.index,
      tokenizer(src: string) {
        const match = /^\$\$\s*([\s\S]+?)\s*\$\$(?:\n|$)/.exec(src);
        if (match) return { type: "blockMath", raw: match[0], text: match[1]!.trim() };
        return undefined;
      },
      // focusable so a formula wider than a phone screen can be scrolled with the keyboard
      renderer: (token) => `<div class="u-math" tabindex="0" role="group" aria-label="Formula">${math((token as unknown as { text: string }).text, true)}</div>\n`,
    },
    {
      name: "inlineMath",
      level: "inline",
      start: (src: string) => src.match(/\$(?!\s)/)?.index,
      tokenizer(src: string) {
        const match = /^\$(?!\s)([^$\n]+?)(?<!\s)\$(?!\d)/.exec(src);
        if (match) return { type: "inlineMath", raw: match[0], text: match[1]!.trim() };
        return undefined;
      },
      renderer: (token) => math((token as unknown as { text: string }).text, false),
    },
  ],
  renderer: baseRenderer,
});

/** Renders untrusted Markdown to safe HTML. */
export function renderMarkdown(source: string | null | undefined): string {
  if (!source) return "";
  return marked.parse(source, { async: false }) as string;
}

/** Drops a leading "# Title" that repeats the page heading. */
export function stripLeadingTitle(source: string, title: string): string {
  const match = source.match(/^\s*#\s+(.+)\n/);
  if (match && match[1]!.trim().toLowerCase() === title.trim().toLowerCase()) return source.slice(match[0].length);
  return source;
}

export interface MarkdownSection {
  /** The heading as plain text (inline Markdown marks removed). */
  heading: string;
  level: 2 | 3;
  /** The section body as Markdown, without its own heading line; deeper headings stay inside. */
  body: string;
}

export interface MarkdownSections {
  /** Text before the first split heading. */
  intro: string;
  sections: MarkdownSection[];
}

const plainHeading = (raw: string): string =>
  raw
    .replace(/\s+#+\s*$/, "")
    .replace(/!?\[([^\]]*)\]\([^)]*\)/g, "$1")
    .replace(/[`*_~]/g, "")
    .trim();

/**
 * Splits Markdown at its H2 headings (at H3 when there are fewer than two H2s) so a lesson can be shown
 * one part at a time. Only an ATX heading at the start of a line counts: a `#` inside a fenced code block,
 * a list item, a block quote or an indented code block never splits. Returns no sections when there are
 * fewer than two, because one part is the whole lesson.
 */
export function splitMarkdownSections(source: string | null | undefined): MarkdownSections {
  const text = source ?? "";
  const lines = text.split("\n");
  const heads: Array<{ line: number; level: 2 | 3; text: string }> = [];
  let fence: { char: string; size: number } | null = null;
  lines.forEach((line, i) => {
    const open = /^ {0,3}(`{3,}|~{3,})/.exec(line);
    if (fence) {
      const close = /^ {0,3}(`{3,}|~{3,})\s*$/.exec(line);
      if (close && close[1]![0] === fence.char && close[1]!.length >= fence.size) fence = null;
      return;
    }
    if (open) {
      fence = { char: open[1]![0]!, size: open[1]!.length };
      return;
    }
    const match = /^(#{2,3})[ \t]+(\S.*)$/.exec(line);
    if (match) heads.push({ line: i, level: match[1]!.length as 2 | 3, text: match[2]! });
  });
  const level: 2 | 3 | null = heads.filter((h) => h.level === 2).length >= 2 ? 2 : heads.filter((h) => h.level === 3).length >= 2 ? 3 : null;
  if (level === null) return { intro: text, sections: [] };
  // an H3 inside an H2 section stays in it when the split is at H2; at H3 the (single) H2s are plain text
  const cuts = heads.filter((h) => h.level === level);
  const sections = cuts.map((cut, k) => ({
    heading: plainHeading(cut.text),
    level,
    body: lines.slice(cut.line + 1, cuts[k + 1]?.line ?? lines.length).join("\n").trim(),
  }));
  return { intro: lines.slice(0, cuts[0]!.line).join("\n").trim(), sections };
}
