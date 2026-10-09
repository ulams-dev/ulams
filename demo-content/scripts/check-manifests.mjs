// Lint for the content packages: every ulams-interactive.json in demo-content must be valid, name
// files that exist, and every vendored copy of the bridge must equal a fresh build of it.
//   node scripts/check-manifests.mjs
import { execFileSync } from "node:child_process";
import { existsSync, readFileSync, readdirSync, statSync } from "node:fs";
import { dirname, join, relative } from "node:path";
import { fileURLToPath } from "node:url";
import { packageFiles } from "./pack.mjs";
import { validateManifest } from "./lib/manifest.mjs";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const bridgeDir = join(root, "..", "front", "interactive-bridge");
const SKIP = new Set(["node_modules", "dist", "release", ".git"]);

function walk(dir, out = []) {
  for (const name of readdirSync(dir)) {
    if (SKIP.has(name)) continue;
    const path = join(dir, name);
    if (statSync(path).isDirectory()) walk(path, out);
    else out.push(path);
  }
  return out;
}

const all = walk(root);
const problems = [];

// 1. manifests (those a package ships as a file; gravity's is generated, see gravity/scripts/manifest.mjs)
for (const file of all.filter((f) => f.endsWith("/ulams-interactive.json"))) {
  const pkg = dirname(file);
  const files = new Set(packageFiles(pkg).map((f) => f.to)); // what the upload would contain
  let manifest;
  try {
    manifest = JSON.parse(readFileSync(file, "utf8"));
  } catch (error) {
    problems.push(`${relative(root, file)}: ${error.message}`);
    continue;
  }
  for (const p of validateManifest(manifest, files)) problems.push(`${relative(root, file)}: ${p}`);
}

// 2. vendored copies equal their source: the bridge (a fresh build of it) and the shared atlas
const copies = all.filter((f) => f.endsWith("/vendor/interactive-bridge.js"));
if (copies.length) {
  execFileSync("node", ["scripts/bundle.mjs"], { cwd: bridgeDir, stdio: "ignore" });
  const fresh = readFileSync(join(bridgeDir, "dist", "interactive-bridge.js"), "utf8");
  for (const copy of copies) if (readFileSync(copy, "utf8") !== fresh) problems.push(`${relative(root, copy)}: differs from front/interactive-bridge/dist (run: yarn workspace @ulams/demo-content sync-bridge)`);
}

const atlasSource = readFileSync(join(root, "shared", "atlas.js"), "utf8");
for (const copy of all.filter((f) => f.endsWith("/vendor/atlas.js"))) if (readFileSync(copy, "utf8") !== atlasSource) problems.push(`${relative(root, copy)}: differs from shared/atlas.js (run: yarn workspace @ulams/demo-content sync-bridge)`);

if (problems.length) {
  console.error(problems.map((p) => `  ${p}`).join("\n"));
  console.error(`\n${problems.length} problem(s) in demo-content.`);
  process.exit(1);
}
console.log(`demo-content: ${all.filter((f) => f.endsWith("/ulams-interactive.json")).length} manifest(s) and ${copies.length} bridge copy/copies OK`);
