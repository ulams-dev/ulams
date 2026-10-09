// @ts-check
import { connect } from "./vendor/interactive-bridge.js";
import { startShell } from "./vendor/ulam-shell.js";
import { SIGMA, describeRun, estimate, expectedError, logPosition, newRun, throwPoints } from "./logic.js";

const $ = (/** @type {string} */ s) => /** @type {any} */ (document.querySelector(s));
const square = /** @type {HTMLCanvasElement} */ ($("#square"));
const chart = /** @type {HTMLCanvasElement} */ ($("#chart"));
const sctx = /** @type {CanvasRenderingContext2D} */ (square.getContext("2d"));
const cctx = /** @type {CanvasRenderingContext2D} */ (chart.getContext("2d"));

/** What the step shows when the learner opens it straight away (poster mode), so a poster is never empty. */
const POSTER_THROWS = { idea: 0, throw: 300, converge: 20_000, error: 100_000 };
const CHART_STEPS = new Set(["converge", "error"]);
const COMPLETE_AT = 10_000;

let run = newRun(2026);
let dpr = 1;
let sizeSq = 360;
let completed = false;
/** @type {any} */
let shell = null;

function seedValue() {
  const v = Math.round(Number($("#seed").value));
  return Number.isFinite(v) ? Math.min(999999, Math.max(1, v)) : 2026;
}

function layout() {
  dpr = Math.min(2, window.devicePixelRatio || 1);
  const stage = $("#ix-stage");
  const wide = (stage.clientWidth || 640) >= 900;
  const room = window.innerHeight - (square.getBoundingClientRect().top + window.scrollY) - 120;
  sizeSq = Math.max(220, Math.min(wide ? 420 : (stage.clientWidth || 640) - 8, 420, Math.max(220, room)));
  square.style.width = `${sizeSq}px`; square.style.height = `${sizeSq}px`;
  square.width = sizeSq * dpr; square.height = sizeSq * dpr;
  const cw = Math.max(260, Math.min(wide ? stage.clientWidth - sizeSq - 40 : (stage.clientWidth || 640) - 8, 560));
  chart.style.width = `${cw}px`; chart.style.height = `${Math.round(cw * 0.6)}px`;
  chart.width = cw * dpr; chart.height = Math.round(cw * 0.6) * dpr;
}

function drawSquare() {
  const s = sizeSq, pad = 14, side = s - 2 * pad;
  sctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  sctx.clearRect(0, 0, s, s);
  sctx.fillStyle = "#fffdf7"; sctx.fillRect(0, 0, s, s);
  const dot = run.drawn > 20_000 ? 1 : run.drawn > 2000 ? 1.5 : 2.5;
  // points: inside the quarter circle in blue, outside in red
  for (let i = 0; i < run.drawn; i++) {
    const x = run.xs[i], y = run.ys[i];
    sctx.fillStyle = x * x + y * y <= 1 ? "rgba(29,59,143,0.75)" : "rgba(178,58,46,0.75)";
    sctx.fillRect(pad + x * side - dot / 2, pad + (1 - y) * side - dot / 2, dot, dot);
  }
  sctx.strokeStyle = "#1e2230"; sctx.lineWidth = 1.5;
  sctx.strokeRect(pad, pad, side, side);
  sctx.beginPath(); sctx.arc(pad, pad + side, side, -Math.PI / 2, 0); sctx.stroke();
  sctx.fillStyle = "#4a4f60"; sctx.font = "12px system-ui, sans-serif";
  sctx.fillText("1", pad + side - 8, pad + side + 12); sctx.fillText("1", 2, pad + 10); sctx.fillText("0", 2, pad + side + 12);
  square.setAttribute("aria-label", run.total === 0
    ? "A unit square with a quarter circle. No points yet."
    : `A unit square with a quarter circle and ${run.total.toLocaleString("en")} random points, ${run.inside.toLocaleString("en")} of them inside the circle.`);
}

