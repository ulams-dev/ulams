// @ts-check
/* Poland, measured: a map and charts, as an ulams interactive package (ADR 0086, 0088).
   Plain ES5 apart from the bridge import. The story text and figures are in data/steps.json, the source
   list in data/sources.json, the world map in data/world.topo.json (Natural Earth, public domain, via
   world-atlas). The map engine is vendor/atlas.js (demo-content/shared/atlas.js).

   Two ways to run:
   - on its own: a scroll story, the map flies from chapter to chapter (EN or PL, ?lang=, ?instant);
   - inside a lesson page (window.parent !== window): step mode. The page owns the stepper, the language and
     the narration; goToStep opens one step (the map view and the figures), every change is reported with
     stepChanged, init.chrome decides what the frame shows ("none": the map and a panel of figures,
     "minimal": one story card with Back and Next). ?ulams-poster#<step> renders one step for the poster images. */
import { connect } from "./vendor/interactive-bridge.js";

(function () {
"use strict";
var Atlas = /** @type {any} */ (window).Atlas;
var $ = function (s, r) { return (r || document).querySelector(s); };
var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

var poster = /[?&]ulams-poster\b/.test(location.search);
var embedded = window.parent !== window;
var stepMode = embedded || poster;
var chrome = stepMode ? "none" : "full";
var reduced = (window.matchMedia && matchMedia("(prefers-reduced-motion: reduce)").matches) || /[?&]instant\b/.test(location.search);
var paused = false;
var dirty = true;
var CARDS = {}, CONTENT = null, STEPS = [], UI = {}, CHAPTERS = [], HERO = null, topo = null;
var range = { lo: 0, hi: 1e9 };
var initMsg = null;

/* =====================================================================
   LANGUAGE
   ===================================================================== */
var lang = (function () {
  var q = /[?&]lang=(en|pl)/.exec(location.search); if (q) return q[1];
  if (stepMode) return "en"; // the lesson page sends its locale with init
  try { var s = localStorage.getItem("pl26-lang"); if (s === "en" || s === "pl") return s; } catch (e) { /* storage may be blocked */ }
  return (navigator.language || "").toLowerCase().indexOf("pl") === 0 ? "pl" : "en";
})();
function T(o) { return o == null ? "" : (typeof o === "string" || typeof o === "number") ? o : (o[lang] != null ? o[lang] : o.en); }
function nf(v, dec) { return new Intl.NumberFormat(lang === "pl" ? "pl-PL" : "en-GB", { minimumFractionDigits: dec || 0, maximumFractionDigits: dec || 0 }).format(v); }
function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]; }); }
function stripTags(s) { return String(s).replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim(); }

var BADGES = {
  observed: { en: "Measured", pl: "Zmierzone" }, forecast: { en: "Forecast", pl: "Prognoza" }, company_reported: { en: "Company data", pl: "Dane firmy" },
  operator_data: { en: "Operator data", pl: "Dane operatora" }, operator_report: { en: "Operator data", pl: "Dane operatora" }, institutional_status: { en: "Official", pl: "Oficjalne" },
  service_available: { en: "Live service", pl: "Działająca usługa" }, announced_contract: { en: "Contract", pl: "Umowa" }, in_development: { en: "In development", pl: "W budowie" },
  programme: { en: "Programme", pl: "Program" }, verified_role: { en: "Verified", pl: "Potwierdzone" }, historical_credit: { en: "Historical record", pl: "Zapis historyczny" },
  calculation: { en: "Calculated", pl: "Wyliczone" }, university_report: { en: "University data", pl: "Dane uczelni" }, pilot: { en: "Pilot", pl: "Pilotaż" },
  project_exists: { en: "Project", pl: "Projekt" }, announced_schedule: { en: "Schedule", pl: "Rozkład" }, announced_transaction: { en: "Announced", pl: "Ogłoszone" },
  imf_estimate: { en: "IMF estimate", pl: "Szacunek MFW" }, estimate: { en: "Estimate", pl: "Szacunek" }, specialist_analysis: { en: "Analysis", pl: "Analiza" }, industry_report: { en: "Industry data", pl: "Dane branży" }
};

