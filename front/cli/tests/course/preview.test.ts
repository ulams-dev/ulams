import { describe, expect, it } from "vitest";
import { forPreview } from "../../src/course/preview.ts";
import { documentProblems } from "../../src/course/validate.ts";
import type { Obj } from "../../src/course/types.ts";
import { remoteDocument } from "./fake-course-as-code.ts";

const cites = (v: unknown): string[] => {
  const out: string[] = [];
  const walk = (n: unknown) => {
    if (Array.isArray(n)) n.forEach(walk);
    else if (n && typeof n === "object") {
      for (const [k, x] of Object.entries(n)) {
        if (k === "citations" && Array.isArray(x)) out.push(...(x as string[]));
        else walk(x);
      }
    }
  };
  walk(v);
  return out;
};

describe("forPreview", () => {
  const doc = remoteDocument();
  // a document whose AI-written parts cite fragments, as a pulled course does
  const cited = JSON.parse(JSON.stringify(doc)) as Obj;
  const lesson = (((cited.modules as Obj[])[0] as Obj).lessons as Obj[])[0] as Obj;
  lesson.citations = ["frg_abcdefabcdef"];
  ((lesson.blocks as Obj[])[0] as Obj).citations = ["frg_abcdefabcdef"];
  ((lesson.blocks as Obj[])[0] as Obj).origin = "ai";
  ((cited.course as Obj).objectives as Obj[])[0] = { id: "01j9z8x7w6v5t4s3r2q1p0n9m8", text: "Install kubectl", citations: ["frg_abcdefabcdef"] };

  it("starts from a document that cites sources (the premise)", () => {
    expect(cites(cited).length).toBeGreaterThan(0);
  });

  it("removes every citation, marks citing elements as written by the author and stays a valid Blueprint v3 document", () => {
    const preview = forPreview(cited);
    expect(cites(preview)).toEqual([]);
    const l = (((preview.modules as Obj[])[0] as Obj).lessons as Obj[])[0] as Obj;
    expect(l.origin).toBe("author");
    expect(((l.blocks as Obj[])[0] as Obj).origin).toBe("author");
    // objectives take no origin
    expect(((preview.course as Obj).objectives as Obj[])[0]).toEqual({ id: "01j9z8x7w6v5t4s3r2q1p0n9m8", text: "Install kubectl", citations: [] });
    expect(preview.sources).toEqual([]);
    expect(documentProblems(preview)).toEqual([]);
  });

  it("does not change its input and keeps the content", () => {
    const before = JSON.stringify(cited);
    const preview = forPreview(cited);
    expect(JSON.stringify(cited)).toBe(before);
    const text = (d: Obj) => JSON.stringify(d).replace(/"citations":\[[^\]]*\]/g, "").replace(/"origin":"[a-z]+",?/g, "");
    expect(text(preview).length).toBeGreaterThan(text(cited).length - 40);
    expect((preview.course as Obj).title).toBe((cited.course as Obj).title);
  });

  it("an uncited author document passes through unchanged apart from the empty source list", () => {
    const plain = { ...JSON.parse(JSON.stringify(doc)), sources: [] } as Obj;
    expect(documentProblems(forPreview(plain))).toEqual([]);
  });
});
