import assert from "node:assert/strict";
import { test } from "node:test";
import { MAX_DRAWN, SIGMA, describeRun, estimate, expectedError, logPosition, mulberry32, newRun, throwPoints } from "../../ulam/monte-carlo/logic.js";

test("the seeded generator is repeatable and stays in [0, 1)", () => {
  const a = mulberry32(1), b = mulberry32(1);
  const first = [a(), a(), a()];
  assert.deepEqual(first, [b(), b(), b()]);
  assert.equal(first[0], 0.6270739405881613); // mulberry32(1), frozen
  assert.notDeepEqual(first, [mulberry32(2)(), 0, 0].slice(0, 3));
  const g = mulberry32(99);
  for (let i = 0; i < 10_000; i++) { const v = g(); assert.ok(v >= 0 && v < 1); }
});

test("the estimate is four times the share inside; the expected error shrinks as 1 over the square root of n", () => {
  assert.equal(estimate(785, 1000), 3.14);
  assert.equal(estimate(0, 0), 0);
  near(SIGMA, 1.6422, 1e-3);
  near(expectedError(100), 0.16422, 1e-4);
  near(expectedError(10_000) * 10, expectedError(100), 1e-9); // 100 times the throws, a tenth of the error
  assert.equal(expectedError(0), Infinity);
});

test("a run with a given seed always gives the same counts, however the throws are split into batches", () => {
  const whole = throwPoints(newRun(2026), 10_000);
  const split = newRun(2026);
  throwPoints(split, 100); throwPoints(split, 9_900);
  assert.equal(whole.inside, 7812);
  assert.equal(split.inside, 7812);
  near(estimate(whole.inside, whole.total), 3.1248, 1e-9);
  const big = throwPoints(newRun(12345), 100_000);
  assert.equal(big.inside, 78_490);
  near(estimate(big.inside, big.total), 3.1396, 1e-9);
});

test("the sample points are log-spaced, end at the last throw and stay few", () => {
  const run = throwPoints(newRun(7), 100_000);
  const ns = run.samples.map((s) => s.n);
  assert.equal(ns[0], 1);
  assert.equal(ns.at(-1), 100_000);
  assert.deepEqual(ns, [...ns].sort((a, b) => a - b));
  assert.equal(new Set(ns).size, ns.length);
  assert.ok(ns.length < 70, `${ns.length} samples`);
  for (const s of run.samples) assert.ok(s.error >= 0);
});

test("over many seeds the mean error after 10,000 throws is the expected size", () => {
  let sum = 0;
  const seeds = 60;
  for (let s = 1; s <= seeds; s++) { const r = throwPoints(newRun(s), 10_000); sum += Math.abs(estimate(r.inside, r.total) - Math.PI); }
  const mean = sum / seeds, expected = expectedError(10_000) * Math.sqrt(2 / Math.PI); // mean absolute value of a normal variable
  assert.ok(mean > expected * 0.6 && mean < expected * 1.4, `mean ${mean}, expected about ${expected}`);
});

test("the drawing buffer is capped while the counts go on", () => {
  const run = throwPoints(newRun(3), MAX_DRAWN + 500);
  assert.equal(run.drawn, MAX_DRAWN);
  assert.equal(run.total, MAX_DRAWN + 500);
});

test("the log axis maps the ends and clamps zero", () => {
  near(logPosition(1, 1, 100_000, 500), 0, 1e-9);
  near(logPosition(100_000, 1, 100_000, 500), 500, 1e-9);
  near(logPosition(316.2277660168379, 1, 100_000, 500), 250, 1e-6);
  near(logPosition(0, 1e-4, 10, 100), 0, 1e-9);
});

test("the sentence under the picture says what happened", () => {
  const run = throwPoints(newRun(2026), 10_000);
  assert.match(describeRun(run), /10,000 throws, 7,812 inside the quarter circle\. Estimate of π: 3\.1248\. Error: 0\.0168; about 0\.0164 is to be expected \(seed 2026\)\./);
  assert.match(describeRun(newRun(5)), /No points yet \(seed 5\)/);
});

function near(a, b, eps) { assert.ok(Math.abs(a - b) <= eps, `${a} is not within ${eps} of ${b}`); }
