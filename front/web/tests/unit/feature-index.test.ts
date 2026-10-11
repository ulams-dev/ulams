// @vitest-environment node
import { readdirSync, statSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";
import type { UiNode } from "@ulams/ui/render-core";
import { landingDocs, localizedDocs } from "../../src/lib/docs.ts";

const packagesDir = fileURLToPath(new URL("../../../../api/packages/", import.meta.url));
const packages = readdirSync(packagesDir).filter((d) => statSync(`${packagesDir}${d}`).isDirectory());

/** Packages that are not a product feature, with the reason. Anything else must appear in the feature index. */
const EXCLUDED: Record<string, string> = {
  core: "base classes every other package needs",
};

interface Group {
  id: string;
  title: string;
  items?: Array<{ title: string; text?: string; packages?: string[] }>;
  tags?: string[];
}
const indexOf = (doc: UiNode): UiNode => {
  const find = (n: UiNode): UiNode | undefined => (n.component === "FeatureIndex" ? n : n.children?.map(find).find(Boolean));
  return find(doc)!;
};
const groupsOf = (doc: UiNode) => (indexOf(doc).props as { groups: Group[] }).groups;
const en = landingDocs.platform!;

describe("the landing feature index", () => {
  it("lists every package under api/packages, or excludes it with a reason", () => {
    const referenced = new Set(groupsOf(en).flatMap((g) => (g.items ?? []).flatMap((i) => i.packages ?? [])));
    expect(packages.filter((p) => !referenced.has(p) && !(p in EXCLUDED))).toEqual([]);
    expect(Object.keys(EXCLUDED).filter((p) => referenced.has(p) || !packages.includes(p))).toEqual([]);
  });

  it("names only packages that exist", () => {
    const names = groupsOf(en).flatMap((g) => (g.items ?? []).flatMap((i) => i.packages ?? []));
    expect(names.filter((n) => !packages.includes(n))).toEqual([]);
  });

  it("keeps ids and lengths identical in Polish and Chinese", () => {
    for (const lang of ["pl", "zh"] as const) {
      const other = groupsOf(localizedDocs.platform![lang]!);
      expect(other.map((g) => [g.id, g.items?.length, g.tags?.length]), lang).toEqual(groupsOf(en).map((g) => [g.id, g.items?.length, g.tags?.length]));
    }
  });

  it("lists the H5P content types published on h5p.org", () => {
    const tags = groupsOf(en).find((g) => g.id === "h5p")?.tags ?? [];
    expect(tags).toHaveLength(54);
    for (const name of ["Accordion", "Interactive Video", "Course Presentation", "Branching Scenario", "Crossword"]) expect(tags).toContain(name);
  });
});
