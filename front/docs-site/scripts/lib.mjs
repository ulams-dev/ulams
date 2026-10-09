// Helpers shared by the content generators (scripts/generators/*.mjs).
import { mkdirSync, readFileSync, writeFileSync, existsSync, readdirSync, statSync } from "node:fs";
import { dirname, join, posix, relative, resolve } from "node:path";
import { fileURLToPath } from "node:url";

export const SITE_DIR = resolve(dirname(fileURLToPath(import.meta.url)), "..");
export const ROOT = resolve(SITE_DIR, "../..");
export const DOCS_DIR = join(SITE_DIR, "src/content/docs");
export const REPO_URL = "https://github.com/ulams-dev/ulams";
export const BLOB = `${REPO_URL}/blob/main`;
export const TREE = `${REPO_URL}/tree/main`;
export const EDIT = `${REPO_URL}/edit/main`;

export const read = (rel) => readFileSync(join(ROOT, rel), "utf8");
export const exists = (rel) => existsSync(join(ROOT, rel));
export const isDir = (rel) => existsSync(join(ROOT, rel)) && statSync(join(ROOT, rel)).isDirectory();
export const list = (rel) => (isDir(rel) ? readdirSync(join(ROOT, rel)).sort() : []);

/** Every file under a repository folder (relative paths), optionally filtered. */
export function walk(rel, filter = () => true) {
  const out = [];
  const visit = (r) => {
    for (const name of list(r)) {
      if (name === "node_modules" || name === "vendor" || name.startsWith(".")) continue;
      const p = posix.join(r, name);
      if (isDir(p)) visit(p);
      else if (filter(p)) out.push(p);
    }
  };
  visit(rel);
  return out;
}

const yamlValue = (v, indent = "") => {
  if (Array.isArray(v)) return `[${v.map((x) => JSON.stringify(x)).join(", ")}]`;
  if (typeof v === "boolean" || typeof v === "number") return String(v);
  if (v && typeof v === "object") {
    const inner = `${indent}  `;
    return `\n${Object.entries(v)
      .filter(([, x]) => x !== undefined && x !== null)
      .map(([k, x]) => `${inner}${k}: ${yamlValue(x, inner)}`)
      .join("\n")}`;
  }
  return JSON.stringify(String(v ?? ""));
};

/** Writes a page into src/content/docs with YAML frontmatter. */
export function writePage(relPath, frontmatter, body) {
  const file = join(DOCS_DIR, relPath);
  mkdirSync(dirname(file), { recursive: true });
  const fm = Object.entries(frontmatter)
    .filter(([, v]) => v !== undefined && v !== null)
    .map(([k, v]) => `${k}: ${yamlValue(v)}`)
    .join("\n");
  writeFileSync(file, `---\n${fm}\n---\n\n${body.trim()}\n`);
  return relative(SITE_DIR, file);
}

/** One line of plain text, at most `max` characters, for descriptions. */
export function plain(text, max = 200) {
  const s = String(text ?? "")
    .replace(/<!--[\s\S]*?-->/g, "")
    .replace(/!\[[^\]]*\]\([^)]*\)/g, "")
    .replace(/\[([^\]]*)\]\([^)]*\)/g, "$1")
    .replace(/[`*_>#|]/g, "")
    .replace(/\s+/g, " ")
    .trim();
  if (s.length <= max) return s;
  const cut = s.slice(0, max - 1);
  return `${cut.slice(0, cut.lastIndexOf(" ") > 40 ? cut.lastIndexOf(" ") : cut.length)}…`;
}

/** Splits a Markdown document into its first H1 and the rest. */
export function splitTitle(md) {
  const m = md.match(/^\s*#\s+(.+)\n/);
  if (!m) return { title: null, body: md };
  return { title: m[1].trim(), body: md.slice(m.index + m[0].length) };
}

/** First real paragraph of Markdown (skips headings, lists, tables, code, quotes). */
export function firstParagraph(md) {
  let inCode = false;
  const paras = [];
  let cur = [];
  for (const line of md.split("\n")) {
    if (line.startsWith("```")) {
      inCode = !inCode;
      continue;
    }
    if (inCode) continue;
    if (line.trim() === "") {
      if (cur.length) paras.push(cur.join(" "));
      cur = [];
      continue;
    }
    if (/^\s*(#|[-*+] |\d+\. |\||>|<)/.test(line) && !cur.length) continue;
    cur.push(line.trim());
  }
  if (cur.length) paras.push(cur.join(" "));
  return paras.find((p) => p.length > 30) ?? paras[0] ?? "";
}

/**
 * Rewrites relative links of a Markdown file that lives at `sourceRel` in the repository:
 * links resolved by `mapInternal` (e.g. another ADR) become site links, everything else
 * becomes an absolute GitHub URL, so generated pages never contain dead relative links.
 */
export function rewriteLinks(md, sourceRel, mapInternal = () => null) {
  const baseDir = posix.dirname(sourceRel);
  const fix = (target) => {
    if (/^(https?:|mailto:|tel:|#|\/\/)/.test(target)) return target;
    const [path, hash] = target.split("#");
    const repoPath = path.startsWith("/") ? path.slice(1) : posix.normalize(posix.join(baseDir, path));
    const internal = mapInternal(repoPath);
    if (internal) return hash ? `${internal}#${hash}` : internal;
    const kind = isDir(repoPath) ? TREE : BLOB;
    return `${kind}/${repoPath}${hash ? `#${hash}` : ""}`;
  };
  return md
    .replace(/(\]\()([^)\s]+)(\s+"[^"]*")?\)/g, (_, a, t, title = "") => `${a}${fix(t)}${title})`)
    .replace(/^(\[[^\]]+\]:\s*)(\S+)/gm, (_, a, t) => `${a}${fix(t)}`);
}

/** Moves every Markdown heading one level down (outside fenced code blocks). */
export function demoteHeadings(md) {
  let fence = null;
  return md
    .split("\n")
    .map((line) => {
      const f = line.match(/^\s*(`{3,}|~{3,})/);
      if (f) {
        if (!fence) fence = f[1][0];
        else if (f[1][0] === fence) fence = null;
        return line;
      }
      return !fence && /^#{1,5} /.test(line) ? `#${line}` : line;
    })
    .join("\n");
}

/** Escapes text for a Markdown table cell. */
export const cell = (s) =>
  String(s ?? "")
    .replace(/\|/g, "\\|")
    .replace(/\n+/g, " ")
    .trim();

/** Slug for site paths. */
export const slug = (s) =>
  String(s)
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-|-$/g, "");
