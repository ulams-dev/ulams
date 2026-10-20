// Machine-readable output of the docs site, written into dist/ after `astro build` (Phase 7.6):
//
//   llms.txt            index of the guides in the llmstxt.org format, each entry linking its Markdown page
//   llms-full.txt       the guides (not the generated reference) as one Markdown document
//   <page>/index.md     the Markdown source of every page, MDX components flattened to plain Markdown
//   schemas/**          the public JSON Schemas (course blueprint, webhook events, UI catalogue manifests)
//   schemas/index.json  what is published, with the schema ids
//
//   node scripts/agent-files.mjs [--check]
//
// `--check` builds everything in memory and fails on a broken conversion (leftover JSX, a missing
// schema source) without needing a built site. Links are absolute: the site root is DOCS_SITE + DOCS_BASE,
// the same variables astro.config.mjs reads.
import { existsSync, mkdirSync, readFileSync, writeFileSync } from "node:fs";
import { dirname, join, relative } from "node:path";
import { parse as parseYaml } from "yaml";
import { DOCS_DIR, ROOT, SITE_DIR, walk } from "./lib.mjs";

const check = process.argv.includes("--check");
const SITE = (process.env.DOCS_SITE || "https://ulams-dev.github.io").replace(/\/$/, "");
const BASE = (process.env.DOCS_BASE || "/").replace(/\/?$/, "/");
const ORIGIN = `${SITE}${BASE}`; // always ends with a slash
const OUT = join(SITE_DIR, "dist");

/** Sections of llms.txt in reading order: directory, heading. Generated trees go under "Optional". */
const SECTIONS = [
  ["getting-started", "Getting started"],
  ["creators", "Content creators"],
  ["learners", "Learners"],
  ["admin", "Administrators"],
  ["developers", "Developers"],
  ["extending", "Extending ulams"],
  ["operators", "Operators"],
  ["contributing", "Contributing"],
];
const OPTIONAL = [
  ["reference", "Reference (generated from the code)"],
  ["decisions", "Architecture decision records"],
  ["catalogue", "UI component catalogue"],
];

/** Schemas published at <site>/schemas/<path>. `from` is relative to the repository root. */
const SCHEMAS = [
  { path: "course-blueprint/v1.json", from: "api/packages/course-builder/resources/schemas/course-blueprint/v1.json" },
  { path: "course-blueprint/v2.json", from: "api/packages/course-builder/resources/schemas/course-blueprint/v2.json" },
  { path: "webhooks/2026-10-20/event.json", from: "api/packages/webhooks/resources/schemas/webhook-event-2026-10-20.json" },
  { path: "ui-catalogue/builder-v1.json", from: "front/ui/catalogue/manifest.json", title: "UI component catalogue for the course builder (A2UI, props as JSON Schema)" },
  { path: "ui-catalogue/page-v1.json", from: "front/ui/catalogue/page-manifest.json", title: "UI component catalogue for pages" },
  { path: "ui-catalogue/learner-layout-v1.json", from: "front/ui/catalogue/learner-layout-manifest.json", title: "UI component catalogue for learner layouts" },
];

const problems = [];

// ---- pages ---------------------------------------------------------------------------------------

function frontmatter(src) {
  const m = src.match(/^---\n([\s\S]*?)\n---\n?/);
  if (!m) return { data: {}, body: src };
  let data = {};
  try {
    data = parseYaml(m[1]) ?? {};
  } catch {
    data = {};
  }
  return { data, body: src.slice(m[0].length) };
}

/** `developers/webhooks.mdx` -> `developers/webhooks/`, `index.mdx` -> ``, `admin/index.mdx` -> `admin/`. */
function pagePath(rel) {
  const p = rel.replace(/\.mdx?$/, "").replace(/(^|\/)index$/, "");
  return p === "" ? "" : `${p}/`;
}

const attr = (tag, name) => tag.match(new RegExp(`${name}=(?:"([^"]*)"|\\{\`([^\`]*)\`\\}|\\{"([^"]*)"\\})`))?.slice(1).find(Boolean);

