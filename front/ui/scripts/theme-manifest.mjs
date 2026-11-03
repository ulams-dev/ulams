// Writes catalogue/themes.json: the one list of tenant theme presets (tokens, mood, browser chrome colour),
// generated from src/theme/presets.ts, and its copy in the Settings package
// (api/packages/settings/resources/themes.json), which validates `theme.theme` and renders the accent
// adjustment server-side. `--check` fails when either file is stale.
import { readFileSync, writeFileSync, mkdirSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { themeManifest } from "../src/theme/presets.ts";

const here = dirname(fileURLToPath(import.meta.url));
const files = [
  resolve(here, "../catalogue/themes.json"),
  resolve(here, "../../../api/packages/settings/resources/themes.json"),
];
const json = `${JSON.stringify(themeManifest(), null, 2)}\n`;
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
    console.error(`out of date: ${file} (run yarn workspace @ulams/ui theme-manifest)`);
    stale = true;
    continue;
  }
  mkdirSync(dirname(file), { recursive: true });
  writeFileSync(file, json);
  console.log(`wrote ${file}`);
}
if (stale) process.exit(1);
