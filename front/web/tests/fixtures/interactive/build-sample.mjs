// Builds the sample interactive package (a 30-line stepped page that uses the bridge) as a zip:
//   node tests/fixtures/interactive/build-sample.mjs [out.zip]
// Upload it in the admin (Courses, Interactive) or with
//   ulams topics create-interactive --lesson <id> --title "Sample" --file out.zip --json
import { execFileSync } from "node:child_process";
import { cpSync, mkdtempSync, mkdirSync, rmSync } from "node:fs";
import { tmpdir } from "node:os";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const here = dirname(fileURLToPath(import.meta.url));
const out = resolve(process.argv[2] ?? "sample-interactive.zip");
const bridge = resolve(here, "../../../../interactive-bridge");

execFileSync("node", ["scripts/bundle.mjs"], { cwd: bridge, stdio: "inherit" });
const work = mkdtempSync(join(tmpdir(), "ulams-sample-"));
cpSync(join(here, "minimal"), work, { recursive: true });
rmSync(join(work, "silent.html")); // only the e2e spec uses it
mkdirSync(join(work, "vendor"));
cpSync(join(bridge, "dist", "interactive-bridge.js"), join(work, "vendor", "interactive-bridge.js"));
rmSync(out, { force: true });
execFileSync("zip", ["-qr", out, "."], { cwd: work });
rmSync(work, { recursive: true, force: true });
console.log(`wrote ${out}`);