/** Flattens the Starlight components to Markdown. Fenced code is left alone. */
function mdxToMarkdown(body) {
  const imported = [...body.matchAll(/^import\s+([^\n]*?)\s+from\s/gm)].flatMap((m) => m[1].replace(/[{}]/g, "").split(",").map((x) => x.trim().split(/\s+as\s+/).pop()).filter(Boolean));
  const parts = body.split(/(^```[\s\S]*?^```[^\n]*$)/m);
  const out = parts.map((part, i) => {
    if (i % 2 === 1) return part; // a fenced block
    let t = part;
    t = t.replace(/^import\s[^\n]*\n/gm, "").replace(/^export\s[^\n]*\n/gm, "");
    t = t.replace(/<Aside\b([^>]*)>([\s\S]*?)<\/Aside>/g, (_, a, inner) => {
      const title = attr(a, "title");
      const type = attr(a, "type") ?? "note";
      const label = title ?? type.charAt(0).toUpperCase() + type.slice(1);
      const text = inner.trim().replace(/\n\s+/g, "\n");
      return `> **${label}:** ${text.replace(/\n/g, "\n> ")}\n`;
    });
    t = t.replace(/<Badge\b([^>]*?)\/>/g, (_, a) => `(${attr(a, "text") ?? ""})`);
    t = t.replace(/<LinkCard\b([^>]*?)\/>/g, (_, a) => `- [${attr(a, "title") ?? "link"}](${attr(a, "href") ?? ""})${attr(a, "description") ? `: ${attr(a, "description")}` : ""}`);
    t = t.replace(/<Screenshot\b([^>]*?)\/>/g, (_, a) => `*Screenshot: ${attr(a, "alt") ?? attr(a, "name") ?? ""}*`);
    t = t.replace(/<Code\b([^>]*?)\/>/g, (_, a) => `*Code listing${attr(a, "title") ? `: ${attr(a, "title")}` : ""} (see the HTML page)*`);
    t = t.replace(/<TabItem\b([^>]*)>/g, (_, a) => `\n**${attr(a, "label") ?? "Tab"}**\n`);
    t = t.replace(/<Card\b([^>]*)>/g, (_, a) => `\n**${attr(a, "title") ?? ""}**\n`);
    t = t.replace(/<\/?(?:Tabs|TabItem|Steps|CardGrid|Card|FileTree|Aside)\b[^>]*>/g, "");
    // any other component the page imports (a live widget, a playground): nothing to show in Markdown
    for (const name of imported) t = t.replace(new RegExp(`<${name}\\b[^>]*?/>`, "g"), "");
    return t;
  });
  const md = out.join("").replace(/\n{3,}/g, "\n\n").trim();
  mdxToMarkdown.imported = imported;

  return md;
}

/** Internal links point at the Markdown page of the target; anchors are kept. */
function absolutise(md) {
  return md.replace(/\]\((\/[^)\s]*)\)/g, (m, href) => {
    const [path, hash = ""] = href.split("#");
    if (/\.[a-z0-9]{2,5}$/i.test(path) && !path.endsWith("/")) return `](${ORIGIN}${path.slice(1)}${hash ? `#${hash}` : ""})`;
    if (path.startsWith("/api/") || path === "/api") return `](${ORIGIN}${path.slice(1)})`;
    return `](${ORIGIN}${path.slice(1)}${path.endsWith("/") ? "" : "/"}index.md${hash ? `#${hash}` : ""})`;
  });
}

