/* eslint-env node */
// Fails when src/ (including src/lib, which eslint ignores) imports GPL H5P
// code. H5P/Lumi runs only in the separate api/h5p service (iframe + postMessage).
const fs = require("fs");
const path = require("path");

const pattern = /(?:from\s+|import\s*\(\s*|require\s*\(\s*)["'](@lumieducation\/[^"']*|@escolalms\/h5p-react)["']/;
const hits = [];
const walk = (dir) => {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      if (entry.name !== "node_modules") walk(p);
    } else if (/\.(c|m)?(t|j)sx?$/.test(entry.name)) {
      fs.readFileSync(p, "utf8")
        .split("\n")
        .forEach((line, i) => {
          if (pattern.test(line)) hits.push(`${p}:${i + 1}: ${line.trim()}`);
        });
    }
  }
};
walk(path.resolve(__dirname, "..", "src"));
if (hits.length) {
  console.error(hits.join("\n"));
  console.error("GPL H5P code must stay in api/h5p; use the H5PFrame iframe wrapper.");
  process.exit(1);
}
