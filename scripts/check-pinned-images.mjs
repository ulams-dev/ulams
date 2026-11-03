#!/usr/bin/env node
// One tag per infrastructure image across every compose file, workflow and stack renderer, so the
// developer stack, CI and the shipped installs run the same version (ADR 0040, docs/plans/phase-8-self-hosting.md 4.1).
// A family is checked when every reference found in tracked .yml, .yaml and .ts files has the same
// tag and that tag starts with the expected major. A reference is `image: <name>:<tag>` or a quoted
// "<name>:<tag>"; an unquoted value such as `PG_OLD_IMAGE: postgres:12` (the source version of the
// upgrade job) is not one.
//
//   node scripts/check-pinned-images.mjs
import { execFileSync } from "node:child_process";
import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");

// Add Valkey here when its milestone lands (P8-5). SeaweedFS: ADR 0041, docs/plans/phase-8-self-hosting.md 4.3.
export const FAMILIES = [
  { name: "postgres", image: "postgres", majorPrefix: "17" },
  { name: "seaweedfs", image: "chrislusf/seaweedfs", majorPrefix: "4." },
];

export function findReferences(text, image) {
  const re = new RegExp(`(?:image:\\s*|["'\`])${image}:([\\w.-]+)`, "g");
  return [...text.matchAll(re)].map((m) => m[1]);
}

export function check(files, families = FAMILIES) {
  const problems = [];
  for (const family of families) {
    const tags = new Map();
    for (const [path, text] of files) {
      for (const tag of findReferences(text, family.image)) {
        if (!tags.has(tag)) tags.set(tag, []);
        tags.get(tag).push(path);
      }
    }
    if (tags.size === 0) problems.push(`${family.name}: no reference found, the check is looking in the wrong places`);
    if (tags.size > 1) {
      problems.push(`${family.name}: ${tags.size} different tags in use: ${[...tags].map(([t, p]) => `${t} (${[...new Set(p)].join(", ")})`).join("; ")}`);
    }
    for (const [tag, paths] of tags) {
      if (!tag.startsWith(family.majorPrefix)) {
        problems.push(`${family.name}: tag ${tag} is not ${family.majorPrefix}.x (${[...new Set(paths)].join(", ")})`);
      }
    }
  }
  return problems;
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  const tracked = execFileSync("git", ["ls-files", "-z", "--", "*.yml", "*.yaml", "*.ts"], { cwd: root, encoding: "utf8" })
    .split("\0")
    .filter(Boolean);
  const files = tracked.map((p) => [p, readFileSync(join(root, p), "utf8")]);
  const problems = check(files);
  if (problems.length) {
    for (const p of problems) console.error(`::error::${p}`);
    process.exit(1);
  }
  console.log(`pinned images agree: ${FAMILIES.map((f) => f.name).join(", ")}`);
}
