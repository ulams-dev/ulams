/**
 * The math-free part of the Markdown renderer: the escaping and the link, code and table rules shared
 * with `markdown.ts`, without KaTeX, so a client script (the tutor panel) can render answers without
 * pulling the formula engine into its chunk. Source is untrusted: raw HTML is escaped, links must use
 * safe schemes.
 */
import { Marked, type RendererObject, type Tokens } from "marked";
import { isSafeHref } from "../schema.ts";

export const escapeHtml = (s: string): string =>
  s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#39;");

/** Renderer rules shared by the full and the basic renderer. */
export const baseRenderer: RendererObject = {
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
  code(token: Tokens.Code) {
    return `<pre tabindex="0"><code>${escapeHtml(token.text)}</code></pre>\n`;
  },
  table(token: Tokens.Table) {
    const head = token.header.map((cell) => `<th scope="col"${cell.align ? ` style="text-align:${cell.align}"` : ""}>${this.parser.parseInline(cell.tokens)}</th>`).join("");
    const rows = token.rows
      .map((row) => `<tr>${row.map((cell) => `<td${cell.align ? ` style="text-align:${cell.align}"` : ""}>${this.parser.parseInline(cell.tokens)}</td>`).join("")}</tr>`)
      .join("");
    return `<div class="u-table" tabindex="0" role="region" aria-label="Table"><table><thead><tr>${head}</tr></thead><tbody>${rows}</tbody></table></div>`;
  },
};

// Model-written text never loads an image: the alt text stands in for it.
const marked = new Marked({
  gfm: true,
  breaks: false,
  renderer: { ...baseRenderer, image: (token: Tokens.Image) => escapeHtml(token.text) },
});

/** Renders untrusted Markdown to safe HTML without math and without images. */
export function renderBasicMarkdown(source: string | null | undefined): string {
  if (!source) return "";
  return marked.parse(source, { async: false }) as string;
}
