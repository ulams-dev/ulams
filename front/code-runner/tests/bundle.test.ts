import { describe, expect, it } from "vitest";
import { bundleExercise, entrySource, normalisePath, resolveImport } from "../src/bundle.ts";
import { esbuild, file } from "./helpers.ts";

describe("normalisePath", () => {
  it("collapses dots and refuses to leave the root", () => {
    expect(normalisePath("a/b/../c.js")).toBe("a/c.js");
    expect(normalisePath("./a//b.js")).toBe("a/b.js");
    expect(normalisePath("../a.js")).toBeNull();
    expect(normalisePath("a/../../b.js")).toBeNull();
  });
});

describe("resolveImport", () => {
  const paths = new Set(["sum.js", "lib/index.js", "lib/math.ts", "data.json", "util/index.ts", "a/b/c.mjs"]);
  it("finds a relative import with the usual suffixes", () => {
    expect(resolveImport("sum.test.js", "./sum.js", paths)).toBe("sum.js");
    expect(resolveImport("sum.test.js", "./sum", paths)).toBe("sum.js");
    expect(resolveImport("sum.test.js", "./lib", paths)).toBe("lib/index.js");
    expect(resolveImport("sum.test.js", "./util", paths)).toBe("util/index.ts");
    expect(resolveImport("sum.test.js", "./data.json", paths)).toBe("data.json");
    expect(resolveImport("lib/index.js", "./math.js", paths)).toBe("lib/math.ts");
    expect(resolveImport("a/b/x.js", "./c.mjs", paths)).toBe("a/b/c.mjs");
    expect(resolveImport("lib/index.js", "../sum.js", paths)).toBe("sum.js");
  });
  it("never resolves a bare name, a URL, an absolute path or a path outside the exercise", () => {
    for (const spec of ["lodash", "node:fs", "https://evil.example/x.js", "//evil.example/x.js", "/etc/passwd", "../../etc/passwd", "./missing.js"]) {
      expect(resolveImport("sum.test.js", spec, paths)).toBeNull();
    }
  });
});

describe("entrySource", () => {
  it("imports each test file for its side effects", () => {
    expect(entrySource([file("a.test.js", ""), file("b/c.test.js", "")])).toBe('import "./a.test.js";\nimport "./b/c.test.js";');
  });
});

describe("bundleExercise (real esbuild-wasm)", () => {
  it("bundles JavaScript files and their tests into one script", async () => {
    const result = await bundleExercise(esbuild, {
      language: "javascript",
      files: [file("sum.js", "export const sum = (a, b) => a + b;")],
      tests: [file("sum.test.js", "import { sum } from './sum.js';\ntest('adds', () => expect(sum(1, 2)).toBe(3));")],
    });
    expect(result.ok).toBe(true);
    if (result.ok) {
      expect(result.code).toContain("adds");
      expect(result.code).not.toMatch(/\bimport\s*\(/);
    }
  });

  it("strips TypeScript types, including in a folder with index files", async () => {
    const result = await bundleExercise(esbuild, {
      language: "typescript",
      files: [file("math/index.ts", "export const double = (n: number): number => n * 2;\nexport type N = number;")],
      tests: [file("math.test.ts", "import { double, type N } from './math';\nconst x: N = 4;\ntest('doubles', () => expect(double(x)).toBe(8));")],
    });
    expect(result.ok).toBe(true);
    if (result.ok) expect(result.code).not.toContain(": number");
  });

  it("reports a syntax error with the file and line", async () => {
    const result = await bundleExercise(esbuild, {
      language: "javascript",
      files: [file("sum.js", "export const sum = (a, b) => {\n  return a +;\n};")],
      tests: [file("sum.test.js", "import './sum.js';")],
    });
    expect(result.ok).toBe(false);
    if (!result.ok) expect(result.errors[0]).toMatch(/^sum\.js:2:\d+: /);
  });

  it.each([
    ["a package", "lodash"],
    ["a node module", "node:fs"],
    ["a URL", "https://evil.example/x.js"],
    ["a protocol-relative URL", "//evil.example/x.js"],
    ["a path outside the exercise", "../../etc/passwd"],
    ["an absolute path", "/etc/passwd"],
    ["a file that is not there", "./missing.js"],
  ])("refuses to import %s", async (_label, specifier) => {
    const result = await bundleExercise(esbuild, {
      language: "javascript",
      files: [file("sum.js", `import x from ${JSON.stringify(specifier)};\nexport default x;`)],
      tests: [file("sum.test.js", "import './sum.js';")],
    });
    expect(result.ok).toBe(false);
    if (!result.ok) expect(result.errors.join(" ")).toMatch(/Cannot (import|find)/);
  });

  it("refuses a dynamic import of a URL as well", async () => {
    const result = await bundleExercise(esbuild, {
      language: "javascript",
      files: [file("sum.js", "export const load = () => import('https://evil.example/x.js');")],
      tests: [file("sum.test.js", "import './sum.js';")],
    });
    expect(result.ok).toBe(false);
  });

  it("needs at least one test", async () => {
    const result = await bundleExercise(esbuild, { language: "javascript", files: [file("a.js", "")], tests: [] });
    expect(result).toEqual({ ok: false, errors: ["The exercise has no tests."] });
  });
});
