// @ts-check
import { connect } from "./vendor/interactive-bridge.js";
import { startShell } from "./vendor/ulam-shell.js";
import { countOn, describeElementary, elementaryNext, lifeStep, placePreset, singleCell, suStart, suStep } from "./logic.js";

const $ = (/** @type {string} */ s) => /** @type {any} */ (document.querySelector(s));
const canvas = /** @type {HTMLCanvasElement} */ ($("#ca"));
const ctx = /** @type {CanvasRenderingContext2D} */ (canvas.getContext("2d"));

const WIDTH = 201, MAX_ELEMENTARY = 100;        // elementary: 201 cells wide, 100 generations
const LIFE_W = 64, LIFE_H = 40;                 // Life: a 64 x 40 torus
const UW_MAX = 60, UW_SIZE = 2 * UW_MAX + 3;    // growth rule: the pattern never reaches the edge
const SPEED = { elementary: 20, life: 8, uw: 8 }; // generations per second while playing

const MODES = {
  rule30: { mode: "elementary", rule: 30 },
  rule90: { mode: "elementary", rule: 90 },
  rule110: { mode: "elementary", rule: 110 },
  life: { mode: "life" },
  "ulam-growth": { mode: "uw" },
};
const HINTS = {
  elementary: "Each row is the next generation: a cell's new state depends on itself and its two neighbours, and the rule number encodes the answer for all eight cases. Try rules 30, 90 and 110, or any number from 0 to 255.",
  life: "Cells live on with two or three live neighbours and are born with exactly three. Focus the picture, move with the arrow keys and press Space to toggle a cell. The grid wraps around its edges.",
  uw: "The Schrandt-Ulam rule (R. G. Schrandt and S. M. Ulam; OEIS A170896): cells that are on stay on. A cell turns on when exactly one of its four edge-neighbours is on and that neighbour turned on in the last generation, unless the two cells touching it at the far corners are already on, or it is a far corner of another new cell.",
};

const state = {
  mode: "elementary", rule: 30, gen: 0,
  /** @type {Uint8Array[]} */ rows: [],
  life: new Uint8Array(LIFE_W * LIFE_H), lifeGen: 0, cursor: { x: 0, y: 0 },
  uw: suStart(UW_SIZE), uwAge: new Uint8Array(UW_SIZE * UW_SIZE), uwGen: 0,
  playing: false, held: false,
};
let cell = 3, px = 0;
let last = 0, acc = 0;
/** @type {any} */
let shell = null;

function resetElementary() { state.rows = [singleCell(WIDTH)]; state.gen = 0; }
function resetLife() { state.life = placePreset($("#preset").value, LIFE_W, LIFE_H); state.lifeGen = 0; }
function resetUw() { state.uw = suStart(UW_SIZE); state.uwAge = new Uint8Array(UW_SIZE * UW_SIZE); state.uwGen = 0; }
function resetCurrent() {
  state.playing = false;
  if (state.mode === "elementary") resetElementary(); else if (state.mode === "life") resetLife(); else resetUw();
  refresh();
}

function stepOnce() {
  if (state.mode === "elementary") {
    if (state.gen >= MAX_ELEMENTARY) return false;
    state.rows.push(elementaryNext(state.rows[state.gen], state.rule)); state.gen++;
  } else if (state.mode === "life") {
    state.life = lifeStep(state.life, LIFE_W, LIFE_H); state.lifeGen++;
  } else {
    if (state.uwGen >= UW_MAX) return false;
    state.uw = suStep(state.uw, UW_SIZE); state.uwGen++;
    for (let i = 0; i < state.uw.fresh.length; i++) if (state.uw.fresh[i]) state.uwAge[i] = state.uwGen;
  }
  return true;
}
function runTo(count) { for (let i = 0; i < count; i++) if (!stepOnce()) break; refresh(); }
function runToEnd() { runTo(state.mode === "elementary" ? MAX_ELEMENTARY : state.mode === "uw" ? UW_MAX : 50); }

