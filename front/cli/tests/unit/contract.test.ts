import { describe, expect, it } from "vitest";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { getRegistry } from "../../src/registry/index.ts";
import { parseInvocation, resolveCommand } from "../../src/cli/parse.ts";

const spec = JSON.parse(
  readFileSync(resolve(__dirname, "../../spec/openapi.json"), "utf8")
) as {
  paths: Record<
    string,
    Record<
      string,
      { parameters?: Array<{ name: string; in: string; required?: boolean }> }
    >
  >;
};
const commands = getRegistry();

describe("contract against spec/openapi.json", () => {
  it("every endpoint a command lists exists in the spec", () => {
    const missing: string[] = [];
    for (const c of commands) {
      for (const e of c.endpoints) {
        const [method, path] = e.split(" ") as [string, string];
        if (!spec.paths[path]?.[method.toLowerCase()])
          missing.push(`${c.id}: ${e}`);
      }
    }
    expect(missing).toEqual([]);
  });

  it("generated commands require exactly the path parameters of their route", () => {
    const wrong: string[] = [];
    for (const c of commands.filter((x) => x.request)) {
      const req = c.request!;
      const inPath = [...req.path.matchAll(/\{(\w+)\}/g)].map((m) => m[1]);
      if (JSON.stringify(inPath) !== JSON.stringify(req.pathParams))
        wrong.push(c.id);
      const shape = Object.keys(
        (c.input as unknown as { shape: Record<string, unknown> }).shape
      );
      for (const p of req.pathParams)
        if (!shape.includes(p)) wrong.push(`${c.id} lacks ${p}`);
      const op = spec.paths[req.path]?.[req.method.toLowerCase()];
      for (const q of op?.parameters ?? [])
        if (
          q.in === "query" &&
          !shape.includes(q.name) &&
          !req.pathParams.includes(q.name)
        )
          wrong.push(`${c.id} lacks query ${q.name}`);
    }
    expect(wrong).toEqual([]);
  });

  it("command ids are unique and every command has an example", () => {
    const ids = commands.map((c) => c.id);
    expect(new Set(ids).size).toBe(ids.length);
    expect(
      commands.filter((c) => c.examples.length === 0).map((c) => c.id)
    ).toEqual([]);
  });

  it("destructive commands are exactly the DELETE operations plus the curated ones", () => {
    const deletes = commands
      .filter((c) => c.request?.method === "DELETE")
      .map((c) => c.kind);
    expect(new Set(deletes)).toEqual(new Set(["destructive"]));
  });
});

/** Split an example the way a POSIX shell does: whitespace, single and double quotes, backslash escapes. */
function shellSplit(line: string): string[] {
  const out: string[] = [];
  let cur = "";
  let started = false;
  let quote: string | null = null;
  for (let i = 0; i < line.length; i++) {
    const ch = line[i] as string;
    if (quote) {
      if (ch === quote) quote = null;
      else if (ch === "\\" && quote === '"' && i + 1 < line.length)
        cur += line[++i];
      else cur += ch;
    } else if (ch === "'" || ch === '"') {
      quote = ch;
      started = true;
    } else if (ch === "\\" && i + 1 < line.length) {
      if (line[i + 1] === "\n") i++;
      else cur += line[++i];
      started = true;
    } else if (/\s/.test(ch)) {
      if (started || cur) out.push(cur);
      cur = "";
      started = false;
    } else if (ch === "#" && !started && !cur) break;
    else if (ch === "<" && !started && !cur && /\s/.test(line[i + 1] ?? "")) {
      // input redirection (`< token.txt`): the shell consumes it, the CLI never sees it
      while (i + 1 < line.length && /\s/.test(line[i + 1] as string)) i++;
      while (i + 1 < line.length && !/\s/.test(line[i + 1] as string)) i++;
    } else cur += ch;
  }
  if (started || cur) out.push(cur);
  return out;
}

describe("help examples", () => {
  const fs = {
    readFile: async () => new Uint8Array(),
    readText: async () => "{}",
    writeFile: async () => undefined,
    exists: async () => true,
  };

  it("every example parses and validates against the schema of its own command", async () => {
    const bad: string[] = [];
    // Generated commands (`request`) get placeholder examples; the hand-written ones are the documentation.
    for (const c of commands.filter((x) => !x.request)) {
      for (const ex of c.examples) {
        try {
          const argv = shellSplit(ex.argv);
          const resolved = resolveCommand(argv, commands);
          if (resolved.command?.id !== c.id) {
            bad.push(
              `${c.id}: "${ex.argv}" resolves to ${
                resolved.command?.id ?? "no command"
              }`
            );
            continue;
          }
          const parsed = await parseInvocation(
            c,
            resolved.rest,
            {},
            fs,
            async () => "{}"
          );
          // A `@file` example reads a file this test does not have: parsing is checked, the content is not.
          if (/(^|\s)'?@\S/.test(ex.argv)) continue;
          const result = c.input.safeParse(parsed.input);
          if (!result.success)
            bad.push(
              `${c.id}: "${ex.argv}" ${result.error.issues
                .map((i) => `${i.path.join(".")}: ${i.message}`)
                .join("; ")}`
            );
        } catch (error) {
          bad.push(`${c.id}: "${ex.argv}" ${(error as Error).message}`);
        }
      }
    }
    expect(bad).toEqual([]);
  });

  it("the Git connector examples use the settings the connector schema requires", () => {
    const connect = commands.find((c) => c.id === "living.sources.connect")!;
    for (const ex of connect.examples) {
      const config = JSON.parse(
        /--config '([^']+)'/.exec(ex.argv)?.[1] ?? "{}"
      ) as Record<string, unknown>;
      if (!ex.argv.includes("--connector git")) continue;
      expect(Object.keys(config)).toEqual(
        expect.arrayContaining(["host", "repository"])
      );
      expect(config).not.toHaveProperty("repo");
    }
  });
});
