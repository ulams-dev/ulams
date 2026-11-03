#!/usr/bin/env node
// Keeps .trivyignore.yaml honest (patch policy, SECURITY.md): every entry has an id, a reason
// (statement) and an expiry date (expired_at, YYYY-MM-DD), no entry has expired, and none expires more
// than a year ahead. Trivy itself stops ignoring an expired entry, but only when the finding is still
// there; this check also catches an expired entry whose finding was fixed (delete it) and a forgotten date.
//
//   node scripts/check-trivyignore.mjs [file] [--today YYYY-MM-DD]
//
// The file is read line by line (no YAML dependency): an entry starts at "  - id: X" under a top-level
// list key, and its fields are indented four spaces. Folded text (`>-`) under statement counts as set.
import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const MAX_DAYS = 366;

export function checkIgnoreFile(text, today) {
  const problems = [];
  const entries = [];
  let current = null;
  let lastField = null;
  for (const [index, line] of text.split("\n").entries()) {
    const start = /^ {2}- id:\s*(\S+)/.exec(line);
    if (start) {
      current = { id: start[1], line: index + 1, statement: false, expiredAt: null };
      entries.push(current);
      lastField = "id";
      continue;
    }
    if (!current || !/^ {4}\S/.test(line)) {
      // Folded statement text is indented deeper than the field name; keep counting it as content.
      if (current && lastField === "statement" && /^ {6}\S/.test(line)) current.statement = true;
      continue;
    }
    const field = /^ {4}([a-z_]+):\s*(.*)$/.exec(line);
    if (!field) continue;
    lastField = field[1];
    if (field[1] === "statement" && field[2].replace(/^[>|][-+]?$/, "").replace(/^["']|["']$/g, "").trim() !== "") current.statement = true;
    if (field[1] === "expired_at") current.expiredAt = field[2].trim().replace(/^["']|["']$/g, "");
  }
  const limit = new Date(today.getTime() + MAX_DAYS * 86400000);
  for (const e of entries) {
    const where = `line ${e.line} (${e.id})`;
    if (!e.statement) problems.push(`${where}: statement (the reason) is missing`);
    if (!e.expiredAt) {
      problems.push(`${where}: expired_at is missing`);
      continue;
    }
    if (!/^\d{4}-\d{2}-\d{2}$/.test(e.expiredAt) || Number.isNaN(Date.parse(e.expiredAt))) {
      problems.push(`${where}: expired_at "${e.expiredAt}" is not a YYYY-MM-DD date`);
      continue;
    }
    const date = new Date(`${e.expiredAt}T00:00:00Z`);
    if (date < today) problems.push(`${where}: expired on ${e.expiredAt}; fix the finding and delete the entry, or renew it with a new reason`);
    else if (date > limit) problems.push(`${where}: expires ${e.expiredAt}, more than a year ahead`);
  }
  return { entries: entries.length, problems };
}

if (process.argv[1] && import.meta.url === new URL(`file://${process.argv[1]}`).href) {
  const args = process.argv.slice(2);
  const todayIndex = args.indexOf("--today");
  const todayArg = todayIndex >= 0 ? args.splice(todayIndex, 2)[1] : new Date().toISOString().slice(0, 10);
  const file = args[0] ?? join(dirname(fileURLToPath(import.meta.url)), "..", ".trivyignore.yaml");
  const { entries, problems } = checkIgnoreFile(readFileSync(file, "utf8"), new Date(`${todayArg}T00:00:00Z`));
  if (problems.length) {
    for (const p of problems) console.error(`::error file=.trivyignore.yaml::${p}`);
    process.exit(1);
  }
  console.log(`.trivyignore.yaml: ${entries} entries, none expired`);
}
