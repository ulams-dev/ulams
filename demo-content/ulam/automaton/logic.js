// @ts-check
// The pure logic of the cellular automata interactive: elementary rules, Conway's Game of Life and the
// Ulam-Warburton growth rule. No DOM, so the unit tests run it in Node.

/** @param {number} rule 0 … 255 @returns {Uint8Array} out[v] is the next state for a neighbourhood v = left·4 + centre·2 + right */
export function ruleTable(rule) {
  const t = new Uint8Array(8);
  for (let v = 0; v < 8; v++) t[v] = (rule >> v) & 1;
  return t;
}

/** One generation of an elementary automaton on a ring of cells. */
export function elementaryNext(/** @type {Uint8Array} */ cells, /** @type {number} */ rule) {
  const t = ruleTable(rule), n = cells.length, out = new Uint8Array(n);
  for (let i = 0; i < n; i++) out[i] = t[(cells[(i + n - 1) % n] << 2) | (cells[i] << 1) | cells[(i + 1) % n]];
  return out;
}

/** The first row: one live cell in the middle. */
export function singleCell(/** @type {number} */ width) {
  const row = new Uint8Array(width);
  row[Math.floor(width / 2)] = 1;
  return row;
}

/** The rows of an elementary automaton: rows[0] is the start, rows[g] the generation g. */
export function evolveElementary(/** @type {number} */ rule, /** @type {number} */ width, /** @type {number} */ generations) {
  const rows = [singleCell(width)];
  for (let g = 0; g < generations; g++) rows.push(elementaryNext(rows[g], rule));
  return rows;
}

export const countOn = (/** @type {Uint8Array} */ cells) => cells.reduce((a, b) => a + b, 0);

/** Conway's Game of Life on a torus (cells leave one edge and arrive at the opposite one). */
export function lifeStep(/** @type {Uint8Array} */ grid, /** @type {number} */ w, /** @type {number} */ h) {
  const out = new Uint8Array(grid.length);
  for (let y = 0; y < h; y++) {
    for (let x = 0; x < w; x++) {
      let n = 0;
      for (let dy = -1; dy <= 1; dy++) for (let dx = -1; dx <= 1; dx++) if (dx || dy) n += grid[((y + dy + h) % h) * w + ((x + dx + w) % w)];
      const alive = grid[y * w + x];
      out[y * w + x] = n === 3 || (alive && n === 2) ? 1 : 0;
    }
  }
  return out;
}

/** Presets as lists of [x, y] cells relative to the pattern's top-left corner. */
export const LIFE_PRESETS = {
  glider: [[1, 0], [2, 1], [0, 2], [1, 2], [2, 2]],
  blinker: [[0, 0], [1, 0], [2, 0]],
  toad: [[1, 0], [2, 0], [3, 0], [0, 1], [1, 1], [2, 1]],
  "r-pentomino": [[1, 0], [2, 0], [0, 1], [1, 1], [1, 2]],
  acorn: [[1, 0], [3, 1], [0, 2], [1, 2], [4, 2], [5, 2], [6, 2]],
};

/** A grid with a preset placed near the middle. */
export function placePreset(/** @type {keyof typeof LIFE_PRESETS} */ name, /** @type {number} */ w, /** @type {number} */ h) {
  const grid = new Uint8Array(w * h), cells = LIFE_PRESETS[name];
  const maxX = Math.max(...cells.map((c) => c[0])), maxY = Math.max(...cells.map((c) => c[1]));
  const ox = Math.floor((w - maxX) / 2), oy = Math.floor((h - maxY) / 2);
  for (const [x, y] of cells) grid[(oy + y) * w + ox + x] = 1;
  return grid;
}

/**
 * The Ulam-Warburton growth rule on a square grid that does not wrap: an off cell turns on when exactly one of
 * its four orthogonal neighbours is on. Start from a single cell in the middle.
 */
export function uwStep(/** @type {Uint8Array} */ grid, /** @type {number} */ size) {
  const out = grid.slice();
  for (let y = 0; y < size; y++) {
    for (let x = 0; x < size; x++) {
      if (grid[y * size + x]) continue;
      const n = (x > 0 ? grid[y * size + x - 1] : 0) + (x < size - 1 ? grid[y * size + x + 1] : 0) + (y > 0 ? grid[(y - 1) * size + x] : 0) + (y < size - 1 ? grid[(y + 1) * size + x] : 0);
      if (n === 1) out[y * size + x] = 1;
    }
  }
  return out;
}

export function uwStart(/** @type {number} */ size) {
  const grid = new Uint8Array(size * size);
  grid[Math.floor(size / 2) * size + Math.floor(size / 2)] = 1;
  return grid;
}

/** The number of live cells after g generations of the growth rule (g = 0 is the single cell). */
export function uwCounts(/** @type {number} */ generations) {
  const size = 2 * generations + 3;
  let grid = uwStart(size);
  const counts = [1];
  for (let g = 0; g < generations; g++) { grid = uwStep(grid, size); counts.push(countOn(grid)); }
  return counts;
}

/** What the learner reads under the picture. */
export function describeElementary(/** @type {number} */ rule, /** @type {number} */ generation, /** @type {Uint8Array} */ row) {
  return `Rule ${rule}, generation ${generation}: ${countOn(row)} cells on in this row.`;
}
