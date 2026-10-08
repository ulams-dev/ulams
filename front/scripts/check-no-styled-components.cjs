/* eslint-env node */
// Fails when front/src (including src/lib, which eslint ignores) or admin/src imports
// styled-components. Both apps style with CSS Modules and --ulams-* custom properties
// (front/src/lib/components/theme/README.md). styled-components may still sit in
// node_modules as a transitive dependency of @umijs/plugins, so a stray import would resolve.
const fs = require("fs");
const path = require("path");

const pattern =
  /(?:from\s+|import\s*\(\s*|require\s*\(\s*|import\s+)["'](styled-components(?:\/[^"']*)?|babel-plugin-styled-components)["']/;
const root = path.resolve(__dirname, "..", "..");
const dirs = [path.join(root, "front", "src"), path.join(root, "admin", "src")];
// Legacy helpers nothing imports any more; delete the folder and this entry together.
const skip = new Set([path.join(root, "front", "src", "style")]);

const hits = [];
const walk = (dir) => {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      if (entry.name !== "node_modules" && !entry.name.startsWith(".") && !skip.has(p)) walk(p);
    } else if (/\.(c|m)?(t|j)sx?$/.test(entry.name)) {
      fs.readFileSync(p, "utf8")
        .split("\n")
        .forEach((line, i) => {
          if (pattern.test(line)) hits.push(`${path.relative(root, p)}:${i + 1}: ${line.trim()}`);
        });
    }
  }
};
dirs.filter((d) => fs.existsSync(d)).forEach(walk);
if (hits.length) {
  console.error(hits.join("\n"));
  console.error(
    "styled-components was removed; use a CSS Module and var(--ulams-*) (front/src/lib/components/theme/README.md)."
  );
  process.exit(1);
}
