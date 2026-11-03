import { existsSync } from "node:fs";
import { createRequire } from "node:module";
import { dirname, resolve } from "node:path";
import { spawnSync } from "node:child_process";

/**
 * Finds the ulams CLI that does the work: `--cli`, then ULAMS_CLI, then the `ulams` package this one depends on, then
 * `ulams` on PATH.
 *
 * @returns {{command:string,args:string[],source:string}|null} the command to spawn, without the CLI's own arguments
 */
export function resolveCli({ flag, env = process.env, requireFrom = import.meta.url, exists = existsSync, run = spawnSync } = {}) {
  const script = (path, source) => ({ command: process.execPath, args: [resolve(path)], source });
  if (flag) return exists(flag) ? script(flag, "--cli") : null;
  if (env.ULAMS_CLI) return exists(env.ULAMS_CLI) ? script(env.ULAMS_CLI, "ULAMS_CLI") : null;
  try {
    const manifest = createRequire(requireFrom).resolve("ulams/package.json");
    const entry = resolve(dirname(manifest), "dist/ulams.mjs");
    if (exists(entry)) return script(entry, "bundled ulams");
  } catch {
    // not installed next to this package
  }
  const onPath = run("ulams", ["--version"], { encoding: "utf8", timeout: 15000 });
  if (onPath.status === 0) return { command: "ulams", args: [], source: "PATH" };

  return null;
}

/** The arguments of `ulams stack up` for the parsed options. */
export function stackArgs({ directory, name, options }) {
  const args = ["stack", "up", "--directory", directory, "--name", name, "--mode", options.mode, "--target", options.target];
  const flag = (key, value) => value !== undefined && args.push(key, String(value));
  flag("--domain", options.domain);
  flag("--tenant", options.academy);
  flag("--academy-name", options.academyName);
  flag("--theme", options.theme);
  flag("--users", options.users);
  flag("--admin-email", options.adminEmail);
  flag("--http-port", options.httpPort);
  flag("--image-tag", options.imageTag);
  flag("--tls", options.tls);
  if (options.dryRun) args.push("--dry-run");

  return args;
}
