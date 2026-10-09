// Copies front/interactive-bridge/dist/interactive-bridge.js into every package's vendor/ folder.
import { execFileSync } from "node:child_process";
import { copyFileSync, readdirSync, statSync, existsSync, mkdirSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const bridgeDir = join(root, "..", "front", "interactive-bridge");
execFileSync("node", ["scripts/bundle.mjs"], { cwd: bridgeDir, stdio: "inherit" });
const dist = join(bridgeDir, "dist", "interactive-bridge.js");

// A package folder holds a ulams-interactive.json and is not built (gravity bundles the bridge itself).
const packages = [];
const scan = (dir) => {
  for (const name of readdirSync(dir)) {
    if (["node_modules", "dist", "release", "scripts", "tests", "gravity"].includes(name)) continue;
    const path = join(dir, name);
    if (!statSync(path).isDirectory()) continue;
    if (existsSync(join(path, "ulams-interactive.json"))) packages.push(path);
    else scan(path);
  }
};
scan(root);
for (const dir of packages) {
  mkdirSync(join(dir, "vendor"), { recursive: true });
  copyFileSync(dist, join(dir, "vendor", "interactive-bridge.js"));
  console.log(`synced ${dir.slice(root.length + 1)}/vendor/interactive-bridge.js`);
}
