/**
 * Sanitiser for the small HTML fragments the API returns (webinar and event descriptions,
 * agendas). Untrusted input: only a few formatting tags survive, every attribute is
 * dropped and everything else is escaped as text.
 */
const ALLOWED = new Set(["p", "br", "strong", "b", "em", "i", "ul", "ol", "li", "h3", "h4", "blockquote"]);
const TAG = /<\/?([a-zA-Z][a-zA-Z0-9]*)\b[^<>]*>/g;

const escapeText = (s: string): string => s.replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");

export function sanitizeHtml(input: string | null | undefined): string {
  if (!input) return "";
  // drop whole elements whose content must never show
  const source = input.replace(/<(script|style|iframe|object|template)\b[\s\S]*?<\/\1\s*>/gi, "");
  let out = "";
  let last = 0;
  for (const match of source.matchAll(TAG)) {
    out += escapeText(source.slice(last, match.index));
    const name = match[1]!.toLowerCase();
    if (ALLOWED.has(name)) out += match[0].startsWith("</") ? `</${name}>` : name === "br" ? "<br>" : `<${name}>`;
    last = match.index! + match[0].length;
  }
  out += escapeText(source.slice(last));
  // entities from the source are kept; a bare "&" that is not an entity is escaped
  return out.replace(/&(?!(?:[a-z]+|#\d+|#x[0-9a-f]+);)/gi, "&amp;");
}
