// Coverage check: every module, admin route, learner route and topic type of the repository
// must be documented by at least one written page (frontmatter `modules`, `adminRoutes`,
// `learnerRoutes`, `topicTypes`). Generated pages (with `generatedFrom`) do not count, so the
// reference pages cannot hide a missing guide. Every page must also have a description.
//
//   yarn workspace @ulams/docs coverage            check, exit 1 on gaps
//   yarn workspace @ulams/docs coverage --report   also list "Needs review" and "Coming" pages
import { readFileSync } from "node:fs";
import { relative } from "node:path";
import { parse as parseYaml } from "yaml";
import { pathToFileURL } from "node:url";
import { join } from "node:path";
import { DOCS_DIR, isDir, list, read, walk, ROOT } from "./lib.mjs";

const APPS = {
  api: "api (Laravel application)",
  admin: "admin (admin panel)",
  front: "front (React learner app, legacy)",
  web: "front/web (Astro reference frontend)",
  sdk: "front/sdk (@ulams/sdk)",
  ui: "front/ui (@ulams/ui)",
  "api-h5p": "api/h5p (H5P service)",
  "api-pdf": "api/pdf (PDF service)",
};

export function inventory() {
  const modules = [...list("api/packages").filter((p) => isDir(`api/packages/${p}`)), ...Object.keys(APPS)];

  const routesSrc = read("admin/config/routes.ts");
  const adminRoutes = [];
  const re = /path:\s*'([^']+)'([\s\S]*?)(?=\n\s*(?:\{|\}|path:))/g;
  for (const m of routesSrc.matchAll(re)) {
    const [, path, rest] = m;
    if (/redirect:/.test(rest) || ["/", "*", "/user"].includes(path)) continue;
    if (!adminRoutes.includes(path)) adminRoutes.push(path);
  }

  const learnerRoutes = walk("front/web/src/pages", (p) => /\.(astro|ts|md)$/.test(p)).map((p) => {
    const r = p
      .replace(/^front\/web\/src\/pages/, "")
      .replace(/\.(astro|ts|md)$/, "")
      .replace(/\/index$/, "");
    return r === "" ? "/" : r;
  });

  const topicTypes = [];
  for (const pkg of list("api/packages")) {
    for (const file of walk(`api/packages/${pkg}/src`, (p) => p.endsWith("ServiceProvider.php"))) {
      const src = read(file);
      for (const m of src.matchAll(/registerContentClass(?:es)?\(\s*(\[[\s\S]*?\]|[^)]*)\)/g)) {
        for (const c of m[1].matchAll(/(\w+)::class/g)) if (!topicTypes.includes(c[1])) topicTypes.push(c[1]);
      }
    }
  }
  return { modules, adminRoutes, learnerRoutes, topicTypes };
}

/** UI catalogue components (front/ui/src/registry.ts); each needs front/ui/catalogue/examples/<Name>.json for the playground. */
export async function catalogueComponents() {
  const mod = await import(pathToFileURL(join(ROOT, "front/ui/src/registry.ts")).href);
  return Object.keys(mod.registry);
}

export function pages() {
  return walk(relative(ROOT, DOCS_DIR), (p) => /\.mdx?$/.test(p)).map((file) => {
    const src = readFileSync(`${ROOT}/${file}`, "utf8");
    const fm = src.match(/^---\n([\s\S]*?)\n---/);
    let data = {};
    try {
      data = fm ? (parseYaml(fm[1]) ?? {}) : {};
    } catch (e) {
      data = { __error: String(e.message).split("\n")[0] };
    }
    return { file: relative(relative(ROOT, DOCS_DIR), file), data };
  });
}

const matches = (entry, key) => {
  if (entry === key) return true;
  if (entry.endsWith("/**")) {
    const prefix = entry.slice(0, -3);
    return key === prefix || key.startsWith(`${prefix}/`);
  }
  return false;
};

const isMain = process.argv[1] && import.meta.url.endsWith(process.argv[1].split("/").pop());
if (isMain) {
  const inv = inventory();
  const all = pages();
  const written = all.filter((p) => !p.data.generatedFrom);
  const kinds = [
    ["modules", "module", inv.modules],
    ["adminRoutes", "admin route", inv.adminRoutes],
    ["learnerRoutes", "learner route", inv.learnerRoutes],
    ["topicTypes", "topic type", inv.topicTypes],
  ];
  const problems = [];
  for (const [field, label, keys] of kinds) {
    const covered = keys.filter((k) => written.some((p) => (p.data[field] ?? []).some((e) => matches(e, k))));
    const missing = keys.filter((k) => !covered.includes(k));
    console.log(`coverage: ${label}s ${covered.length}/${keys.length}`);
    for (const k of missing) problems.push(`no page documents ${label} "${k}" (frontmatter \`${field}\`)`);
    for (const p of written) {
      for (const e of p.data[field] ?? []) {
        if (!keys.some((k) => matches(e, k))) problems.push(`${p.file}: \`${field}\` entry "${e}" matches nothing in the code`);
      }
    }
  }
  for (const p of all) {
    if (p.data.__error) problems.push(`${p.file}: invalid frontmatter (${p.data.__error})`);
    else if (!p.data.description || String(p.data.description).trim().length < 20)
      problems.push(`${p.file}: missing or too short description`);
  }
  // The component playground (ADR 0054) renders each catalogue component from an example file.
  const components = await catalogueComponents();
  const exampleFiles = list("front/ui/catalogue/examples").filter((f) => f.endsWith(".json"));
  const withExample = components.filter((c) => exampleFiles.includes(`${c}.json`));
  console.log(`coverage: catalogue examples ${withExample.length}/${components.length}`);
  for (const c of components) {
    if (!exampleFiles.includes(`${c}.json`)) problems.push(`component "${c}" has no example (front/ui/catalogue/examples/${c}.json), so the playground cannot render it`);
    else {
      try {
        const example = JSON.parse(read(`front/ui/catalogue/examples/${c}.json`));
        if (!example.props || typeof example.props !== "object" || !example.invalid || typeof example.invalid !== "object")
          problems.push(`front/ui/catalogue/examples/${c}.json needs "props" (valid) and "invalid" (props that fail the schema)`);
      } catch (e) {
        problems.push(`front/ui/catalogue/examples/${c}.json is not valid JSON (${e.message})`);
      }
    }
  }
  for (const f of exampleFiles) if (!components.includes(f.slice(0, -5))) problems.push(`front/ui/catalogue/examples/${f} matches no catalogue component`);

  if (process.argv.includes("--report")) {
    const review = written.filter((p) => p.data.needsReview);
    const coming = written.filter((p) => p.data.coming);
    console.log(`\nNeeds review (${review.length}):`);
    for (const p of review) console.log(`- ${p.file}${typeof p.data.needsReview === "string" ? `: ${p.data.needsReview}` : ""}`);
    console.log(`\nComing (${coming.length}):`);
    for (const p of coming) console.log(`- ${p.file}`);
    console.log(`\nPages: ${all.length} (${written.length} written, ${all.length - written.length} generated)`);
  }
  if (problems.length) {
    console.error(`\ncoverage: ${problems.length} problem(s)`);
    for (const p of problems) console.error(`- ${p}`);
    process.exit(1);
  }
  console.log("coverage: ok");
}
