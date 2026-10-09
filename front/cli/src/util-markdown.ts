const esc = (s: string) => s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");

function inline(text: string): string {
  return esc(text)
    .replace(/`([^`]+)`/g, "<code>$1</code>")
    .replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>")
    .replace(/(^|[^*])\*([^*]+)\*/g, "$1<em>$2</em>")
    .replace(/\[([^\]]+)\]\((https?:[^)\s]+)\)/g, '<a href="$2">$1</a>');
}

/** Small Markdown to HTML converter for RichText topics: headings, paragraphs, lists, code, quotes, inline marks. */
export function markdownToHtml(md: string): string {
  const out: string[] = [];
  const lines = md.replace(/\r\n/g, "\n").split("\n");
  let i = 0;
  while (i < lines.length) {
    const line = lines[i] as string;
    if (/^```/.test(line)) {
      const code: string[] = [];
      i++;
      while (i < lines.length && !/^```/.test(lines[i] as string)) code.push(lines[i++] as string);
      i++;
      out.push(`<pre><code>${esc(code.join("\n"))}</code></pre>`);
    } else if (/^#{1,6}\s/.test(line)) {
      const level = line.match(/^#+/)![0].length;
      out.push(`<h${level}>${inline(line.replace(/^#+\s+/, ""))}</h${level}>`);
      i++;
    } else if (/^\s*[-*]\s+/.test(line) || /^\s*\d+\.\s+/.test(line)) {
      const ordered = /^\s*\d+\./.test(line);
      const items: string[] = [];
      while (i < lines.length && (/^\s*[-*]\s+/.test(lines[i] as string) || /^\s*\d+\.\s+/.test(lines[i] as string))) {
        items.push(`<li>${inline((lines[i++] as string).replace(/^\s*(?:[-*]|\d+\.)\s+/, ""))}</li>`);
      }
      out.push(`<${ordered ? "ol" : "ul"}>${items.join("")}</${ordered ? "ol" : "ul"}>`);
    } else if (/^>\s?/.test(line)) {
      const q: string[] = [];
      while (i < lines.length && /^>\s?/.test(lines[i] as string)) q.push((lines[i++] as string).replace(/^>\s?/, ""));
      out.push(`<blockquote><p>${inline(q.join(" "))}</p></blockquote>`);
    } else if (line.trim() === "") {
      i++;
    } else {
      const p: string[] = [];
      while (i < lines.length && (lines[i] as string).trim() !== "" && !/^(#{1,6}\s|```|>\s?|\s*[-*]\s+|\s*\d+\.\s+)/.test(lines[i] as string)) p.push(lines[i++] as string);
      out.push(`<p>${inline(p.join(" "))}</p>`);
    }
  }
  return out.join("\n");
}
