import { execFileSync, spawnSync } from "node:child_process";
import { existsSync, mkdirSync, mkdtempSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { afterAll, describe, expect, it } from "vitest";
import { resolveCli } from "../src/cli.mjs";

const root = resolve(fileURLToPath(new URL("..", import.meta.url)));
const bin = join(root, "bin/create-ulams.mjs");
const cliDist = resolve(root, "../cli/dist/ulams.mjs");
const built = existsSync(cliDist);
const scratch = mkdtempSync(join(tmpdir(), "create-ulams-"));
afterAll(() => rmSync(scratch, { recursive: true, force: true }));

const run = (args, env = {}) =>
  spawnSync(process.execPath, [bin, ...args], { encoding: "utf8", cwd: scratch, env: { ...process.env, ULAMS_CLI: "", ...env } });

describe("the create-ulams command", () => {
  it("prints help", () => {
    const result = run(["--help"]);
    expect(result.status).toBe(0);
    expect(result.stdout).toContain("npx create-ulams [directory]");
  });

  it("rejects a bad option with exit code 2", () => {
    const result = run(["--mode", "staging"]);
    expect(result.status).toBe(2);
    expect(result.stderr).toContain("--mode must be");
  });

  it("refuses a directory with files in it and leaves them alone", () => {
    const dir = join(scratch, "occupied");
    mkdirSync(dir);
    writeFileSync(join(dir, "notes.txt"), "mine");

    const result = run([dir, "--dry-run"]);

    expect(result.status).toBe(2);
    expect(result.stderr).toContain("is not empty (notes.txt)");
  });

  it("says so when the CLI is missing", () => {
    const result = run(["fresh", "--dry-run", "--cli", join(scratch, "nope.mjs")]);
    expect(result.status).toBe(1);
    expect(result.stderr).toContain("does not exist");
  });

  it.skipIf(!built)("plans a stack through the real ulams CLI without touching the disk", () => {
    const dir = join(scratch, "planned");
    const result = run([dir, "--dry-run", "--academy-name", "Latte Lab", "--users", "2"], { ULAMS_CLI: cliDist, ULAMS_CONFIG_DIR: join(scratch, "cfg") });

    expect(result.status, result.stderr).toBe(0);
    expect(result.stderr).toContain("Planning your academy");
    expect(result.stdout).toMatch(/planned|dryRun|dry run|Latte Lab/i);
    expect(existsSync(dir)).toBe(false);
  });

  it.skipIf(!built)("finds the bundled ulams package by itself", () => {
    expect(resolveCli({ env: {} })?.source).toBe("bundled ulams");
  });
});

describe("the npm package", () => {
  it("ships the bin, the sources and the licence, and nothing else", () => {
    const [pack] = JSON.parse(execFileSync("npm", ["pack", "--dry-run", "--json", "--ignore-scripts"], { cwd: root, encoding: "utf8" }));
    const files = pack.files.map((f) => f.path).sort();

    expect(files).toEqual(["LICENSE", "README.md", "bin/create-ulams.mjs", "package.json", "src/args.mjs", "src/cli.mjs", "src/main.mjs", "src/preflight.mjs"]);
  });

  it("depends on the ulams CLI at the version of this repository", () => {
    const own = JSON.parse(execFileSync("node", ["-p", "JSON.stringify(require('./package.json'))"], { cwd: root, encoding: "utf8" }));
    const cli = JSON.parse(execFileSync("node", ["-p", "JSON.stringify(require('../cli/package.json'))"], { cwd: root, encoding: "utf8" }));

    expect(own.dependencies.ulams).toBe(cli.version);
    expect(own.bin["create-ulams"]).toBe("bin/create-ulams.mjs");
    expect(own.engines.node).toBe(cli.engines.node);
  });
});
