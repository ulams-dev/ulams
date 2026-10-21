/**
 * The messages between the runner page and the page that runs the code (exec.html), one frame inside the
 * other (ADR 0170). Both are opaque (sandboxed) frames on the content origin. The runner makes up a nonce
 * for every run and passes it in the frame's URL fragment (which is never sent anywhere); each side accepts
 * messages only from its counterpart's window, with origin "null" and that nonce.
 */
import type { HarnessTest } from "./harness.ts";

export const EXEC_KIND = "ulams-exec";
/** Largest bundle the runner sends to exec.html. */
export const MAX_CODE_BYTES = 2 * 1024 * 1024;

export type ToExec = { kind: typeof EXEC_KIND; nonce: string; type: "exec"; code: string; timeoutMs: number };

export type FromExec =
  | { kind: typeof EXEC_KIND; nonce: string; type: "ready" }
  | { kind: typeof EXEC_KIND; nonce: string; type: "result"; tests: HarnessTest[]; output: string; truncated: boolean }
  | { kind: typeof EXEC_KIND; nonce: string; type: "crash"; message: string }
  | { kind: typeof EXEC_KIND; nonce: string; type: "timeout" };

const isObj = (v: unknown): v is Record<string, unknown> => typeof v === "object" && v !== null && !Array.isArray(v);

/** A message for exec.html, or null. */
export function parseToExec(data: unknown, nonce: string): ToExec | null {
  if (!isObj(data) || data.kind !== EXEC_KIND || data.nonce !== nonce || data.type !== "exec") return null;
  if (typeof data.code !== "string" || data.code.length > MAX_CODE_BYTES) return null;
  if (typeof data.timeoutMs !== "number" || !Number.isInteger(data.timeoutMs) || data.timeoutMs < 500 || data.timeoutMs > 30_000) return null;
  return data as unknown as ToExec;
}

const isTest = (t: unknown): t is HarnessTest =>
  isObj(t) && typeof t.name === "string" && (t.status === "pass" || t.status === "fail" || t.status === "error") && (t.message === undefined || typeof t.message === "string");

/** A message from exec.html, or null. Test names and messages are cut to their limits; at most 200 tests are kept. */
export function parseFromExec(data: unknown, nonce: string): FromExec | null {
  if (!isObj(data) || data.kind !== EXEC_KIND || data.nonce !== nonce) return null;
  switch (data.type) {
    case "ready":
    case "timeout":
      return { kind: EXEC_KIND, nonce, type: data.type };
    case "crash":
      return typeof data.message === "string" ? { kind: EXEC_KIND, nonce, type: "crash", message: data.message.slice(0, 500) } : null;
    case "result": {
      if (!Array.isArray(data.tests) || !data.tests.every(isTest) || typeof data.output !== "string" || typeof data.truncated !== "boolean") return null;
      const tests = data.tests.slice(0, 200).map((t) => ({
        name: t.name.slice(0, 160) || "(unnamed test)",
        status: t.status,
        ...(t.message ? { message: t.message.slice(0, 500) } : {}),
      }));
      return { kind: EXEC_KIND, nonce, type: "result", tests, output: data.output.slice(0, 8192), truncated: data.truncated || data.output.length > 8192 };
    }
    default:
      return null;
  }
}
