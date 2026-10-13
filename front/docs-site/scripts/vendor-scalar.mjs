// Fetches the browser bundle of Scalar's API reference (MIT) into public/vendor/scalar/, so the API
// browser is served from the docs site itself and nothing loads from a CDN. The version is pinned and
// the tarball is checked against the registry's integrity hash. The file is not committed.
//
// Scalar is not a yarn dependency on purpose: its Vue tree adds ~1,300 lines to the monorepo lockfile
// that the learner front and the staging server installs share.
import { createHash } from "node:crypto";
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join, resolve } from "node:path";
import { execFileSync } from "node:child_process";

const VERSION = "1.72.1";
const INTEGRITY = "sha512-XpEJ0rfc/cXbCrR+iC4kK0o/WwX2dTtGQbT049OlPKAHD6IMIFdTeGz2r+M27P2EDWKyUITRm/NzlP8z2pw/Ow==";
const OUT = resolve(import.meta.dirname, "../public/vendor/scalar");
const stamp = join(OUT, ".version");

if (existsSync(join(OUT, "standalone.js")) && existsSync(stamp) && readFileSync(stamp, "utf8") === VERSION) {
  console.log(`scalar ${VERSION}: already vendored`);
  process.exit(0);
}

const res = await fetch(`https://registry.npmjs.org/@scalar/api-reference/-/api-reference-${VERSION}.tgz`);
if (!res.ok) throw new Error(`scalar: download failed (${res.status})`);
const buf = Buffer.from(await res.arrayBuffer());
const actual = `sha512-${createHash("sha512").update(buf).digest("base64")}`;
if (actual !== INTEGRITY) throw new Error(`scalar: integrity mismatch for ${VERSION}: ${actual}`);

const tmp = mkdtempSync(join(tmpdir(), "scalar-"));
try {
  writeFileSync(join(tmp, "pkg.tgz"), buf);
  execFileSync("tar", ["-xzf", join(tmp, "pkg.tgz"), "-C", tmp, "package/dist/browser/standalone.js", "package/package.json"]);
  mkdirSync(OUT, { recursive: true });
  writeFileSync(join(OUT, "standalone.js"), readFileSync(join(tmp, "package/dist/browser/standalone.js")));
  const meta = JSON.parse(readFileSync(join(tmp, "package/package.json"), "utf8"));
  writeFileSync(join(OUT, "NOTICE.txt"), `@scalar/api-reference ${meta.version}, ${meta.license} licence (package.json), https://github.com/scalar/scalar\n`);
  writeFileSync(stamp, VERSION);
  console.log(`scalar ${VERSION}: vendored (${(buf.length / 1e6).toFixed(1)} MB tarball)`);
} finally {
  rmSync(tmp, { recursive: true, force: true });
}
