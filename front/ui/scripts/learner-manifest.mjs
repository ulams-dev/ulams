// Writes catalogue/learner-layout-manifest.json: the approved learner-layout components (description,
// closed props JSON Schema) the API validates generated layouts against, and its copy in the Layout topic
// package (api/packages/topic-type-layout/resources), which validates stored documents server-side
// (ADR 0052). `--check` fails when either file is stale.
import { readFileSync, writeFileSync, mkdirSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { learnerLayoutManifest } from "../src/registry.ts";

const here = dirname(fileURLToPath(import.meta.url));
const files = [
  resolve(here, "../catalogue/learner-layout-manifest.json"),
  resolve(here, "../../../api/packages/topic-type-layout/resources/learner-layout-manifest.json"),
];
const json = `${JSON.stringify(learnerLayoutManifest(), null, 2)}\n`;
let stale = false;
for (const file of files) {
  let current = "";
  try {
    current = readFileSync(file, "utf8");
  } catch {
    current = "";
  }
  if (current === json) continue;
  if (process.argv.includes("--check")) {
    console.error(`out of date: ${file} (run yarn workspace @ulams/ui learner-manifest)`);
    stale = true;
    continue;
  }
  mkdirSync(dirname(file), { recursive: true });
  writeFileSync(file, json);
  console.log(`wrote ${file}`);
}
if (stale) process.exit(1);
