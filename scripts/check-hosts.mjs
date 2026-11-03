#!/usr/bin/env node
// Fails when source code hard-codes a `ulams.app` host. Hostnames must come from configuration
// (ULAMS_DOCS_URL, the tenant host pattern, the runtime settings); the few places that legitimately
// name one (the public landing, examples in API docblocks, documented patterns) are listed in
// scripts/hosts-allowed.txt as `<path prefix> <host>`, with a comment above each group saying why.
//
//   node scripts/check-hosts.mjs          check (CI)
//   node scripts/check-hosts.mjs --list   print every occurrence in allow-list format
//
// No dependencies, so it runs before `yarn install`.
import { readFileSync, readdirSync, statSync, existsSync } from "node:fs";
import { join, relative, sep } from "node:path";
import { fileURLToPath } from "node:url";

const root = join(fileURLToPath(new URL(".", import.meta.url)), "..");
const SKIP_DIRS = new Set(["node_modules", "vendor", "dist", "build", ".git", "coverage", "tests", "__snapshots__"]);
const SKIP_EXT = /\.(png|jpe?g|gif|webp|avif|ico|svg|woff2?|ttf|eot|pdf|zip|mp4|webm|mp3|map|lock)$/i;
const HOST = /\b((?:[a-z0-9-]+\.)*ulams\.app)\b/gi;

const roots = ["admin/src", "front/src", "front/web/src", "api/README.md"];
for (const pkg of existsSync(join(root, "api/packages")) ? readdirSync(join(root, "api/packages")) : []) {
  roots.push(`api/packages/${pkg}/src`);
}

function* walk(path) {
  if (!existsSync(path)) return;
  const st = statSync(path);
  if (st.isFile()) {
    if (!SKIP_EXT.test(path)) yield path;
    return;
  }
  for (const name of readdirSync(path)) {
    if (SKIP_DIRS.has(name)) continue;
    yield* walk(join(path, name));
  }
}

const allowed = [];
const allowFile = join(root, "scripts/hosts-allowed.txt");
if (existsSync(allowFile)) {
  for (const raw of readFileSync(allowFile, "utf8").split("\n")) {
    const line = raw.trim();
    if (!line || line.startsWith("#")) continue;
    const [prefix, host] = line.split(/\s+/);
    allowed.push({ prefix, host: (host || "").toLowerCase(), used: false });
  }
}

const listMode = process.argv.includes("--list");
const found = new Set();
const violations = [];
for (const r of roots) {
  for (const file of walk(join(root, r))) {
    const rel = relative(root, file).split(sep).join("/");
    const lines = readFileSync(file, "utf8").split("\n");
    lines.forEach((text, i) => {
      for (const m of text.matchAll(HOST)) {
        const host = m[1].toLowerCase();
        found.add(`${rel} ${host}`);
        const hit = allowed.find((a) => a.host === host && rel.startsWith(a.prefix));
        if (hit) hit.used = true;
        else violations.push(`${rel}:${i + 1}: ${host}`);
      }
    });
  }
}

if (listMode) {
  console.log([...found].sort().join("\n"));
  process.exit(0);
}

const stale = allowed.filter((a) => !a.used);
if (violations.length || stale.length) {
  for (const v of violations) {
    console.error(`::error file=${v.split(":")[0]},line=${v.split(":")[1]}::hard-coded host ${v.split(": ")[1]}; read it from configuration or add it to scripts/hosts-allowed.txt with a reason`);
  }
  for (const s of stale) {
    console.error(`::error file=scripts/hosts-allowed.txt::stale allow-list entry "${s.prefix} ${s.host}" matches nothing; remove it`);
  }
  process.exit(1);
}
console.log(`check-hosts: ${found.size} host references, all allowed`);
