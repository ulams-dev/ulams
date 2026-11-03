#!/usr/bin/env node
// JSON Schemas of the files of a course folder (ulams.yaml, course.yaml, module.yaml, lesson front matter, quiz).
// Generated from the zod schemas in src/course/schemas.ts and committed under schemas/course-folder/, so editors can
// validate while typing and the docs site can publish them (front/docs-site/scripts/agent-files.mjs).
//
//   node --experimental-strip-types --no-warnings scripts/gen-course-schemas.mjs           write
//   node --experimental-strip-types --no-warnings scripts/gen-course-schemas.mjs --check   fail when a file is stale
import { mkdirSync, readFileSync, writeFileSync, existsSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { FILE_SCHEMAS, jsonSchemaFor } from "../src/course/schemas.ts";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const dir = resolve(root, "schemas/course-folder");
const check = process.argv.includes("--check");
const stale = [];

for (const name of Object.keys(FILE_SCHEMAS)) {
  const text = `${JSON.stringify(jsonSchemaFor(name), null, 2)}\n`;
  const path = resolve(dir, name);
  if (check) {
    if (!existsSync(path) || readFileSync(path, "utf8") !== text) stale.push(name);
  } else {
    mkdirSync(dir, { recursive: true });
    writeFileSync(path, text);
  }
}
if (check && stale.length) {
  console.error(`Stale course folder schemas: ${stale.join(", ")}. Run \`yarn workspace ulams schemas\` and commit the result.`);
  process.exit(1);
}
console.log(check ? "course folder schemas are up to date" : `wrote ${Object.keys(FILE_SCHEMAS).length} schemas to schemas/course-folder`);
