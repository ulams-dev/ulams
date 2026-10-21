/**
 * The test harness that runs inside the Worker, in front of the exercise's bundle (ADR 0170): `test`/`it`,
 * `describe`, `beforeEach`/`afterEach` and a small `expect`. It also takes the network and the other
 * ways out of the Worker away from the learner's code (the content security policy of the page that runs
 * the Worker is what really forbids them, this is a second layer), and captures `console` output.
 *
 * Results are collected here and sent to the page with a reference to `postMessage` taken before the
 * learner's code runs. In practice mode this is a convenience, not a security boundary: the learner's
 * browser is untrusted and a determined learner can forge a result (the API stores it unverified).
 *
 * The file is compiled to a string and put in front of the bundle, so it must not import anything.
 */

type Fn = () => unknown;
interface Case {
  name: string;
  fn: Fn;
  before: Fn[];
  after: Fn[];
}
export interface HarnessTest {
  name: string;
  status: "pass" | "fail" | "error";
  message?: string;
}

const MARK = "__ulamsAssertion";
/** Replaced by exec.js with a secret of this run before the Worker starts: a result without it is not a result. */
const TOKEN = "__ULAMS_TOKEN__";

export function installHarness(scope: Record<string, unknown>): void {
  const post = (scope.postMessage as (m: unknown) => void).bind(scope);
  const cases: Case[] = [];
  const stack: Array<{ name: string; before: Fn[]; after: Fn[] }> = [];
  const OUTPUT_LIMIT = 8 * 1024;
  let output = "";
  let truncated = false;

  // --- take the ways out of the Worker away (the CSP forbids them as well)
  for (const name of ["fetch", "XMLHttpRequest", "WebSocket", "EventSource", "importScripts", "Worker", "SharedWorker", "WebTransport", "indexedDB", "caches", "BroadcastChannel", "navigator", "postMessage", "close"]) {
    try {
      Object.defineProperty(scope, name, { value: undefined, configurable: true, writable: true });
    } catch {
      /* not configurable here: the policy still applies */
    }
  }

  // --- console capture
  const write = (...args: unknown[]) => {
    if (truncated) return;
    const line = args.map((a) => (typeof a === "string" ? a : format(a))).join(" ") + "\n";
    if (output.length + line.length > OUTPUT_LIMIT) {
      output += line.slice(0, OUTPUT_LIMIT - output.length);
      truncated = true;
    } else output += line;
  };
  const console_ = { log: write, info: write, warn: write, error: write, debug: write, trace: write };
  try {
    Object.defineProperty(scope, "console", { value: console_, configurable: true, writable: true });
  } catch {
    /* keep the native console */
  }

  // --- formatting
  function format(value: unknown, depth = 0, seen: unknown[] = []): string {
    if (typeof value === "string") return JSON.stringify(value);
    if (typeof value === "bigint") return `${value}n`;
    if (typeof value === "function") return `[Function ${(value as { name?: string }).name || "anonymous"}]`;
    if (typeof value === "symbol") return String(value);
    if (value === null || typeof value !== "object") return String(value);
    if (seen.includes(value)) return "[Circular]";
    if (value instanceof Error) return `${value.name}: ${value.message}`;
    if (depth >= 3) return Array.isArray(value) ? "[Array]" : "[Object]";
    const next = [...seen, value];
    let text: string;
    if (Array.isArray(value)) text = `[${value.slice(0, 20).map((v) => format(v, depth + 1, next)).join(", ")}${value.length > 20 ? ", …" : ""}]`;
    else if (value instanceof Map) text = `Map(${value.size})`;
    else if (value instanceof Set) text = `Set(${value.size})`;
    else {
      const keys = Object.keys(value as object);
      text = `{${keys.slice(0, 20).map((k) => `${k}: ${format((value as Record<string, unknown>)[k], depth + 1, next)}`).join(", ")}${keys.length > 20 ? ", …" : ""}}`;
    }
    return text.length > 200 ? text.slice(0, 197) + "…" : text;
  }

  // --- equality
  function equals(a: unknown, b: unknown, strict: boolean, seen: Array<[unknown, unknown]> = []): boolean {
    if (Object.is(a, b)) return true;
    if (typeof a !== "object" || typeof b !== "object" || a === null || b === null) return false;
    if (seen.some(([x, y]) => x === a && y === b)) return true;
    if (Object.getPrototypeOf(a) !== Object.getPrototypeOf(b) && strict) return false;
    if (a instanceof Date && b instanceof Date) return a.getTime() === b.getTime();
    if (a instanceof RegExp && b instanceof RegExp) return String(a) === String(b);
    if (Array.isArray(a) !== Array.isArray(b)) return false;
    const next: Array<[unknown, unknown]> = [...seen, [a, b]];
    if (a instanceof Map && b instanceof Map) return a.size === b.size && [...a].every(([k, v]) => b.has(k) && equals(v, b.get(k), strict, next));
    if (a instanceof Set && b instanceof Set) return a.size === b.size && [...a].every((v) => b.has(v));
    const own = (o: object) => Object.keys(o).filter((k) => strict || (o as Record<string, unknown>)[k] !== undefined);
    const ka = own(a);
    const kb = own(b);
    return ka.length === kb.length && ka.every((k) => Object.prototype.hasOwnProperty.call(b, k) && equals((a as Record<string, unknown>)[k], (b as Record<string, unknown>)[k], strict, next));
  }

  // --- expect
  const fail = (message: string): never => {
    const error = new Error(message);
    (error as unknown as Record<string, unknown>)[MARK] = true;
    throw error;
  };

  function matchers(actual: unknown, negate: boolean) {
    const check = (pass: boolean, message: string, negatedMessage: string) => {
      if (pass === negate) fail(negate ? negatedMessage : message);
    };
    const m = {
      toBe: (expected: unknown) => check(Object.is(actual, expected), `Expected ${format(expected)}, received ${format(actual)}`, `Expected not ${format(expected)}`),
      toEqual: (expected: unknown) => check(equals(actual, expected, false), `Expected ${format(expected)}, received ${format(actual)}`, `Expected value not to equal ${format(expected)}`),
      toStrictEqual: (expected: unknown) => check(equals(actual, expected, true), `Expected ${format(expected)} (strictly), received ${format(actual)}`, `Expected value not to strictly equal ${format(expected)}`),
      toBeTruthy: () => check(Boolean(actual), `Expected a truthy value, received ${format(actual)}`, `Expected a falsy value, received ${format(actual)}`),
      toBeFalsy: () => check(!actual, `Expected a falsy value, received ${format(actual)}`, `Expected a truthy value, received ${format(actual)}`),
      toBeNull: () => check(actual === null, `Expected null, received ${format(actual)}`, "Expected a value that is not null"),
      toBeUndefined: () => check(actual === undefined, `Expected undefined, received ${format(actual)}`, "Expected a value that is not undefined"),
      toBeDefined: () => check(actual !== undefined, "Expected a defined value", "Expected undefined"),
      toBeNaN: () => check(Number.isNaN(actual), `Expected NaN, received ${format(actual)}`, "Expected a number that is not NaN"),
      toBeGreaterThan: (n: number) => check((actual as number) > n, `Expected ${format(actual)} to be greater than ${n}`, `Expected ${format(actual)} not to be greater than ${n}`),
      toBeGreaterThanOrEqual: (n: number) => check((actual as number) >= n, `Expected ${format(actual)} to be at least ${n}`, `Expected ${format(actual)} to be below ${n}`),
      toBeLessThan: (n: number) => check((actual as number) < n, `Expected ${format(actual)} to be less than ${n}`, `Expected ${format(actual)} not to be less than ${n}`),
      toBeLessThanOrEqual: (n: number) => check((actual as number) <= n, `Expected ${format(actual)} to be at most ${n}`, `Expected ${format(actual)} to be above ${n}`),
      toBeCloseTo: (n: number, digits = 2) => check(Math.abs((actual as number) - n) < Math.pow(10, -digits) / 2, `Expected ${format(actual)} to be close to ${n}`, `Expected ${format(actual)} not to be close to ${n}`),
      toContain: (item: unknown) => {
        const has = typeof actual === "string" ? actual.includes(String(item)) : Array.isArray(actual) ? actual.includes(item) : actual instanceof Set ? actual.has(item) : false;
        check(has, `Expected ${format(actual)} to contain ${format(item)}`, `Expected ${format(actual)} not to contain ${format(item)}`);
      },
      toContainEqual: (item: unknown) =>
        check(Array.isArray(actual) && actual.some((v) => equals(v, item, false)), `Expected ${format(actual)} to contain an item equal to ${format(item)}`, `Expected ${format(actual)} not to contain an item equal to ${format(item)}`),
      toHaveLength: (n: number) => check((actual as { length?: number } | null)?.length === n, `Expected length ${n}, received ${format((actual as { length?: number } | null)?.length)}`, `Expected length not to be ${n}`),
      toMatch: (pattern: RegExp | string) => check(typeof actual === "string" && (typeof pattern === "string" ? actual.includes(pattern) : pattern.test(actual)), `Expected ${format(actual)} to match ${String(pattern)}`, `Expected ${format(actual)} not to match ${String(pattern)}`),
      toBeInstanceOf: (type: new (...a: never[]) => unknown) => check(actual instanceof type, `Expected an instance of ${type.name}, received ${format(actual)}`, `Expected not an instance of ${type.name}`),
      toHaveProperty: (key: string, ...rest: unknown[]) => {
        const path = key.split(".");
        let node: unknown = actual;
        let found = true;
        for (const p of path) {
          if (node !== null && node !== undefined && p in Object(node)) node = (node as Record<string, unknown>)[p];
          else {
            found = false;
            break;
          }
        }
        check(found && (rest.length === 0 || equals(node, rest[0], false)), `Expected ${format(actual)} to have property "${key}"${rest.length > 0 ? ` equal to ${format(rest[0])}` : ""}`, `Expected ${format(actual)} not to have property "${key}"`);
      },
      toThrow: (expected?: RegExp | string | (new (...a: never[]) => Error)) => {
        let thrown: unknown;
        let did = false;
        try {
          (actual as Fn)();
        } catch (e) {
          did = true;
          thrown = e;
        }
        const message = thrown instanceof Error ? thrown.message : String(thrown);
        const matches =
          expected === undefined ? true : typeof expected === "string" ? message.includes(expected) : expected instanceof RegExp ? expected.test(message) : thrown instanceof expected;
        check(did && matches, did ? `Expected the error to match ${String(expected)}, received ${format(message)}` : "Expected the function to throw", "Expected the function not to throw");
      },
    };
    return m;
  }

  type Matchers = ReturnType<typeof matchers>;
  interface Expectation extends Matchers {
    not: Matchers;
    resolves: Record<keyof Matchers, (...a: never[]) => Promise<void>>;
    rejects: Record<keyof Matchers, (...a: never[]) => Promise<void>>;
  }
  const expect = (actual: unknown): Expectation => {
    const wrap = (get: () => Promise<unknown>) =>
      new Proxy({} as Record<string, (...a: unknown[]) => Promise<void>>, {
        get: (_t, name: string) => async (...args: unknown[]) => (matchers(await get(), false) as unknown as Record<string, (...a: unknown[]) => void>)[name]!(...args),
      });
    return {
      ...matchers(actual, false),
      not: matchers(actual, true),
      resolves: wrap(() => Promise.resolve(actual)),
      rejects: wrap(async () => {
        try {
          await actual;
        } catch (e) {
          return e;
        }
        return fail("Expected the promise to reject");
      }),
    } as unknown as Expectation;
  };
  Object.freeze(expect);

  // --- registration
  const add = (name: string, fn: Fn) => {
    cases.push({
      name: [...stack.map((s) => s.name), name].join(" > "),
      fn,
      before: stack.flatMap((s) => s.before),
      after: [...stack].reverse().flatMap((s) => s.after),
    });
  };
  const test = Object.assign((name: string, fn: Fn) => add(String(name), fn), {
    skip: (_name: string, _fn?: Fn) => undefined,
  });
  const describe_ = (name: string, body: () => void) => {
    stack.push({ name: String(name), before: [], after: [] });
    try {
      body();
    } finally {
      stack.pop();
    }
  };
  const hook = (kind: "before" | "after") => (fn: Fn) => void stack[stack.length - 1]?.[kind].push(fn);

  const globals: Record<string, unknown> = { test, it: test, describe: describe_, beforeEach: hook("before"), afterEach: hook("after"), expect };
  for (const [name, value] of Object.entries(globals)) Object.defineProperty(scope, name, { value, configurable: true, writable: true });

  // --- run
  Object.defineProperty(scope, "__ulamsRun", {
    enumerable: false,
    value: async () => {
      const tests: HarnessTest[] = [];
      for (const c of cases) {
        try {
          for (const h of c.before) await h();
          await c.fn();
          for (const h of c.after) await h();
          tests.push({ name: c.name, status: "pass" });
        } catch (e) {
          const assertion = Boolean(e && typeof e === "object" && (e as Record<string, unknown>)[MARK]);
          const message = e instanceof Error ? e.message : format(e);
          tests.push({ name: c.name, status: assertion ? "fail" : "error", message: message.slice(0, 500) });
        }
      }
      post({ type: "result", token: TOKEN, tests, output, truncated });
    },
  });
}