var uid = 0;
function fmt(v,c){ return T(c.pre||'') + nf(v,c.dec||0) + T(c.suf||''); }
function countSpan(v,c){ return '<span data-count="'+v+'" data-dec="'+(c.dec||0)+'" data-pre="'+esc(T(c.pre||''))+'" data-suf="'+esc(T(c.suf||''))+'">'+fmt(v,c)+'</span>'; }
function figWrap(ch, inner, table){
  var t = ch.title ? '<div class="figt"><span>'+esc(T(ch.title))+'</span></div>' : '';
  return '<figure class="fig" style="margin-left:0;margin-right:0">'+t+inner+(table?'<table class="sr">'+table+'</table>':'')+'</figure>';
}
var R = {};
R.kpi = function(ch){
  var h = '<div class="kpis'+(ch.cols===3?' k3':'')+'">';
  ch.items.forEach(function(it){ h += '<div class="kpi"><div class="v">'+countSpan(it.v,it)+'</div><div class="l">'+esc(T(it.l))+'</div></div>'; });
  return figWrap(ch, h+'</div>');
};
R.bars = function(ch){
  var W=400, rowH=30, lw=128, x0=lw+8, x1=W-62, n=ch.rows.length, H=n*rowH+(ch.ref?16:2);
  var s = '<svg viewBox="0 0 '+W+' '+H+'" role="img" aria-label="'+esc(T(ch.title))+'">', tb='';
  if (ch.ref){ var rx = x0+(x1-x0)*ch.ref/ch.max; s += '<line x1="'+rx+'" x2="'+rx+'" y1="0" y2="'+(H-14)+'" stroke="rgba(255,255,255,.35)" stroke-dasharray="2 3"/><text class="tm" x="'+rx+'" y="'+(H-2)+'" text-anchor="middle">'+esc(T(ch.refl))+' '+ch.ref+'</text>'; }
  ch.rows.forEach(function(r,i){
    var y = i*rowH+15, xe = x0+(x1-x0)*Math.min(1,r.v/ch.max), cls = r.hl?'mk-pl':'mk-ctx', id='p'+(++uid);
    s += '<text class="tl'+(r.hl?' b':'')+'" x="0" y="'+(y+4)+'">'+esc(T(r.l))+'</text>';
    s += '<line class="trk" x1="'+x0+'" x2="'+x1+'" y1="'+y+'" y2="'+y+'" stroke-width="6"/>';
    s += '<line class="'+cls+'" x1="'+x0+'" x2="'+x0+'" y1="'+y+'" y2="'+y+'" stroke-width="6" data-tw="x2" data-from="'+x0+'" data-to="'+xe.toFixed(1)+'" data-i="'+i+'"><title>'+esc(T(r.l))+': '+fmt(r.v,ch)+'</title></line>';
    s += '<circle class="'+cls+'" r="4.5" cx="'+x0+'" cy="'+y+'" data-tw="cx" data-from="'+x0+'" data-to="'+xe.toFixed(1)+'" data-i="'+i+'" stroke="#000" stroke-width="2"/>';
    if (xe-x0 > 24){ for (var k=0;k<2;k++) s += '<circle class="ptc" r="1.3"><animateMotion dur="'+(1.6+k*.7+i*.15).toFixed(2)+'s" begin="'+(k*0.8)+'s" repeatCount="indefinite" path="M'+x0+','+y+' L'+(xe-5).toFixed(1)+','+y+'"/></circle>'; }
    s += '<text class="tv" x="'+(x1+8)+'" y="'+(y+4.5)+'">'+countSpan(r.v,ch).replace('<span','<tspan').replace('</span>','</tspan>')+'</text>';
    tb += '<tr><th>'+esc(T(r.l))+'</th><td>'+fmt(r.v,ch)+'</td></tr>';
  });
  return figWrap(ch, s+'</svg>', tb);
};
R.rings = function(ch){
  var W=400, H=170, cx=85, cy=85, s='<svg viewBox="0 0 '+W+' '+H+'" role="img" aria-label="'+esc(T(ch.title))+'">', tb='';
  ch.rows.forEach(function(r,i){
    var rad = 74-i*20, C = 2*Math.PI*rad, len = C*r.v/100, cls = r.hl?'mk-pl':'mk-ctx';
    s += '<circle cx="'+cx+'" cy="'+cy+'" r="'+rad+'" fill="none" class="trk" stroke-width="7"/>';
    s += '<circle cx="'+cx+'" cy="'+cy+'" r="'+rad+'" fill="none" class="'+cls+'" stroke-width="7" transform="rotate(-90 '+cx+' '+cy+')" stroke-dasharray="'+C.toFixed(1)+'" stroke-dashoffset="'+C.toFixed(1)+'" data-tw="stroke-dashoffset" data-from="'+C.toFixed(1)+'" data-to="'+(C-len).toFixed(1)+'" data-i="'+i+'" style="fill:none"/>';
    var a1 = -Math.PI/2 + 2*Math.PI*r.v/100, large = r.v>50?1:0;
    var p = 'M'+cx+','+(cy-rad)+' A'+rad+','+rad+' 0 '+large+' 1 '+(cx+rad*Math.cos(a1)).toFixed(2)+','+(cy+rad*Math.sin(a1)).toFixed(2);
    s += '<circle class="ptc" r="2.2"><animateMotion dur="'+(3+i)+'s" repeatCount="indefinite" path="'+p+'"/></circle>';
    var ly = 40+i*44;
    s += '<line x1="190" x2="204" y1="'+(ly-4)+'" y2="'+(ly-4)+'" class="'+cls+'" stroke-width="3"/>';
    s += '<text class="tv" x="214" y="'+ly+'" style="font-size:20px">'+countSpan(r.v,{suf:'%'}).replace('<span','<tspan').replace('</span>','</tspan>')+'</text>';
    s += '<text class="tl'+(r.hl?' b':'')+'" x="214" y="'+(ly+17)+'">'+esc(T(r.l))+'</text>';
    tb += '<tr><th>'+esc(T(r.l))+'</th><td>'+r.v+'%</td></tr>';
  });
  return figWrap(ch, s+'</svg>', tb);
};
R.slope = function(ch){
  var W=400, top=22, bot=26, n=ch.rows.length, H=140, xa=100, xb=300;
  function y(v){ return top+(H-top-bot)*(1-(v-ch.min)/(ch.max-ch.min)); }
  var s='<svg viewBox="0 0 '+W+' '+H+'" role="img" aria-label="'+esc(T(ch.title))+'">', tb='';
  s += '<line x1="'+xa+'" x2="'+xa+'" y1="'+top+'" y2="'+(H-bot)+'" stroke="rgba(255,255,255,.18)"/><line x1="'+xb+'" x2="'+xb+'" y1="'+top+'" y2="'+(H-bot)+'" stroke="rgba(255,255,255,.18)"/>';
  s += '<text class="tm" x="'+xa+'" y="'+(H-6)+'" text-anchor="middle">'+esc(T(ch.from))+'</text><text class="tm" x="'+xb+'" y="'+(H-6)+'" text-anchor="middle">'+esc(T(ch.to))+'</text>';
  ch.rows.forEach(function(r,i){
    var ya=y(r.a), yb=y(r.b), cls=r.hl?'mk-pl':'mk-ctx', L=Math.hypot(xb-xa,yb-ya);
    s += '<line class="'+cls+'" x1="'+xa+'" y1="'+ya.toFixed(1)+'" x2="'+xb+'" y2="'+yb.toFixed(1)+'" stroke-width="2" stroke-dasharray="'+L.toFixed(1)+'" stroke-dashoffset="'+L.toFixed(1)+'" data-tw="stroke-dashoffset" data-from="'+L.toFixed(1)+'" data-to="0" data-i="'+i+'"/>';
    s += '<circle class="'+cls+'" cx="'+xa+'" cy="'+ya.toFixed(1)+'" r="4.5" stroke="#000" stroke-width="2"/><circle class="'+cls+'" cx="'+xb+'" cy="'+yb.toFixed(1)+'" r="5.5" stroke="#000" stroke-width="2"/>';
    s += '<circle class="ptc" r="2"><animateMotion dur="2.6s" begin="'+(i*.5)+'s" repeatCount="indefinite" path="M'+xa+','+ya.toFixed(1)+' L'+xb+','+yb.toFixed(1)+'"/></circle>';
    s += '<text class="tv" x="'+(xa-10)+'" y="'+(ya+4).toFixed(1)+'" text-anchor="end" style="fill:var(--ink-2)">'+esc(fmt(r.a,ch))+'</text>';
    s += '<text class="tv" x="'+(xb+12)+'" y="'+(yb+4).toFixed(1)+'">'+countSpan(r.b,ch).replace('<span','<tspan').replace('</span>','</tspan>')+'</text>';
    if (n>1 || T(r.l)!==T(ch.title)) s += '<text class="tl" x="'+((xa+xb)/2)+'" y="'+((ya+yb)/2-9).toFixed(1)+'" text-anchor="middle">'+esc(T(r.l))+'</text>';
    tb += '<tr><th>'+esc(T(r.l))+'</th><td>'+esc(T(ch.from))+': '+fmt(r.a,ch)+'</td><td>'+esc(T(ch.to))+': '+fmt(r.b,ch)+'</td></tr>';
  });
  return figWrap(ch, s+'</svg>', tb);
};
R.waffles = function(ch){
  var n = ch.items.length, cell=13, gW=cell*10, gap=40, W=Math.max(400,n*gW+(n-1)*gap), H=gW+38;
  var s='<svg viewBox="0 0 '+W+' '+H+'" role="img" aria-label="'+esc(T(ch.title))+'">', tb='';
  ch.items.forEach(function(it,j){
    var ox = j*(gW+gap);
    for (var i=0;i<100;i++){
      var c=i%10, r=9-Math.floor(i/10), lit = i<it.v;
      s += '<circle class="wd dot-off" data-lit="'+(lit?(it.hl?'pl':'ctx'):'')+'" data-d="'+i+'" cx="'+(ox+c*cell+cell/2)+'" cy="'+(r*cell+cell/2)+'" r="4.4"'+(lit && i%7===3?' data-tw2="1"':'')+'/>';
    }
    s += '<text class="tv" x="'+ox+'" y="'+(gW+20)+'" style="font-size:18px">'+countSpan(it.v,{suf:'%'}).replace('<span','<tspan').replace('</span>','</tspan>')+'</text>';
    s += '<text class="tl" x="'+ox+'" y="'+(gW+35)+'">'+esc(T(it.l))+'</text>';
    tb += '<tr><th>'+esc(T(it.l))+'</th><td>'+it.v+' / 100</td></tr>';
  });
  return figWrap(ch, s+'</svg>', tb);
};
R.split = function(ch){
  var cell=15, W=400, s='<svg viewBox="0 0 '+W+' '+(ch.rows.length*44)+'" role="img" aria-label="'+esc(T(ch.title))+'">', tb='', k=0;
  ch.rows.forEach(function(r,j){
    var y=j*44+12;
    s += '<text class="tl'+(r.hl?' b':'')+'" x="0" y="'+(y+4)+'">'+esc(T(r.l))+'</text>';
    for (var i=0;i<r.v;i++) s += '<circle class="wd dot-off" data-lit="'+(r.hl?'pl':'ctx')+'" data-d="'+(k++)+'" cx="'+(64+i*cell)+'" cy="'+y+'" r="5.2"/>';
    s += '<text class="tm" x="64" y="'+(y+24)+'">≈ '+r.v+(lang==='pl'?' mln':' million')+'</text>';
    tb += '<tr><th>'+esc(T(r.l))+'</th><td>'+r.v+'</td></tr>';
  });
  return figWrap(ch, s+'</svg>', tb);
};
R.dotdiff = function(ch){
  var na=Math.round(ch.a/ch.per), nb=Math.round(ch.b/ch.per), cols=19, cell=20.5, rows=Math.ceil(na/cols), W=400, H=rows*cell+34;
  var s='<svg viewBox="0 0 '+W+' '+H+'" role="img" aria-label="'+esc(T(ch.title))+'">';
  for (var i=0;i<na;i++){
    var x=(i%cols)*cell+cell/2, y=Math.floor(i/cols)*cell+cell/2, keep=i<nb;
    s += '<circle class="wd" data-dd="'+(keep?'keep':'fade')+'" data-d="'+i+'" cx="'+x+'" cy="'+y+'" r="5.6" style="fill:var(--ctx)" stroke="none"/>';
  }
  s += '<circle r="5.6" style="fill:var(--pl)" cx="'+(cell/2)+'" cy="'+(rows*cell+16)+'"/><text class="tl b" x="'+(cell/2+12)+'" y="'+(rows*cell+20)+'">'+esc(T(ch.lb))+'</text>';
  s += '<circle r="5" fill="none" stroke="rgba(255,255,255,.4)" cx="'+(W/2+10)+'" cy="'+(rows*cell+16)+'"/><text class="tl" x="'+(W/2+22)+'" y="'+(rows*cell+20)+'">'+esc(T(ch.la))+'</text>';
  return figWrap(ch, s+'</svg>', '<tr><td>'+esc(T(ch.la))+'</td><td>'+esc(T(ch.lb))+'</td></tr>');
};
R.stages = function(ch){
  var W=400, lw=128, x0=lw+10, x1=W-8, ns=ch.stages.length, step=(x1-x0)/(ns-1), rowH=26, H=ch.rows.length*rowH+44;
  var s='<svg viewBox="0 0 '+W+' '+H+'" role="img" aria-label="'+esc(T(ch.title))+'">', tb='';
  ch.stages.forEach(function(st,i){ var x=x0+i*step; s += '<line x1="'+x+'" x2="'+x+'" y1="8" y2="'+(H-36)+'" stroke="rgba(255,255,255,.08)"/><text class="tm" x="'+x+'" y="'+(H-14-(i%2)*12)+'" text-anchor="middle" style="font-size:8.5px">'+esc(T(st))+'</text>'; });
  ch.rows.forEach(function(r,i){
    var y=i*rowH+16, xe=x0+r.s*step;
    s += '<text class="tl" x="0" y="'+(y+4)+'" style="font-size:11.5px">'+esc(T(r.l))+'</text>';
    s += '<line x1="'+x0+'" x2="'+x1+'" y1="'+y+'" y2="'+y+'" class="trk" stroke-width="2"/>';
    s += '<line class="mk-pl" x1="'+x0+'" x2="'+x0+'" y1="'+y+'" y2="'+y+'" stroke-width="2" data-tw="x2" data-from="'+x0+'" data-to="'+xe+'" data-i="'+i+'"/>';
    for (var k=0;k<=r.s;k++) s += '<circle cx="'+(x0+k*step)+'" cy="'+y+'" r="2.6" style="fill:var(--pl)"/>';
    s += '<circle cx="'+xe+'" cy="'+y+'" r="6" fill="none" stroke="var(--pl)" stroke-width="1.2"><animate attributeName="r" values="5;10;5" dur="2.4s" repeatCount="indefinite" begin="'+(i*.3)+'s"/><animate attributeName="opacity" values="1;0;1" dur="2.4s" repeatCount="indefinite" begin="'+(i*.3)+'s"/></circle>';
    if (r.s>0) s += '<circle class="ptc" r="1.8"><animateMotion dur="'+(1.2+r.s*.35)+'s" repeatCount="indefinite" path="M'+x0+','+y+' L'+xe+','+y+'"/></circle>';
    tb += '<tr><th>'+esc(T(r.l))+'</th><td>'+esc(T(ch.stages[r.s]))+'</td></tr>';
  });
  return figWrap(ch, s+'</svg>', tb);
};
R.day = function(ch){
  var h='<ol class="day"><li class="runner" aria-hidden="true"></li>';
  ch.items.forEach(function(it,i){ h += '<li style="transition-delay:'+(i*0.18)+'s"><time>'+it.t+'</time><span>'+esc(T(it.l))+'</span></li>'; });
  return figWrap(ch, h+'</ol>');
};
R.ledger = function(ch){
  var h='<div class="ledger"><div class="row hd"><div>'+esc(T(ch.head[0]))+'</div><div>'+esc(T(ch.head[1]))+'</div></div>';
  ch.rows.forEach(function(r){ h += '<div class="row"><div><h4>'+esc(T(r.t))+'</h4><p>'+esc(T(r.bad))+'</p></div><div class="good"><h4>&nbsp;</h4><p>'+esc(T(r.good))+'</p></div></div>'; });
  return figWrap(ch, h+'</div>');
};


