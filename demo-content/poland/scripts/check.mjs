// Lint for the poland package (ADR 0088): no third-party framing or weak sources, every figure resolves to a
// primary source, the map file and the package stay small, nothing is loaded from the network, and the manifest
// is the one data/steps.json produces.
//   node scripts/check.mjs
import { existsSync, readFileSync, readdirSync, statSync } from "node:fs";
import { dirname, join, relative } from "node:path";
import { fileURLToPath } from "node:url";
import { buildManifest } from "./build-manifest.mjs";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const MAX_TOPO_KB = 400;
const MAX_PACKAGE_MB = 3;

/** Strings that must not appear in the shipped files: the article this was once a reply to, and weak sources. */
const BANNED = [/tomwojcik/i, /news\.ycombinator/i, /reported_in_uploaded_document/, /\bessay\b/i, /\besej\b/i, /\bESSAY\b/, /wikipedia\.org/i, /fonts\.googleapis|fonts\.gstatic/i, /September 2026: the world today/i];
/** Source hosts that are press, aggregators or encyclopedias: never a primary source. */
const SECONDARY_HOSTS = ["notesfrompoland.com", "wikipedia.org", "ft.com", "bankier.pl", "money.pl", "rmf24.pl", "intellinews.com", "pap.pl", "defence24", "interia.pl", "spidersweb.pl", "rp.pl", "tomwojcik.com", "ycombinator.com", "marketscreener.com", "dealroom.co", "sacra.com", "worldinmaps", "theglobalist.com", "tovima.com", "notesfrompoland"];

const read = (p) => readFileSync(join(root, p), "utf8");
const walk = (dir, out = []) => {
  for (const name of readdirSync(dir)) {
    if (name === "node_modules") continue;
    const path = join(dir, name);
    if (statSync(path).isDirectory()) walk(path, out);
    else out.push(path);
  }
  return out;
};

export function findProblems(dir = root, readText = (p) => readFileSync(join(dir, p), "utf8")) {
  const problems = [];
  const steps = JSON.parse(readText("data/steps.json"));
  const sources = JSON.parse(readText("data/sources.json"));

  // 1. nothing banned in the shipped text files
  for (const file of ["index.html", "app.js", "data/steps.json", "data/sources.json", "ulams-interactive.json"]) {
    const text = readText(file);
    for (const re of BANNED) if (re.test(text)) problems.push(`${file}: contains ${re}`);
  }

  // 2. every source id used resolves, every entry is used, every source is primary and on https
  const used = new Set(steps.hero.src);
  for (const st of steps.steps) for (const id of st.src ?? []) used.add(id);
  for (const id of used) if (!sources[id]) problems.push(`source "${id}" is used but missing from data/sources.json`);
  for (const id of Object.keys(sources)) if (!used.has(id)) problems.push(`source "${id}" is not used by any step`);
  for (const [id, entry] of Object.entries(sources)) {
    if (!entry.s?.length) problems.push(`source "${id}" has no link`);
    for (const [title, url] of entry.s ?? []) {
      if (!/^https:\/\//.test(url)) problems.push(`source "${id}": "${url}" is not https`);
      if (SECONDARY_HOSTS.some((h) => new URL(url).hostname.includes(h))) problems.push(`source "${id}": ${new URL(url).hostname} is not a primary source`);
      if (!title) problems.push(`source "${id}": a link has no title`);
    }
  }

  // 3. every step has EN and PL text, a view the page knows, and at least one source unless it is a chapter opener
  const views = new Set([...read("app.js").matchAll(/^\s{2}(\w+):\s*\{bb:/gm)].map((m) => m[1]));
  const seen = new Set();
  for (const st of steps.steps) {
    if (seen.has(st.id)) problems.push(`step "${st.id}" is used twice`);
    seen.add(st.id);
    if (!views.has(st.view)) problems.push(`step "${st.id}": unknown map view "${st.view}"`);
    for (const lang of ["en", "pl"]) {
      const fields = st.type === "chapter" ? [st.title, st.lede] : [st.title, st.body, st.kicker];
      if (st.type !== "chapter" && st.type !== "hero") for (const f of fields) if (!f?.[lang]) problems.push(`step "${st.id}": missing ${lang} text`);
      if (st.type === "chapter") for (const f of fields) if (!f?.[lang]) problems.push(`step "${st.id}": missing ${lang} text`);
    }
    if (st.type !== "chapter" && st.type !== "hero" && !st.src?.length) problems.push(`step "${st.id}" cites no source`);
  }

  // 4. sizes
  const topoKb = Buffer.byteLength(readText("data/world.topo.json")) / 1024;
  if (topoKb > MAX_TOPO_KB) problems.push(`data/world.topo.json is ${Math.round(topoKb)} KB (limit ${MAX_TOPO_KB} KB)`);
  const topo = JSON.parse(readText("data/world.topo.json"));
  if (!topo.objects?.c?.geometries?.some((g) => g.id === "616")) problems.push("data/world.topo.json has no Poland (616)");

  // 5. nothing is loaded from the network
  const html = readText("index.html");
  if (/<(script|link|img|iframe)[^>]+(src|href)="https?:/i.test(html)) problems.push("index.html loads a remote resource");
  if (/fetch\(\s*['"`]https?:/.test(readText("app.js"))) problems.push("app.js fetches a remote URL");

  // 6. the manifest is the one the data produces
  if (dir === root) {
    const expected = JSON.stringify(buildManifest());
    const actual = JSON.stringify(JSON.parse(readText("ulams-interactive.json")));
    if (expected !== actual) problems.push("ulams-interactive.json is out of date (run: node scripts/build-manifest.mjs)");
    const total = walk(root).filter((f) => !relative(root, f).startsWith("scripts")).reduce((n, f) => n + statSync(f).size, 0);
    if (total > MAX_PACKAGE_MB * 1024 * 1024) problems.push(`the package is ${(total / 1048576).toFixed(1)} MB (limit ${MAX_PACKAGE_MB} MB)`);
    for (const st of steps.steps) if (!existsSync(join(root, "posters", `${st.id}.webp`))) problems.push(`posters/${st.id}.webp is missing (run: node scripts/posters.mjs)`);
  }
  return problems;
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const problems = findProblems();
  if (problems.length) {
    console.error(problems.map((p) => `  ${p}`).join("\n"));
    console.error(`\n${problems.length} problem(s) in demo-content/poland.`);
    process.exit(1);
  }
  const steps = JSON.parse(read("data/steps.json")).steps.length;
  console.log(`poland package OK: ${steps} steps, ${Math.round(statSync(join(root, "data", "world.topo.json")).size / 1024)} KB map`);
}
