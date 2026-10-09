import { describe, expect, it } from "vitest";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";
// @ts-expect-error plain .mjs script without types
import { buildCommands, emit } from "../../scripts/gen-commands.mjs";

const spec = JSON.parse(readFileSync(resolve(__dirname, "../fixtures/mini-spec.json"), "utf8"));

describe("generator", () => {
  const { commands, warnings } = buildCommands({ spec, overrides: { nouns: {}, operations: {} }, exclusions: {} });
  const byId = Object.fromEntries(commands.map((c: { id: string }) => [c.id, c]));

  it("names commands from method and path", () => {
    expect(Object.keys(byId).sort()).toEqual(["widgets.create", "widgets.delete", "widgets.export", "widgets.get", "widgets.list"]);
    expect(warnings).toEqual([]);
  });

  it("derives kind, idempotency, scopes, positionals and paging", () => {
    expect(byId["widgets.list"]).toMatchObject({ kind: "read", idempotent: true, paginated: true, audience: ["admin"] });
    expect(byId["widgets.create"]).toMatchObject({ kind: "write", idempotent: false, bodyMode: "json" });
    expect(byId["widgets.delete"]).toMatchObject({ kind: "destructive", positionals: ["id"] });
    expect(byId["widgets.export"]).toMatchObject({ download: true });
  });

  it("applies overrides and exclusions", () => {
    const r = buildCommands({
      spec,
      overrides: { nouns: { widgets: "gadgets" }, operations: { "GET /api/admin/widgets/{id}": { id: "gadgets.show", kind: "write" } } },
      exclusions: { "not-for-cli": ["* /api/admin/widgets/{id}/export"] },
    });
    const ids = r.commands.map((c: { id: string }) => c.id);
    expect(ids).toContain("gadgets.show");
    expect(ids).not.toContain("gadgets.export");
    expect(r.commands.find((c: { id: string }) => c.id === "gadgets.show")?.kind).toBe("write");
  });

  it("emits deterministic TypeScript with zod inputs", () => {
    const a = emit(commands);
    expect(a).toBe(emit(commands));
    expect(a).toContain('id: "widgets.create"');
    expect(a).toContain('"name": z.string().describe("Widget name")');
    expect(a).toContain('"size": z.number().int().optional()');
    expect(a).toMatchSnapshot();
  });
});
