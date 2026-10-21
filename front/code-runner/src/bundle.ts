import type { CodeFile, CodeLanguage } from "@ulams/interactive-bridge/protocol";

/**
 * Bundles an exercise (the learner's files plus the author's tests) into one script with esbuild(-wasm).
 * Every import is resolved inside the exercise's own files: there is no file system, no node_modules and
 * no network, so a bare module name, a URL or a path that leaves the exercise is a build error.
 */

export interface BundleInput {
  language: CodeLanguage;
  files: readonly CodeFile[];
  tests: readonly CodeFile[];
}

export type BundleResult = { ok: true; code: string } | { ok: false; errors: string[] };

/** The part of esbuild's API this module uses (esbuild-wasm in the browser and in node). */
export interface EsbuildLike {
  build(options: Record<string, unknown>): Promise<{ outputFiles?: Array<{ text: string }> }>;
}

const MAX_BUNDLE_BYTES = 2 * 1024 * 1024;
const ENTRY = "ulams-entry";
const NS_ENTRY = "ulams-entry";
const NS_FILE = "ulams-file";
const SCRIPT_EXTENSIONS = ["js", "mjs", "ts"];

const LOADERS: Record<string, string> = { js: "js", mjs: "js", ts: "ts", json: "json", txt: "text", md: "text" };

/** `a/b/../c.js` → `a/c.js`; null when the path leaves the root. */
export function normalisePath(path: string): string | null {
  const out: string[] = [];
  for (const part of path.split("/")) {
    if (part === "" || part === ".") continue;
    if (part === "..") {
      if (out.length === 0) return null;
      out.pop();
    } else out.push(part);
  }
  return out.join("/");
}

const dirname = (path: string): string => (path.includes("/") ? path.slice(0, path.lastIndexOf("/")) : "");

/**
 * The exercise file a relative import points at, trying the usual suffixes the way a bundler would:
 * the path itself, `.js`/`.mjs`/`.ts`/`.json`, `index.*` in a folder, and `.ts` for a `.js` specifier
 * (TypeScript's convention). Null for anything that is not a relative path, or has no such file.
 */
export function resolveImport(importer: string | null, specifier: string, paths: ReadonlySet<string>): string | null {
  if (!specifier.startsWith("./") && !specifier.startsWith("../")) return null;
  const base = normalisePath(importer === null ? specifier : `${dirname(importer)}/${specifier}`);
  if (base === null || base === "") return null;
  const candidates = [base, ...[...SCRIPT_EXTENSIONS, "json"].map((e) => `${base}.${e}`), ...SCRIPT_EXTENSIONS.map((e) => `${base}/index.${e}`)];
  if (/\.m?js$/.test(base)) candidates.push(base.replace(/\.m?js$/, ".ts"));
  return candidates.find((c) => paths.has(c)) ?? null;
}

const extension = (path: string): string => path.slice(path.lastIndexOf(".") + 1).toLowerCase();

/** The synthetic entry: it imports every test file, in order, for its side effects (the tests register themselves). */
export function entrySource(tests: readonly CodeFile[]): string {
  return tests.map((t) => `import ${JSON.stringify("./" + t.path)};`).join("\n");
}

/** `file:line:col: text` for each esbuild message, short enough to show. */
function describe(messages: Array<{ text: string; location?: { file?: string; line?: number; column?: number } | null }>): string[] {
  return messages.map((m) => {
    const where = m.location?.file ? `${m.location.file.replace(/^ulams-file:/, "")}:${m.location.line ?? 0}:${(m.location.column ?? 0) + 1}: ` : "";
    return `${where}${m.text}`.slice(0, 300);
  });
}

export async function bundleExercise(esbuild: EsbuildLike, input: BundleInput): Promise<BundleResult> {
  const contents = new Map<string, string>();
  for (const f of [...input.files, ...input.tests]) contents.set(f.path, f.content);
  const paths = new Set(contents.keys());
  const testPaths = new Set(input.tests.map((t) => t.path));
  if (input.tests.length === 0) return { ok: false, errors: ["The exercise has no tests."] };

  const plugin = {
    name: "ulams-exercise-files",
    setup(build: {
      onResolve(o: { filter: RegExp; namespace?: string }, cb: (a: { path: string; importer: string; namespace: string }) => unknown): void;
      onLoad(o: { filter: RegExp; namespace: string }, cb: (a: { path: string }) => unknown): void;
    }) {
      build.onResolve({ filter: /.*/ }, (args) => {
        if (args.namespace === "" && args.path === ENTRY) return { path: ENTRY, namespace: NS_ENTRY };
        const importer = args.namespace === NS_FILE ? args.importer.replace(/^ulams-file:/, "") : null;
        const target = resolveImport(importer, args.path, paths);
        if (target !== null) return { path: target, namespace: NS_FILE };
        const relative = args.path.startsWith("./") || args.path.startsWith("../");
        return {
          errors: [
            {
              text: relative
                ? `Cannot find "${args.path}" among the exercise's files.`
                : `Cannot import "${args.path}": an exercise can only import its own files (no packages, no URLs).`,
            },
          ],
        };
      });
      build.onLoad({ filter: /.*/, namespace: NS_ENTRY }, () => ({ contents: entrySource(input.tests), loader: "js", resolveDir: "/" }));
      build.onLoad({ filter: /.*/, namespace: NS_FILE }, (args) => {
        const loader = LOADERS[extension(args.path)] ?? "text";
        // test files may be TypeScript in a JavaScript exercise's folder only by extension: the loader follows the extension
        return { contents: contents.get(args.path) ?? "", loader: testPaths.has(args.path) && loader === "text" ? "js" : loader };
      });
    },
  };

  try {
    const result = await esbuild.build({
      entryPoints: [ENTRY],
      bundle: true,
      write: false,
      format: "iife",
      platform: "neutral",
      target: "es2020",
      outfile: "exercise.js",
      logLevel: "silent",
      legalComments: "none",
      plugins: [plugin],
    });
    const code = result.outputFiles?.[0]?.text ?? "";
    if (code === "") return { ok: false, errors: ["The build produced no code."] };
    if (code.length > MAX_BUNDLE_BYTES) return { ok: false, errors: ["The built exercise is larger than 2 MB."] };
    return { ok: true, code };
  } catch (error) {
    const failure = error as { errors?: Array<{ text: string; location?: { file?: string; line?: number; column?: number } | null }>; message?: string };
    const errors = failure.errors && failure.errors.length > 0 ? describe(failure.errors) : [String(failure.message ?? error).slice(0, 300)];
    return { ok: false, errors: errors.slice(0, 5) };
  }
}
