import assert from "node:assert/strict";
import { mkdirSync, mkdtempSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { test } from "node:test";
import { packageFiles } from "../../scripts/pack.mjs";

test("a package ships what the browser needs: no scripts, tests or dotfiles, licences as .txt", () => {
  const dir = mkdtempSync(join(tmpdir(), "pack-"));
  for (const d of ["scripts", "tests", "data", "node_modules/x", "release"]) mkdirSync(join(dir, d), { recursive: true });
  for (const f of ["index.html", "app.js", "package.json", "README.md", "LICENSE", "LICENSE-content", "NOTICE", ".gitignore", "data/a.json", "scripts/build.mjs", "tests/a.mjs", "node_modules/x/i.js", "release/old.zip"]) writeFileSync(join(dir, f), "x");
  const files = packageFiles(dir).map((f) => f.to).sort();
  assert.deepEqual(files, ["LICENSE-content.txt", "LICENSE.txt", "NOTICE.txt", "app.js", "data/a.json", "index.html"]);
});
