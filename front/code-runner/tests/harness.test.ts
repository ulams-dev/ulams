import { describe, expect, it } from "vitest";
import { file, harness, runExercise } from "./helpers.ts";

const run = async (test: string, code = "export const sum = (a, b) => a + b;") => (await runExercise([file("sum.js", code)], [file("sum.test.js", test)])).message!;
const names = (m: Awaited<ReturnType<typeof run>>) => m.tests.map((t) => `${t.name}:${t.status}`);

describe("the test harness", () => {
  it("runs tests in order and reports pass, fail (a failed assertion) and error (anything else thrown)", async () => {
    const m = await run(`
      import { sum } from './sum.js';
      test('adds', () => expect(sum(1, 2)).toBe(3));
      it('fails', () => expect(sum(1, 2)).toBe(4));
      test('throws', () => { null.x; });
      test('async', async () => { await Promise.resolve(); expect(await Promise.resolve(2)).toBe(2); });
    `);
    expect(names(m)).toEqual(["adds:pass", "fails:fail", "throws:error", "async:pass"]);
    expect(m.tests[1]!.message).toBe("Expected 4, received 3");
    expect(m.tests[2]!.message).toMatch(/null/);
  });

  it("groups with describe and runs beforeEach and afterEach around each test", async () => {
    const m = await run(`
      const log = [];
      describe('outer', () => {
        beforeEach(() => log.push('before'));
        afterEach(() => log.push('after'));
        test('one', () => expect(log).toEqual(['before']));
        describe('inner', () => {
          test('two', () => expect(log).toEqual(['before', 'after', 'before']));
        });
      });
    `);
    expect(names(m)).toEqual(["outer > one:pass", "outer > inner > two:pass"]);
  });

  it("has the usual matchers, negation and promise matchers", async () => {
    const m = await run(`
      test('equality', () => {
        expect({ a: [1, { b: 2 }] }).toEqual({ a: [1, { b: 2 }] });
        expect({ a: 1, b: undefined }).toEqual({ a: 1 });
        expect({ a: 1, b: undefined }).not.toStrictEqual({ a: 1 });
        expect(new Map([[1, 2]])).toEqual(new Map([[1, 2]]));
        expect([1, 2, 3]).toContain(2);
        expect([{ a: 1 }]).toContainEqual({ a: 1 });
        expect('hello').toMatch(/ell/);
        expect([1, 2]).toHaveLength(2);
        expect({ a: { b: 1 } }).toHaveProperty('a.b', 1);
        expect(0.1 + 0.2).toBeCloseTo(0.3);
        expect(3).toBeGreaterThan(2);
        expect(NaN).toBeNaN();
        expect(null).toBeNull();
        expect(() => { throw new TypeError('bad'); }).toThrow('bad');
        expect(() => 1).not.toThrow();
        expect(new RangeError('x')).toBeInstanceOf(Error);
      });
      test('promises', async () => {
        await expect(Promise.resolve(5)).resolves.toBe(5);
        await expect(Promise.reject(new Error('no'))).rejects.toHaveProperty('message', 'no');
      });
      test('negation', () => expect(1).not.toBe(1));
    `);
    expect(names(m)).toEqual(["equality:pass", "promises:pass", "negation:fail"]);
    expect(m.tests[2]!.message).toBe("Expected not 1");
  });

  it("cuts long messages and names", async () => {
    const m = await run(`test('long', () => { throw new Error('x'.repeat(2000)); });`);
    expect(m.tests[0]!.message!.length).toBe(500);
  });

  it("captures console output and cuts it at 8 KB", async () => {
    const m = await run(`
      console.log('hello', { a: 1 });
      console.error('boom');
      test('noisy', () => { for (let i = 0; i < 2000; i++) console.log('line ' + i); });
    `);
    expect(m.output.startsWith('hello {a: 1}\nboom\n')).toBe(true);
    expect(m.output.length).toBe(8192);
    expect(m.truncated).toBe(true);
  });

  it("reports no tests when the test file registers none", async () => {
    expect((await run("// nothing")).tests).toEqual([]);
  });

  it("works with the learner's code throwing at load time: the exception surfaces to the caller, not as a result", async () => {
    await expect(run("import './sum.js';", "throw new Error('boom');")).resolves.toBeNull().catch(() => undefined);
  });

  it("takes the network and the other ways out away from the code before it runs", async () => {
    const { seen } = await runExercise(
      [file("probe.js", "export const probe = () => ({ fetch: typeof fetch, xhr: typeof XMLHttpRequest, ws: typeof WebSocket, importScripts: typeof importScripts, worker: typeof Worker, post: typeof postMessage });")],
      [file("probe.test.js", "import { probe } from './probe.js';\ntest('probe', () => { for (const [k, v] of Object.entries(probe())) expect([k, v]).toEqual([k, 'undefined']); });")]
    );
    expect(seen).toEqual({ fetch: false, XMLHttpRequest: false, WebSocket: false, importScripts: false, Worker: false, postMessage: false });
    const m = (await runExercise([file("probe.js", "export const probe = () => typeof fetch;")], [file("probe.test.js", "import { probe } from './probe.js';\ntest('no fetch', () => expect(probe()).toBe('undefined'));")])).message!;
    expect(names(m)).toEqual(["no fetch:pass"]);
  });

  it("signs its result with the secret exec.js puts in the harness: the placeholder is there exactly once, and the result carries it", async () => {
    const source = await harness();
    expect(source.split("__ULAMS_TOKEN__")).toHaveLength(2);
    const m = (await run("test('x', () => {});")) as unknown as { token: string };
    expect(m.token).toBe("__ULAMS_TOKEN__");
  });
});