/* =====================================================================
   STORY RENDERING
   ===================================================================== */
function badgesFor(ids) {
  var seen = {}, out = "";
  ids.forEach(function (id) { var c = CARDS[id]; if (!c) return; var b = BADGES[c.st] || { en: c.st, pl: c.st }; var k = T(b); if (!seen[k]) { seen[k] = 1; out += '<span class="badge">' + esc(k) + "</span>"; } });
  return out;
}
function sourcesFor(ids) {
  var seen = {}, li = "";
  ids.forEach(function (id) { var c = CARDS[id]; if (!c) return; c.s.forEach(function (s) { if (seen[s[1]]) return; seen[s[1]] = 1; li += '<li><a href="' + esc(s[1]) + '" target="_blank" rel="noopener">' + esc(s[0]) + "</a></li>"; }); });
  return li ? "<details><summary>" + esc(T(UI.sources)) + "</summary><ol>" + li + "</ol></details>" : "";
}
function metaFor(ids) { return ids && ids.length ? '<div class="meta">' + badgesFor(ids) + sourcesFor(ids) + "</div>" : ""; }

function stepHtml(st) {
  var h = '<section class="step step--' + (st.type || "std") + '" id="s-' + st.id + '" data-view="' + st.view + '" data-chap="' + st.chap + '"><div class="card">';
  if (st.type === "hero") {
    h += '<div class="hero-eyebrow"><span>' + esc(T(HERO.eyebrow)) + "</span></div>";
    h += "<h1>" + T(HERO.title) + '</h1><p class="hero-sub">' + T(HERO.sub) + '</p><p class="lede">' + T(HERO.lede) + "</p>";
    h += R.kpi({ items: HERO.kpis }).replace('class="kpis"', 'class="kpis hero-kpis"');
    h += '<p class="byline" style="margin-top:18px">' + esc(T(HERO.byline)) + "</p>";
    if (stepMode) h += metaFor(HERO.src);
    if (!stepMode) h += '<div class="scrollcue"><i></i>' + esc(T(UI.scroll)) + "</div>";
  } else if (st.type === "chapter") {
    h += '<div class="chapno" aria-hidden="true">' + st.no + "</div>";
    h += '<div class="kicker">' + esc(T(UI.chapterLabel)) + " " + st.no + "</div>";
    h += "<h2>" + esc(T(st.title)) + '</h2><p class="lede">' + esc(T(st.lede)) + "</p>";
  } else {
    h += '<div class="kicker">' + esc(T(st.kicker)) + "</div><h2>" + esc(T(st.title)) + '</h2><div class="body">' + T(st.body) + "</div>";
    (st.charts || []).forEach(function (ch) { h += R[ch.type](ch); });
    if (st.caveat) h += '<p class="caveat" data-label="' + esc(T(UI.caveat).toUpperCase()) + '">' + esc(T(st.caveat)) + "</p>";
    h += metaFor(st.src);
  }
  return h + "</div></section>";
}

/** The plain text of a step (title and narration), the text alternative the lesson page shows. */
function plainText(st) {
  if (st.type === "hero") return { title: stripTags(T(HERO.title)), text: stripTags(T(HERO.sub)) + ". " + stripTags(T(HERO.lede)) };
  if (st.type === "chapter") return { title: T(st.title), text: T(st.lede) };
  return { title: T(st.title), text: stripTags(T(st.body)) };
}

function renderFixed() {
  $$("[data-i]").forEach(function (el) { var k = el.getAttribute("data-i"); if (UI[k]) el.textContent = T(UI[k]); });
  var seen = {}, used = {}, list = [], ls = {};
  STEPS.forEach(function (st) { (st.src || []).forEach(function (id) { used[id] = 1; }); }); HERO.src.forEach(function (id) { used[id] = 1; });
  Object.keys(used).forEach(function (id) { var c = CARDS[id]; if (!c) return; c.s.forEach(function (s) { if (!ls[s[1]]) { ls[s[1]] = 1; list.push(s); } }); });
  list.sort(function (a, b) { return a[0].localeCompare(b[0]); });
  void seen;
  $("#srclist").innerHTML = list.map(function (s) { return '<a href="' + esc(s[1]) + '" target="_blank" rel="noopener">' + esc(s[0]) + " ↗</a>"; }).join("");
  var nav = ""; CHAPTERS.forEach(function (c) { nav += '<a href="#s-c-' + c.k + '" data-chap="' + c.k + '">' + esc(T(c.t)) + "</a>"; });
  $("#nav").innerHTML = nav;
  document.documentElement.lang = lang;
  document.title = stripTags(T(HERO.title));
  $$(".lang button").forEach(function (b) { b.setAttribute("aria-pressed", b.getAttribute("data-lang") === lang ? "true" : "false"); });
  var pt = $("#paneltoggle"); if (pt) pt.textContent = T($("body").classList.contains("panel-open") ? UI.hideChart : UI.showChart);
}

function renderStory() { // the whole scroll story
  $("#story").innerHTML = STEPS.map(stepHtml).join("");
  renderFixed();
}

/* animate a step's figures once it becomes the active one */
function ease(t) { return 1 - Math.pow(1 - t, 3); }
function runFigures(stepEl) {
  if (!stepEl || stepEl._ran) return; stepEl._ran = true;
  var card = stepEl.querySelector(".card"); card.classList.add("run");
  var tws = $$("[data-tw]", stepEl), counts = $$("[data-count]", stepEl);
  var dots = $$("[data-lit]", stepEl), dd = $$("[data-dd]", stepEl);
  dots.forEach(function (d) { var lit = d.getAttribute("data-lit"); if (!lit) return; var i = +d.getAttribute("data-d");
    setTimeout(function () { d.classList.remove("dot-off"); d.style.fill = lit === "pl" ? "var(--pl)" : "var(--ctx)"; d.style.stroke = "none"; if (d.hasAttribute("data-tw2")) d.classList.add("tw"); }, reduced ? 0 : 200 + i * 14); });
  dd.forEach(function (d) { var i = +d.getAttribute("data-d"); if (d.getAttribute("data-dd") === "keep") { setTimeout(function () { d.style.fill = "var(--pl)"; }, reduced ? 0 : 300 + i * 6); }
    else setTimeout(function () { d.style.fill = "transparent"; d.style.stroke = "rgba(255,255,255,.35)"; d.style.strokeWidth = "1"; }, reduced ? 0 : 900 + (i - 80) * 22); });
  if (reduced) { tws.forEach(function (e) { e.setAttribute(e.getAttribute("data-tw"), e.getAttribute("data-to")); }); pauseFigureAnimations(stepEl); return; }
  var t0 = performance.now(), D = 1300;
  function frame(now) {
    var done = true;
    tws.forEach(function (e) { var i = +(e.getAttribute("data-i") || 0), t = Math.max(0, Math.min(1, (now - t0 - i * 90) / D)); if (t < 1) done = false;
      var a = +e.getAttribute("data-from"), b = +e.getAttribute("data-to"); e.setAttribute(e.getAttribute("data-tw"), (a + (b - a) * ease(t)).toFixed(2)); });
    counts.forEach(function (e) { var t = Math.max(0, Math.min(1, (now - t0) / D)); if (t < 1) done = false; var v = +e.getAttribute("data-count"), dec = +e.getAttribute("data-dec");
      e.textContent = e.getAttribute("data-pre") + nf(v * ease(t), dec) + e.getAttribute("data-suf"); });
    if (!done) requestAnimationFrame(frame);
  }
  // reset counters to zero first so the count-up is visible
  counts.forEach(function (e) { e.textContent = e.getAttribute("data-pre") + nf(0, +e.getAttribute("data-dec")) + e.getAttribute("data-suf"); });
  requestAnimationFrame(frame);
}
/** Reduced motion: the particles and pulses drawn with SMIL stand still. */
function pauseFigureAnimations(root) {
  $$("svg", root).forEach(function (s) { if (s.pauseAnimations) s.pauseAnimations(); });
}

var P = Atlas.P, arc = Atlas.arc, gc = Atlas.gc, line = Atlas.line, RAD = Atlas.RAD;
var C = { // lon,lat
  waw:[21.01,52.23], kra:[19.94,50.06], wro:[17.04,51.11], lod:[19.46,51.76], poz:[16.93,52.41], gda:[18.65,54.35], szc:[14.55,53.43], byd:[18.0,53.12], lub:[22.57,51.25],
  bia:[23.16,53.13], kat:[19.02,50.26], gdy:[18.53,54.52], cze:[19.12,50.81], rad:[21.15,51.40], rze:[22.0,50.04], tor:[18.6,53.01], kie:[20.63,50.87], ols:[20.48,53.78],
  zg:[15.5,51.94], opo:[17.93,50.67], gor:[15.23,52.73], kos:[16.17,54.19], plo:[19.7,52.55], elb:[19.4,54.16], tar:[20.99,50.01], kal:[18.09,51.76], leg:[16.16,51.21],
  suw:[22.93,54.1], zam:[23.25,50.72], ns:[20.69,49.62], prz:[22.77,49.78], slu:[17.03,54.46], swi:[14.25,53.91]
};
var CITIES = /** @type {Array<[string, number]>} */ ([['waw',1.86],['kra',.8],['wro',.67],['lod',.65],['poz',.54],['gda',.49],['szc',.39],['byd',.33],['lub',.33],['bia',.29],['kat',.28],['gdy',.24],['cze',.21],['rad',.2],['rze',.2],['tor',.2],['kie',.18],['ols',.17],['zg',.14],['opo',.13],['gor',.12],['kos',.1],['plo',.11],['elb',.11],['tar',.1],['kal',.1],['leg',.1],['suw',.07],['zam',.06],['ns',.08],['prz',.06],['slu',.09]]);
var NAMES = {waw:{en:'Warsaw',pl:'Warszawa'},kra:{en:'Kraków',pl:'Kraków'},wro:{en:'Wrocław',pl:'Wrocław'},lod:{en:'Łódź',pl:'Łódź'},poz:{en:'Poznań',pl:'Poznań'},gda:{en:'Gdańsk',pl:'Gdańsk'},szc:{en:'Szczecin',pl:'Szczecin'},lub:{en:'Lublin',pl:'Lublin'},bia:{en:'Białystok',pl:'Białystok'},kat:{en:'Katowice',pl:'Katowice'},rze:{en:'Rzeszów',pl:'Rzeszów'},byd:{en:'Bydgoszcz',pl:'Bydgoszcz'}};

