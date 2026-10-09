import { describe, expect, it } from "vitest";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { getRegistry } from "../../src/registry/index.ts";

const spec = JSON.parse(readFileSync(resolve(__dirname, "../../spec/openapi.json"), "utf8")) as { paths: Record<string, Record<string, { parameters?: Array<{ name: string; in: string; required?: boolean }> }>> };
const commands = getRegistry();

describe("contract against spec/openapi.json", () => {
  it("every endpoint a command lists exists in the spec", () => {
    const missing: string[] = [];
    for (const c of commands) {
      for (const e of c.endpoints) {
        const [method, path] = e.split(" ") as [string, string];
        if (!spec.paths[path]?.[method.toLowerCase()]) missing.push(`${c.id}: ${e}`);
      }
    }
    expect(missing).toEqual([]);
  });

  it("generated commands require exactly the path parameters of their route", () => {
    const wrong: string[] = [];
    for (const c of commands.filter((x) => x.request)) {
      const req = c.request!;
      const inPath = [...req.path.matchAll(/\{(\w+)\}/g)].map((m) => m[1]);
      if (JSON.stringify(inPath) !== JSON.stringify(req.pathParams)) wrong.push(c.id);
      const shape = Object.keys((c.input as unknown as { shape: Record<string, unknown> }).shape);
      for (const p of req.pathParams) if (!shape.includes(p)) wrong.push(`${c.id} lacks ${p}`);
      const op = spec.paths[req.path]?.[req.method.toLowerCase()];
      for (const q of op?.parameters ?? []) if (q.in === "query" && !shape.includes(q.name) && !req.pathParams.includes(q.name)) wrong.push(`${c.id} lacks query ${q.name}`);
    }
    expect(wrong).toEqual([]);
  });

  it("command ids are unique and every command has an example", () => {
    const ids = commands.map((c) => c.id);
    expect(new Set(ids).size).toBe(ids.length);
    expect(commands.filter((c) => c.examples.length === 0).map((c) => c.id)).toEqual([]);
  });

  it("destructive commands are exactly the DELETE operations plus the curated ones", () => {
    const deletes = commands.filter((c) => c.request?.method === "DELETE").map((c) => c.kind);
    expect(new Set(deletes)).toEqual(new Set(["destructive"]));
  });
});
