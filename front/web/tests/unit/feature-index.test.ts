import { readdirSync, statSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";
import { validateDocument, type UiNode } from "@ulams/ui/render-core";
import { comparisonDocFor } from "../../src/lib/docs.ts";
import { comparisonModel } from "../../src/lib/comparison.ts";

interface Group { id: string; audience: string; items: Array<{ title: string; text: string; packages?: string[] }>; tags?: string[]; }
interface Index { audiences: Array<{ id: string }>; groups: Group[]; }
const index = (doc: UiNode): Index => doc.children!.find((n) => n.component === "Main")!.children!.find((n) => n.component === "FeatureIndex")!.props as unknown as Index;
const en = index(comparisonDocFor("en")!);

describe("comparison stakeholder feature catalogue", () => {
  it("restores all 102 descriptions and 54 H5P content types", () => {
    const items = en.groups.flatMap((g) => g.items);
    expect(items).toHaveLength(102);
    expect(items.every((item) => item.text.length > 0)).toBe(true);
    expect(en.groups.find((g) => g.id === "h5p")!.tags).toHaveLength(54);
  });
  it("assigns every category to one of six stakeholders", () => {
    expect(en.audiences.map((a) => a.id)).toEqual(["learner", "author", "owner", "sales", "admin", "engineering"]);
    expect(new Set(en.groups.map((g) => g.id)).size).toBe(14);
    expect(en.groups.every((g) => en.audiences.some((a) => a.id === g.audience))).toBe(true);
  });
  it("covers every product package and links only to existing packages", () => {
    const dir = fileURLToPath(new URL("../../../../api/packages/", import.meta.url));
    const packages = readdirSync(dir).filter((name) => statSync(`${dir}${name}`).isDirectory());
    const refs = new Set(en.groups.flatMap((g) => g.items.flatMap((item) => item.packages ?? [])));
    expect(packages.filter((name) => name !== "core" && !refs.has(name))).toEqual([]);
    expect([...refs].filter((name) => !packages.includes(name))).toEqual([]);
  });
  it("keeps categories, stakeholders and feature counts parallel in all languages", () => {
    for (const lang of ["pl", "zh"] as const) {
      const other = index(comparisonDocFor(lang)!);
      expect(other.groups.map((g) => [g.id, g.audience, g.items.length, g.tags?.length])).toEqual(en.groups.map((g) => [g.id, g.audience, g.items.length, g.tags?.length]));
      expect(other.audiences.map((a) => a.id)).toEqual(en.audiences.map((a) => a.id));
    }
  });
  it("validates the complete page catalogue for all languages", () => {
    for (const lang of ["en", "pl", "zh"] as const) expect(validateDocument(comparisonDocFor(lang)!, { comparison: comparisonModel(undefined, lang) })).toEqual([]);
  });
});
