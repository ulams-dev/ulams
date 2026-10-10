// @ts-check
import { connect } from "./vendor/interactive-bridge.js";
import { startShell } from "./vendor/ulam-shell.js";
import { cellOf, describeNumber, gridSize, indexAtCell, primeFlags, eulerValuesIn, spiralPositions, summarise } from "./logic.js";

const $ = (/** @type {string} */ s) => /** @type {any} */ (document.querySelector(s));
const canvas = /** @type {HTMLCanvasElement} */ ($("#sp"));
const ctx = /** @type {CanvasRenderingContext2D} */ (canvas.getContext("2d"));

/** Per-step settings: what the picture shows when the learner arrives. */
const PRESETS = {
  grid: { count: 100, start: 1, primes: false, poly: false },
  primes: { count: 2500, start: 1, primes: true, poly: false },
  diagonals: { count: 1600, start: 41, primes: true, poly: true },
  explore: { count: 10000, start: 1, primes: true, poly: false },
};

const state = { count: 2500, start: 1, primes: true, poly: false, cursor: 0, reveal: Infinity };
/** Showcase (landing hero): the spiral winds outwards slowly, number by number, and the picture is all there is. */
let showcase = false, paused = false, revealFrame = 0;
const REVEAL_MS = 6500;
/** @type {Uint8Array} */ let flags = new Uint8Array(0), positions = spiralPositions(0), size = 1, px = 4, polySet = new Set();

function readControls() {
  const clamp = (v, lo, hi, d) => (Number.isFinite(v) ? Math.min(hi, Math.max(lo, Math.round(v))) : d);
  state.count = clamp(Number($("#count").value), 100, 40000, state.count);
  state.start = clamp(Number($("#start").value), 1, 1000000, state.start);
  state.primes = $("#show-primes").checked;
  state.poly = $("#show-poly").checked;
}
function writeControls() {
  $("#count").value = String(state.count); $("#count-range").value = String(state.count); $("#start").value = String(state.start);
  $("#show-primes").checked = state.primes; $("#show-poly").checked = state.poly;
}

function layout() {
  const stage = $("#ix-stage");
  // beside the text when there is room, above it otherwise; never taller than what is left of the frame
  const wide = (stage.clientWidth || 640) >= 900;
  const room = window.innerHeight - (canvas.getBoundingClientRect().top + window.scrollY) - 20;
  const avail = showcase
    ? Math.max(120, Math.min(stage.clientWidth || 640, window.innerHeight) - 16)
    : Math.max(200, Math.min(wide ? stage.clientWidth - 320 : stage.clientWidth || 640, 720, room));
  px = Math.max(2, Math.floor(avail / size));
  const css = size * px, dpr = Math.min(2, window.devicePixelRatio || 1);
  canvas.style.width = `${css}px`; canvas.style.height = `${css}px`;
  canvas.width = css * dpr; canvas.height = css * dpr;
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
}

function recompute() {
  flags = primeFlags(state.start, state.count);
  positions = spiralPositions(state.count);
  size = gridSize(state.count);
  polySet = state.poly ? eulerValuesIn(state.start, state.start + state.count - 1) : new Set();
  state.cursor = Math.min(state.cursor, state.count - 1);
  layout();
  draw();
  $("#summary").textContent = summarise({ start: state.start, count: state.count, flags, polynomial: state.poly }).text;
  describeCursor(false);
}

