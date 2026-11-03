// Writes public/openapi.json for the API browser (/developers/api-browser/).
//
// Source, first that works:
//   1. DOCS_OPENAPI=<path to an OpenAPI json file>
//   2. `php api/scripts/openapi.php` (needs `composer install --no-scripts` in api/; this is what CI does)
//   3. the local API container: `docker exec api-api-1 php scripts/openapi.php`
// In CI (CI=true) a missing spec fails the build; locally the page shows a note instead.
import { copyFileSync, existsSync, mkdirSync, readFileSync, statSync } from "node:fs";
import { execFileSync } from "node:child_process";
import { dirname, resolve } from "node:path";
import { ROOT } from "./lib.mjs";

const out = resolve(import.meta.dirname, "../public/openapi.json");
mkdirSync(dirname(out), { recursive: true });

const attempts = [
  () => {
    const file = process.env.DOCS_OPENAPI;
    if (!file) throw new Error("DOCS_OPENAPI is not set");
    copyFileSync(file, out);
  },
  () => {
    if (!existsSync(resolve(ROOT, "api/vendor/autoload.php"))) throw new Error("api/vendor is not installed");
    execFileSync("php", ["scripts/openapi.php", out], { cwd: resolve(ROOT, "api"), stdio: ["ignore", "ignore", "inherit"] });
  },
  () => {
    execFileSync("docker", ["exec", "api-api-1", "php", "-d", "error_reporting=0", "scripts/openapi.php", "/tmp/ulams-openapi.json"], { cwd: ROOT, stdio: "ignore" });
    execFileSync("docker", ["cp", "api-api-1:/tmp/ulams-openapi.json", out], { cwd: ROOT, stdio: "ignore" });
  },
];

const errors = [];
for (const attempt of attempts) {
  try {
    attempt();
    const spec = JSON.parse(readFileSync(out, "utf8"));
    const ops = Object.values(spec.paths ?? {}).reduce((n, p) => n + Object.keys(p).filter((m) => ["get", "post", "put", "patch", "delete"].includes(m)).length, 0);
    if (!ops) throw new Error("the spec has no operations");
    console.log(`openapi: ${Object.keys(spec.paths).length} paths, ${ops} operations (${(statSync(out).size / 1e6).toFixed(1)} MB)`);
    process.exit(0);
  } catch (e) {
    errors.push(e.message.split("\n")[0]);
  }
}
if (process.env.CI) {
  console.error(`openapi: no spec could be built:\n  ${errors.join("\n  ")}`);
  process.exit(1);
}
console.warn(`openapi: skipped (${errors.join("; ")}). The API browser page will say the spec is missing.`);
