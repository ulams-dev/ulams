// The demo-content rule (ADR 0088): packages under demo-content/ run only as sandboxed content, so
// nothing outside demo-content/ may import, require, include or depend on them. PHP seeders may read
// their files as data (file_get_contents, ZipArchive::addFile, glob), never require/include them.
//   node scripts/check-demo-content-boundary.mjs
import { execFileSync } from "node:child_process";
import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const JS = /\.(m?[jt]sx?|cjs|astro|vue|svelte)$/;
const PHP = /\.php$/;
const PKG = /(^|\/)package\.json$/;
const IGNORED = /(^|\/)(node_modules|vendor|dist|\.git|storage|coverage)\//;

const JS_IMPORT = /(?:\bfrom\s*|\bimport\s*\(?\s*|\brequire\s*\(\s*|\bimport\.meta\.glob\s*\(\s*)['"`][^'"`\n]*\bdemo-content\//;
const PHP_INCLUDE = /\b(?:require|require_once|include|include_once)\b[^;\n]*\bdemo-content\//;
const WORKSPACE_DEP = /["']@ulams\/demo-content|["']@ulams\/demo-gravity/;

/** @param {string} path repository-relative @param {string} text */
export function violationsIn(path, text) {
  if (path.startsWith("demo-content/") || IGNORED.test(path)) return [];
  const found = [];
  if (JS.test(path) && JS_IMPORT.test(text)) found.push(`${path}: imports a file under demo-content/`);
  if (PHP.test(path) && PHP_INCLUDE.test(text)) found.push(`${path}: requires or includes a file under demo-content/ (read it as data instead)`);
  if (PKG.test(path) && WORKSPACE_DEP.test(text)) found.push(`${path}: depends on a demo-content workspace`);
  return found;
}

export function findViolations(root, files) {
  const out = [];
  for (const file of files) {
    let text;
    try {
      text = readFileSync(join(root, file), "utf8");
    } catch {
      continue;
    }
    out.push(...violationsIn(file, text));
  }
  return out;
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const root = join(dirname(fileURLToPath(import.meta.url)), "..", "..");
  const files = execFileSync("git", ["ls-files", "-z", "--cached", "--others", "--exclude-standard"], { cwd: root, maxBuffer: 64 * 1024 * 1024 })
    .toString()
    .split("\0")
    .filter((f) => f && (JS.test(f) || PHP.test(f) || PKG.test(f)));
  const violations = findViolations(root, files);
  if (violations.length) {
    console.error(violations.map((v) => `  ${v}`).join("\n"));
    console.error("\ndemo-content boundary violated (ADR 0088).");
    process.exit(1);
  }
  console.log(`demo-content boundary OK (${files.length} source files checked)`);
}
