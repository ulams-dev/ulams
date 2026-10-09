/*! ulams demo-content atlas: a small Mercator map engine (SVG land, canvas overlay, smooth camera).
 *  MIT License, Copyright (c) 2026 Mateusz Wojczal. Extracted from the poland package so that the
 *  poland and Ulam (lwow-map) packages use one copy; sync-vendor copies it into each package's vendor/.
 *  Plain ES5, no dependencies. Works as a classic script (window.Atlas) and as a CommonJS module. */
(function (root, factory) {
  if (typeof module === "object" && module.exports) module.exports = factory();
  else /** @type {any} */ (root).Atlas = factory();
})(typeof self !== "undefined" ? self : this, function () {
  "use strict";

  var S0 = 1000;
  var RAD = Math.PI / 180;
  var NS = "http://www.w3.org/2000/svg";

  /** lon/lat to Mercator plane units (y grows downwards). */
  function P(ll) {
    var la = Math.max(-85, Math.min(85, ll[1]));
    return [S0 * ll[0] * RAD, -S0 * Math.log(Math.tan(Math.PI / 4 + (la * RAD) / 2))];
  }
  function Pinv(p) {
    return [p[0] / S0 / RAD, (2 * Math.atan(Math.exp(-p[1] / S0)) - Math.PI / 2) / RAD];
  }
  /** A curved line between two lon/lat points (quadratic bezier in plane units). */
  function arc(a, b, bend) {
    var p0 = P(a), p1 = P(b), mx = (p0[0] + p1[0]) / 2, my = (p0[1] + p1[1]) / 2, dx = p1[0] - p0[0], dy = p1[1] - p0[1];
    var k = bend || 0.22, cx = mx - dy * k, cy = my + dx * k, out = [], i, t, u;
    if (cy > my) { cx = mx + dy * k; cy = my - dx * k; }
    for (i = 0; i <= 40; i++) { t = i / 40; u = 1 - t; out.push([u * u * p0[0] + 2 * u * t * cx + t * t * p1[0], u * u * p0[1] + 2 * u * t * cy + t * t * p1[1]]); }
    return out;
  }
  /** A great circle between two lon/lat points, in plane units. */
  function gc(a, b) {
    var f1 = a[1] * RAD, l1 = a[0] * RAD, f2 = b[1] * RAD, l2 = b[0] * RAD, out = [], i, t, A, B, x, y, z;
    var d = 2 * Math.asin(Math.sqrt(Math.pow(Math.sin((f2 - f1) / 2), 2) + Math.cos(f1) * Math.cos(f2) * Math.pow(Math.sin((l2 - l1) / 2), 2)));
    for (i = 0; i <= 60; i++) {
      t = i / 60; A = Math.sin((1 - t) * d) / Math.sin(d); B = Math.sin(t * d) / Math.sin(d);
      x = A * Math.cos(f1) * Math.cos(l1) + B * Math.cos(f2) * Math.cos(l2);
      y = A * Math.cos(f1) * Math.sin(l1) + B * Math.cos(f2) * Math.sin(l2);
      z = A * Math.sin(f1) + B * Math.sin(f2);
      out.push(P([Math.atan2(y, x) / RAD, Math.atan2(z, Math.sqrt(x * x + y * y)) / RAD]));
    }
    return out;
  }
  function line(ll) { return ll.map(P); }

  /** van Wijk and Nuij smooth zoom, as in d3.interpolateZoom. */
  function zoomInterp(p0, p1) {
    var rho = Math.SQRT2, ux0 = p0[0], uy0 = p0[1], w0 = p0[2], ux1 = p1[0], uy1 = p1[1], w1 = p1[2], dx = ux1 - ux0, dy = uy1 - uy0, d2 = dx * dx + dy * dy, S;
    /** @type {any} */ var i;
    if (d2 < 1e-12) {
      S = Math.log(w1 / w0) / rho;
      i = function (t) { return [ux0 + t * dx, uy0 + t * dy, w0 * Math.exp(rho * t * S)]; };
    } else {
      var d1 = Math.sqrt(d2), b0 = (w1 * w1 - w0 * w0 + 4 * d2) / (2 * w0 * 2 * d1), b1 = (w1 * w1 - w0 * w0 - 4 * d2) / (2 * w1 * 2 * d1);
      var r0 = Math.log(Math.sqrt(b0 * b0 + 1) - b0), r1 = Math.log(Math.sqrt(b1 * b1 + 1) - b1);
      S = (r1 - r0) / rho;
      i = function (t) { var s = t * S, c0 = Math.cosh(r0), u = (w0 / (2 * d1)) * (c0 * Math.tanh(rho * s + r0) - Math.sinh(r0)); return [ux0 + u * dx, uy0 + u * dy, (w0 * c0) / Math.cosh(rho * s + r0)]; };
    }
    i.duration = Math.abs(S) * 1000;
    return i;
  }

  /**
   * Builds the map.
   * @param {object} o
   *   svg, canvas, stage  elements (stage gets its height set and data-dim)
   *   topo                a TopoJSON topology whose object "c" holds the countries (id = ISO numeric, as a string)
   *   views               {name: {bb: [[w,s],[e,n]], mbb?: [[w,s],[e,n]] (narrow screens), hi?: {id: class}, dim?: bool}}
   *   focusRect           function (name, W, H) -> {x, y, w, h}: where on screen the view's box should sit
   *   isReduced           function () -> bool: reduced motion (instant moves, no animation)
   *   onChange            function (): called after every camera change
   *   graticule           bool, default true
   *   fit                 "window" (default: the map fills the window, as in the poland package) or "stage" (it fills
   *                       the stage element, whose size the page sets in CSS)
   */
  function create(o) {
    var svg = o.svg, canvas = o.canvas, stage = o.stage, topo = o.topo, views = o.views;
    var tf = topo.transform;
    var arcsLL = topo.arcs.map(function (a) { var x = 0, y = 0; return a.map(function (p) { x += p[0]; y += p[1]; return [x * tf.scale[0] + tf.translate[0], y * tf.scale[1] + tf.translate[1]]; }); });
    var arcsP = arcsLL.map(function (a) { return a.map(P); });
    function ringLL(ids) { var out = []; ids.forEach(function (id, n) { var pts = id < 0 ? arcsLL[~id].slice().reverse() : arcsLL[id].slice(); if (n > 0) pts.shift(); out = out.concat(pts); }); return out; }
    function ringPath(ids) {
      var d = "", first = true, prev = null;
      ids.forEach(function (id, n) {
        var pts = id < 0 ? arcsP[~id].slice().reverse() : arcsP[id], i, p;
        for (i = n > 0 ? 1 : 0; i < pts.length; i++) {
          p = pts[i];
          if (prev && Math.abs(p[0] - prev[0]) > S0 * Math.PI) { d += "M" + p[0].toFixed(1) + "," + p[1].toFixed(1); first = false; prev = p; continue; }
          d += (first ? "M" : "L") + p[0].toFixed(1) + "," + p[1].toFixed(1); first = false; prev = p;
        }
      });
      return d + "Z";
    }

    var cam = document.createElementNS(NS, "g"), gGrat = document.createElementNS(NS, "g"), gLand = document.createElementNS(NS, "g");
    gGrat.setAttribute("class", "grat"); gLand.setAttribute("class", "land"); cam.appendChild(gGrat); cam.appendChild(gLand); svg.appendChild(cam);
    if (o.graticule !== false) {
      var d = "", lo, la, a, b, p;
      for (lo = -180; lo <= 180; lo += 10) { a = P([lo, -60]); b = P([lo, 80]); d += "M" + a[0].toFixed(0) + "," + a[1].toFixed(0) + "L" + b[0].toFixed(0) + "," + b[1].toFixed(0); }
      for (la = -60; la <= 80; la += 10) { a = P([-180, la]); b = P([180, la]); d += "M" + a[0].toFixed(0) + "," + a[1].toFixed(0) + "L" + b[0].toFixed(0) + "," + b[1].toFixed(0); }
      p = document.createElementNS(NS, "path"); p.setAttribute("d", d); gGrat.appendChild(p);
    }
    var landById = {}, polyLL = {};
    topo.objects.c.geometries.forEach(function (g) {
      if (!g.arcs) return;
      var polys = g.type === "Polygon" ? [g.arcs] : g.arcs, dd = "";
      polys.forEach(function (poly) { poly.forEach(function (r) { dd += ringPath(r); }); });
      var path = document.createElementNS(NS, "path"); path.setAttribute("d", dd); gLand.appendChild(path);
      if (g.id != null) { landById[g.id] = path; polyLL[g.id] = polys.map(function (poly) { return poly.map(ringLL); }); }
    });

    var ctx = canvas.getContext("2d");
    var A = { W: 0, H: 0, DPR: 1, cur: null, tx: 0, ty: 0, active: null };
    var flyTok = 0, layoutW = 0, layoutH = 0;

    /** Re-measures the window; returns true when the size changed. Mobile address-bar height jitter (under 160 px) is ignored. */
    function measure() {
      var fit = o.fit === "stage", w, h;
      if (fit) { w = stage.clientWidth || 640; h = stage.clientHeight || 400; }
      else { w = innerWidth; h = innerHeight; if (w === layoutW && layoutH && Math.abs(h - layoutH) < 160) h = Math.max(h, layoutH); }
      var changed = w !== layoutW || h !== layoutH;
      layoutW = w; layoutH = h; A.W = w; A.H = h;
      if (changed) { if (!fit) stage.style.height = A.H + "px"; A.DPR = Math.min(2, window.devicePixelRatio || 1); canvas.width = A.W * A.DPR; canvas.height = A.H * A.DPR; }
      return changed;
    }
    function viewFor(name) {
      var v = views[name], bb = (A.W < 900 && v.mbb) || v.bb, p0 = P([bb[0][0], bb[1][1]]), p1 = P([bb[1][0], bb[0][1]]), f = o.focusRect(name, A.W, A.H);
      var k = Math.min(f.w / (p1[0] - p0[0]), f.h / (p1[1] - p0[1]));
      return { cx: (p0[0] + p1[0]) / 2, cy: (p0[1] + p1[1]) / 2, k: k, fx: f.x + f.w / 2, fy: f.y + f.h / 2 };
    }
    function apply(v) {
      A.cur = v; A.tx = v.fx - v.k * v.cx; A.ty = v.fy - v.k * v.cy;
      cam.setAttribute("transform", "translate(" + A.tx.toFixed(2) + "," + A.ty.toFixed(2) + ") scale(" + v.k.toFixed(5) + ")");
      if (o.onChange) o.onChange();
    }
    function flyTo(name, instant) {
      var b = viewFor(name);
      if (instant || o.isReduced() || !A.cur) { flyTok++; apply(b); return; }
      var a = A.cur, iz = zoomInterp([a.cx, a.cy, A.W / a.k], [b.cx, b.cy, A.W / b.k]), dur = Math.max(1100, Math.min(2600, iz.duration * 1.1)), t0 = performance.now(), tok = ++flyTok;
      function fr(now) {
        if (tok !== flyTok) return;
        var t = Math.min(1, (now - t0) / dur), e = t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2, z = iz(e);
        apply({ cx: z[0], cy: z[1], k: A.W / z[2], fx: a.fx + (b.fx - a.fx) * e, fy: a.fy + (b.fy - a.fy) * e });
        if (t < 1) requestAnimationFrame(fr);
      }
      requestAnimationFrame(fr);
    }
    function setView(name, instant) {
      A.active = name;
      var hi = views[name].hi || {};
      Object.keys(landById).forEach(function (id) { var c = hi[id] || ""; if (landById[id].getAttribute("class") !== c) landById[id].setAttribute("class", c); });
      stage.setAttribute("data-dim", views[name].dim ? "1" : "0");
      flyTo(name, instant);
    }
    /** Jumps to the current view's framing now (after a resize or a layout change). */
    function reframe() { if (A.cur && A.active) { flyTok++; apply(viewFor(A.active)); } }

    // ---- canvas helpers ----
    function S(b) { return [A.tx + A.cur.k * b[0], A.ty + A.cur.k * b[1]]; }
    function routeLen(r) { if (r.L) return; r.L = [0]; for (var i = 1; i < r.p.length; i++) r.L.push(r.L[i - 1] + Math.hypot(r.p[i][0] - r.p[i - 1][0], r.p[i][1] - r.p[i - 1][1])); }
    function along(r, t) {
      var L = t * r.L[r.L.length - 1], j = 1;
      while (j < r.L.length - 1 && r.L[j] < L) j++;
      var u = (L - r.L[j - 1]) / (r.L[j] - r.L[j - 1] || 1), a = r.p[j - 1], b = r.p[j];
      return [a[0] + (b[0] - a[0]) * u, a[1] + (b[1] - a[1]) * u];
    }
    function approach(x, target, dt) { if (o.isReduced()) return target; return x + (target - x) * Math.min(1, dt * 3.2); }
    function glowDot(x, y, r, rgb, al) {
      var g = ctx.createRadialGradient(x, y, 0, x, y, r * 4);
      g.addColorStop(0, "rgba(" + rgb + "," + 0.7 * al + ")"); g.addColorStop(1, "rgba(" + rgb + ",0)");
      ctx.fillStyle = g; ctx.beginPath(); ctx.arc(x, y, r * 4, 0, 6.283); ctx.fill();
      ctx.fillStyle = "rgba(" + rgb + "," + al + ")"; ctx.beginPath(); ctx.arc(x, y, r, 0, 6.283); ctx.fill();
    }
    var hasLS = "letterSpacing" in ctx;
    /** Draws outlined text. opt: {w: weight, size, mono, ls: letter spacing, color, outline (rgb list, default "0,0,0"), family: css font family list for the non-mono face} */
    function label(text, x, y, align, al, opt) {
      opt = opt || {};
      ctx.font = (opt.w || 500) + " " + (opt.size || 11) + "px " + (opt.mono ? '"JetBrains Mono",monospace' : opt.family || '"Barlow Semi Condensed","Barlow",sans-serif');
      if (hasLS) ctx.letterSpacing = (opt.ls != null ? opt.ls : 2) + "px";
      ctx.textAlign = align || "left"; ctx.textBaseline = "middle";
      ctx.lineJoin = "round"; ctx.lineWidth = 3; ctx.strokeStyle = "rgba(" + (opt.outline || "0,0,0") + "," + 0.8 * al + ")"; ctx.strokeText(text, x, y);
      ctx.fillStyle = opt.color || "rgba(255,255,255," + al + ")"; ctx.fillText(text, x, y);
    }

    return {
      state: A, ctx: ctx, landById: landById, polyLL: polyLL,
      P: P, Pinv: Pinv, arc: arc, gc: gc, line: line,
      measure: measure, viewFor: viewFor, setView: setView, reframe: reframe,
      S: S, routeLen: routeLen, along: along, approach: approach, glowDot: glowDot, label: label, hasLetterSpacing: hasLS,
      /** Marks the camera as moved by hand (stops a running fly-to). */
      stop: function () { flyTok++; },
    };
  }

  return { create: create, P: P, Pinv: Pinv, arc: arc, gc: gc, line: line, RAD: RAD, S0: S0, zoomInterp: zoomInterp };
});
