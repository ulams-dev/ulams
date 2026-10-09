// @ts-check
// The pure logic of the Monte Carlo interactive: a seeded generator, the π estimate and its error, and the
// log-spaced samples behind the convergence chart. No DOM, so the unit tests run it in Node.

/**
 * mulberry32, a small seeded generator: the same seed always gives the same sequence, so a run can be repeated.
 * @param {number} seed
 * @returns {() => number} a function returning numbers in [0, 1)
 */
export function mulberry32(seed) {
  let a = seed >>> 0;
  return () => {
    a = (a + 0x6d2b79f5) >>> 0;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

/** The estimate of π from `inside` points in the quarter circle out of `total` points in the unit square. */
export const estimate = (/** @type {number} */ inside, /** @type {number} */ total) => (total > 0 ? (4 * inside) / total : 0);

/** The standard deviation of the estimator for one throw: 4·sqrt(p(1-p)) with p = π/4, about 1.642. */
export const SIGMA = 4 * Math.sqrt((Math.PI / 4) * (1 - Math.PI / 4));

/** The error to expect after n throws: SIGMA / sqrt(n), shrinking as 1 over the square root of n. */
export const expectedError = (/** @type {number} */ n) => (n > 0 ? SIGMA / Math.sqrt(n) : Infinity);

/**
 * @typedef {{ n: number, inside: number, error: number }} Sample
 * @typedef {{ seed: number, rng: () => number, total: number, inside: number, xs: Float32Array, ys: Float32Array, drawn: number, samples: Sample[], next: number }} Run
 */

/** The most points kept for drawing; beyond this the counts go on but no more dots are added. */
export const MAX_DRAWN = 100_000;

/** A new run from a seed. */
export function newRun(/** @type {number} */ seed) {
  /** @type {Run} */
  const run = { seed, rng: mulberry32(seed), total: 0, inside: 0, xs: new Float32Array(MAX_DRAWN), ys: new Float32Array(MAX_DRAWN), drawn: 0, samples: [], next: 1 };
  return run;
}

/**
 * Throws n more points. A sample is recorded at log-spaced counts (every 25% more throws), and at the end of the
 * batch, so the chart gets a point per step of the way. Returns the run for chaining.
 */
export function throwPoints(/** @type {Run} */ run, /** @type {number} */ n) {
  for (let i = 0; i < n; i++) {
    const x = run.rng(), y = run.rng();
    run.total++;
    if (x * x + y * y <= 1) run.inside++;
    if (run.drawn < MAX_DRAWN) { run.xs[run.drawn] = x; run.ys[run.drawn] = y; run.drawn++; }
    if (run.total >= run.next) {
      record(run);
      run.next = Math.max(run.total + 1, Math.ceil(run.total * 1.25));
    }
  }
  const last = run.samples[run.samples.length - 1];
  if (!last || last.n !== run.total) record(run);
  return run;
}

function record(/** @type {Run} */ run) {
  const last = run.samples[run.samples.length - 1];
  if (last && last.n === run.total) return;
  run.samples.push({ n: run.total, inside: run.inside, error: Math.abs(estimate(run.inside, run.total) - Math.PI) });
}

/** Maps a value on a logarithmic axis from min to max onto 0 … size (a lower bound clamps zero errors). */
export function logPosition(/** @type {number} */ value, /** @type {number} */ min, /** @type {number} */ max, /** @type {number} */ size) {
  const v = Math.max(value, min);
  return ((Math.log10(v) - Math.log10(min)) / (Math.log10(max) - Math.log10(min))) * size;
}

/** The sentence under the picture. */
export function describeRun(/** @type {Run} */ run) {
  if (run.total === 0) return `No points yet (seed ${run.seed}). Throw some to estimate π.`;
  const est = estimate(run.inside, run.total);
  const f = (v, d) => v.toLocaleString("en", { minimumFractionDigits: d, maximumFractionDigits: d });
  return `${run.total.toLocaleString("en")} throws, ${run.inside.toLocaleString("en")} inside the quarter circle. Estimate of π: ${f(est, 4)}. Error: ${f(Math.abs(est - Math.PI), 4)}; about ${f(expectedError(run.total), 4)} is to be expected (seed ${run.seed}).`;
}
