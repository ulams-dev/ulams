// Writes the page catalogue manifest (every registry component with its closed props JSON Schema) to
// catalogue/page-manifest.json and the API copy. `--check` fails when either file is out of date.
import { readFileSync, writeFileSync, mkdirSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { pageManifest } from "../src/registry.ts";

const here = dirname(fileURLToPath(import.meta.url));
const targets = [
  resolve(here, "../catalogue/page-manifest.json"),
  resolve(here, "../../../api/packages/course-builder/resources/catalogue/page-manifest.json"),
];
const json = `${JSON.stringify(pageManifest(), null, 2)}\n`;
const check = process.argv.includes("--check");
let stale = false;

for (const file of targets) {
  let current = "";
  try {
    current = readFileSync(file, "utf8");
  } catch {
    current = "";
  }
  if (current === json) continue;
  if (check) {
    console.error(`out of date: ${file} (run yarn workspace @ulams/ui page-manifest)`);
    stale = true;
  } else {
    mkdirSync(dirname(file), { recursive: true });
    writeFileSync(file, json);
    console.log(`wrote ${file}`);
  }
}
process.exit(stale ? 1 : 0);
