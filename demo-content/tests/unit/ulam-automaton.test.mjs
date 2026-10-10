import assert from "node:assert/strict";
import { test } from "node:test";
import { LIFE_PRESETS, countOn, elementaryNext, evolveElementary, lifeStep, placePreset, ruleTable, singleCell, suCounts, suStart, suStep } from "../../ulam/automaton/logic.js";

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

// OEIS A170896, "Number of ON cells after n generations of the Schrandt-Ulam cellular automaton on the square grid" (n = 0..66,
// read on oeis.org on 2026-10-10): a(0) = 0 and the single starting cell is generation 1, so our generation g is a(g + 1).
const A170896 = [0, 1, 5, 9, 13, 25, 29, 41, 53, 65, 85, 97, 117, 145, 157, 169, 181, 201, 229, 249, 285, 321, 365, 409, 445, 497, 549, 577, 605, 633, 669, 713, 757, 825, 893, 969, 1045, 1105, 1173, 1241, 1309, 1377, 1437, 1473, 1541, 1609, 1693, 1793, 1869, 1945, 2037, 2105, 2189, 2281, 2381, 2521, 2621, 2753, 2869, 2969, 3053, 3129, 3237, 3377, 3485, 3585, 3685, 3817, 3909];

test("the Schrandt-Ulam growth rule reproduces OEIS A170896: 1, 5, 9, 13, 25, 29, 41, ... for 66 generations", () => {
  const counts = suCounts(66);
  assert.deepEqual(counts.slice(0, 8), [1, 5, 9, 13, 25, 29, 41, 53]);
  assert.deepEqual(counts, A170896.slice(1, 68));
});

test("the Schrandt-Ulam rule: the first generations by hand", () => {
  const size = 15;
  const cells = (g) => [...g.on].flatMap((v, i) => (v ? [[(i % size) - 7, Math.floor(i / size) - 7]] : [])).sort().join(";");
  let s = suStart(size);
  assert.equal(cells(s), "0,0");
  s = suStep(s, size); // the four edge-neighbours of the middle
  assert.equal(cells(s), "-1,0;0,-1;0,0;0,1;1,0");
  s = suStep(s, size); // each of them turns on its far neighbour; the diagonal cells have two fresh neighbours and stay off
  assert.equal(countOn(s.on), 9);
  assert.equal(cells(s).includes("2,0") && cells(s).includes("0,-2") && !cells(s).includes("1,1"), true);
  s = suStep(s, size); // 13: the cells at distance 3 on the axes; the side cells next to them are cancelled by rule (c)
  assert.equal(countOn(s.on), 13);
  assert.equal(cells(s).includes("3,0") && !cells(s).includes("2,1"), true);
  // cells that are on never turn off
  const before = s.on.slice(); s = suStep(s, size);
  for (let i = 0; i < before.length; i++) if (before[i]) assert.equal(s.on[i], 1);
});
