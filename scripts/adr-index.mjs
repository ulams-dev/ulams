#!/usr/bin/env node
// Rebuilds the ADR index table in docs/decisions/README.md from the records themselves
// (the "# NNNN. Title" heading and the "- Status:" line). Only the part between the
// BEGIN/END markers is generated; the intro text stays hand-written.
//
//   node scripts/adr-index.mjs           rewrite the table
//   node scripts/adr-index.mjs --check   exit 1 when the README is stale (used by CI)
import { readdirSync, readFileSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const dir = join(dirname(fileURLToPath(import.meta.url)), "..", "docs", "decisions");
const readme = join(dir, "README.md");
const BEGIN = "<!-- BEGIN GENERATED: adr-index (run `node scripts/adr-index.mjs`, do not edit by hand) -->";
const END = "<!-- END GENERATED: adr-index -->";

const rows = [];
const problems = [];
for (const file of readdirSync(dir).filter((f) => /^\d{4}-.+\.md$/.test(f)).sort()) {
  const text = readFileSync(join(dir, file), "utf8");
  const number = file.slice(0, 4);
  const title = /^# (\d{4})\. (.+)$/m.exec(text);
  const status = /^[-*]?\s*Status:\s*(.+)$/im.exec(text);
  if (!title || title[1] !== number) problems.push(`${file}: first heading must read "# ${number}. Title"`);
  if (!status) problems.push(`${file}: no "- Status:" line`);
  if (title && status) {
    // "Accepted (2026-10-09)" shows as "Accepted"; a trailing date is not part of the status
    const state = status[1].trim().replace(/\s*\(\d{4}-\d{2}-\d{2}\)\s*$/, "");
    rows.push(`| [${number}](${file}) | ${title[2].trim().replace(/\|/g, "\\|")} | ${state} |`);
  }
}
if (problems.length) {
  console.error(problems.join("\n"));
  process.exit(1);
}

const table = ["| # | Title | Status |", "|---|---|---|", ...rows].join("\n");
const current = readFileSync(readme, "utf8");
const start = current.indexOf(BEGIN);
const end = current.indexOf(END);
if (start < 0 || end < start) {
  console.error(`docs/decisions/README.md needs the markers:\n${BEGIN}\n${END}`);
  process.exit(1);
}
const next = `${current.slice(0, start)}${BEGIN}\n\n${table}\n\n${END}${current.slice(end + END.length)}`;

if (process.argv.includes("--check")) {
  if (next !== current) {
    console.error("docs/decisions/README.md is stale. Run `node scripts/adr-index.mjs` and commit the result (on a merge conflict in the table, re-run it instead of resolving rows by hand).");
    process.exit(1);
  }
  console.log(`ADR index is up to date (${rows.length} records).`);
} else {
  if (next !== current) writeFileSync(readme, next);
  console.log(`ADR index: ${rows.length} records.`);
}