function drawChart() {
  const w = chart.width / dpr, h = chart.height / dpr, L = 52, R = 12, T = 12, B = 34;
  const xMin = 1, xMax = 100_000, yMin = 1e-4, yMax = 10;
  const X = (n) => L + logPosition(n, xMin, xMax, w - L - R);
  const Y = (e) => h - B - logPosition(e, yMin, yMax, h - T - B);
  cctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  cctx.clearRect(0, 0, w, h);
  cctx.fillStyle = "#fffdf7"; cctx.fillRect(0, 0, w, h);
  cctx.font = "11px system-ui, sans-serif"; cctx.fillStyle = "#4a4f60"; cctx.strokeStyle = "rgba(30,34,48,0.15)"; cctx.lineWidth = 1;
  for (let e = 0; e <= 5; e++) { const n = 10 ** e; cctx.beginPath(); cctx.moveTo(X(n), T); cctx.lineTo(X(n), h - B); cctx.stroke(); cctx.textAlign = "center"; cctx.fillText(n >= 1000 ? `${n / 1000}k` : String(n), X(n), h - B + 14); }
  for (let e = -4; e <= 1; e++) { const v = 10 ** e; cctx.beginPath(); cctx.moveTo(L, Y(v)); cctx.lineTo(w - R, Y(v)); cctx.stroke(); cctx.textAlign = "right"; cctx.fillText(String(v), L - 6, Y(v) + 4); }
  cctx.textAlign = "center"; cctx.fillText("throws", (L + w - R) / 2, h - 6);
  cctx.save(); cctx.translate(12, (T + h - B) / 2); cctx.rotate(-Math.PI / 2); cctx.fillText("error", 0, 0); cctx.restore();
  // the error to expect
  cctx.setLineDash([6, 4]); cctx.strokeStyle = "#b23a2e"; cctx.lineWidth = 1.5; cctx.beginPath();
  cctx.moveTo(X(xMin), Y(SIGMA / Math.sqrt(xMin))); cctx.lineTo(X(xMax), Y(SIGMA / Math.sqrt(xMax))); cctx.stroke(); cctx.setLineDash([]);
  // the run
  cctx.fillStyle = "#1d3b8f";
  for (const s of run.samples) { cctx.beginPath(); cctx.arc(X(s.n), Y(s.error), 3, 0, Math.PI * 2); cctx.fill(); }
  chart.setAttribute("aria-label", run.samples.length
    ? `Log-log chart: after ${run.total.toLocaleString("en")} throws the error is ${Math.abs(estimate(run.inside, run.total) - Math.PI).toFixed(4)}, close to the expected ${expectedError(run.total).toFixed(4)}.`
    : "Log-log chart of the error of the estimate against the number of throws. No data yet.");
}

function fillTable() {
  const rows = run.samples.filter((_, i, a) => a.length <= 40 || i % Math.ceil(a.length / 40) === 0 || i === a.length - 1);
  $("#data tbody").innerHTML = rows.map((s) => `<tr><td>${s.n.toLocaleString("en")}</td><td>${estimate(s.inside, s.n).toFixed(4)}</td><td>${s.error.toFixed(4)}</td></tr>`).join("");
}

function refresh() {
  drawSquare(); drawChart(); fillTable();
  $("#result").textContent = describeRun(run);
  if (!completed && run.total >= COMPLETE_AT && shell) { completed = true; shell.complete(); }
}

function throwMore(n) {
  throwPoints(run, n);
  refresh();
}

function reset() {
  run = newRun(seedValue());
  refresh();
}

$("#buttons").addEventListener("click", (e) => {
  const b = /** @type {HTMLElement} */ (e.target).closest("button");
  if (!b) return;
  if (b.id === "reset") reset(); else throwMore(Number(b.getAttribute("data-n")));
});
$("#seed").addEventListener("change", () => { $("#seed").value = String(seedValue()); reset(); });
addEventListener("resize", () => { layout(); refresh(); });

shell = await startShell({
  connect,
  render(step, sh) {
    $("#chart-box").hidden = !CHART_STEPS.has(step.id);
    for (const b of document.querySelectorAll("#buttons button")) /** @type {HTMLButtonElement} */ (b).disabled = step.id === "idea" && !sh.poster;
    layout();
    if (sh.poster && run.total === 0) throwPoints(run, POSTER_THROWS[step.id] ?? 0);
    refresh();
  },
  onResume() { refresh(); },
});