var VIEWS = {
  home:     {bb:[[3,44],[38,59]], hi:{'616':'hi'}},
  gas:      {bb:[[2,50.6],[24,61]], mbb:[[3,50.8],[23,60.5]], hi:{'616':'hi','578':'hi2','208':'hi2'}},
  solar:    {bb:[[13.6,48.9],[24.4,55.0]], hi:{'616':'hi'}},
  offshore: {bb:[[13.2,49.6],[24.2,56.2]], hi:{'616':'hi'}},
  food:     {bb:[[-9,37],[33,59]], hi:{'616':'hi','276':'hi2','528':'hi2','826':'hi2','250':'hi2','380':'hi2','203':'hi2'}},
  eurochips:{bb:[[-10.5,35.5],[29,58.5]], hi:{'616':'hi','276':'hi2','203':'hi2','724':'hi2','620':'hi2','348':'hi2','300':'hi2'}},
  eight:    {bb:[[12.5,41.2],[41,60]], mbb:[[13,41.5],[40.5,59.6]], hi:{'616':'hi','233':'hi2','428':'hi2','440':'hi2','112':'hi2','804':'hi2','498':'hi2','642':'hi2','100':'hi2'}},
  growth:   {bb:[[-125,20],[42,63]], hi:{'616':'hi','840':'hi2'}, euHi:true},
  flank:    {bb:[[7,41],[37,69.5]], hi:{'616':'hi','246':'hi2','233':'hi2','428':'hi2','440':'hi2','703':'hi2','348':'hi2','642':'hi2','100':'hi2'}},
  defence:  {bb:[[11,36],[31,60.5]], hi:{'616':'hi','233':'hi2','428':'hi2','440':'hi2','300':'hi2'}},
  yards:    {bb:[[12.6,50.6],[22.4,55.6]], hi:{'616':'hi'}},
  safe:     {bb:[[13.6,48.9],[24.4,55.0]], hi:{'616':'hi'}},
  world:    {bb:[[-128,18],[45,66]], hi:{'616':'hi','840':'hi2','826':'hi2','246':'hi2'}},
  eurobiz:  {bb:[[-11,36],[30,59]], hi:{'616':'hi','826':'hi2','250':'hi2','380':'hi2','724':'hi2','620':'hi2','642':'hi2'}},
  world2:   {bb:[[-128,30],[45,64]], hi:{'616':'hi','124':'hi2'}},
  orbit:    {bb:[[-140,-40],[160,65]], hi:{'616':'hi','840':'hi2'}},
  digital:  {bb:[[13.6,48.9],[24.4,55.0]], hi:{'616':'hi'}},
  roads:    {bb:[[13.6,48.9],[24.4,55.0]], hi:{'616':'hi'}},
  transit:  {bb:[[13.6,48.9],[24.4,55.0]], hi:{'616':'hi'}},
  travel:   {bb:[[-12,30],[42,62]], hi:{'616':'hi'}},
  health:   {bb:[[13.6,48.9],[24.4,55.0]], hi:{'616':'hi'}},
  krakow:   {bb:[[17.6,49.2],[22.4,51.2]], hi:{'616':'hi'}},
  people:   {bb:[[13.6,48.9],[24.4,55.0]], hi:{'616':'hi'}},
  ledger:   {bb:[[3,44],[38,59]], hi:{'616':'hi'}, dim:true},
  outro:    {bb:[[-16,32],[52,64]], hi:{'616':'hi'}}
};

/* overlay layers. pts in lon/lat; every layer names the views it belongs to */
var ROUTES = [];
function route(v, pts, o){ o=o||{}; ROUTES.push({v:v, p:pts, color:o.color||'255,217,168', w:o.w||1.2, n:o.n||8, sp:o.sp||0.06, dash:o.dash, size:o.size||2.2, a:0}); }
// gas
route(['gas'], line([[5.5,59.28],[6.4,58.1],[7.4,56.6],[8.1,55.65],[9.4,55.5],[10.8,55.32],[12.0,55.2],[12.9,54.95],[14.0,54.55],[15.07,54.1],[15.6,53.6],[16.2,52.9]]), {n:14, sp:.05, size:2.6});
route(['gas'], line([[-6,50.2],[-2.5,50.0],[1.4,50.95],[3.4,52.0],[5.6,54.0],[7.6,56.9],[10.4,57.9],[11.4,56.6],[10.95,55.4],[11.3,54.55],[12.6,54.5],[13.5,54.3],[14.27,53.93]]), {n:6, sp:.03, size:3, color:'255,255,255', dash:[2,5]});
// offshore wind cable
route(['offshore'], line([[17.62,54.98],[17.55,54.75],[17.5,54.55]]), {n:5, sp:.15});
// food exports
[[10.4,51.1],[5.3,52.2],[-1.6,52.6],[2.4,46.9],[12.4,43.2],[15.4,49.8],[24,56.8],[-8,40]].forEach(function(d,i){ route(['food'], arc([19.4,52.1],d,.2), {n:5, sp:.08+i*.006, w:1}); });
// eight countries: each one streams into a single total
var EIGHT=[[25.5,58.7],[25.4,56.9],[23.9,55.3],[28.0,53.5],[31.0,49.3],[28.5,47.2],[24.9,45.9],[25.3,42.7]], EIGHT_SUM=[33.2,43.7];
EIGHT.forEach(function(c,i){ route(['eight'], arc(c,EIGHT_SUM,.12), {n:4, sp:.09+i*.007, w:.9, color:'190,202,220', size:2}); });
// security: Eastern Sentry and Baltic Sentry
route(['flank'], line([[27.8,59.4],[27.6,57.6],[28.1,56.1],[26.6,55.6],[25.2,54.0],[23.6,53.95],[23.9,53.1],[23.2,52.3],[23.6,51.6],[24.1,50.8],[22.7,49.2],[24.9,47.9],[26.6,48.25],[28.1,46.6],[28.6,44.6],[28.0,43.4]]), {n:18, sp:.02, color:'255,255,255', size:2, dash:[3,4]});
route(['flank'], line([[10.9,54.6],[13.8,55.0],[16.5,55.6],[19.0,56.8],[20.6,58.6],[23.0,59.6],[26.5,60.0],[24.0,59.3],[21.2,57.5],[19.6,55.9],[17.2,55.1],[14.3,54.6],[10.9,54.6]]), {n:10, sp:.025, color:'255,255,255', size:2, dash:[3,4]});
// world: talent and companies
[[-122.42,37.77],[-74.0,40.71],[-0.13,51.5],[24.94,60.17]].forEach(function(d,i){ route(['world'], gc(C.waw,d), {n:7, sp:.05+i*.01}); });
[[-0.13,51.5],[2.35,48.86],[9.19,45.46],[-3.7,40.4],[-9.14,38.72],[26.1,44.43]].forEach(function(d,i){ route(['eurobiz'], arc(C.waw,d,.18), {n:6, sp:.06+i*.008}); });
[[-123.12,49.28]].forEach(function(d){ route(['world2'], gc([16.93,52.41],d), {n:10, sp:.04, size:2.6}); });
[[2.35,48.86],[13.4,52.52],[-0.13,51.5],[-74,40.71],[139.69,35.69]].forEach(function(d,i){ route(['world2'], gc(C.waw,d), {n:5, sp:.05+i*.01, w:.8, color:'255,255,255'}); });
// travel: holiday arcs
[[-3.7,40.4],[2.17,41.39],[12.5,41.9],[23.73,37.98],[28.98,41.01],[31.23,30.04],[-15.4,28.1],[14.5,35.9],[-0.13,51.5],[10.75,59.91],[16.37,48.21],[19.04,47.5]].forEach(function(d,i){ route(['travel'], arc(C.waw,d,.2), {n:4, sp:.05+i*.006, w:.9}); });
// roads, schematic
var ROADS = [
  ['gda','tor',[19.6,51.9],[19.7,51.4],'cze','kat',[18.38,49.93]],
  [[14.63,52.31],'poz',[18.25,52.22],[19.6,51.9],'waw',[22.27,52.17]],
  [[15.03,51.15],'leg','wro','opo','kat','kra','tar','rze',[23.07,49.95]],
  ['gda','elb',[20.28,53.58],[20.38,52.62],'waw','rad','kie','kra'],
  ['wro',[18.2,51.4],[19.7,51.4],'waw',[21.9,52.8],'bia'],
  ['swi','szc','gor','zg','leg',[16.0,50.7]],
  [[18.75,53.48],'byd',[17.6,52.53],'poz',[16.57,51.84],'wro'],
  ['waw',[21.6,51.9],'lub','zam',[23.6,50.25]],
  ['bia',[22.4,52.3],'lub','rze',[21.7,49.43]],
  ['szc','kos','slu','gdy','gda']
];
ROADS.forEach(function(r){ var pts=r.map(function(x){ return typeof x==='string'?C[x]:x; }); route(['roads','transit'], line(pts), {n:Math.max(4,Math.round(pts.length*1.6)), sp:.02, w:1.4, color:'255,255,255', size:1.8}); });
// orbit ground track (illustrative)
var ORB=[]; for (var i=0;i<=360;i++){ var lo=-180+i, la=51.6*Math.sin((lo+40)*RAD*1.0); ORB.push(P([lo,la])); }
route(['orbit'], ORB, {n:1, sp:.018, w:.8, color:'255,255,255', size:4});

