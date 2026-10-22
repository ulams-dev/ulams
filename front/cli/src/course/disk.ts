import { createHash } from "node:crypto";
import { mkdirSync, readFileSync, readdirSync, renameSync, statSync, writeFileSync } from "node:fs";
import { dirname, join, resolve, sep } from "node:path";
import { CliError } from "../errors.ts";
import type { FmtPlan } from "./fmt.ts";
import { COURSE_FILE, FINAL_TEST_FILE, MODULES_DIR, ULAMS_FILE } from "./layout.ts";
import type { AssetLookup, Files } from "./types.ts";

export interface DiskFolder {
  root: string;
  files: Files;
  assets: AssetLookup;
}

const isFile = (path: string): boolean => {
  try {
    return statSync(path).isFile();
  } catch {
    return false;
  }
};

/** Reads the text files of a course folder. Other files under modules/ are listed with empty text so the validator can flag them. */
export function readFolder(dir: string): DiskFolder {
  const root = resolve(dir);
  let st;
  try {
    st = statSync(root);
  } catch {
    throw new CliError("NOT_FOUND", `${dir} does not exist.`, { hint: "Pass the path of a course folder (the one holding ulams.yaml)." });
  }
  if (!st.isDirectory()) throw new CliError("INPUT_INVALID", `${dir} is not a folder.`, { hint: "Pass the path of a course folder (the one holding ulams.yaml)." });

  const files: Files = new Map();
  for (const name of [ULAMS_FILE, COURSE_FILE, FINAL_TEST_FILE]) {
    if (isFile(join(root, name))) files.set(name, readFileSync(join(root, name), "utf8"));
  }
  const walk = (rel: string) => {
    for (const entry of readdirSync(join(root, rel), { withFileTypes: true })) {
      const path = `${rel}/${entry.name}`;
      if (entry.isDirectory()) walk(path);
      else if (entry.isFile()) files.set(path, /\.(md|yaml)$/.test(entry.name) ? readFileSync(join(root, path), "utf8") : "");
    }
  };
  try {
    walk(MODULES_DIR);
  } catch {
    /* no modules/ folder: nothing to read */
  }

  const cache = new Map<string, { bytes: number; sha256: string } | null>();
  const assets: AssetLookup = (path) => {
    if (cache.has(path)) return cache.get(path) as { bytes: number; sha256: string } | null;
    const full = resolve(root, path);
    let info: { bytes: number; sha256: string } | null = null;
    if (full.startsWith(root + sep) && isFile(full)) {
      const data = readFileSync(full);
      info = { bytes: data.length, sha256: createHash("sha256").update(data).digest("hex") };
    }
    cache.set(path, info);
    return info;
  };
  return { root, files, assets };
}

/** Renames folders and files (through temporary names, so swaps work), then writes the changed files. */
export function applyPlanToDisk(root: string, plan: FmtPlan): void {
  const move = (pairs: Array<{ from: string; to: string }>) => {
    const tmp = pairs.map((p, i) => ({ ...p, tmp: `${p.from}.fmt-${i}` }));
    for (const p of tmp) renameSync(join(root, p.from), join(root, p.tmp));
    for (const p of tmp) {
      mkdirSync(dirname(join(root, p.to)), { recursive: true });
      renameSync(join(root, p.tmp), join(root, p.to));
    }
  };
  move(plan.dirRenames);
  move([...plan.fileRenames].map(([from, to]) => ({ from, to })));
  for (const [path, text] of plan.writes) {
    mkdirSync(dirname(join(root, path)), { recursive: true });
    writeFileSync(join(root, path), text);
  }
}