/** Tags of components the page imports that survived the conversion (generic types in prose are not JSX). */
function leftoverJsx(md, names) {
  if (!names.length) return [];
  const outside = md.replace(/^```[\s\S]*?^```[^\n]*$/gm, "").replace(/`[^`\n]*`/g, "");

  return outside.match(new RegExp(`</?(?:${names.join("|")})\\b[^>]*>`, "g")) ?? [];
}

function collectPages() {
  const files = walk(relative(ROOT, DOCS_DIR), (p) => /\.mdx?$/.test(p));
  const pages = [];
  for (const file of files) {
    const rel = relative(relative(ROOT, DOCS_DIR), file);
    const { data, body } = frontmatter(readFileSync(join(ROOT, file), "utf8"));
    if (data.draft) continue;
    const path = pagePath(rel);
    const md = absolutise(mdxToMarkdown(body));
    const left = leftoverJsx(md, mdxToMarkdown.imported);
    if (left.length) problems.push(`${rel}: JSX left in the Markdown: ${[...new Set(left)].slice(0, 3).join(" ")}`);
    pages.push({
      rel,
      path,
      title: String(data.title ?? path),
      description: String(data.description ?? "").trim(),
      generated: Boolean(data.generatedFrom),
      md,
    });
  }
  return pages.sort((a, b) => a.path.localeCompare(b.path));
}

const pageMarkdown = (p) =>
  `# ${p.title}\n\n${p.description ? `> ${p.description}\n\n` : ""}Canonical page: ${ORIGIN}${p.path}\n\n${p.md}\n`;

// ---- llms.txt ------------------------------------------------------------------------------------

function buildLlmsTxt(pages) {
  const entry = (p) => `- [${p.title}](${ORIGIN}${p.path}index.md)${p.description ? `: ${p.description.replace(/\s+/g, " ")}` : ""}`;
  const inDir = (dir) => pages.filter((p) => p.path.startsWith(`${dir}/`));
  const lines = [
    "# ulams",
    "",
    "> ulams is an open, AI-native, headless LMS built on Laravel and React: courses that stay in sync with their sources, adapt to each learner and cite every generated element. This file indexes the documentation as Markdown; every page is also available as HTML at the same address without `index.md`.",
    "",
    `Machine-readable resources: the OpenAPI document is at ${ORIGIN}openapi.json, JSON Schemas at ${ORIGIN}schemas/index.json, the guides as one file at ${ORIGIN}llms-full.txt. Agents working on the repository should read AGENTS.md at its root first.`,
    "",
  ];
  for (const [dir, heading] of SECTIONS) {
    const items = inDir(dir);
    if (!items.length) continue;
    lines.push(`## ${heading}`, "", ...items.map(entry), "");
  }
  const top = pages.filter((p) => p.path === "roadmap/");
  if (top.length) lines.push("## Roadmap", "", ...top.map(entry), "");
  lines.push("## Optional", "");
  for (const [dir, heading] of OPTIONAL) {
    const items = inDir(dir);
    if (items.length) lines.push(`- [${heading}](${ORIGIN}${dir}/index.md): ${items.length} pages`);
  }
  lines.push("");

  return lines.join("\n");
}

function buildLlmsFull(pages) {
  const skip = new Set(OPTIONAL.map(([d]) => `${d}/`));
  const guides = pages.filter((p) => ![...skip].some((s) => p.path.startsWith(s)));

  return `# ulams documentation\n\nThe guides of ${ORIGIN} as one Markdown document, ${guides.length} pages.\n\n${guides.map((p) => `---\n\n${pageMarkdown(p)}`).join("\n")}`;
}

/** Directory index pages for the Optional section: a list of the pages of a generated tree. */
function dirIndex(pages, dir, heading) {
  const items = pages.filter((p) => p.path.startsWith(`${dir}/`) && p.path !== `${dir}/`);
  if (!items.length) return null;

  return `# ${heading}\n\n${items.map((p) => `- [${p.title}](${ORIGIN}${p.path}index.md)${p.description ? `: ${p.description.replace(/\s+/g, " ")}` : ""}`).join("\n")}\n`;
}

// ---- schemas -------------------------------------------------------------------------------------

function buildSchemas() {
  const published = [];
  for (const s of SCHEMAS) {
    const file = join(ROOT, s.from);
    if (!existsSync(file)) {
      problems.push(`schema source missing: ${s.from}`);
      continue;
    }
    const raw = readFileSync(file, "utf8");
    let json;
    try {
      json = JSON.parse(raw);
    } catch (e) {
      problems.push(`${s.from} is not valid JSON: ${e.message}`);
      continue;
    }
    published.push({
      path: s.path,
      url: `${ORIGIN}schemas/${s.path}`,
      id: json.$id ?? json.catalogId ?? null,
      title: s.title ?? json.title ?? s.path,
      description: json.description ?? null,
      raw,
    });
  }

  return published;
}

// ---- run -----------------------------------------------------------------------------------------

const pages = collectPages();
const schemas = buildSchemas();
const llms = buildLlmsTxt(pages);
const full = buildLlmsFull(pages);
if (!pages.length) problems.push("no pages found under src/content/docs (run `yarn sync` first)");
if (!/^# ulams\n/.test(llms) || !/## Developers/.test(llms)) problems.push("llms.txt has no Developers section");

if (problems.length) {
  for (const p of problems) console.error(`agent-files: ${p}`);
  process.exit(1);
}
if (check) {
  console.log(`agent-files: ok (${pages.length} pages, ${schemas.length} schemas, llms.txt ${llms.length} bytes)`);
  process.exit(0);
}
if (!existsSync(OUT)) {
  console.error("agent-files: dist/ does not exist; run `astro build` first.");
  process.exit(1);
}

const put = (rel, content) => {
  const file = join(OUT, rel);
  mkdirSync(dirname(file), { recursive: true });
  writeFileSync(file, content);
};
for (const p of pages) put(`${p.path}index.md`, pageMarkdown(p));
for (const [dir, heading] of OPTIONAL) {
  const index = dirIndex(pages, dir, heading);
  if (index && !pages.some((p) => p.path === `${dir}/`)) put(`${dir}/index.md`, index);
}
put("llms.txt", llms);
put("llms-full.txt", full);
for (const s of schemas) put(`schemas/${s.path}`, s.raw);
put(
  "schemas/index.json",
  `${JSON.stringify(
    {
      note: "Public JSON Schemas of ulams. A schema never changes in place: a new version gets a new file.",
      schemas: schemas.map(({ raw, ...rest }) => rest),
    },
    null,
    2,
  )}\n`,
);
console.log(`agent-files: ${pages.length} Markdown pages, llms.txt, llms-full.txt, ${schemas.length} schemas -> dist/`);
