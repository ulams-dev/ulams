// Writes catalogue/learner-layout-manifest.json: the approved learner-layout components (description,
// closed props JSON Schema) the API validates generated layouts against. `--check` fails when it is stale.
import { readFileSync, writeFileSync, mkdirSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { learnerLayoutManifest } from "../src/registry.ts";

const file = resolve(dirname(fileURLToPath(import.meta.url)), "../catalogue/learner-layout-manifest.json");
const json = `${JSON.stringify(learnerLayoutManifest(), null, 2)}\n`;
let current = "";
try {
  current = readFileSync(file, "utf8");
} catch {
  current = "";
}
if (current !== json) {
  if (process.argv.includes("--check")) {
    console.error(`out of date: ${file} (run yarn workspace @ulams/ui learner-manifest)`);
    process.exit(1);
  }
  mkdirSync(dirname(file), { recursive: true });
  writeFileSync(file, json);
  console.log(`wrote ${file}`);
}
