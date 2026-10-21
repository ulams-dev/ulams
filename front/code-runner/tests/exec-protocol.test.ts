import { describe, expect, it } from "vitest";
import { EXEC_KIND, MAX_CODE_BYTES, parseFromExec, parseToExec } from "../src/exec-protocol.ts";

const N = "a".repeat(32);

describe("parseToExec", () => {
  const ok = { kind: EXEC_KIND, nonce: N, type: "exec", code: "x", timeoutMs: 5000 };
  it("accepts the runner's message with the right nonce", () => {
    expect(parseToExec(ok, N)).not.toBeNull();
  });
  it("refuses a wrong nonce, kind, type, oversized code and bad time limits", () => {
    for (const bad of [{ ...ok, nonce: "b".repeat(32) }, { ...ok, kind: "x" }, { ...ok, type: "run" }, { ...ok, code: "x".repeat(MAX_CODE_BYTES + 1) }, { ...ok, code: 5 }, { ...ok, timeoutMs: 100 }, { ...ok, timeoutMs: 60_000 }, { ...ok, timeoutMs: 1000.5 }, null, "exec", []]) {
      expect(parseToExec(bad, N)).toBeNull();
    }
  });
});

describe("parseFromExec", () => {
  const base = { kind: EXEC_KIND, nonce: N };
  it("accepts ready, timeout, crash and result", () => {
    expect(parseFromExec({ ...base, type: "ready" }, N)).toEqual({ ...base, type: "ready" });
    expect(parseFromExec({ ...base, type: "timeout" }, N)).toEqual({ ...base, type: "timeout" });
    expect(parseFromExec({ ...base, type: "crash", message: "m".repeat(900) }, N)).toMatchObject({ type: "crash", message: "m".repeat(500) });
    expect(parseFromExec({ ...base, type: "result", tests: [{ name: "a", status: "pass" }], output: "o", truncated: false }, N)).toMatchObject({ type: "result", tests: [{ name: "a", status: "pass" }] });
  });
  it("cuts what the Worker sent to the limits, whatever it claims", () => {
    const tests = Array.from({ length: 300 }, (_, i) => ({ name: "n".repeat(500) + i, status: "fail", message: "m".repeat(900) }));
    const parsed = parseFromExec({ ...base, type: "result", tests, output: "o".repeat(20_000), truncated: false }, N);
    expect(parsed).toMatchObject({ type: "result", truncated: true });
    if (parsed?.type === "result") {
      expect(parsed.tests).toHaveLength(200);
      expect(parsed.tests[0]!.name).toHaveLength(160);
      expect(parsed.tests[0]!.message).toHaveLength(500);
      expect(parsed.output).toHaveLength(8192);
    }
  });
  it("refuses a wrong nonce, unknown types and malformed results", () => {
    for (const bad of [
      { ...base, nonce: "b".repeat(32), type: "ready" },
      { ...base, type: "launch" },
      { ...base, type: "result", tests: [{ name: "a", status: "skipped" }], output: "", truncated: false },
      { ...base, type: "result", tests: "none", output: "", truncated: false },
      { ...base, type: "result", tests: [], output: 5, truncated: false },
      { ...base, type: "crash", message: 5 },
      undefined,
    ]) {
      expect(parseFromExec(bad, N)).toBeNull();
    }
  });
});
