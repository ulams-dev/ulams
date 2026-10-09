// Copies the shared vendor files into every unbuilt package:
//   - front/interactive-bridge/dist/interactive-bridge.js  into <package>/vendor/ of every package
//   - demo-content/shared/atlas.js                         into <package>/vendor/ of the map packages
// (gravity is built and bundles the bridge itself). check-manifests.mjs fails when a copy differs.
import { execFileSync } from "node:child_process";
import { copyFileSync, readdirSync, statSync, existsSync, mkdirSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const bridgeDir = join(root, "..", "front", "interactive-bridge");
export const ATLAS_USERS = ["poland", "ulam/lwow-map"];

execFileSync("node", ["scripts/bundle.mjs"], { cwd: bridgeDir, stdio: "inherit" });
const bridge = join(bridgeDir, "dist", "interactive-bridge.js");
const atlas = join(root, "shared", "atlas.js");

const packages = [];
const scan = (dir) => {
  for (const name of readdirSync(dir)) {
    if (["node_modules", "dist", "release", "scripts", "tests", "gravity", "shared", "fonts", "data", "posters", "vendor"].includes(name)) continue;
    const path = join(dir, name);
    if (!statSync(path).isDirectory()) continue;
    if (existsSync(join(path, "ulams-interactive.json")) || existsSync(join(path, "index.html"))) packages.push(path);
    else scan(path);
  }
};
scan(root);
for (const dir of packages) {
  mkdirSync(join(dir, "vendor"), { recursive: true });
  copyFileSync(bridge, join(dir, "vendor", "interactive-bridge.js"));
  console.log(`synced ${dir.slice(root.length + 1)}/vendor/interactive-bridge.js`);
  if (ATLAS_USERS.includes(dir.slice(root.length + 1))) {
    copyFileSync(atlas, join(dir, "vendor", "atlas.js"));
    console.log(`synced ${dir.slice(root.length + 1)}/vendor/atlas.js`);
  }
}
