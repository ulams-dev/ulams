// @ts-check
import { connect } from "./vendor/interactive-bridge.js";
import { startShell } from "./vendor/ulam-shell.js";
import { greatCircle, legText, legs, viewBox } from "./logic.js";

const Atlas = /** @type {any} */ (window).Atlas;
const $ = (/** @type {string} */ s) => /** @type {any} */ (document.querySelector(s));
const NS = "http://www.w3.org/2000/svg";

/** @type {{ placeholder?: boolean, note?: string, caption: string, stops: import("./logic.js").Stop[] }} */
let route = { caption: "", stops: [] };
/** @type {any} */ let places = null;
/** @type {any} */ let topo = null;
/** @type {any} */ let atlas = null;
let current = 0;
let scheduled = false;

const loading = Promise.all(["data/route.json", "data/places.json", "data/world.topo.json"].map((u) => fetch(u).then((r) => { if (!r.ok) throw new Error(`${u} ${r.status}`); return r.json(); })))
  .then(([r, p, t]) => { route = r; places = p; topo = t; });

function makeViews() {
  /** @type {Record<string, any>} */
  const views = {};
  for (const s of route.stops) {
    const first = s.id === "lwow";
    views[s.id] = { bb: viewBox(s.lonlat, first ? 14 : 9, first ? 7 : 5.5), hi: first ? { 804: "hi", 616: "hi2" } : { 840: "hi" } };
  }
  return views;
}

const schedule = () => { if (scheduled) return; scheduled = true; requestAnimationFrame(() => { scheduled = false; draw(); }); };

function draw() {
  if (!atlas || !atlas.state.cur) return;
  const A = atlas.state, ctx = atlas.ctx, S = atlas.S, P = Atlas.P;
  ctx.setTransform(A.DPR, 0, 0, A.DPR, 0, 0);
  ctx.clearRect(0, 0, A.W, A.H);
  // the legs: travelled ones solid, the rest dashed
  legs(route.stops).forEach((leg, i) => {
    const pts = greatCircle(leg.from.lonlat, leg.to.lonlat).map((ll) => S(P(ll)));
    ctx.beginPath(); pts.forEach((p, k) => (k ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1])));
    ctx.setLineDash(i < current ? [] : [6, 5]); ctx.lineWidth = i < current ? 2.5 : 1.5;
    ctx.strokeStyle = i < current ? "#1d3b8f" : "rgba(30,34,48,0.4)"; ctx.stroke(); ctx.setLineDash([]);
  });
  route.stops.forEach((s, i) => {
    const p = S(P(s.lonlat));
    if (p[0] < -20 || p[0] > A.W + 20 || p[1] < -20 || p[1] > A.H + 20) return;
    const here = i === current;
    ctx.beginPath(); ctx.arc(p[0], p[1], here ? 8 : 5, 0, Math.PI * 2);
    ctx.fillStyle = here ? "#b23a2e" : i < current ? "#1d3b8f" : "#fffdf7"; ctx.fill();
    ctx.lineWidth = 2; ctx.strokeStyle = here ? "#fffdf7" : "#1e2230"; ctx.stroke();
    atlas.label(s.name, p[0] + (here ? 13 : 10), p[1] - 10, "left", here ? 1 : 0.85, { color: "#1e2230", outline: "247,243,232", size: here ? 13 : 12, w: here ? 700 : 500, ls: 0, family: "system-ui, sans-serif" });
  });
  $("#fx").setAttribute("aria-label", `Map of the journey. Stop ${current + 1} of ${route.stops.length}: ${route.stops[current].name}.`);
}

function drawInset(/** @type {boolean} */ show) {
  const box = $("#inset-box");
  box.hidden = !show;
  if (!show || !places) return;
  const svg = $("#inset");
  svg.replaceChildren();
  const el = (/** @type {string} */ tag, /** @type {Record<string, string>} */ attrs, /** @type {string} */ text = "") => { const e = document.createElementNS(NS, tag); for (const k in attrs) e.setAttribute(k, attrs[k]); if (text) e.textContent = text; svg.append(e); return e; };
  el("rect", { x: "1", y: "1", width: "298", height: "188", fill: "#fffdf7", stroke: "#4a4f60", "stroke-dasharray": "4 3" });
  for (const p of places.inset.points) {
    const x = 24 + p.x * 252, y = 22 + p.y * 146;
    el("circle", { cx: String(x), cy: String(y), r: "6", fill: "#1d3b8f", stroke: "#fffdf7", "stroke-width": "2" });
    el("text", { x: String(x + 10), y: String(y + 4), "font-size": "11", fill: "#1e2230", "font-family": "system-ui, sans-serif" }, p.label.replace(" (placeholder)", ""));
  }
  el("text", { x: "280", y: "20", "font-size": "11", fill: "#4a4f60", "text-anchor": "end", "font-family": "system-ui, sans-serif" }, "N ↑");
  svg.setAttribute("aria-label", `${places.inset.title}: ${places.inset.points.map((p) => p.label.replace(" (placeholder)", "")).join("; ")}. Positions are placeholders.`);
  box.querySelector("figcaption").textContent = places.inset.caption;
}

function buildStops(/** @type {any} */ sh) {
  const ol = $("#stops");
  ol.replaceChildren();
  route.stops.forEach((s) => {
    const li = document.createElement("li"), b = document.createElement("button");
    b.type = "button"; b.textContent = s.name; b.dataset.id = s.id;
    b.addEventListener("click", () => sh.goTo(s.id));
    li.append(b); ol.append(li);
  });
}

function paintStops(/** @type {any} */ sh) {
  for (const b of $("#stops").querySelectorAll("button")) {
    const i = route.stops.findIndex((s) => s.id === b.dataset.id);
    if (i === current) b.setAttribute("aria-current", "step"); else b.removeAttribute("aria-current");
    b.disabled = i < sh.range.lo || i > sh.range.hi;
  }
}

function render(/** @type {{id: string}} */ step, /** @type {any} */ sh) {
  if (!atlas) {
    buildStops(sh);
    atlas = Atlas.create({
      svg: $("#map"), canvas: $("#fx"), stage: $("#map-wrap"), topo, views: makeViews(), fit: "stage",
      focusRect: (/** @type {string} */ _n, /** @type {number} */ w, /** @type {number} */ h) => ({ x: 12, y: 12, w: w - 24, h: h - 24 }),
      isReduced: () => sh.reduced, onChange: schedule,
    });
    atlas.measure();
    addEventListener("resize", () => { if (atlas.measure()) { atlas.reframe(); schedule(); } });
  }
  current = Math.max(0, route.stops.findIndex((s) => s.id === step.id));
  const banner = $("#banner");
  banner.hidden = !route.placeholder; banner.textContent = route.note || "";
  $("#caption").textContent = route.caption;
  $("#leg").textContent = legText(route.stops, current);
  paintStops(sh);
  drawInset(step.id === "lwow");
  atlas.setView(step.id, sh.poster || sh.reduced);
  draw();
}

await startShell({ connect, load: loading, render, onResume() { schedule(); } });
