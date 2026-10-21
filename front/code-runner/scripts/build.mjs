// Builds the browser runner of code exercises (ADR 0170) into the API package that serves it:
//   api/packages/code-exercises/resources/runtime/code-runner/<version>/
//     runner.html, exec.html, runner.js, exec.js, esbuild.js   committed (built from src/ and esbuild-wasm)
//     esbuild.wasm                                            not committed (14 MB): copied here for local use,
//                                                              fetched at image build by bin/fetch-esbuild.sh
// `--check` writes nothing and exits 1 when a committed file differs from a fresh build.
import { build } from "esbuild";
import { copyFileSync, existsSync, mkdirSync, readFileSync, writeFileSync } from "node:fs";
import { createRequire } from "node:module";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const check = process.argv.includes("--check");
const VERSION = 1;
const out = join(root, "../../api/packages/code-exercises/resources/runtime/code-runner", String(VERSION));
const require = createRequire(import.meta.url);
const wasmPackage = dirname(require.resolve("esbuild-wasm/package.json"));

const common = { bundle: true, minify: true, target: "es2020", legalComments: "none", write: false, logLevel: "warning", charset: "utf8" };
const banner = (name) => `/*! @ulams/code-runner ${name} (ADR 0170) - MIT License - Copyright (c) 2026 ulams contributors - https://github.com/ulams-dev/ulams/tree/main/front/code-runner */`;
const text = async (options) => (await build({ ...common, ...options })).outputFiles[0].text;

// the harness becomes a string that exec.js puts in front of the exercise's bundle inside the Worker
const harness = await text({ entryPoints: [join(root, "src/harness-entry.ts")], format: "iife" });
const exec = await text({ entryPoints: [join(root, "src/exec.ts")], format: "iife", banner: { js: banner("exec") }, define: { __HARNESS__: JSON.stringify(harness.trim()) } });
const runner = await text({ entryPoints: [join(root, "src/runner.ts")], format: "iife", banner: { js: banner("runner") } });

const page = (title, scripts) => `<!doctype html>\n<html lang="en">\n<head>\n<meta charset="utf-8">\n<title>${title}</title>\n</head>\n<body>\n${scripts.map((s) => `<script src="${s}"></script>`).join("\n")}\n</body>\n</html>\n`;

const files = {
  "runner.html": page("Code exercise runner", ["esbuild.js", "runner.js"]),
  "exec.html": page("Code exercise test run", ["exec.js"]),
  "runner.js": runner,
  "exec.js": exec,
  // the browser build of esbuild-wasm, unchanged (MIT): the runner loads it as a classic script
  "esbuild.js": readFileSync(join(wasmPackage, "lib/browser.min.js"), "utf8"),
};

if (check) {
  const stale = Object.entries(files).filter(([name, content]) => !existsSync(join(out, name)) || readFileSync(join(out, name), "utf8") !== content);
  if (stale.length) {
    console.error(`Stale: ${stale.map(([name]) => name).join(", ")} in api/packages/code-exercises/resources/runtime/code-runner/${VERSION}/ differ from front/code-runner.`);
    console.error("Rebuild and commit: yarn workspace @ulams/code-runner build");
    process.exit(1);
  }
  console.log("code-runner: committed files are current");
} else {
  mkdirSync(out, { recursive: true });
  for (const [name, content] of Object.entries(files)) writeFileSync(join(out, name), content);
  copyFileSync(join(wasmPackage, "esbuild.wasm"), join(out, "esbuild.wasm"));
  console.log(`code-runner: wrote ${Object.keys(files).length} files and esbuild.wasm to ${out}`);
}
