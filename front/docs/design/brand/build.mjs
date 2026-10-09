/**
 * Builds the ulams "Orbital Folio" brand assets from the hand-written geometry below.
 *
 *   corepack yarn brand:build
 *
 * Output (all committed, so apps need no build step to use them):
 * - front/docs/design/brand/svg/   master vector files (symbol, wordmark, lockups, app icons)
 * - front/docs/design/brand/png/   raster exports (icons, OG image, board)
 * - front/ui/src/brand/paths.ts    the same geometry for inline use in Astro components
 * - public folders of web, docs-site, admin and the old front (favicons, touch icons, PWA icons)
 *
 * The geometry is drawn, not converted from a font, so no font licence applies to the logo
 * (ADR 0038). Coordinates are traced from the product owner's board: the symbol in a 4x grid
 * (see SYMBOL), the wordmark in a 3x grid (see GLYPHS); both are scaled on output.
 */
import { createRequire } from "node:module";
import { mkdirSync, writeFileSync, copyFileSync, readFileSync, existsSync } from "node:fs";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const require = createRequire(import.meta.url);
const sharp = require("sharp");
const { optimize } = require("svgo");

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = resolve(HERE, "../../../..");
const FRONT = join(ROOT, "front");

export const INDIGO = "#0F2B46";
export const ORANGE = "#FF7A2E";

/* ------------------------------------------------------------------ geometry */

/** Symbol: small left wing, main left wing with the sweeping arc, right wing, orbit dot. 4x grid. */
const SYMBOL = {
  wings: [
    "M70,604L178,320Q262,320 345,352Z",
    "M65,683L369,372Q620,148 888,108Q640,178 420,386L500,440Q512,447 512,458L512,762Q400,650 250,648Q140,648 65,683Z",
    "M548,458Q560,440 600,415Q750,300 920,285L1035,615Q770,576 548,762Z",
  ],
  dot: { cx: 995, cy: 103, r: 64 },
  box: { x: 65, y: 39, w: 994, h: 724 },
};

/** Wordmark "ulams": drawn rounded-square letterforms. 3x grid. */
const GLYPHS = {
  d: [
    "M35,118H82V260A25,25 0 0 0 107,285H192A25,25 0 0 0 217,260V118H262V260A70,70 0 0 1 192,330H105A70,70 0 0 1 35,260Z",
    "M305,38H352V262Q352,285 375,285H422V330H375A70,70 0 0 1 305,260Z",
    "M475,118H595A85,85 0 0 1 680,203V330H520A70,70 0 0 1 450,260V262A60,60 0 0 1 510,202H635V197A35,35 0 0 0 600,162H475ZM513,247H635V285H513A16,16 0 0 1 497,269V263A16,16 0 0 1 513,247Z",
    "M722,118H983A75,75 0 0 1 1058,193V330H1010V190A28,28 0 0 0 982,162H915Q910,162 910,167V330H865V190A28,28 0 0 0 837,162H770Q765,162 765,167V330H722Z",
    "M1315,118H1145A48,48 0 0 0 1097,166V205A40,40 0 0 0 1137,245H1263A20,20 0 0 1 1283,265A20,20 0 0 1 1263,285H1100V330H1275A50,50 0 0 0 1328,280V250A50,50 0 0 0 1278,200H1163A25,25 0 0 1 1140,172V162H1315Z",
  ],
  box: { x: 35, y: 38, w: 1293, h: 292 },
};

const num = (n) => String(Math.round(n * 100) / 100);

/** Scale and translate an absolute-command path (M L H V Q A Z). */
function xform(d, sx, sy, tx, ty) {
  const tokens = d.match(/[MLHVQAZ]|-?\d*\.?\d+/g);
  const out = [];
  let i = 0;
  const next = () => parseFloat(tokens[i++]);
  while (i < tokens.length) {
    const c = tokens[i++];
    if (c === "Z") out.push("Z");
    else if (c === "H") out.push("H" + num(next() * sx + tx));
    else if (c === "V") out.push("V" + num(next() * sy + ty));
    else if (c === "A") {
      const rx = next() * sx;
      const ry = next() * sy;
      const rot = next();
      const large = next();
      const sweep = next();
      const x = next() * sx + tx;
      const y = next() * sy + ty;
      out.push(`A${num(rx)} ${num(ry)} ${rot} ${large} ${sweep} ${num(x)} ${num(y)}`);
    } else {
      const pairs = c === "Q" ? 2 : 1;
      const pts = [];
      for (let p = 0; p < pairs; p++) pts.push(`${num(next() * sx + tx)} ${num(next() * sy + ty)}`);
      out.push(c + pts.join(" "));
    }
  }
  return out.join("");
}

