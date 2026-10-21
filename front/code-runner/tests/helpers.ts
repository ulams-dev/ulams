import { build } from "esbuild";
import * as esbuildWasm from "esbuild-wasm";
import { createContext, runInContext } from "node:vm";
import { fileURLToPath } from "node:url";
import type { CodeFile } from "@ulams/interactive-bridge/protocol";
import { bundleExercise } from "../src/bundle.ts";
import type { HarnessTest } from "../src/harness.ts";

const root = fileURLToPath(new URL("..", import.meta.url));

let harnessSource: string | undefined;
/** The harness exactly as the build embeds it in exec.js. */
export async function harness(): Promise<string> {
  harnessSource ??= (await build({ entryPoints: [`${root}src/harness-entry.ts`], bundle: true, minify: true, format: "iife", target: "es2020", write: false, logLevel: "silent" })).outputFiles[0]!.text;
  return harnessSource;
}

export const esbuild = esbuildWasm;

export const file = (path: string, content: string): CodeFile => ({ path, content });

export interface VmRun {
  /** What the harness posted to the page, or null when the code never finished (an infinite loop would not return at all). */
  message: { type: string; tests: HarnessTest[]; output: string; truncated: boolean } | null;
  /** Which globals were still there when the learner's code ran (the harness takes the ways out away). */
  seen: Record<string, boolean>;
}

/** Bundles an exercise and runs it the way exec.js does, in a vm context standing in for the Worker's global scope. */
export async function runExercise(files: CodeFile[], tests: CodeFile[], language: "javascript" | "typescript" = "javascript"): Promise<VmRun> {
  const built = await bundleExercise(esbuild, { language, files, tests });
  if (!built.ok) throw new Error(built.errors.join("\n"));
  let message: VmRun["message"] = null;
  const scope: Record<string, unknown> = { postMessage: (m: VmRun["message"]) => (message = m), setTimeout, queueMicrotask, Promise };
  scope.self = scope;
  const context = createContext(scope);
  runInContext(`${await harness()}\n${built.code}\n;self.__ulamsRun();`, context);
  // the run is asynchronous: let the microtasks and timers settle
  for (let i = 0; i < 50 && message === null; i++) await new Promise((r) => setTimeout(r, 5));
  return { message, seen: Object.fromEntries(["fetch", "XMLHttpRequest", "WebSocket", "importScripts", "Worker", "postMessage"].map((n) => [n, typeof scope[n] === "function"])) };
}
