import assert from "node:assert/strict";
import { test } from "node:test";
import { LIFE_PRESETS, countOn, elementaryNext, evolveElementary, lifeStep, placePreset, ruleTable, singleCell, uwCounts } from "../../ulam/automaton/logic.js";

const show = (rows) => rows.map((r) => [...r].map((v) => (v ? "#" : ".")).join(""));

test("a rule number is the table of answers: rule 30 is 00011110", () => {
  assert.deepEqual([...ruleTable(30)], [0, 1, 1, 1, 1, 0, 0, 0]); // index = neighbourhood 0 … 7
  assert.deepEqual([...ruleTable(0)], [0, 0, 0, 0, 0, 0, 0, 0]);
  assert.deepEqual([...ruleTable(255)], [1, 1, 1, 1, 1, 1, 1, 1]);
});

test("rule 90 from one cell draws Sierpinski's triangle and rule 30 the known first rows", () => {
  assert.deepEqual(show(evolveElementary(90, 17, 4)), [
    "........#........",
    ".......#.#.......",
    "......#...#......",
    ".....#.#.#.#.....",
    "....#.......#....",
  ]);
  assert.deepEqual(show(evolveElementary(30, 13, 4)), [
    "......#......",
    ".....###.....",
    "....##..#....",
    "...##.####...",
    "..##..#...#..",
  ]);
});

test("rule 90 has 2^(number of ones in the generation's binary form) live cells", () => {
  const rows = evolveElementary(90, 129, 64);
  for (const g of [1, 2, 3, 5, 7, 8, 12, 31, 63]) {
    const ones = g.toString(2).split("").filter((c) => c === "1").length;
    assert.equal(countOn(rows[g]), 2 ** ones, `generation ${g}`);
  }
});

test("rule 0 clears and rule 255 fills, and the ring wraps around", () => {
  assert.equal(countOn(elementaryNext(singleCell(9), 0)), 0);
  assert.equal(countOn(elementaryNext(singleCell(9), 255)), 9);
  const edge = new Uint8Array(9); edge[0] = 1;
  const next = elementaryNext(edge, 90); // neighbours of cell 0 are cells 8 and 1
  assert.deepEqual([...next], [0, 1, 0, 0, 0, 0, 0, 0, 1]);
});

test("Life: a blinker oscillates with period 2, a glider moves one cell diagonally every 4 generations, a block stays", () => {
  const w = 12, h = 12;
  const blinker = placePreset("blinker", w, h);
  assert.notDeepEqual(lifeStep(blinker, w, h), blinker);
  assert.deepEqual(lifeStep(lifeStep(blinker, w, h), w, h), blinker);
  const glider = placePreset("glider", w, h);
  let g = glider;
  for (let i = 0; i < 4; i++) g = lifeStep(g, w, h);
  const cells = (grid) => [...grid].flatMap((v, i) => (v ? [[i % w, Math.floor(i / w)]] : []));
  const moved = cells(glider).map(([x, y]) => [x + 1, y + 1]).sort().join();
  assert.equal(cells(g).sort().join(), moved);
  assert.equal(countOn(g), 5);
  const block = new Uint8Array(w * h); for (const i of [5 * w + 5, 5 * w + 6, 6 * w + 5, 6 * w + 6]) block[i] = 1;
  assert.deepEqual(lifeStep(block, w, h), block);
});

test("Life presets have the right number of cells and fit the grid", () => {
  assert.deepEqual(Object.fromEntries(Object.keys(LIFE_PRESETS).map((k) => [k, countOn(placePreset(k, 64, 40))])), { glider: 5, blinker: 3, toad: 6, "r-pentomino": 5, acorn: 7 });
});

test("the growth rule counts: 1, 5, 9, 21, 25, 37, 49, 85, 89, 101, 113, 149, 161, and (4^(k+1) - 1)/3 after 2^k - 1 generations", () => {
  const c = uwCounts(31);
  assert.deepEqual(c.slice(0, 13), [1, 5, 9, 21, 25, 37, 49, 85, 89, 101, 113, 149, 161]);
  for (const k of [1, 2, 3, 4, 5]) assert.equal(c[2 ** k - 1], (4 ** (k + 1) - 1) / 3, `generation ${2 ** k - 1}`);
});