/* markers */
var MARKERS = [];
function mk(v, ll, name, tip, o){ o=o||{}; MARKERS.push({v:v, ll:ll, b:P(ll), name:name, tip:tip, kind:o.kind||'dot', chip:o.chip, la:o.la||'r', sz:o.sz||1, ctx:o.ctx, a:0}); }
mk(['gas'], [15.07,54.1], {en:'Niechorze',pl:'Niechorze'}, {en:'Baltic Pipe comes ashore here: up to 10 bcm a year from the Norwegian shelf, operating since October 2022.',pl:'Tu Baltic Pipe wychodzi na ląd: do 10 mld m³ rocznie z norweskiego szelfu, od października 2022.'}, {kind:'ring', la:'r'});
mk(['gas'], [14.27,53.91], {en:'Świnoujście LNG',pl:'LNG Świnoujście'}, {en:'Expanded to 8.3 bcm a year from January 2025. Tankers arrive through the North Sea and the Danish straits.',pl:'Rozbudowany do 8,3 mld m³ rocznie od stycznia 2025. Tankowce płyną przez Morze Północne i cieśniny duńskie.'}, {kind:'ring', la:'b'});
mk(['gas'], [5.5,59.28], {en:'Norwegian shelf',pl:'Szelf norweski'}, {en:'Where the gas for Baltic Pipe comes from.',pl:'Stąd pochodzi gaz dla Baltic Pipe.'}, {la:'r'});
mk(['gas'], [19.4,52.1], {en:'Poland · storage 99%',pl:'Polska · magazyny 99%'}, {en:'Gas Storage Poland: facilities 99% full at the end of gas day 29 September 2026, about 3.3 bcm of capacity.',pl:'Gas Storage Poland: magazyny pełne w 99% na koniec doby gazowej 29 września 2026, ok. 3,3 mld m³ pojemności.'}, {kind:'chip', chip:'99%'});
mk(['offshore'], [17.62,54.98], {en:'Baltic Power',pl:'Baltic Power'}, {en:'Poland’s first offshore wind farm. First electricity to the grid on 10 July 2026.',pl:'Pierwsza polska morska farma wiatrowa. Pierwszy prąd w sieci 10 lipca 2026.'}, {kind:'wind', la:'r'});
mk(['offshore'], [21.01,52.23], {en:'Warsaw',pl:'Warszawa'}, {en:'Coal’s share of generation: 72.5% in 2021, 52.7% in 2025.',pl:'Udział węgla w produkcji: 72,5% w 2021, 52,7% w 2025.'}, {la:'r'});
mk(['food'], [19.4,52.1], {en:'Poland',pl:'Polska'}, {en:'Agri-food exports €58.4bn in 2025, surplus €19.8bn.',pl:'Eksport rolno-spożywczy 58,4 mld € w 2025, nadwyżka 19,8 mld €.'}, {kind:'ring', la:'b'});
[['276',[10.4,51.1],115,'b'],['203',[15.4,49.75],92,'b'],['724',[-3.7,40.0],92,'b'],['616',[19.4,52.1],81,'b'],['620',[-8.2,39.6],81,'b'],['348',[19.4,47.1],76,'b'],['300',[22.0,39.4],68,'b']].forEach(function(r){
  mk(['eurochips'], r[1], '', {en:'GDP per person in PPS, 2025: '+r[2]+' (EU = 100).',pl:'PKB na osobę w PPS, 2025: '+r[2]+' (UE = 100).'}, {kind:'chip', chip:String(r[2]), ctx:r[0]!=='616'}); });
mk(['eight'], [19.4,52.1], {en:'Poland',pl:'Polska'}, {en:'GDP $1.036 trillion, 36.5 million people, about $28.4k per person (IMF, 2025).',pl:'PKB 1,036 bln $, 36,5 mln ludzi, ok. 28,4 tys. $ na osobę (MFW, 2025).'}, {kind:'chip', chip:{en:'$1.036T',pl:'1,036 bln $'}});
mk(['eight'], EIGHT_SUM, {en:'Eight countries',pl:'Osiem krajów'}, {en:'Estonia, Latvia, Lithuania, Belarus, Ukraine, Moldova, Romania and Bulgaria: $1.076 trillion, 75.4 million people, about $14.3k per person (IMF, 2025).',pl:'Estonia, Łotwa, Litwa, Białoruś, Ukraina, Mołdawia, Rumunia i Bułgaria: 1,076 bln $, 75,4 mln ludzi, ok. 14,3 tys. $ na osobę (MFW, 2025).'}, {kind:'chip', chip:{en:'$1.076T',pl:'1,076 bln $'}, ctx:1});
mk(['growth'], [19.4,52.1], {en:'Poland',pl:'Polska'}, {en:'GDP +3.7% year on year in Q2 2026 (Eurostat flash estimate, 14 August 2026, seasonally adjusted).',pl:'PKB +3,7% r/r w II kw. 2026 (szybki szacunek Eurostatu, 14 sierpnia 2026, po wyrównaniu sezonowym).'}, {kind:'chip', chip:'+3.7%'});
mk(['growth'], [4.4,50.85], {en:'EU',pl:'UE'}, {en:'EU GDP +1.2% year on year in Q2 2026 (Eurostat).',pl:'PKB UE +1,2% r/r w II kw. 2026 (Eurostat).'}, {kind:'chip', chip:'+1.2%', ctx:1});
mk(['growth'], [-98,39], {en:'United States',pl:'USA'}, {en:'US GDP +2.1% year on year in Q2 2026 (Eurostat).',pl:'PKB USA +2,1% r/r w II kw. 2026 (Eurostat).'}, {kind:'chip', chip:'+2.1%', ctx:1});
[[[25.73,66.5],{en:'Finland',pl:'Finlandia'}],[[25.96,59.26],{en:'Estonia',pl:'Estonia'}],[[24.32,57.07],{en:'Latvia',pl:'Łotwa'}],[[24.2,55.06],{en:'Lithuania',pl:'Litwa'}],[[21.95,53.81],{en:'Poland',pl:'Polska'}],[[19.33,48.37],{en:'Slovakia',pl:'Słowacja'}],[[18.32,47.65],{en:'Hungary',pl:'Węgry'},'l'],[[24.8,45.92],{en:'Romania',pl:'Rumunia'}],[[26.13,42.3],{en:'Bulgaria',pl:'Bułgaria'}]].forEach(function(b){
  mk(['flank'], b[0], b[1], {en:'NATO multinational battlegroup (location approximate).',pl:'Wielonarodowa grupa bojowa NATO (położenie przybliżone).'}, {kind:'shield', la:b[2]||'r'}); });
[['616',[19.4,52.1],'4.68'],['233',[25.6,58.75],'5.10'],['428',[25.6,56.9],'4.92'],['440',[23.9,55.25],'5.33'],['300',[22.0,39.4],'3.65']].forEach(function(r){
  mk(['defence'], r[1], '', {en:'Defence spending, 2026 estimate: '+r[2]+'% of GDP.',pl:'Wydatki obronne, szacunek 2026: '+String(r[2]).replace('.',',')+'% PKB.'}, {kind:'chip', chip:r[2]+'%', ctx:r[0]!=='616'}); });
mk(['yards'], [18.53,54.52], {en:'Gdynia · ORP Wicher',pl:'Gdynia · ORP Wicher'}, {en:'Frigate launched in August 2026; 138.7 m; entry into service planned for 2029.',pl:'Fregata zwodowana w sierpniu 2026; 138,7 m; wejście do służby planowane na 2029.'}, {kind:'ring', la:'r'});
mk(['yards'], [17.62,54.98], {en:'Baltic Power',pl:'Baltic Power'}, {en:'Offshore wind: first power July 2026.',pl:'Morski wiatr: pierwszy prąd w lipcu 2026.'}, {kind:'wind', la:'l'});
mk(['yards'], [14.27,53.91], {en:'Świnoujście LNG',pl:'LNG Świnoujście'}, {en:'Operating at an expanded 8.3 bcm a year.',pl:'Działa z rozbudowaną przepustowością 8,3 mld m³ rocznie.'}, {la:'r'});
mk(['world'], [-122.42,37.77], {en:'San Francisco · OpenAI',pl:'San Francisco · OpenAI'}, {en:'Jakub Pachocki is OpenAI’s chief scientist; Wojciech Zaremba was a founding member.',pl:'Jakub Pachocki jest głównym naukowcem OpenAI; Wojciech Zaremba był członkiem zespołu założycielskiego.'}, {kind:'ring', la:'b'});
mk(['world'], [-74.0,40.71], {en:'New York · ElevenLabs',pl:'Nowy Jork · ElevenLabs'}, {en:'Founded by Poles; valued at $22bn in a staff tender announced 30 September 2026.',pl:'Założona przez Polaków; wyceniona na 22 mld $ w ofercie dla pracowników z 30 września 2026.'}, {kind:'ring', la:'b'});
mk(['world'], [24.94,60.17], {en:'Helsinki · ICEYE',pl:'Helsinki · ICEYE'}, {en:'Radar-satellite maker; agreed in May 2025 to supply satellites to the Polish Armed Forces.',pl:'Producent satelitów radarowych; w maju 2025 zgodził się dostarczyć satelity Siłom Zbrojnym RP.'}, {la:'r'});
mk(['world','world2','eurobiz'], C.waw, {en:'Warsaw',pl:'Warszawa'}, {en:'University of Warsaw: ICPC world finals 31 years in a row.',pl:'Uniwersytet Warszawski: finały ICPC 31 lat z rzędu.'}, {la:'r'});
mk(['eurobiz'], [-0.13,51.5], {en:'United Kingdom',pl:'Wielka Brytania'}, {en:'Part of InPost’s international network.',pl:'Część międzynarodowej sieci InPost.'}, {la:'l'});
mk(['eurobiz'], [2.35,48.86], {en:'France',pl:'Francja'}, {en:'Part of InPost’s international network.',pl:'Część międzynarodowej sieci InPost.'}, {la:'l'});
mk(['eurobiz'], [9.19,45.46], {en:'Italy',pl:'Włochy'}, {en:'Part of InPost’s international network.',pl:'Część międzynarodowej sieci InPost.'}, {la:'l'});
mk(['eurobiz'], [-3.7,40.4], {en:'Spain · Bizum',pl:'Hiszpania · Bizum'}, {en:'BLIK interoperability pilot with Bizum, early 2026.',pl:'Pilotaż połączenia BLIK z Bizum, początek 2026.'}, {la:'r'});
mk(['eurobiz'], [-9.14,38.72], {en:'Portugal · MB WAY',pl:'Portugalia · MB WAY'}, {en:'BLIK interoperability pilot with MB WAY, early 2026.',pl:'Pilotaż połączenia BLIK z MB WAY, początek 2026.'}, {la:'b'});
mk(['eurobiz'], [26.1,44.43], {en:'Romania · Żabka',pl:'Rumunia · Żabka'}, {en:'173 Żabka stores at the end of 2025.',pl:'173 sklepy Żabka na koniec 2025.'}, {la:'r'});
mk(['world2'], [-123.12,49.28], {en:'Vancouver',pl:'Vancouver'}, {en:'Firm order for 107 Solaris trolleybuses, with options for 77 more.',pl:'Wiążące zamówienie na 107 trolejbusów Solaris, z opcją na 77 kolejnych.'}, {kind:'ring', la:'r'});
mk(['world2'], [16.93,52.41], {en:'Poznań · Solaris',pl:'Poznań · Solaris'}, {en:'1,631 vehicles delivered to 15 countries in 2025; 86% low- or zero-emission.',pl:'1631 pojazdów do 15 krajów w 2025; 86% nisko- lub zeroemisyjnych.'}, {la:'l'});
mk(['orbit'], [-80.6,28.6], {en:'Kennedy Space Center',pl:'Centrum Kosmiczne Kennedy’ego'}, {en:'Ignis launched on 25 June 2025 aboard Falcon 9 and Dragon; back on 15 July after 20 days.',pl:'Ignis wystartowała 25 czerwca 2025 na Falconie 9 z Dragonem; powrót 15 lipca po 20 dniach.'}, {kind:'ring', la:'b'});
mk(['orbit'], C.waw, {en:'Poland',pl:'Polska'}, {en:'13 Polish-led experiments flew on the mission.',pl:'Na misję poleciało 13 eksperymentów prowadzonych przez Polaków.'}, {la:'r'});
mk(['transit'], [20.97,52.17], {en:'Warsaw Chopin',pl:'Lotnisko Chopina'}, {en:'24.1 million passengers in 2025, up 13% (Warsaw Chopin Airport).',pl:'24,1 mln pasażerów w 2025, +13% (Lotnisko Chopina).'}, {kind:'ring', la:'r', sz:1.4});
mk(['transit'], [18.7,54.4], {en:'Port of Gdańsk',pl:'Port Gdańsk'}, {en:'80.4 million tonnes of cargo; container terminals handled nearly 2.8 million TEU, up 23%.',pl:'80,4 mln ton ładunków; terminale kontenerowe przeładowały prawie 2,8 mln TEU, +23%.'}, {kind:'ring', la:'r', sz:1.2});
[[19.79,50.08,'Kraków'],[16.88,51.1,'Wrocław'],[19.08,50.47,'Katowice'],[16.83,52.42,'Poznań'],[22.02,50.11,'Rzeszów']].forEach(function(a){ mk(['transit'], [a[0],a[1]], '', {en:a[2]+' airport',pl:'Lotnisko '+a[2]}, {kind:'ring', sz:.8}); });
mk(['krakow'], C.kra, {en:'Kraków',pl:'Kraków'}, {en:'All eight stations met the annual PM10 limit in 2023 and 2024. Coal and wood banned in boilers and fireplaces since 2019.',pl:'Wszystkie osiem stacji spełniło roczną normę PM10 w 2023 i 2024. Zakaz węgla i drewna w kotłach i kominkach od 2019.'}, {kind:'ring', la:'r', sz:1.6});
mk(['krakow'], [19.93,50.01], {en:'Bujaka station',pl:'Stacja Bujaka'}, {en:'PM10 days over the limit: 78 in 2016, 23 in 2024.',pl:'Dni z przekroczeniem PM10: 78 w 2016, 23 w 2024.'}, {la:'b'});