function layout() {
  const stage = $("#ix-stage");
  const cols = state.mode === "elementary" ? WIDTH : state.mode === "life" ? LIFE_W : UW_SIZE;
  const rows = state.mode === "elementary" ? MAX_ELEMENTARY + 1 : state.mode === "life" ? LIFE_H : UW_SIZE;
  const room = window.innerHeight - (canvas.getBoundingClientRect().top + window.scrollY) - 100; // leave room for the status line and the hint
  const avail = Math.min(stage.clientWidth || 640, 760);
  cell = Math.max(2, Math.floor(Math.min(avail / cols, Math.max(120, room) / rows)));
  const w = cols * cell, h = rows * cell, dpr = Math.min(2, window.devicePixelRatio || 1);
  px = dpr;
  canvas.style.width = `${w}px`; canvas.style.height = `${h}px`;
  canvas.width = w * dpr; canvas.height = h * dpr;
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
}

function draw() {
  const w = canvas.width / px, h = canvas.height / px;
  ctx.clearRect(0, 0, w, h);
  ctx.fillStyle = "#fffdf7"; ctx.fillRect(0, 0, w, h);
  if (state.mode === "elementary") {
    ctx.fillStyle = "#1e2230";
    for (let g = 0; g <= state.gen; g++) { const row = state.rows[g]; for (let x = 0; x < WIDTH; x++) if (row[x]) ctx.fillRect(x * cell, g * cell, cell, cell); }
  } else if (state.mode === "life") {
    if (cell >= 6) { ctx.strokeStyle = "rgba(30,34,48,0.1)"; for (let x = 0; x <= LIFE_W; x++) { ctx.beginPath(); ctx.moveTo(x * cell + 0.5, 0); ctx.lineTo(x * cell + 0.5, h); ctx.stroke(); } for (let y = 0; y <= LIFE_H; y++) { ctx.beginPath(); ctx.moveTo(0, y * cell + 0.5); ctx.lineTo(w, y * cell + 0.5); ctx.stroke(); } }
    ctx.fillStyle = "#1d3b8f";
    for (let y = 0; y < LIFE_H; y++) for (let x = 0; x < LIFE_W; x++) if (state.life[y * LIFE_W + x]) ctx.fillRect(x * cell + 1, y * cell + 1, cell - 2 > 0 ? cell - 2 : cell, cell - 2 > 0 ? cell - 2 : cell);
    if (document.activeElement === canvas) { ctx.strokeStyle = "#b23a2e"; ctx.lineWidth = 2; ctx.strokeRect(state.cursor.x * cell + 1, state.cursor.y * cell + 1, cell - 2, cell - 2); ctx.lineWidth = 1; }
  } else {
    for (let y = 0; y < UW_SIZE; y++) for (let x = 0; x < UW_SIZE; x++) {
      const i = y * UW_SIZE + x;
      if (!state.uw.on[i]) continue;
      ctx.fillStyle = state.uwAge[i] === state.uwGen && state.uwGen > 0 ? "#b23a2e" : "#1d3b8f";
      ctx.fillRect(x * cell, y * cell, cell, cell);
    }
  }
}

function statusText() {
  if (state.mode === "elementary") return `${describeElementary(state.rule, state.gen, state.rows[state.gen])} ${state.gen >= MAX_ELEMENTARY ? "The picture is full: reset to start again." : ""}`.trim();
  if (state.mode === "life") return `Generation ${state.lifeGen}: ${countOn(state.life)} live cells.`;
  return `Generation ${state.uwGen}: ${countOn(state.uw.on)} cells on${state.uwGen >= UW_MAX ? " (the largest picture this page draws)" : ""}.`;
}

function refresh() {
  draw();
  $("#status").textContent = statusText();
  canvas.setAttribute("aria-label",
    state.mode === "elementary" ? `Rule ${state.rule} from one cell, ${state.gen} generations drawn, one row each, ${countOn(state.rows[state.gen])} cells on in the last row.`
    : state.mode === "life" ? `Game of Life grid, generation ${state.lifeGen}, ${countOn(state.life)} live cells. Arrow keys move the cursor, Space toggles a cell.`
    : `Growth from one cell, generation ${state.uwGen}, ${countOn(state.uw.on)} cells on.`);
  $("#play").textContent = state.playing ? "Pause" : "Play";
  $("#play").setAttribute("aria-pressed", String(state.playing));
}

