// Zips a package folder the way the upload expects it (the files a learner's browser needs, nothing else):
//   node scripts/pack.mjs <package-dir> [out.zip]
// Left out: scripts/, tests/, node_modules/, dist/, release/, package.json, README.md, dotfiles. LICENSE, LICENSE-content
// and NOTICE ship as .txt (the upload accepts only the file types in its allow-list, and a bare LICENSE has none).
import { execFileSync } from "node:child_process";
import { copyFileSync, mkdirSync, mkdtempSync, readdirSync, rmSync, statSync } from "node:fs";
import { tmpdir } from "node:os";
import { basename, dirname, join, relative, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const SKIP_DIRS = new Set(["scripts", "tests", "node_modules", "dist", "release", "test-results"]);
const SKIP_FILES = new Set(["package.json", "README.md", "yarn.lock", "package-lock.json"]);
const RENAME = { LICENSE: "LICENSE.txt", "LICENSE-content": "LICENSE-content.txt", NOTICE: "NOTICE.txt", CREDITS: "CREDITS.txt" };

/** @returns {Array<{from: string, to: string}>} the files of the package with their path inside the zip */
export function packageFiles(dir) {
  const out = [];
  const walk = (d) => {
    for (const name of readdirSync(d).sort()) {
      if (name.startsWith(".")) continue;
      const path = join(d, name);
      const rel = relative(dir, path);
      if (statSync(path).isDirectory()) {
        if (!(d === dir && SKIP_DIRS.has(name))) walk(path);
      } else if (!(d === dir && SKIP_FILES.has(name))) {
        out.push({ from: path, to: d === dir && RENAME[name] ? RENAME[name] : rel });
      }
    }
  };
  walk(dir);
  return out;
}

/** Builds the zip and returns its path. */
export function pack(dir, out) {
  const work = mkdtempSync(join(tmpdir(), "ulams-pack-"));
  for (const { from, to } of packageFiles(dir)) {
    mkdirSync(dirname(join(work, to)), { recursive: true });
    copyFileSync(from, join(work, to));
  }
  const zip = resolve(out ?? join(dir, "release", `${basename(dir)}-ulams.zip`));
  mkdirSync(dirname(zip), { recursive: true });
  rmSync(zip, { force: true });
  execFileSync("zip", ["-qrXD", zip, "."], { cwd: work });
  rmSync(work, { recursive: true, force: true });
  return zip;
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const [dir, out] = process.argv.slice(2);
  if (!dir) {
    console.error("usage: node scripts/pack.mjs <package-dir> [out.zip]");
    process.exit(1);
  }
  console.log(`wrote ${pack(resolve(dir), out)}`);
}