var LABELS_EIGHT = [[{en:'Estonia',pl:'Estonia'},[25.8,58.75]],[{en:'Latvia',pl:'Łotwa'},[25.6,56.85]],[{en:'Lithuania',pl:'Litwa'},[23.6,55.45]],[{en:'Belarus',pl:'Białoruś'},[27.9,53.7]],[{en:'Ukraine',pl:'Ukraina'},[30.6,49.6]],[{en:'Moldova',pl:'Mołdawia'},[28.4,47.3]],[{en:'Romania',pl:'Rumunia'},[24.6,45.9]],[{en:'Bulgaria',pl:'Bułgaria'},[25.2,42.7]]];
var LABELS = /** @type {any[]} */ ([
  {t:{en:'Baltic Sea',pl:'Morze Bałtyckie'}, ll:[18.6,55.7], w:1, v:['gas','offshore','yards','solar','digital','roads','transit','health','people','safe']},
  {t:{en:'North Sea',pl:'Morze Północne'}, ll:[3.6,56.4], w:1, v:['gas','food','eurobiz']},
  {t:{en:'Norway',pl:'Norwegia'}, ll:[8.6,60.6], v:['gas']}, {t:{en:'Denmark',pl:'Dania'}, ll:[9.2,56.1], v:['gas']}, {t:{en:'Sweden',pl:'Szwecja'}, ll:[15.2,58.6], v:['gas']},
  {t:{en:'Atlantic Ocean',pl:'Atlantyk'}, ll:[-40,42], w:1, v:['world','world2','growth','orbit']},
  {t:{en:'Pacific Ocean',pl:'Pacyfik'}, ll:[-145,20], w:1, v:['orbit']},
  {t:{en:'Mediterranean Sea',pl:'Morze Śródziemne'}, ll:[17,35.3], w:1, v:['food','eurochips','travel','eurobiz','outro']},
  {t:{en:'Germany',pl:'Niemcy'}, ll:[10.2,52.6], v:['food','home']}, {t:{en:'Ukraine',pl:'Ukraina'}, ll:[31.5,49.0], v:['home','flank','ledger']},
  {t:{en:'Belarus',pl:'Białoruś'}, ll:[27.8,53.4], v:['home']}, {t:{en:'Russia',pl:'Rosja'}, ll:[33.5,57.2], v:['flank']},
  {t:{en:'Kaliningrad',pl:'Obwód królewiecki'}, ll:[21.2,54.75], v:['flank','safe']}
]);
LABELS_EIGHT.forEach(function(l){ LABELS.push({t:l[0], ll:l[1], v:['eight']}); });
CITIES.forEach(function(c){ if (NAMES[c[0]]) LABELS.push({t:NAMES[c[0]], ll:C[c[0]], city:1, v:['solar','digital','roads','safe','health','people']}); });
LABELS.forEach(function(l){ l.b=P(l.ll); l.a=0; });

/* solar lights inside Poland: rejection-sampled, seeded so the pattern is stable */
var SOLAR=[];
function buildSolar(){
  var polys = atlas.polyLL['616']||[], seed=7; function rnd(){ seed=(seed*16807)%2147483647; return seed/2147483647; }
  function inRing(pt,ring){ var c=false; for (var i=0,j=ring.length-1;i<ring.length;j=i++){ var a=ring[i], b=ring[j]; if (((a[1]>pt[1])!==(b[1]>pt[1])) && (pt[0] < (b[0]-a[0])*(pt[1]-a[1])/(b[1]-a[1])+a[0])) c=!c; } return c; }
  function inPL(pt){ return polys.some(function(poly){ return inRing(pt,poly[0]); }); }
  var n=0, guard=0; while (n<164 && guard++<20000){ var pt=[14.1+rnd()*10.1, 49.0+rnd()*5.85]; if (inPL(pt)){ SOLAR.push({b:P(pt), ph:rnd()*6.28, sp:.6+rnd()*1.4}); n++; } }
}
var solarA=0, citiesA=0;


/** The views with the EU group expanded into per-country highlights (the map engine only knows `hi`). */
function VIEWS_EXPANDED() {
  var EU = ['276','250','380','724','620','528','056','040','203','703','348','642','100','300','752','246','233','428','440','208','372','191','705','196','470','442'];
  var out = {};
  Object.keys(VIEWS).forEach(function (k) {
    var v = VIEWS[k], hi = {}; Object.keys(v.hi || {}).forEach(function (id) { hi[id] = v.hi[id]; });
    if (v.euHi) EU.forEach(function (id) { if (!hi[id]) hi[id] = 'hi2'; });
    out[k] = { bb: v.bb, mbb: v.mbb, hi: hi, dim: v.dim };
  });
  return out;
}

/* ---------- map camera (vendor/atlas.js) ---------- */
var stage = $("#stage"), canvas = /** @type {HTMLCanvasElement} */ ($("#fx")), tipEl = $("#tip");
var atlas, ctx, W = 0, H = 0, NICE = [5, 10, 20, 50, 100, 200, 500, 1000, 2000, 5000];
var hasPanel = false; // step mode, "none" chrome: the figures sit in a panel on the right

function focusRect(name, w, h) {
  var top = stepMode && !poster ? 124 : 64; // the lesson page draws a header over the top of the frame
  if (w < 900) return name === "orbit" ? { x: 8, y: top - 4, w: w - 16, h: h * 0.40 } : { x: 16, y: top, w: w - 32, h: h * 0.36 };
  var left = Math.min(w * 0.44, 560);
  if (poster) left = 24; // no lesson card in the poster: the map gets the left side
  if (!stepMode && name === "home") return { x: w * 0.36, y: h * 0.06, w: w * 0.6, h: h * 0.6 };
  if (name === "orbit" || name === "outro") left = Math.min(w * 0.38, 520);
  if (stepMode) {
    var right = hasPanel ? Math.min(380, w * 0.30) + 44 : 70;
    var bottom = poster ? 56 : 84;
    return { x: left + 40, y: top + 4, w: Math.max(240, w - left - 40 - right), h: h - top - bottom - 8 };
  }
  return { x: left + 40, y: 84, w: Math.max(240, w - left - 110), h: h - 180 };
}

function setView(name, instant) {
  atlas.setView(name, instant);
  hideTip(); dirty = true; listMarkers(name);
}
function updateMeta() {
  dirty = true;
  var A = atlas.state; if (!A.cur || stepMode) return;
  var f = focusRect(A.active, A.W, A.H), ll = atlas.Pinv([(f.x + f.w / 2 - A.tx) / A.cur.k, (f.y + f.h / 2 - A.ty) / A.cur.k]);
  var kmPerPx = 6371 * Math.cos(ll[1] * Atlas.RAD) / (Atlas.S0 * A.cur.k), km = NICE[0]; NICE.forEach(function (n) { if (n / kmPerPx <= 110) km = n; });
  $("#scalebar").style.width = Math.round(km / kmPerPx) + "px"; $("#scaletext").textContent = nf(km) + " km";
  function dms(v, pos, neg) { var a = Math.abs(v), d = Math.floor(a), m = Math.round((a - d) * 60); if (m === 60) { d++; m = 0; } return d + "°" + (m < 10 ? "0" : "") + m + "′" + (v >= 0 ? pos : neg); }
  $("#coords").textContent = dms(ll[1], "N", "S") + " " + dms(ll[0], "E", "W");
  $("#alt").textContent = nf(Math.round(A.W * kmPerPx * 0.62 / 10) * 10) + " km";
}