/** Symbol parts at `k` units per 4x-grid unit, with the top-left of its box at (ox, oy). */
function symbolParts(k, ox, oy) {
  const { box, wings, dot } = SYMBOL;
  const tx = ox - box.x * k;
  const ty = oy - box.y * k;
  return {
    wings: wings.map((d) => xform(d, k, k, tx, ty)),
    dot: { cx: num(dot.cx * k + tx), cy: num(dot.cy * k + ty), r: num(dot.r * k) },
    w: box.w * k,
    h: box.h * k,
  };
}

/** Wordmark paths at `k` units per 3x-grid unit, top-left of its box at (ox, oy). */
function wordParts(k, ox, oy) {
  const { box, d } = GLYPHS;
  const tx = ox - box.x * k;
  const ty = oy - box.y * k;
  return { paths: d.map((p) => xform(p, k, k, tx, ty)), w: box.w * k, h: box.h * k };
}

/* ------------------------------------------------------------------ svg builders */

const COLORWAYS = {
  primary: { ink: INDIGO, dot: ORANGE },
  reversed: { ink: "#FFFFFF", dot: ORANGE },
  black: { ink: "#000000", dot: "#000000" },
  white: { ink: "#FFFFFF", dot: "#FFFFFF" },
};

const SYM_K = 0.25; // symbol grid -> lockup units (board pixels)
const WORD_K = 1 / 3;
const GAP_H = 22;

function drawSymbol(parts, c) {
  return (
    `<g fill="${c.ink}">${parts.wings.map((d) => `<path d="${d}"/>`).join("")}</g>` +
    `<circle cx="${parts.dot.cx}" cy="${parts.dot.cy}" r="${parts.dot.r}" fill="${c.dot}"/>`
  );
}
function drawWord(parts, c) {
  return `<path fill="${c.ink}" fill-rule="evenodd" d="${parts.paths.join("")}"/>`;
}

function svgDoc(w, h, body, title = "ulams", extra = "") {
  return (
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${num(w)} ${num(h)}" role="img" aria-label="${title}">` +
    `<title>${title}</title>${extra}${body}</svg>`
  );
}

function symbolSvg(c) {
  const s = symbolParts(SYM_K, 0, 0);
  return svgDoc(s.w, s.h, drawSymbol(s, c));
}
function wordmarkSvg(c) {
  const w = wordParts(WORD_K, 0, 0);
  return svgDoc(w.w, w.h, drawWord(w, c));
}
function horizontalSvg(c) {
  const s = symbolParts(SYM_K, 0, 0);
  const wm0 = wordParts(WORD_K, 0, 0);
  // the word's baseline sits 40 units above the symbol's lowest point (as on the board)
  const wordTop = s.h - 42.5 - wm0.h + 0.0;
  const w = wordParts(WORD_K, s.w + GAP_H, wordTop);
  return svgDoc(s.w + GAP_H + w.w, s.h, drawSymbol(s, c) + drawWord(w, c));
}
function stackedSvg(c) {
  const w = wordParts(WORD_K, 0, 0);
  const k = (w.w * 0.81) / SYMBOL.box.w;
  const s0 = symbolParts(k, 0, 0);
  const s = symbolParts(k, (w.w - s0.w) / 2, 0);
  const gap = 34;
  const wd = wordParts(WORD_K, 0, s0.h + gap);
  return svgDoc(w.w, s0.h + gap + w.h, drawSymbol(s, c) + drawWord(wd, c));
}

/** Square app icon on a 1024 grid. `rounded` false = full bleed (maskable / touch icon). */
function appIconSvg({ bg, c, border, rounded = true, symbolWidth = 0.62, size = 1024 }) {
  const k = (size * symbolWidth) / SYMBOL.box.w;
  const s0 = symbolParts(k, 0, 0);
  const s = symbolParts(k, (size - s0.w) / 2, (size - s0.h) / 2 + size * 0.01);
  const rx = rounded ? Math.round(size * 0.2188) : 0;
  const inset = border ? 2 : 0;
  const rect = `<rect x="${inset}" y="${inset}" width="${size - inset * 2}" height="${size - inset * 2}" rx="${rx}" fill="${bg}"${border ? ` stroke="${border}" stroke-width="4"` : ""}/>`;
  return svgDoc(size, size, rect + drawSymbol(s, c), "ulams app icon");
}

