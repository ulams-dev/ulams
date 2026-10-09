#!/bin/sh
# Installs the Adapt framework and its core plugins into $1 (image build only; needs network).
#   install-framework.sh <dir> <framework tag> <adapt-cli version>
set -eu
DIR="$1"
TAG="$2"
CLI_VERSION="$3"

git clone --quiet --depth 1 --branch "$TAG" https://github.com/adaptlearning/adapt_framework.git "$DIR"
cd "$DIR"
# src/core (adapt-contrib-core) at the version pinned in .gitmodules
node gitmodules.js
# runtime build dependencies only (cypress, eslint and the release tooling are dev dependencies)
npm ci --omit=dev --ignore-scripts --no-audit --no-fund
# the plugins listed in adapt.json, resolved for this framework version
npx --yes "adapt-cli@${CLI_VERSION}" install
# record what was installed (part of the corresponding source of the image)
node -e '
const fs = require("fs");
const path = require("path");
const out = { framework: require("./package.json").version, plugins: {} };
for (const type of ["core", "components", "extensions", "menu", "theme"]) {
  const base = path.join("src", type);
  if (!fs.existsSync(base)) continue;
  const dirs = type === "core" ? ["."] : fs.readdirSync(base);
  for (const d of dirs) {
    for (const f of ["package.json", "bower.json"]) {
      const p = path.join(base, d, f);
      if (fs.existsSync(p)) {
        const j = JSON.parse(fs.readFileSync(p, "utf8"));
        out.plugins[j.name] = j.version;
        break;
      }
    }
  }
}
fs.writeFileSync("ulams-adapt-versions.json", JSON.stringify(out, null, 2) + "\n");
console.log(JSON.stringify(out));
'
# the bundled example course is replaced by every build
rm -rf src/course .git