function draw() {
  const css = size * px;
  ctx.clearRect(0, 0, css, css);
  ctx.fillStyle = "#fffdf7"; ctx.fillRect(0, 0, css, css);
  const showNumbers = px >= 22;
  ctx.font = `${Math.max(9, Math.min(14, px * 0.38))}px system-ui, sans-serif`;
  ctx.textAlign = "center"; ctx.textBaseline = "middle";
  const shown = Math.min(state.count, Math.ceil(state.reveal));
  for (let i = 0; i < shown; i++) {
    const { col, row } = cellOf(positions.x[i], positions.y[i], size);
    const x = col * px, y = row * px, n = state.start + i, prime = flags[i] === 1;
    if (state.primes && prime) { ctx.fillStyle = "#1d3b8f"; ctx.fillRect(x + (px > 4 ? 0.5 : 0), y + (px > 4 ? 0.5 : 0), px - (px > 4 ? 1 : 0), px - (px > 4 ? 1 : 0)); }
    else if (px >= 6) { ctx.strokeStyle = "rgba(30,34,48,0.08)"; ctx.strokeRect(x + 0.5, y + 0.5, px - 1, px - 1); }
    if (polySet.has(n)) { ctx.strokeStyle = "#b23a2e"; ctx.lineWidth = px > 6 ? 2 : 1; ctx.strokeRect(x + 1, y + 1, px - 2, px - 2); ctx.lineWidth = 1; }
    if (showNumbers) { ctx.fillStyle = state.primes && prime ? "#ffffff" : "#1e2230"; ctx.fillText(String(n), x + px / 2, y + px / 2); }
  }
  if (showcase) return; // no cursor in the hero
  // the cursor
  const { col, row } = cellOf(positions.x[state.cursor], positions.y[state.cursor], size);
  ctx.strokeStyle = "#1e2230"; ctx.lineWidth = 3; ctx.strokeRect(col * px + 1.5, row * px + 1.5, px - 3, px - 3); ctx.lineWidth = 1;
}

/** Reveals the spiral from the centre outwards over REVEAL_MS (a still with reduced motion). */
function startReveal(reduced) {
  cancelAnimationFrame(revealFrame);
  if (reduced) { state.reveal = Infinity; draw(); return; }
  state.reveal = 0; draw();
  let last = performance.now(), elapsed = 0;
  const tick = (now) => {
    if (!paused) elapsed += now - last;
    last = now;
    state.reveal = Math.min(state.count, (elapsed / REVEAL_MS) * state.count);
    if (!paused) draw();
    if (elapsed < REVEAL_MS) revealFrame = requestAnimationFrame(tick);
    else { state.reveal = Infinity; draw(); }
  };
  revealFrame = requestAnimationFrame(tick);
}

function describeCursor(announce = true) {
  const n = state.start + state.cursor;
  $("#cell").textContent = describeNumber(n, flags[state.cursor] === 1) + ` (position ${state.cursor + 1} of ${state.count.toLocaleString("en")} on the spiral)`;
  if (announce) draw();
}

function moveCursor(dx, dy) {
  const { x, y } = { x: positions.x[state.cursor] + dx, y: positions.y[state.cursor] + dy };
  const mid = (size - 1) / 2;
  const i = indexAtCell(x + mid, mid - y, size, positions, state.count);
  if (i >= 0) { state.cursor = i; describeCursor(); }
}

canvas.addEventListener("keydown", (e) => {
  const k = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, 1], ArrowDown: [0, -1] }[e.key];
  if (k) { e.preventDefault(); moveCursor(k[0], k[1]); }
  else if (e.key === "Home") { e.preventDefault(); state.cursor = 0; describeCursor(); }
  else if (e.key === "Enter" || e.key === " ") { e.preventDefault(); describeCursor(); }
});
canvas.addEventListener("click", (e) => {
  const r = canvas.getBoundingClientRect();
  const i = indexAtCell(Math.floor((e.clientX - r.left) / px), Math.floor((e.clientY - r.top) / px), size, positions, state.count);
  if (i >= 0) { state.cursor = i; describeCursor(); canvas.focus(); }
});

for (const id of ["#count", "#start", "#show-primes", "#show-poly"]) $(id).addEventListener("change", () => { readControls(); writeControls(); recompute(); });
$("#count-range").addEventListener("input", () => { $("#count").value = $("#count-range").value; readControls(); recompute(); });
addEventListener("resize", () => { if (flags.length) { layout(); draw(); } });

await startShell({
  connect,
  onPause() { paused = true; },
  onResume() { paused = false; draw(); },
  render(step, sh) {
    showcase = sh.showcase;
    const preset = PRESETS[step.id];
    if (preset) { Object.assign(state, preset, { cursor: 0 }); writeControls(); }
    state.reveal = showcase && !sh.reduced ? 0 : Infinity;
    recompute();
    if (showcase) startReveal(sh.reduced);
    if (step.id === "explore") sh.complete();
    if (sh.poster) document.title = `${step.title} · The Ulam spiral`;
  },
});
