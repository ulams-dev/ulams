// @ts-check
// The pure logic of the Ulam spiral: primes, the spiral walk, the highlighted diagonal and the summary.
// No DOM here, so the unit tests (tests/unit/ulam-spiral.test.mjs) can run it in Node.

/** Euler's prime-generating polynomial n² + n + 41 (prime for n = 0 to 39). */
export const eulerValue = (/** @type {number} */ n) => n * n + n + 41;

/**
 * Which of the numbers start … start + count - 1 are prime (a segmented sieve of Eratosthenes).
 * @param {number} start first number (at least 1)
 * @param {number} count how many numbers
 * @returns {Uint8Array} flags[i] is 1 when start + i is prime
 */
export function primeFlags(start, count) {
  const flags = new Uint8Array(count).fill(1);
  const end = start + count - 1;
  const root = Math.floor(Math.sqrt(end));
  // small primes up to sqrt(end)
  const small = new Uint8Array(root + 1).fill(1);
  for (let p = 2; p * p <= root; p++) if (small[p]) for (let m = p * p; m <= root; m += p) small[m] = 0;
  for (let p = 2; p <= root; p++) {
    if (!small[p]) continue;
    let first = Math.max(p * p, Math.ceil(start / p) * p);
    for (let m = first; m <= end; m += p) flags[m - start] = 0;
  }
  for (let n = start; n < 2 && n <= end; n++) flags[n - start] = 0; // 0 and 1 are not prime
  return flags;
}

/** Number of primes up to and including n (n at most a few million). */
export function primeCount(n) {
  if (n < 2) return 0;
  return primeFlags(1, n).reduce((a, b) => a + b, 0);
}

/**
 * The square spiral: position i (0-based) as lattice coordinates, i = 0 at the origin, then right 1, up 1,
 * left 2, down 2, right 3, up 3, …
 * @param {number} count
 * @returns {{ x: Int16Array, y: Int16Array }}
 */
export function spiralPositions(count) {
  const x = new Int16Array(count), y = new Int16Array(count);
  const dirs = [[1, 0], [0, 1], [-1, 0], [0, -1]];
  let cx = 0, cy = 0, i = 1, leg = 1, d = 0;
  while (i < count) {
    for (let twice = 0; twice < 2 && i < count; twice++) {
      for (let s = 0; s < leg && i < count; s++) {
        cx += dirs[d][0]; cy += dirs[d][1];
        x[i] = cx; y[i] = cy; i++;
      }
      d = (d + 1) % 4;
    }
    leg++;
  }
  return { x, y };
}

/** The side of the smallest square grid that holds the spiral of `count` numbers. */
export const gridSize = (/** @type {number} */ count) => {
  const s = Math.ceil(Math.sqrt(count));
  return s % 2 === 0 ? s + 1 : s; // an odd side keeps the first number in the middle
};

/**
 * Lattice coordinates to canvas cells: the origin sits in the middle of a size x size grid.
 * @returns {{ col: number, row: number }}
 */
export function cellOf(/** @type {number} */ x, /** @type {number} */ y, /** @type {number} */ size) {
  const mid = (size - 1) / 2;
  return { col: mid + x, row: mid - y };
}

/** The index (0-based) whose lattice position is the cell (col, row), or -1 when no number sits there. */
export function indexAtCell(/** @type {number} */ col, /** @type {number} */ row, /** @type {number} */ size, /** @type {{x: Int16Array, y: Int16Array}} */ pos, /** @type {number} */ count) {
  const mid = (size - 1) / 2;
  const x = col - mid, y = mid - row;
  for (let i = 0; i < count; i++) if (pos.x[i] === x && pos.y[i] === y) return i;
  return -1;
}

/** Values of n² + n + 41 inside start … end. */
export function eulerValuesIn(/** @type {number} */ start, /** @type {number} */ end) {
  const out = new Set();
  for (let n = 0; eulerValue(n) <= end; n++) if (eulerValue(n) >= start) out.add(eulerValue(n));
  return out;
}

/**
 * What the learner reads under the picture.
 * @param {{ start: number, count: number, flags: Uint8Array, polynomial: boolean }} s
 */
export function summarise({ start, count, flags, polynomial }) {
  const end = start + count - 1;
  const primes = flags.reduce((a, b) => a + b, 0);
  const share = (a, b) => (b ? `${((100 * a) / b).toFixed(1)}%` : "0%");
  let text = `Numbers ${start.toLocaleString("en")} to ${end.toLocaleString("en")}: ${count.toLocaleString("en")} numbers, ${primes.toLocaleString("en")} primes (${share(primes, count)}).`;
  if (polynomial) {
    const values = [...eulerValuesIn(start, end)];
    const primeOnes = values.filter((v) => flags[v - start]).length;
    text += values.length
      ? ` On the highlighted diagonal, ${primeOnes} of ${values.length} values of n² + n + 41 are prime (${share(primeOnes, values.length)}).`
      : " No value of n² + n + 41 lies in this range.";
  }
  return { primes, text };
}

/** What a cell says: its number and whether it is prime (with the smallest factor when it is not). */
export function describeNumber(/** @type {number} */ n, /** @type {boolean} */ isPrime) {
  if (n < 2) return `${n} is not prime: primes start at 2.`;
  if (isPrime) return `${n.toLocaleString("en")} is prime.`;
  let f = 2;
  while (f * f <= n && n % f) f++;
  return `${n.toLocaleString("en")} is not prime: it is divisible by ${f}${f * f === n ? " (a perfect square)" : ""}.`;
}
