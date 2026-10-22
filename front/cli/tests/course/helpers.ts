import { cpSync, existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { readFolder } from "../../src/course/disk.ts";
import type { Files } from "../../src/course/types.ts";

export const fixtures = join(dirname(fileURLToPath(import.meta.url)), "fixtures");
export const example = join(dirname(fileURLToPath(import.meta.url)), "../../examples/kubernetes-101");

/** Ids that read like ids and never change between runs. */
export function counterMint(): () => string {
  let n = 0;
  return () => `m${String(++n).padStart(25, "0")}`;
}

export const loadFixture = (name: string) => readFolder(join(fixtures, name));

export function tempCopy(from: string): { dir: string; cleanup: () => void } {
  const dir = mkdtempSync(join(tmpdir(), "ulams-course-"));
  cpSync(from, dir, { recursive: true });
  return { dir, cleanup: () => rmSync(dir, { recursive: true, force: true }) };
}

const update = () => process.env.UPDATE_GOLDEN === "1";

/** Compares JSON with a committed golden file; UPDATE_GOLDEN=1 rewrites it. */
export function goldenJson(name: string): { read(): unknown; write(value: unknown): void } {
  const path = join(fixtures, name);
  return {
    read: () => JSON.parse(readFileSync(path, "utf8")),
    write: (value) => writeFileSync(path, `${JSON.stringify(value, null, 2)}\n`),
  };
}

export function goldenFiles(name: string, actual: Files): { expected: Files } {
  const dir = join(fixtures, name);
  if (update() || !existsSync(dir)) {
    rmSync(dir, { recursive: true, force: true });
    for (const [path, text] of actual) {
      if (text === "") continue;
      mkdirSync(dirname(join(dir, path)), { recursive: true });
      writeFileSync(join(dir, path), text);
    }
  }
  return { expected: new Map([...readFolder(dir).files].filter(([, t]) => t !== "")) };
}

export const sortedEntries = (files: Files) => [...files.entries()].filter(([, t]) => t !== "").sort(([a], [b]) => a.localeCompare(b));
