// Generates the pages that come from the repository itself (ADRs, roadmap, reference pages,
// contributor files) so the site never holds a second copy of a document. Runs before every
// dev, build and check. Output paths are listed in front/docs-site/.gitignore.
import { readdirSync, rmSync } from "node:fs";
import { join } from "node:path";
import { pathToFileURL } from "node:url";
import { DOCS_DIR, SITE_DIR } from "./lib.mjs";

const GENERATED = [
  "decisions",
  "reference",
  "roadmap.md",
  "contributing/code-of-conduct.md",
  "contributing/security-policy.md",
  "contributing/contributing-guide.md",
];
for (const p of GENERATED) rmSync(join(DOCS_DIR, p), { recursive: true, force: true });

const dir = join(SITE_DIR, "scripts/generators");
const files = readdirSync(dir)
  .filter((f) => f.endsWith(".mjs"))
  .sort();

let pages = 0;
for (const f of files) {
  const mod = await import(pathToFileURL(join(dir, f)).href);
  const written = (await mod.default()) ?? [];
  pages += written.length;
  console.log(`sync: ${f.replace(/\.mjs$/, "")} → ${written.length} page(s)`);
}
console.log(`sync: ${pages} generated page(s)`);