/* ---------- canvas drawing ---------- */
var last = performance.now(), clock = 0;
function draw(now) {
  requestAnimationFrame(draw);
  if (paused || (reduced && !dirty)) { last = now; return; } // reduced motion: draw when something changed
  dirty = false;
  try { drawFrame(now); } catch (e) { if (window.console) console.error(e); }
}
function drawFrame(now) {
  var dt = Math.min(0.05, (now - last) / 1000); last = now; if (!reduced) clock += dt;
  var A = atlas.state; if (!A.cur) return;
  var active = A.active, S = atlas.S, approach = atlas.approach, glowDot = atlas.glowDot, label = atlas.label;
  ctx.setTransform(A.DPR, 0, 0, A.DPR, 0, 0); ctx.clearRect(0, 0, A.W, A.H);
  // solar & cities
  solarA = approach(solarA, active === "solar" ? 1 : 0, dt); citiesA = approach(citiesA, (active === "digital" || active === "people" || active === "safe" || active === "health" || active === "outro") ? 1 : 0, dt);
  if (solarA > 0.01) SOLAR.forEach(function (s) { var p = S(s.b), tw = 0.55 + 0.45 * Math.sin(clock * s.sp * 2 + s.ph); glowDot(p[0], p[1], 2.4, "255,226,180", solarA * Math.min(1, tw * 1.15)); });
  if (citiesA > 0.01) CITIES.forEach(function (c, i) { var p = S(P(C[c[0]])), base = 3 + Math.sqrt(c[1]) * 9, ph = (clock * 0.55 + i * 0.137) % 1;
    ctx.strokeStyle = "rgba(255,217,168," + (citiesA * (1 - ph) * 0.8) + ")"; ctx.lineWidth = 1; ctx.beginPath(); ctx.arc(p[0], p[1], base * (0.4 + ph * 1.6), 0, 6.283); ctx.stroke();
    glowDot(p[0], p[1], 1.4 + Math.sqrt(c[1]) * 2.2, "255,217,168", citiesA * 0.95); });
  // routes
  ROUTES.forEach(function (r) {
    r.a = approach(r.a, r.v.indexOf(active) >= 0 ? 1 : 0, dt); if (r.a < 0.01) return; atlas.routeLen(r);
    ctx.beginPath(); r.p.forEach(function (q, i) { var s = S(q); if (i) ctx.lineTo(s[0], s[1]); else ctx.moveTo(s[0], s[1]); });
    ctx.setLineDash(r.dash || []); ctx.strokeStyle = "rgba(" + r.color + "," + (0.38 * r.a) + ")"; ctx.lineWidth = r.w; ctx.stroke(); ctx.setLineDash([]);
    for (var i = 0; i < r.n; i++) { var t = ((i / r.n) + clock * r.sp) % 1, q = S(atlas.along(r, t)), fade = Math.min(1, t * 8, (1 - t) * 8); glowDot(q[0], q[1], r.size, r.color, r.a * fade); }
  });
  // labels
  LABELS.forEach(function (l) { l.a = approach(l.a, l.v.indexOf(active) >= 0 ? 1 : 0, dt); if (l.a < 0.02) return; var p = S(l.b);
    if (l.city) label(String(T(l.t)).toUpperCase(), p[0] + 8, p[1] - 9, "left", l.a * 0.7, { size: 9.5, ls: 1.5, mono: 1, w: 400 });
    else label(String(T(l.t)).toUpperCase(), p[0], p[1], "center", l.a * (l.w ? 0.45 : 0.6), { size: l.w ? 10 : 11.5, ls: l.w ? 3 : 2.4, w: l.w ? 400 : 500 }); });
  // markers
  MARKERS.forEach(function (m) { m.a = approach(m.a, m.v.indexOf(active) >= 0 ? 1 : 0, dt); if (m.a < 0.02) return; var p = S(m.b), x = p[0], y = p[1], al = m.a; m.sx = x; m.sy = y;
    var rgb = m.ctx ? "125,138,160" : "240,58,78";
    if (m.kind === "chip") { ctx.font = '500 13px "JetBrains Mono",monospace'; if (atlas.hasLetterSpacing) ctx.letterSpacing = "0px"; var ct = String(T(m.chip)), tw = ctx.measureText(ct).width + 18;
      ctx.fillStyle = "rgba(0,0,0," + (0.85 * al) + ")"; ctx.fillRect(x - tw / 2, y - 12, tw, 24); ctx.strokeStyle = "rgba(" + rgb + "," + al + ")"; ctx.lineWidth = 1.2; ctx.strokeRect(x - tw / 2 + 0.5, y - 11.5, tw - 1, 23);
      ctx.fillStyle = "rgba(255,255,255," + al + ")"; ctx.textAlign = "center"; ctx.textBaseline = "middle"; ctx.fillText(ct, x, y + 0.5); m.hr = tw / 2;
      if (T(m.name)) label(String(T(m.name)).toUpperCase(), x, y + 22, "center", al * 0.75, { size: 10, ls: 2 }); return; }
    var ph = (((clock * 0.6 + m.b[0] * 0.001) % 1) + 1) % 1;
    if (m.kind === "ring" || m.kind === "shield") { var R0 = (m.kind === "shield" ? 7 : 8) * m.sz;
      ctx.strokeStyle = "rgba(" + rgb + "," + (al * (1 - ph)) + ")"; ctx.lineWidth = 1.2; ctx.beginPath(); ctx.arc(x, y, R0 + ph * R0 * 2.2, 0, 6.283); ctx.stroke();
      ctx.strokeStyle = "rgba(255,255,255," + al + ")"; ctx.lineWidth = 1.2; ctx.beginPath(); if (m.kind === "shield") { ctx.rect(x - 5, y - 5, 10, 10); } else ctx.arc(x, y, R0 * 0.7, 0, 6.283); ctx.stroke();
      glowDot(x, y, 2.6, rgb, al); }
    else if (m.kind === "wind") { ctx.save(); ctx.translate(x, y); ctx.strokeStyle = "rgba(255,255,255," + al + ")"; ctx.lineWidth = 1.4;
      for (var t = 0; t < 3; t++) { var a = clock * 2.2 + t * 2.094; ctx.beginPath(); ctx.moveTo(0, 0); ctx.lineTo(Math.cos(a) * 11, Math.sin(a) * 11); ctx.stroke(); }
      ctx.restore(); glowDot(x, y, 2.4, "240,58,78", al); }
    else { glowDot(x, y, 3, "255,255,255", al); }
    m.hr = 14;
    var nm = T(m.name); if (!nm) return; nm = String(nm).toUpperCase();
    var off = (m.kind === "dot" ? 10 : 16) * (m.sz > 1 ? m.sz * 0.8 : 1);
    if (m.la === "r") label(nm, x + off, y, "left", al, { size: 11.5 }); else if (m.la === "l") label(nm, x - off, y, "right", al, { size: 11.5 });
    else if (m.la === "t") label(nm, x, y - off - 4, "center", al, { size: 11.5 }); else label(nm, x, y + off + 6, "center", al, { size: 11.5 });
  });
}

/* tooltips (pointer) and a text list of the places on the map (keyboard and screen readers) */
var tipFor = null;
function showTip(m, cx, cy) { tipFor = m; tipEl.innerHTML = "<b></b><span></span>"; tipEl.firstChild.textContent = T(m.name) || T(m.chip) || ""; tipEl.lastChild.textContent = T(m.tip);
  var w = Math.min(280, W - 24); tipEl.style.width = w + "px"; var left = Math.max(12, Math.min(W - w - 12, cx - w / 2)); tipEl.style.left = left + "px";
  var h = tipEl.offsetHeight, top = cy - h - 20; if (top < 70) top = cy + 22; tipEl.style.top = top + "px"; tipEl.classList.add("on"); }
function hideTip() { tipFor = null; tipEl.classList.remove("on"); }
function hit(x, y) { var best = null, bd = 1e9; MARKERS.forEach(function (m) { if (m.a < 0.5 || m.sx == null) return; var d = Math.hypot(m.sx - x, m.sy - y); if (d < (m.hr || 14) + 8 && d < bd) { bd = d; best = m; } }); return best; }
function listMarkers(name) {
  var items = MARKERS.filter(function (m) { return m.v.indexOf(name) >= 0; });
  $("#mlist").innerHTML = items.map(function (m) { var n = T(m.name) || T(m.chip) || ""; return "<li>" + esc(n) + ": " + esc(T(m.tip)) + "</li>"; }).join("");
  canvas.setAttribute("aria-label", T(UI.mapLabel) + ": " + (currentTitle() || ""));
}
function currentTitle() { var st = STEPS[curStep]; return st ? plainText(st).title : ""; }

/* =====================================================================
   SCROLL WIRING (standalone) AND STEP MODE (lesson page, poster)
   ===================================================================== */
var stepEls = [], curStep = -1;
function collect() { stepEls = $$(".step"); }
function chapterOf(chap) { var c = CHAPTERS.filter(function (x) { return x.k === chap; })[0]; return { c: c, n: CHAPTERS.indexOf(c) }; }
function setChapterHud(chap) {
  $$("#nav a").forEach(function (a) { a.classList.toggle("on", a.getAttribute("data-chap") === chap); });
  var cc = chapterOf(chap), n = cc.n;
  $("#chap").textContent = cc.c ? ((n + 1 < 10 ? "0" : "") + (n + 1) + " / " + CHAPTERS.length + " · " + T(cc.c.t)) : "START";
}
function pick(instant) {
  // Hysteresis: a card takes over when its top rises above 70% of the screen, but the story only goes back once the
  // current card has dropped below 88%. Without the gap, a small bounce at the threshold flew the map away and back.
  var vh = atlas.state.H || innerHeight, idx = 0;
  for (var i = 0; i < stepEls.length; i++) { var r = stepEls[i].querySelector(".card").getBoundingClientRect(); if (r.top < vh * 0.70) idx = i; }
  if (curStep >= 0 && idx < curStep && !instant) { var rc = stepEls[curStep].querySelector(".card").getBoundingClientRect(); if (rc.top < vh * 0.88) idx = curStep; }
  var se = document.scrollingElement, max = se.scrollHeight - vh; $("#progress").style.width = (max > 0 ? se.scrollTop / max * 100 : 0) + "%";
  if (idx === curStep) return; curStep = idx;
  var st = stepEls[idx], view = st.getAttribute("data-view"), chap = st.getAttribute("data-chap");
  stage.setAttribute("data-hero", idx === 0 ? "1" : "0"); stage.setAttribute("data-step", st.id);
  if (view !== atlas.state.active || instant) setView(view, instant);
  runFigures(st);
  setChapterHud(chap);
}

