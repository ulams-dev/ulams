import { readdirSync, readFileSync, statSync } from "node:fs";
import { dirname, join, relative } from "node:path";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";

/**
 * Every <iframe> in the learner front, the admin and the old front carries a sandbox attribute, and
 * `allow-same-origin` appears only where the player cannot work without it (ADR 0014, amended
 * 2026-10-09; the reasons are in front/sdk/src/frames.ts). A new iframe fails here until it is
 * sandboxed or listed with a reason.
 */
const ROOT = join(dirname(fileURLToPath(import.meta.url)), "..", "..", "..");
const DIRS = ["front/ui/src", "front/web/src", "front/src", "admin/src"];

/** Frames that run without a sandbox on purpose. */
const UNSANDBOXED: Record<string, string> = {
  "front/ui/src/components/PdfViewer.astro": "the browser's PDF viewer does not render in a sandboxed frame; file from the storage origin (nosniff, no script)",
  "admin/src/components/PdfEditor/index.tsx": "same: PDF preview of an uploaded file",
  "front/src/lib/sdk/react/context/index.tsx": "legacy SCORM player of tenants without a content origin (API origin, ADR 0014 consequences)",
  "front/src/lib/scorm-player/player.tsx": "legacy service-worker SCORM player (front origin), replaced by the content origin",
};

function walk(dir: string, out: string[] = []): string[] {
  for (const name of readdirSync(dir)) {
    if (name === "node_modules" || name === ".umi" || name === ".umi-production") continue;
    const path = join(dir, name);
    if (statSync(path).isDirectory()) walk(path, out);
    else if (/\.(astro|tsx|ts|jsx|html)$/.test(name) && !/\.test\./.test(name)) out.push(path);
  }
  return out;
}

interface Frame {
  file: string;
  tag: string;
}

function frames(): Frame[] {
  const found: Frame[] = [];
  for (const dir of DIRS) {
    for (const path of walk(join(ROOT, dir))) {
      const source = readFileSync(path, "utf8");
      for (const match of source.matchAll(/<iframe\b[\s\S]*?(?:\/>|><\/iframe>|>)/g)) {
        const lineStart = source.lastIndexOf("\n", match.index) + 1;
        // mentions in comments (and the commented-out previews) are not frames
        if (/^\s*(\*|\/\/|\{?\/\*)/.test(source.slice(lineStart, match.index)) || /\/\*[^*]*$|\{\/\*\s*$/.test(source.slice(lineStart, match.index))) continue;
        found.push({ file: relative(ROOT, path), tag: match[0] });
      }
    }
  }
  return found;
}

describe("iframes carry a sandbox", () => {
  const all = frames();

  it("finds the known frames", () => {
    expect(all.length).toBeGreaterThanOrEqual(10);
  });

  it("every iframe has a sandbox attribute unless it is a documented exception", () => {
    const missing = all.filter((f) => !/\bsandbox=/.test(f.tag) && !(f.file in UNSANDBOXED)).map((f) => f.file);
    expect(missing).toEqual([]);
  });

  it("no sandboxed iframe allows top navigation or scripted top access", () => {
    const loose = all.filter((f) => /allow-top-navigation/.test(f.tag)).map((f) => f.file);
    expect(loose).toEqual([]);
  });

  it("the content-origin players keep allow-same-origin only through the documented constants", () => {
    const withLiteral = all.filter((f) => /sandbox="[^"]*allow-same-origin/.test(f.tag)).map((f) => f.file).sort();
    // admin and the old front cannot import the sdk constants; their literals sit next to a comment
    expect(withLiteral).toEqual([
      "admin/src/components/H5P/editor.tsx",
      "admin/src/components/H5P/player.tsx",
      "admin/src/pages/LiaScript/editor.tsx",
      "front/src/components/Courses/Course/Players/LtiPlayer.tsx",
      "front/src/components/Courses/Course/Players/ScormPlayer.tsx",
      "front/src/lib/components/components/players/H5Player/H5Player.tsx",
    ]);
    for (const f of all.filter((f) => withLiteral.includes(f.file))) {
      const source = readFileSync(join(ROOT, f.file), "utf8");
      expect(source, f.file).toMatch(/allow-same-origin (is required|only keeps|is required:)|allow-same-origin is required|allow-same-origin only keeps/);
    }
  });

  it("the unsandboxed exceptions still exist and still have an iframe", () => {
    for (const file of Object.keys(UNSANDBOXED)) {
      expect(all.some((f) => f.file === file), file).toBe(true);
    }
  });
});
