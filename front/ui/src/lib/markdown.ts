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
