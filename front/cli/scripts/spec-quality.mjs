#!/usr/bin/env node
// OpenAPI quality gate (Phase 7.3, "complete published OpenAPI"): every operation of spec/openapi.json has a
// readable, unique operationId and a typed 2xx response (a schema for the JSON body), except the operations
// listed with a reason in spec/untyped-responses.yaml (redirects, downloads, HTML pages, event streams) and
// answers with status 204. Stale allowlist entries fail too. Exits 1 on problems.
import { readFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { parse as parseYaml } from "yaml";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const METHODS = ["get", "post", "put", "patch", "delete"];
const REASONS = ["redirect", "file-download", "event-stream", "html-page", "javascript-file", "font-file", "binary-pdf"];
const ID = /^[a-z0-9-]+(\.[a-z0-9-]+)+$/;

const spec = JSON.parse(readFileSync(resolve(root, "spec/openapi.json"), "utf8"));
const allow = parseYaml(readFileSync(resolve(root, "spec/untyped-responses.yaml"), "utf8")) ?? {};

const problems = [];
const seen = new Map();
const untyped = new Set();
let operations = 0;
for (const [path, item] of Object.entries(spec.paths)) {
  for (const method of METHODS) {
    const op = item[method];
    if (!op) continue;
    operations++;
    const key = `${method.toUpperCase()} ${path}`;
    const id = op.operationId;
    if (!id || !ID.test(id)) problems.push(`${key}: operationId "${id ?? ""}" is missing or not readable (lowercase dotted words, for example admin.courses.by-id.get)`);
    else if (seen.has(id)) problems.push(`${key}: operationId "${id}" is also used by ${seen.get(id)}`);
    else seen.set(id, key);

    const successes = Object.entries(op.responses ?? {}).filter(([code]) => code.startsWith("2"));
    if (successes.some(([code]) => code === "204")) continue;
    const typed = successes.some(([, r]) => Object.values(r.content ?? {}).some((c) => c.schema));
    if (!typed) untyped.add(key);
  }
}
for (const key of untyped) {
  if (!(key in allow)) problems.push(`${key}: no typed 2xx response (add a JsonContent schema, or list it in spec/untyped-responses.yaml with a reason if the answer is not JSON)`);
}
for (const [key, reason] of Object.entries(allow)) {
  if (!REASONS.includes(reason)) problems.push(`untyped-responses.yaml: "${key}" has an unknown reason "${reason}" (${REASONS.join(", ")})`);
  if (!untyped.has(key)) problems.push(`untyped-responses.yaml: "${key}" is not an untyped operation any more: remove it`);
}

console.log(`spec quality: ${operations} operations, ${operations - untyped.size} typed, ${untyped.size} not JSON (allowlisted), ${seen.size} operationIds`);
if (problems.length > 0) {
  for (const p of problems.slice(0, 50)) console.error(`- ${p}`);
  if (problems.length > 50) console.error(`... and ${problems.length - 50} more`);
  process.exit(1);
}
