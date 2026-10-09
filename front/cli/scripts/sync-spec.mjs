#!/usr/bin/env node
// Copies and normalises the generated OpenAPI spec into spec/openapi.json (committed) and writes the slim
// operation list the bundled `ulams api --list` uses.
//
// Generate the source first:
//   docker compose -f api/docker-compose.yml exec api php artisan l5-swagger:generate
import { mkdirSync, readFileSync, writeFileSync, existsSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, "..");
const source = process.argv[2] ?? resolve(root, "../../api/storage/api-docs/api-docs.json");
if (!existsSync(source)) {
  console.error(`Spec not found: ${source}\nGenerate it: docker compose -f api/docker-compose.yml exec api php artisan l5-swagger:generate`);
  process.exit(1);
}

const sortKeys = (node) => {
  if (Array.isArray(node)) return node.map(sortKeys);
  if (node && typeof node === "object") {
    return Object.fromEntries(Object.keys(node).sort().map((k) => [k, sortKeys(node[k])]));
  }
  if (typeof node === "string") return node.replace(/[ \t]+$/gm, "");
  return node;
};

const spec = JSON.parse(readFileSync(source, "utf8"));
delete spec.servers;
const normalised = sortKeys(spec);
mkdirSync(resolve(root, "spec"), { recursive: true });
writeFileSync(resolve(root, "spec/openapi.json"), `${JSON.stringify(normalised, null, 1)}\n`);

const METHODS = ["get", "post", "put", "patch", "delete"];
const operations = [];
for (const [path, item] of Object.entries(normalised.paths)) {
  for (const method of METHODS) {
    const op = item[method];
    if (!op) continue;
    operations.push({ method: method.toUpperCase(), path, summary: (op.summary ?? "").trim(), tag: op.tags?.[0] ?? "" });
  }
}
operations.sort((a, b) => (a.path === b.path ? a.method.localeCompare(b.method) : a.path.localeCompare(b.path)));
mkdirSync(resolve(root, "src/generated"), { recursive: true });
writeFileSync(resolve(root, "src/generated/operations.json"), `${JSON.stringify(operations, null, 1)}\n`);
console.log(`spec/openapi.json: ${Object.keys(normalised.paths).length} paths, ${operations.length} operations`);