const LIGHT_ICON = appIconSvg({ bg: "#FFFFFF", c: COLORWAYS.primary, border: "#D9E0E8" });
const DARK_ICON = appIconSvg({ bg: INDIGO, c: COLORWAYS.reversed });
const MASKABLE_ICON = appIconSvg({ bg: INDIGO, c: COLORWAYS.reversed, rounded: false, symbolWidth: 0.5 });
const TOUCH_ICON = appIconSvg({ bg: INDIGO, c: COLORWAYS.reversed, rounded: false, symbolWidth: 0.62 });
/** Favicon: dark tile, readable at 16px (the symbol is enlarged a little). */
const FAVICON = svgDoc(
  32,
  32,
  `<rect width="32" height="32" rx="7" fill="${INDIGO}"/>` +
    (() => {
      const k = (32 * 0.74) / SYMBOL.box.w;
      const s0 = symbolParts(k, 0, 0);
      return drawSymbol(symbolParts(k, (32 - s0.w) / 2, (32 - s0.h) / 2 + 0.4), COLORWAYS.reversed);
    })(),
  "ulams"
);

function ogSvg() {
  const W = 1200;
  const H = 630;
  const h = horizontalSvg(COLORWAYS.reversed);
  const [, hw, hh] = h.match(/viewBox="0 0 ([\d.]+) ([\d.]+)"/).map(Number);
  const scale = 760 / hw;
  const inner = h.replace(/^<svg[^>]*>/, "").replace(/<\/svg>$/, "").replace(/<title>[^<]*<\/title>/, "");
  return svgDoc(
    W,
    H,
    `<rect width="${W}" height="${H}" fill="${INDIGO}"/>` +
      `<g transform="translate(${num((W - hw * scale) / 2)} ${num((H - hh * scale) / 2)}) scale(${num(scale)})">${inner}</g>`,
    "ulams, living courses from real sources"
  );
}

/* ------------------------------------------------------------------ output */

const svgo = (svg) =>
  optimize(svg, {
    multipass: true,
    floatPrecision: 2,
    plugins: [
      "preset-default",
    ],
  }).data;

const write = (file, data) => {
  mkdirSync(dirname(file), { recursive: true });
  writeFileSync(file, data);
};