/** Step mode: opens one step: the map view, its figures, and tells the lesson page. */
function goTo(id, announce) {
  var i = -1; STEPS.forEach(function (s, k) { if (s.id === id) i = k; });
  if (i < 0 || i < range.lo || i > range.hi) return false;
  var st = STEPS[i], changed = i !== curStep; curStep = i;
  $("#story").innerHTML = stepHtml(st);
  var el = $(".step"); collect();
  var hasFigs = !!(st.charts && st.charts.length) || !!st.caveat || st.type === "hero";
  $("#story").classList.toggle("empty", !hasFigs);
  $("#story").scrollTop = 0;
  stage.setAttribute("data-hero", "0"); stage.setAttribute("data-step", st.id);
  if (chrome === "minimal") addStepNav(el);
  var newHasPanel = chrome === "none" && hasFigs;
  if (newHasPanel !== hasPanel) { hasPanel = newHasPanel; }
  setView(st.view, reduced);
  if (reduced) { /* a layout change of the panel must not leave the map off-centre */ atlas.reframe(); }
  runFigures(el);
  if (reduced) pauseFigureAnimations(el);
  var tgl = $("#paneltoggle"); if (tgl) tgl.hidden = !(chrome === "none" && hasFigs);
  renderFixed();
  dirty = true;
  if (announce !== false && changed) { bridge.stepChanged(id); bridge.progress(STEPS.length > 1 ? i / (STEPS.length - 1) : 1); }
  return true;
}
function addStepNav(el) {
  var nav = document.createElement("div"); nav.className = "stepnav";
  nav.innerHTML = '<button type="button" data-d="-1"></button><button type="button" data-d="1"></button>';
  var b = nav.querySelectorAll("button"); b[0].textContent = T(UI.prev); b[1].textContent = T(UI.next);
  b[0].disabled = curStep <= range.lo; b[1].disabled = curStep >= Math.min(range.hi, STEPS.length - 1);
  nav.addEventListener("click", function (e) { var btn = /** @type {HTMLElement} */ (e.target).closest("button"); if (btn && !btn.disabled) goTo(STEPS[curStep + (+btn.getAttribute("data-d"))].id); });
  el.querySelector(".card").appendChild(nav);
}

/** The lesson page asks the frame to stand still (the learner left the lesson, or the tab is hidden). */
function setPaused(on) {
  paused = on; dirty = true;
  document.documentElement.classList.toggle("paused", on);
  $$("#story svg").forEach(function (sv) { if (on) { if (sv.pauseAnimations) sv.pauseAnimations(); } else if (!reduced && sv.unpauseAnimations) sv.unpauseAnimations(); });
}
function setReduced(on) {
  reduced = on; document.documentElement.classList.toggle("reduced", on); dirty = true;
  $$("#story svg").forEach(function (s) { if (on) { if (s.pauseAnimations) s.pauseAnimations(); } else if (s.unpauseAnimations) s.unpauseAnimations(); });
}
function setLang(l, persist) {
  if (l !== "en" && l !== "pl") return;
  if (l === lang) return;
  lang = l;
  if (persist) { try { localStorage.setItem("pl26-lang", l); } catch (e) { /* ignore */ } }
  if (stepMode) { var id = STEPS[curStep] && STEPS[curStep].id; curStep = -1; if (id) goTo(id, false); else renderFixed(); listMarkers(atlas.state.active); }
  else {
    var anchor = stepEls[curStep], offset = anchor ? anchor.getBoundingClientRect().top : 0, idx = curStep;
    renderStory(); collect();
    var el = stepEls[idx]; if (el) window.scrollTo(0, el.offsetTop - offset);
    curStep = -1; pick(); updateMeta(); tick();
    stepEls.forEach(function (s, i) { if (i <= idx) runFigures(s); });
  }
  dirty = true;
}

/* T+ clock since EU accession */
function tick() {
  if (stepMode) return;
  var t0 = Date.UTC(2004, 4, 1, 0, 0, 0) - 2 * 3600e3, now = Date.now();
  var d = new Date(now), y = d.getUTCFullYear() - 2004; var ann = Date.UTC(2004 + y, 4, 1) - 2 * 3600e3; if (now < ann) { y--; ann = Date.UTC(2004 + y, 4, 1) - 2 * 3600e3; }
  void t0;
  var rem = Math.floor((now - ann) / 1000), days = Math.floor(rem / 86400), hh = Math.floor(rem % 86400 / 3600), mm = Math.floor(rem % 3600 / 60), ss = rem % 60;
  function z(n) { return (n < 10 ? "0" : "") + n; }
  $("#tplus").textContent = "T+ " + y + T(UI.y) + " " + days + T(UI.d) + " " + z(hh) + ":" + z(mm) + ":" + z(ss);
}

/* =====================================================================
   BOOT
   ===================================================================== */
function fetchJson(url) { return fetch(url).then(function (r) { if (!r.ok) throw new Error(url + " " + r.status); return r.json(); }); }
var dataReady = Promise.all([fetchJson("data/world.topo.json"), fetchJson("data/sources.json"), fetchJson("data/steps.json")]).then(function (r) {
  topo = r[0]; CARDS = r[1]; CONTENT = r[2];
  STEPS = CONTENT.steps; UI = CONTENT.ui; CHAPTERS = CONTENT.chapters; HERO = CONTENT.hero;
});
var resolveInit = null;
var initReceived = new Promise(function (resolve) { resolveInit = resolve; });

var bridge = connect({
  steps: function () { return STEPS.map(function (s) { return s.id; }); },
  whenReady: dataReady,
  capabilities: { steps: true, reducedMotion: true, background: true, locales: ["en", "pl"] },
  onInit: function (init) { initMsg = init; if (resolveInit) resolveInit(init); },
  onGoToStep: function (id) { dataReady.then(function () { if (atlas) goTo(id); }); },
  onLocale: function (l) { dataReady.then(function () { if (atlas) setLang(l.slice(0, 2), false); }); },
  onPause: function () { setPaused(true); },
  onResume: function () { setPaused(false); },
});

function applyInit(init) {
  chrome = init.chrome;
  if (init.reducedMotion) reduced = true;
  var l = String(init.locale || "en").slice(0, 2); if (l === "en" || l === "pl") lang = l;
  if (init.range) {
    var lo = init.range.from ? STEPS.map(function (s) { return s.id; }).indexOf(init.range.from) : -1, hi = init.range.to ? STEPS.map(function (s) { return s.id; }).indexOf(init.range.to) : -1;
    range.lo = lo >= 0 ? lo : 0; range.hi = hi >= 0 ? hi : STEPS.length - 1;
  }
}

function boot() {
  var body = document.documentElement;
  if (stepMode) {
    body.classList.add("ix", "ix-" + chrome); if (poster) body.classList.add("ix-poster"); document.body.classList.add("ix-" + chrome);
    if (poster) { document.body.classList.add("ix-poster"); chrome = "none"; }
  }
  if (reduced) body.classList.add("reduced");
  hasPanel = false;
  atlas = Atlas.create({ svg: $("#map"), canvas: canvas, stage: stage, topo: topo, views: VIEWS_EXPANDED(), focusRect: focusRect, isReduced: function () { return reduced; }, onChange: updateMeta });
  ctx = atlas.ctx; atlas.measure(); W = atlas.state.W; H = atlas.state.H;
  buildSolar();
  addEventListener("resize", function () { if (atlas.measure()) { W = atlas.state.W; H = atlas.state.H; atlas.reframe(); dirty = true; } });
  stage.addEventListener("pointermove", function (e) { var m = hit(e.clientX, e.clientY); stage.style.cursor = m ? "pointer" : ""; if (m && e.pointerType === "mouse") showTip(m, m.sx, m.sy); else if (!m && e.pointerType === "mouse") hideTip(); });
  stage.addEventListener("click", function (e) { var m = hit(e.clientX, e.clientY); if (m) showTip(m, m.sx, m.sy); else hideTip(); });
  $$(".lang button").forEach(function (b) { b.addEventListener("click", function () { setLang(b.getAttribute("data-lang"), true); }); });
  var toggle = $("#paneltoggle");
  if (toggle) toggle.addEventListener("click", function () {
    var open = !document.body.classList.contains("panel-open"); document.body.classList.toggle("panel-open", open);
    toggle.setAttribute("aria-expanded", String(open)); toggle.textContent = T(open ? UI.hideChart : UI.showChart);
  });
  document.addEventListener("keydown", function (e) { if (e.key === "Escape" && document.body.classList.contains("panel-open")) { document.body.classList.remove("panel-open"); if (toggle) { toggle.setAttribute("aria-expanded", "false"); toggle.textContent = T(UI.showChart); toggle.focus(); } } });
  requestAnimationFrame(draw);

  if (stepMode) {
    var start = poster ? decodeURIComponent(location.hash.replace(/^#/, "")) : (initMsg && (initMsg.startStep || (initMsg.range && initMsg.range.from))) || "";
    if (!goTo(start, true)) goTo(STEPS[Math.max(0, range.lo)].id, true);
    renderFixed();
  } else {
    renderStory(); collect();
    setView("home", true);
    pick(true);
    addEventListener("scroll", function () { pick(); }, { passive: true });
    setInterval(tick, 1000); tick(); updateMeta();
  }
}

dataReady.then(function () {
  if (embedded) return initReceived.then(function (init) { applyInit(init); });
}).then(boot).catch(function (e) {
  document.body.classList.add("no-data");
  $("#fail").textContent = "The map data could not be loaded.";
  if (window.console) console.error(e);
  bridge.error("data-unavailable", String(e && e.message || e).slice(0, 200));
});
})();
