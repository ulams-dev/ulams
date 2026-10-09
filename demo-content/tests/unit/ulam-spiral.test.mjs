import assert from "node:assert/strict";
import { test } from "node:test";
import { cellOf, describeNumber, eulerValue, eulerValuesIn, gridSize, indexAtCell, primeCount, primeFlags, spiralPositions, summarise } from "../../ulam/spiral/logic.js";

test("the sieve counts the primes: 25 up to 100, 168 up to 1,000, 1,229 up to 10,000, 4,203 up to 40,000", () => {
  assert.equal(primeCount(100), 25);
  assert.equal(primeCount(1000), 168);
  assert.equal(primeCount(10000), 1229);
  assert.equal(primeCount(40000), 4203);
  assert.equal(primeCount(1), 0);
});

test("a segment that does not start at 1 is sieved right (a segmented sieve)", () => {
  const flags = primeFlags(90, 20); // 90 … 109
  const primes = [...flags].map((f, i) => (f ? 90 + i : 0)).filter(Boolean);
  assert.deepEqual(primes, [97, 101, 103, 107, 109]);
  assert.equal(primeFlags(1, 3).join(""), "011"); // 1 is not prime, 2 and 3 are
  assert.equal(primeFlags(0, 3).join(""), "001");
  const big = primeFlags(999_983, 1); // the largest prime below one million
  assert.equal(big[0], 1);
});

test("the spiral walks right 1, up 1, left 2, down 2, right 3, up 3, and so on", () => {
  const { x, y } = spiralPositions(14);
  const path = [...x].map((v, i) => [v, y[i]]);
  assert.deepEqual(path.slice(0, 10), [[0, 0], [1, 0], [1, 1], [0, 1], [-1, 1], [-1, 0], [-1, -1], [0, -1], [1, -1], [2, -1]]);
  assert.deepEqual(path[12], [2, 2]);
  // all positions are distinct
  assert.equal(new Set(path.map(String)).size, path.length);
});

test("the values of n² + n + 41 lie on one diagonal when the spiral starts at 41", () => {
  const { x, y } = spiralPositions(1700);
  for (let k = 0; k < 40; k++) {
    const i = eulerValue(k) - 41;
    assert.equal(x[i], y[i], `n=${k} sits at (${x[i]}, ${y[i]})`);
  }
});

test("Euler's polynomial is prime for n = 0 … 39 and composite at n = 40 (41²)", () => {
  const flags = primeFlags(41, 1700);
  for (let n = 0; n < 40; n++) assert.equal(flags[eulerValue(n) - 41], 1, `n=${n}`);
  assert.equal(eulerValue(40), 1681);
  assert.equal(flags[1681 - 41], 0);
  assert.equal(eulerValuesIn(41, 1640).size, 40);
  assert.equal(eulerValuesIn(1, 100).size, 8); // 41, 43, 47, 53, 61, 71, 83, 97
});

test("the grid has an odd side and cells map back to numbers", () => {
  assert.equal(gridSize(100), 11);
  assert.equal(gridSize(2500), 51);
  const size = gridSize(100), pos = spiralPositions(100);
  const { col, row } = cellOf(pos.x[2], pos.y[2], size);
  assert.equal(indexAtCell(col, row, size, pos, 100), 2);
  assert.equal(indexAtCell(0, 0, size, pos, 100) >= -1, true);
});

test("the summary and the cell text say the right things", () => {
  const flags = primeFlags(1, 100);
  assert.match(summarise({ start: 1, count: 100, flags, polynomial: false }).text, /Numbers 1 to 100: 100 numbers, 25 primes \(25\.0%\)\./);
  assert.match(summarise({ start: 41, count: 1600, flags: primeFlags(41, 1600), polynomial: true }).text, /40 of 40 values of n² \+ n \+ 41 are prime \(100\.0%\)/);
  assert.match(describeNumber(1, false), /not prime/);
  assert.match(describeNumber(7, true), /7 is prime/);
  assert.match(describeNumber(15, false), /divisible by 3/);
  assert.match(describeNumber(49, false), /divisible by 7 \(a perfect square\)/);
});
