// @ts-check
// The pure logic of the cellular automata interactive: elementary rules, Conway's Game of Life and the
// Schrandt-Ulam growth rule. No DOM, so the unit tests run it in Node.

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
 * The Schrandt-Ulam growth rule on a square grid that does not wrap (OEIS A170896, after Schrandt and Ulam):
 * cells that are on stay on, and only the cells that turned on in the last generation ("fresh" cells) try to turn
 * their four edge-neighbours on. An off cell Q turns on in the next generation when
 *   (a) exactly one of its edge-neighbours is on, and that one is a fresh cell P;
 *   (b) the two cells that touch Q only at a corner on the far side from P (its "outer squares") are not on; and
 *   (c) Q is not an outer square of another cell that passed (a) and (b) (both are then left off).
 * The state is `{ on, fresh }`, two size x size grids. Start from a single cell in the middle ({@link suStart}).
 */
export function suStep(/** @type {{ on: Uint8Array, fresh: Uint8Array }} */ state, /** @type {number} */ size) {
  const { on, fresh } = state;
  const at = (/** @type {Uint8Array} */ a, /** @type {number} */ x, /** @type {number} */ y) => (x >= 0 && y >= 0 && x < size && y < size && a[y * size + x] === 1 ? 1 : 0);
  const dirs = [[1, 0], [-1, 0], [0, 1], [0, -1]];
  /** @type {{ x: number, y: number, outer: number[][] }[]} */
  const prospects = [];
  for (let y = 0; y < size; y++) {
    for (let x = 0; x < size; x++) {
      if (on[y * size + x]) continue;
      let alive = 0, freshN = 0, dx = 0, dy = 0;
      for (const [ax, ay] of dirs) {
        alive += at(on, x + ax, y + ay);
        if (at(fresh, x + ax, y + ay)) { freshN++; dx = ax; dy = ay; }
      }
      if (alive !== 1 || freshN !== 1) continue;                        // (a)
      const outer = [[x - dx - dy, y - dy + dx], [x - dx + dy, y - dy - dx]];
      if (outer.some(([ox, oy]) => at(on, ox, oy))) continue;            // (b)
      prospects.push({ x, y, outer });
    }
  }
  const blocked = new Set(prospects.flatMap((p) => p.outer.map(([ox, oy]) => oy * size + ox)));
  const next = { on: on.slice(), fresh: new Uint8Array(size * size) };
  for (const p of prospects) {
    const i = p.y * size + p.x;
    if (blocked.has(i)) continue;                                        // (c)
    next.on[i] = 1; next.fresh[i] = 1;
  }
  return next;
}

export function suStart(/** @type {number} */ size) {
  const on = new Uint8Array(size * size), fresh = new Uint8Array(size * size);
  const middle = Math.floor(size / 2) * size + Math.floor(size / 2);
  on[middle] = 1; fresh[middle] = 1;
  return { on, fresh };
}

/** The number of cells on after g generations of the rule (g = 0 is the single cell): 1, 5, 9, 13, 25, 29, 41, ... */
export function suCounts(/** @type {number} */ generations) {
  const size = 2 * generations + 5;
  let state = suStart(size);
  const counts = [1];
  for (let g = 0; g < generations; g++) { state = suStep(state, size); counts.push(countOn(state.on)); }
  return counts;
}

/** What the learner reads under the picture. */
export function describeElementary(/** @type {number} */ rule, /** @type {number} */ generation, /** @type {Uint8Array} */ row) {
  return `Rule ${rule}, generation ${generation}: ${countOn(row)} cells on in this row.`;
}
