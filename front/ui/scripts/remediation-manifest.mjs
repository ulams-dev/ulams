// Writes catalogue/remediation-manifest.json: the blocks a stored remediation may contain (description,
// closed props JSON Schema), and its copy in the Learner Insights package
// (api/packages/learner-insights/resources), which validates every generated block before it is stored.
// `--check` fails when either file is stale.
import { readFileSync, writeFileSync, mkdirSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { remediationManifest } from "../src/registry.ts";

const here = dirname(fileURLToPath(import.meta.url));
const files = [
  resolve(here, "../catalogue/remediation-manifest.json"),
  resolve(here, "../../../api/packages/learner-insights/resources/remediation-manifest.json"),
];
const json = `${JSON.stringify(remediationManifest(), null, 2)}\n`;
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
    console.error(`out of date: ${file} (run yarn workspace @ulams/ui remediation-manifest)`);
    stale = true;
    continue;
  }
  mkdirSync(dirname(file), { recursive: true });
  writeFileSync(file, json);
  console.log(`wrote ${file}`);
}
if (stale) process.exit(1);