function setMode(mode, rule) {
  state.mode = mode; state.playing = false;
  if (mode === "elementary") { state.rule = rule; $("#rule").value = String(rule); resetElementary(); }
  else if (mode === "life") resetLife(); else resetUw();
  $("#rule-field").hidden = mode !== "elementary"; $("#preset-field").hidden = mode !== "life";
  $("#hint").textContent = HINTS[mode];
  canvas.tabIndex = mode === "life" ? 0 : -1;
  canvas.setAttribute("role", mode === "life" ? "application" : "img");
  layout(); refresh();
}

function loop(now) {
  if (!state.playing) return;
  if (state.held || document.hidden) { last = now; requestAnimationFrame(loop); return; }
  acc += Math.min(0.25, (now - last) / 1000) * SPEED[state.mode]; last = now;
  let advanced = false;
  while (acc >= 1) { acc -= 1; if (!stepOnce()) { state.playing = false; break; } advanced = true; }
  if (advanced || !state.playing) refresh();
  if (state.playing) requestAnimationFrame(loop);
}
function play() {
  if (shell && shell.reduced) return;
  state.playing = !state.playing;
  if (state.playing) { last = performance.now(); acc = 0; requestAnimationFrame(loop); }
  refresh();
}

$("#step").addEventListener("click", () => { state.playing = false; stepOnce(); refresh(); });
$("#play").addEventListener("click", play);
$("#run").addEventListener("click", () => { state.playing = false; runToEnd(); });
$("#reset").addEventListener("click", resetCurrent);
$("#rule").addEventListener("change", () => {
  const v = Math.round(Number($("#rule").value));
  state.rule = Number.isFinite(v) ? Math.min(255, Math.max(0, v)) : state.rule;
  $("#rule").value = String(state.rule);
  resetCurrent();
});
$("#preset").addEventListener("change", resetCurrent);
canvas.addEventListener("keydown", (e) => {
  if (state.mode !== "life") return;
  const moves = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[e.key];
  if (moves) { e.preventDefault(); state.cursor.x = (state.cursor.x + moves[0] + LIFE_W) % LIFE_W; state.cursor.y = (state.cursor.y + moves[1] + LIFE_H) % LIFE_H; refresh(); shell.announce(`Row ${state.cursor.y + 1}, column ${state.cursor.x + 1}: ${state.life[state.cursor.y * LIFE_W + state.cursor.x] ? "alive" : "empty"}`); }
  else if (e.key === " " || e.key === "Enter") { e.preventDefault(); const i = state.cursor.y * LIFE_W + state.cursor.x; state.life[i] = state.life[i] ? 0 : 1; refresh(); shell.announce(state.life[i] ? "Cell is now alive" : "Cell is now empty"); }
});
canvas.addEventListener("click", (e) => {
  if (state.mode !== "life") return;
  const r = canvas.getBoundingClientRect();
  const x = Math.floor((e.clientX - r.left) / cell), y = Math.floor((e.clientY - r.top) / cell);
  if (x < 0 || y < 0 || x >= LIFE_W || y >= LIFE_H) return;
  state.cursor = { x, y }; const i = y * LIFE_W + x; state.life[i] = state.life[i] ? 0 : 1; refresh();
});
canvas.addEventListener("focus", draw); canvas.addEventListener("blur", draw);
addEventListener("resize", () => { layout(); refresh(); });

shell = await startShell({
  connect,
  render(step, sh) {
    const m = MODES[step.id];
    $("#play").hidden = sh.reduced; // no animation: Step and Run to the end instead
    setMode(m.mode, m.rule);
    if (sh.poster) runTo(m.mode === "life" ? 100 : m.mode === "uw" ? 40 : MAX_ELEMENTARY);
    if (sh.visited.size >= 3) sh.complete();
  },
  onPause() { state.held = true; },
  onResume() { state.held = false; last = performance.now(); },
});
