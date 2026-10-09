import assert from "node:assert/strict";
import { test } from "node:test";
import { violationsIn } from "../../scripts/check-demo-content-boundary.mjs";

test("an import of a demo-content path outside the folder is a violation", () => {
  assert.equal(violationsIn("front/web/src/lib/x.ts", `import data from "../../../demo-content/poland/data/steps.json";`).length, 1);
  assert.equal(violationsIn("front/ui/src/y.mjs", `const m = await import("../../demo-content/gravity/x.js");`).length, 1);
  assert.equal(violationsIn("scripts/z.cjs", `require('../demo-content/ulam/spiral/main.js')`).length, 1);
});

test("a PHP require or include is a violation, reading the files as data is not", () => {
  assert.equal(violationsIn("api/database/seeds/A.php", `require_once base_path('../demo-content/x.php');`).length, 1);
  assert.equal(violationsIn("api/database/seeds/B.php", `$json = file_get_contents(base_path('../demo-content/poland/data/steps.json')); $zip->addFile($p, 'demo-content/x');`).length, 0);
});

test("a workspace dependency on a demo-content package is a violation", () => {
  assert.equal(violationsIn("front/web/package.json", `{"dependencies":{"@ulams/demo-gravity":"1.0.0"}}`).length, 1);
});

test("files inside demo-content and unrelated text are not checked", () => {
  assert.equal(violationsIn("demo-content/poland/app.js", `import "../shared/atlas.js"; // demo-content/shared`).length, 0);
  assert.equal(violationsIn("front/web/src/lib/x.ts", `// see demo-content/README.md\nconst s = "demo-content";`).length, 0);
  assert.equal(violationsIn("docs/plans/x.md", `import "demo-content/x"`).length, 0);
});

test("the repository itself passes", async () => {
  const { execFileSync } = await import("node:child_process");
  const { findViolations } = await import("../../scripts/check-demo-content-boundary.mjs");
  const root = new URL("../../../", import.meta.url).pathname;
  const files = execFileSync("git", ["ls-files", "-z", "--cached", "--others", "--exclude-standard"], { cwd: root, maxBuffer: 64 * 1024 * 1024 })
    .toString()
    .split("\0")
    .filter((f) => /\.(m?[jt]sx?|cjs|astro|php)$|package\.json$/.test(f));
  assert.deepEqual(findViolations(root, files), []);
});
