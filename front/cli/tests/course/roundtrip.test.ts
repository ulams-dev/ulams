import { describe, expect, it } from "vitest";
import { folderFromBlueprint, normalizeDocument } from "../../src/course/from-blueprint.ts";
import { formatFiles } from "../../src/course/fmt.ts";
import { mintId } from "../../src/course/ids.ts";
import { loadCourse } from "../../src/course/load.ts";
import { validateFiles } from "../../src/course/validate.ts";
import type { AssetInfo, Files, Obj } from "../../src/course/types.ts";
import { documentFor } from "./gen.ts";

/* export -> files -> parse -> the same blueprint (the main property of the format), over 200 seeded random v3 documents. */

const assets = (path: string): AssetInfo | null => (path.endsWith(".zip") ? { bytes: 0, sha256: "a".repeat(64) } : null);
// The generated documents name an asset with fixed hash and size; the lookup answers with the same values.
const assetsFor = (doc: Obj) => {
  const known = new Map<string, AssetInfo>();
  for (const m of doc.modules as Obj[]) for (const l of m.lessons as Obj[]) {
    const a = l.asset as { path: string; sha256: string; bytes: number } | undefined;
    if (a) known.set(`assets/${a.path}`, { bytes: a.bytes, sha256: a.sha256 });
  }
  return (path: string) => known.get(path) ?? assets(path);
};

const sorted = (files: Files) => [...files.entries()].sort(([a], [b]) => a.localeCompare(b));
const SEEDS = Array.from({ length: 200 }, (_, i) => i + 1);

describe("blueprint -> files -> blueprint", () => {
  it.each(SEEDS)("seed %i round-trips", (seed) => {
    const doc = documentFor(seed);
    const files = folderFromBlueprint(doc);
    const loaded = loadCourse(files, assetsFor(doc));
    const errors = loaded.issues.filter((i) => i.severity === "error");
    expect(errors, JSON.stringify(errors, null, 1)).toEqual([]);
    expect(loaded.doc).toEqual(normalizeDocument(doc));
  });

  it("the generator makes documents that pass the whole validator (Blueprint v3 included)", () => {
    for (const seed of SEEDS.slice(0, 60)) {
      const doc = documentFor(seed);
      const { issues } = validateFiles(folderFromBlueprint(doc), assetsFor(doc));
      const errors = issues.filter((i) => i.severity === "error");
      expect(errors, `seed ${seed}: ${JSON.stringify(errors, null, 1)}`).toEqual([]);
    }
  });

  it("files written from a document are already formatted (fmt changes nothing)", () => {
    for (const seed of SEEDS.slice(0, 80)) {
      const doc = documentFor(seed);
      const files = folderFromBlueprint(doc);
      const { files: formatted, plan } = formatFiles(files, { mint: mintId, assets: assetsFor(doc) });
      expect(plan.blocking).toEqual([]);
      expect(sorted(formatted), `seed ${seed}`).toEqual(sorted(files));
    }
  });

  it("writing is deterministic", () => {
    const doc = documentFor(7);
    expect(sorted(folderFromBlueprint(doc))).toEqual(sorted(folderFromBlueprint(structuredClone(doc))));
  });
});
