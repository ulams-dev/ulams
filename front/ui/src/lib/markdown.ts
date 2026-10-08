/**
 * Markdown to HTML for course content (server only). Course content is untrusted input:
 * raw HTML in the source is escaped, links and images must use safe schemes, and math
 * ($$…$$ and $…$) is rendered by KaTeX as MathML (no client CSS or fonts needed).
 */
import { Marked, type Tokens } from "marked";
import katex from "katex";
import { isSafeHref } from "../schema.ts";

const escapeHtml = (s: string): string =>
  s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#39;");

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
      renderer: (token) => `<div class="u-math">${math((token as unknown as { text: string }).text, true)}</div>\n`,
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
  renderer: {
    html: (token: Tokens.HTML | Tokens.Tag) => escapeHtml(token.text),
    link(token: Tokens.Link) {
      const text = this.parser.parseInline(token.tokens);
      if (!isSafeHref(token.href)) return text;
      const external = /^https?:\/\//i.test(token.href);
      const title = token.title ? ` title="${escapeHtml(token.title)}"` : "";
      return `<a href="${escapeHtml(token.href)}"${title}${external ? ' rel="noopener noreferrer" target="_blank"' : ""}>${text}</a>`;
    },
    image(token: Tokens.Image) {
      if (!isSafeHref(token.href)) return escapeHtml(token.text);
      return `<img src="${escapeHtml(token.href)}" alt="${escapeHtml(token.text)}" loading="lazy" decoding="async">`;
    },
    table(token: Tokens.Table) {
      const head = token.header.map((cell) => `<th scope="col"${cell.align ? ` style="text-align:${cell.align}"` : ""}>${this.parser.parseInline(cell.tokens)}</th>`).join("");
      const rows = token.rows
        .map((row) => `<tr>${row.map((cell) => `<td${cell.align ? ` style="text-align:${cell.align}"` : ""}>${this.parser.parseInline(cell.tokens)}</td>`).join("")}</tr>`)
        .join("");
      return `<div class="u-table" tabindex="0" role="region" aria-label="Table"><table><thead><tr>${head}</tr></thead><tbody>${rows}</tbody></table></div>`;
    },
  },
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
