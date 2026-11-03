import { spawnSync } from "node:child_process";
import { existsSync, readdirSync, statSync } from "node:fs";

export const MIN_NODE = [22, 12];

export function nodeSupported(version = process.versions.node) {
  const [major, minor] = version.split(".").map(Number);
  return major > MIN_NODE[0] || (major === MIN_NODE[0] && minor >= MIN_NODE[1]);
}

/**
 * What is at the target path: `missing`, `empty`, `file` or `not-empty` (with the names inside).
 * @returns {{state:'missing'|'empty'|'file'|'not-empty',entries:string[]}}
 */
export function inspectDirectory(path, fs = { existsSync, readdirSync, statSync }) {
  if (!fs.existsSync(path)) return { state: "missing", entries: [] };
  if (!fs.statSync(path).isDirectory()) return { state: "file", entries: [] };
  const entries = fs.readdirSync(path);
  return { state: entries.length === 0 ? "empty" : "not-empty", entries };
}

/** True when `docker compose` (v2) answers. */
export function dockerComposeAvailable(run = spawnSync) {
  const result = run("docker", ["compose", "version"], { encoding: "utf8", timeout: 15000 });
  return result.status === 0;
}