async function png(svg, size, file, { width, height } = {}) {
  const w = width ?? size;
  const h = height ?? size;
  const vbw = parseFloat(svg.match(/viewBox="[\d.]+ [\d.]+ ([\d.]+)/)[1]);
  const density = Math.min(2000, Math.max(72, Math.ceil((72 * Math.max(w, h)) / vbw)));
  const buf = await sharp(Buffer.from(svg), { density }).resize(w, h).png({ compressionLevel: 9 }).toBuffer();
  write(file, buf);
  return buf;
}

function ico(entries) {
  // entries: [{size, buf(PNG)}]
  const head = Buffer.alloc(6);
  head.writeUInt16LE(0, 0);
  head.writeUInt16LE(1, 2);
  head.writeUInt16LE(entries.length, 4);
  let offset = 6 + entries.length * 16;
  const dirs = [];
  for (const e of entries) {
    const d = Buffer.alloc(16);
    d.writeUInt8(e.size >= 256 ? 0 : e.size, 0);
    d.writeUInt8(e.size >= 256 ? 0 : e.size, 1);
    d.writeUInt16LE(1, 4);
    d.writeUInt16LE(32, 6);
    d.writeUInt32LE(e.buf.length, 8);
    d.writeUInt32LE(offset, 12);
    offset += e.buf.length;
    dirs.push(d);
  }
  return Buffer.concat([head, ...dirs, ...entries.map((e) => e.buf)]);
}

const OUT = HERE;
const files = {}; // name -> svg string (optimised)

for (const [cw, c] of Object.entries(COLORWAYS)) {
  const suffix = cw === "primary" ? "" : `-${cw}`;
  files[`ulams-symbol${suffix}.svg`] = svgo(symbolSvg(c));
  files[`ulams-wordmark${suffix}.svg`] = svgo(wordmarkSvg(c));
  files[`ulams-logo-horizontal${suffix}.svg`] = svgo(horizontalSvg(c));
  files[`ulams-logo-stacked${suffix}.svg`] = svgo(stackedSvg(c));
}
files["ulams-app-icon-light.svg"] = svgo(LIGHT_ICON);
files["ulams-app-icon-dark.svg"] = svgo(DARK_ICON);
files["ulams-app-icon-maskable.svg"] = svgo(MASKABLE_ICON);
files["favicon.svg"] = svgo(FAVICON);
files["ulams-og.svg"] = svgo(ogSvg());

for (const [name, svg] of Object.entries(files)) write(join(OUT, "svg", name), svg);

// raster exports
const rasters = {};
rasters["favicon-16.png"] = await png(files["favicon.svg"], 16, join(OUT, "png/favicon-16.png"));
rasters["favicon-32.png"] = await png(files["favicon.svg"], 32, join(OUT, "png/favicon-32.png"));
rasters["favicon-48.png"] = await png(files["favicon.svg"], 48, join(OUT, "png/favicon-48.png"));
rasters["apple-touch-icon.png"] = await png(svgo(TOUCH_ICON), 180, join(OUT, "png/apple-touch-icon.png"));
rasters["icon-192.png"] = await png(files["ulams-app-icon-dark.svg"], 192, join(OUT, "png/icon-192.png"));
rasters["icon-256.png"] = await png(files["ulams-app-icon-dark.svg"], 256, join(OUT, "png/icon-256.png"));
rasters["icon-512.png"] = await png(files["ulams-app-icon-dark.svg"], 512, join(OUT, "png/icon-512.png"));
rasters["icon-maskable-512.png"] = await png(files["ulams-app-icon-maskable.svg"], 512, join(OUT, "png/icon-maskable-512.png"));
rasters["icon-1024.png"] = await png(files["ulams-app-icon-light.svg"], 1024, join(OUT, "png/app-icon-light-1024.png"));
await png(files["ulams-app-icon-dark.svg"], 1024, join(OUT, "png/app-icon-dark-1024.png"));
rasters["og-image.png"] = await png(files["ulams-og.svg"], 0, join(OUT, "png/og-image.png"), { width: 1200, height: 630 });
const icoBuf = ico([16, 32, 48].map((s) => ({ size: s, buf: rasters[`favicon-${s}.png`] })));
write(join(OUT, "favicon.ico"), icoBuf);
for (const size of [48, 72, 96, 128, 192, 256, 512]) {
  const buf = await sharp(Buffer.from(files["ulams-app-icon-dark.svg"]), { density: 384 }).resize(size, size).webp({ quality: 92 }).toBuffer();
  write(join(OUT, `png/icon-${size}.webp`), buf);
}
rasters["icon-128.png"] = await png(files["ulams-app-icon-dark.svg"], 128, join(OUT, "png/icon-128.png"));

/* inline geometry for Astro / React */
{
  const s = symbolParts(SYM_K, 0, 0);
  const wm0 = wordParts(WORD_K, 0, 0);
  const h = horizontalSvg(COLORWAYS.primary);
  const hvb = h.match(/viewBox="0 0 ([\d.]+) ([\d.]+)"/);
  const wordTop = s.h - 42.5 - wm0.h;
  const wInH = wordParts(WORD_K, s.w + GAP_H, wordTop);
  const ts = `/*
 * ulams "Orbital Folio" logo geometry, for inline SVG in Astro components.
 * Generated by front/docs/design/brand/build.mjs; do not edit by hand (ADR 0038).
 */
export const BRAND_INDIGO = "${INDIGO}";
export const BRAND_ORANGE = "${ORANGE}";

export const SYMBOL = {
  viewBox: "0 0 ${num(s.w)} ${num(s.h)}",
  wings: ${JSON.stringify(s.wings)},
  dot: ${JSON.stringify(s.dot)},
} as const;

export const WORDMARK = {
  viewBox: "0 0 ${num(wm0.w)} ${num(wm0.h)}",
  path: ${JSON.stringify(wordParts(WORD_K, 0, 0).paths.join(""))},
} as const;

/** Symbol plus wordmark, one coordinate space (the horizontal lockup). */
export const HORIZONTAL = {
  viewBox: "0 0 ${hvb[1]} ${hvb[2]}",
  wings: ${JSON.stringify(s.wings)},
  dot: ${JSON.stringify(s.dot)},
  word: ${JSON.stringify(wInH.paths.join(""))},
} as const;
`;
  write(join(FRONT, "ui/src/brand/paths.ts"), ts);
}

/* copy to the apps */
const cp = (from, to) => {
  mkdirSync(dirname(to), { recursive: true });
  copyFileSync(from, to);
};
const svgOut = (n) => join(OUT, "svg", n);
const pngOut = (n) => join(OUT, "png", n);

// front/web (Astro reference frontend)
cp(svgOut("favicon.svg"), join(FRONT, "web/public/favicon.svg"));
cp(join(OUT, "favicon.ico"), join(FRONT, "web/public/favicon.ico"));
cp(pngOut("apple-touch-icon.png"), join(FRONT, "web/public/apple-touch-icon.png"));
cp(pngOut("icon-192.png"), join(FRONT, "web/public/icon-192.png"));
cp(pngOut("icon-512.png"), join(FRONT, "web/public/icon-512.png"));
cp(pngOut("icon-maskable-512.png"), join(FRONT, "web/public/icon-maskable-512.png"));
cp(pngOut("og-image.png"), join(FRONT, "web/public/og-image.png"));

// docs site (+ downloads on the brand page)
cp(svgOut("favicon.svg"), join(FRONT, "docs-site/public/favicon.svg"));
cp(join(OUT, "favicon.ico"), join(FRONT, "docs-site/public/favicon.ico"));
cp(pngOut("apple-touch-icon.png"), join(FRONT, "docs-site/public/apple-touch-icon.png"));
cp(pngOut("og-image.png"), join(FRONT, "docs-site/public/og-image.png"));
cp(svgOut("ulams-logo-horizontal.svg"), join(FRONT, "docs-site/src/assets/logo-light.svg"));
cp(svgOut("ulams-logo-horizontal-reversed.svg"), join(FRONT, "docs-site/src/assets/logo-dark.svg"));
cp(svgOut("ulams-symbol.svg"), join(FRONT, "docs-site/src/assets/symbol-light.svg"));
cp(svgOut("ulams-symbol-reversed.svg"), join(FRONT, "docs-site/src/assets/symbol-dark.svg"));
for (const n of Object.keys(files)) cp(svgOut(n), join(FRONT, "docs-site/public/brand", n));
cp(pngOut("og-image.png"), join(FRONT, "docs-site/public/brand/ulams-og.png"));
if (existsSync(join(OUT, "orbital-folio-board.webp"))) cp(join(OUT, "orbital-folio-board.webp"), join(FRONT, "docs-site/public/brand/orbital-folio-board.webp"));
cp(pngOut("app-icon-light-1024.png"), join(FRONT, "docs-site/public/brand/ulams-app-icon-light-1024.png"));
cp(pngOut("app-icon-dark-1024.png"), join(FRONT, "docs-site/public/brand/ulams-app-icon-dark-1024.png"));

// admin (umi)
const A = join(ROOT, "admin/public");
cp(svgOut("favicon.svg"), join(A, "favicon.svg"));
cp(join(OUT, "favicon.ico"), join(A, "favicon.ico"));
cp(pngOut("apple-touch-icon.png"), join(A, "apple-touch-icon.png"));
cp(svgOut("ulams-logo-horizontal.svg"), join(A, "logo.svg"));
cp(svgOut("ulams-symbol.svg"), join(A, "icon.svg"));
cp(svgOut("ulams-wordmark.svg"), join(A, "ulams-wordmark.svg"));
cp(svgOut("ulams-logo-horizontal.svg"), join(A, "ulams.svg"));
cp(pngOut("icon-128.png"), join(A, "icons/icon-128x128.png"));
cp(pngOut("icon-192.png"), join(A, "icons/icon-192x192.png"));
cp(pngOut("icon-512.png"), join(A, "icons/icon-512x512.png"));

// api: the pages Laravel serves itself (Swagger UI, email verified)
cp(svgOut("favicon.svg"), join(ROOT, "api/public/favicon.svg"));
cp(join(OUT, "favicon.ico"), join(ROOT, "api/public/favicon.ico"));
cp(pngOut("apple-touch-icon.png"), join(ROOT, "api/public/apple-touch-icon.png"));

// old front (Vite)
const F = join(FRONT, "public");
cp(svgOut("favicon.svg"), join(F, "favicon.svg"));
cp(join(OUT, "favicon.ico"), join(F, "favicon.ico"));
cp(pngOut("apple-touch-icon.png"), join(F, "apple-touch-icon.png"));
cp(svgOut("ulams-app-icon-dark.svg"), join(F, "app_icon.svg"));
cp(svgOut("ulams-logo-horizontal.svg"), join(F, "brand/ulams-logo-horizontal.svg"));
cp(svgOut("ulams-logo-horizontal-reversed.svg"), join(F, "brand/ulams-logo-horizontal-reversed.svg"));
cp(pngOut("icon-192.png"), join(F, "icon-192x192.png"));
cp(pngOut("icon-256.png"), join(F, "icon-256x256.png"));
cp(pngOut("icon-512.png"), join(F, "icon-512x512.png"));
cp(pngOut("icon-maskable-512.png"), join(F, "icon-maskable-512x512.png"));
for (const size of [48, 72, 96, 128, 192, 256, 512]) cp(pngOut(`icon-${size}.webp`), join(FRONT, `icons/icon-${size}.webp`));

console.log("brand assets written:", Object.keys(files).length, "svg,", Object.keys(rasters).length + 1, "png");
