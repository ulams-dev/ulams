// Pixel comparison between two capture runs.
//
// No extra dependencies: PNG decoding comes from playwright-core's bundled pngjs and the
// diff from playwright-core's vendored pixelmatch (the same code `toHaveScreenshot` uses).
// Both are loaded by file path because playwright-core does not export pixelmatch.
import { createRequire } from 'node:module';
import fs from 'node:fs';
import path from 'node:path';

const require = createRequire(import.meta.url);
const pwCoreDir = path.dirname(require.resolve('playwright-core/package.json'));
const { PNG } = require('playwright-core/lib/utilsBundle');
const pixelmatch = require(path.join(pwCoreDir, 'lib/third_party/pixelmatch.js'));

/** Pads an image to width x height with a magenta fill so size changes show up as diff. */
function pad(img, width, height) {
  if (img.width === width && img.height === height) return img;
  const out = new PNG({ width, height });
  for (let i = 0; i < out.data.length; i += 4) {
    out.data[i] = 255;
    out.data[i + 1] = 0;
    out.data[i + 2] = 255;
    out.data[i + 3] = 255;
  }
  for (let y = 0; y < img.height; y++) {
    img.data.copy(out.data, y * width * 4, y * img.width * 4, (y + 1) * img.width * 4);
  }
  return out;
}

/**
 * Compares two PNG files. Returns { diffPixels, totalPixels, diffPercent, sizeChanged, ... }
 * and writes a diff PNG when diffPath is given and pixels differ.
 * pixelThreshold is pixelmatch's per-pixel colour tolerance (0..1).
 */
export function comparePngs(basePath, afterPath, diffPath, { pixelThreshold = 0.1 } = {}) {
  const a = PNG.sync.read(fs.readFileSync(basePath));
  const b = PNG.sync.read(fs.readFileSync(afterPath));
  const width = Math.max(a.width, b.width);
  const height = Math.max(a.height, b.height);
  const A = pad(a, width, height);
  const B = pad(b, width, height);
  const diff = new PNG({ width, height });
  const diffPixels = pixelmatch(A.data, B.data, diff.data, width, height, {
    threshold: pixelThreshold,
    includeAA: false,
    alpha: 0.2,
  });
  const totalPixels = width * height;
  if (diffPath && diffPixels > 0) {
    fs.mkdirSync(path.dirname(diffPath), { recursive: true });
    fs.writeFileSync(diffPath, PNG.sync.write(diff));
  }
  return {
    diffPixels,
    totalPixels,
    diffPercent: (diffPixels / totalPixels) * 100,
    sizeChanged: a.width !== b.width || a.height !== b.height,
    baseSize: `${a.width}x${a.height}`,
    afterSize: `${b.width}x${b.height}`,
  };
}

function listPngs(dir) {
  const out = [];
  const walk = (d) => {
    for (const e of fs.readdirSync(d, { withFileTypes: true })) {
      const p = path.join(d, e.name);
      if (e.isDirectory()) walk(p);
      else if (e.name.endsWith('.png')) out.push(path.relative(dir, p));
    }
  };
  if (fs.existsSync(dir)) walk(dir);
  return out.sort();
}

function readManifest(dir) {
  const p = path.join(dir, 'manifest.json');
  if (!fs.existsSync(p)) return { results: [] };
  return JSON.parse(fs.readFileSync(p, 'utf8'));
}

const esc = (s) =>
  String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);

/**
 * Compares every PNG in baseDir with the same relative path in afterDir and writes
 * report.html, report.md and report.json into reportDir (diff images under reportDir/diff).
 */
export function compareDirs({ baseDir, afterDir, reportDir, threshold = 0.5, pixelThreshold = 0.1 }) {
  fs.mkdirSync(reportDir, { recursive: true });
  const base = new Set(listPngs(baseDir));
  const after = new Set(listPngs(afterDir));
  const all = [...new Set([...base, ...after])].sort();
  const rows = [];
  for (const rel of all) {
    if (!base.has(rel) || !after.has(rel)) {
      rows.push({ rel, status: base.has(rel) ? 'missing-after' : 'missing-base', diffPercent: 100 });
      continue;
    }
    const diffPath = path.join(reportDir, 'diff', rel);
    const r = comparePngs(path.join(baseDir, rel), path.join(afterDir, rel), diffPath, { pixelThreshold });
    rows.push({
      rel,
      status: r.diffPercent > threshold || r.sizeChanged ? 'changed' : 'same',
      ...r,
      diff: r.diffPixels > 0 ? path.relative(reportDir, diffPath) : null,
    });
  }

  const failedCaptures = [
    ...readManifest(baseDir).results.filter((r) => r.status !== 'ok').map((r) => ({ run: 'base', ...r })),
    ...readManifest(afterDir).results.filter((r) => r.status !== 'ok').map((r) => ({ run: 'after', ...r })),
  ];

  rows.sort((x, y) => y.diffPercent - x.diffPercent);
  const changed = rows.filter((r) => r.status !== 'same');
  const summary = {
    baseDir,
    afterDir,
    threshold,
    pixelThreshold,
    compared: rows.filter((r) => r.totalPixels).length,
    changed: changed.length,
    maxDiffPercent: Math.max(0, ...rows.filter((r) => r.totalPixels).map((r) => r.diffPercent)),
    meanDiffPercent:
      rows.filter((r) => r.totalPixels).reduce((s, r) => s + r.diffPercent, 0) /
      Math.max(1, rows.filter((r) => r.totalPixels).length),
  };
  fs.writeFileSync(path.join(reportDir, 'report.json'), JSON.stringify({ summary, rows, failedCaptures }, null, 2));

  // Markdown
  const md = [
    `# Visual diff report`,
    ``,
    `- base: \`${baseDir}\``,
    `- after: \`${afterDir}\``,
    `- threshold: ${threshold}% of pixels (pixelmatch colour tolerance ${pixelThreshold})`,
    `- compared: ${summary.compared}, above threshold or resized: **${summary.changed}**`,
    `- max diff: ${summary.maxDiffPercent.toFixed(3)}%, mean diff: ${summary.meanDiffPercent.toFixed(3)}%`,
    ``,
    `## Above threshold`,
    ``,
    changed.length ? `| Screenshot | Diff % | Size base → after | Status |\n|---|---:|---|---|` : '_None._',
    ...changed.map(
      (r) =>
        `| ${r.rel} | ${r.diffPercent?.toFixed(3) ?? '-'} | ${r.baseSize ?? '-'} → ${r.afterSize ?? '-'} | ${r.status} |`,
    ),
    ``,
    `## Failed captures`,
    ``,
    failedCaptures.length
      ? failedCaptures.map((f) => `- [${f.run}] ${f.file}: ${f.status} ${f.error ?? ''}`).join('\n')
      : '_None._',
    ``,
    `## All screenshots`,
    ``,
    `| Screenshot | Diff % |`,
    `|---|---:|`,
    ...rows.map((r) => `| ${r.rel} | ${r.diffPercent?.toFixed(3) ?? '-'} |`),
    ``,
  ].join('\n');
  fs.writeFileSync(path.join(reportDir, 'report.md'), md);

  // HTML (image paths are absolute file:// so the report works wherever it is opened)
  const img = (p) => (p ? `<img loading="lazy" src="file://${esc(p)}">` : '<em>n/a</em>');
  const card = (r) => `
  <section class="${r.status}">
    <h3>${esc(r.rel)} — ${r.diffPercent?.toFixed(3) ?? '-'}% <small>${esc(r.status)} ${esc(r.baseSize ?? '')} → ${esc(r.afterSize ?? '')}</small></h3>
    <div class="row">
      <figure><figcaption>base</figcaption>${img(base.has(r.rel) && path.join(baseDir, r.rel))}</figure>
      <figure><figcaption>after</figcaption>${img(after.has(r.rel) && path.join(afterDir, r.rel))}</figure>
      <figure><figcaption>diff</figcaption>${img(r.diff && path.join(reportDir, r.diff))}</figure>
    </div>
  </section>`;
  const html = `<!doctype html><meta charset="utf-8"><title>Visual diff</title>
<style>
  :root { color-scheme: light dark; }
  body { font: 14px/1.4 system-ui, sans-serif; margin: 16px; }
  .row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
  figure { margin: 0; } img { width: 100%; border: 1px solid #8884; }
  section { border-top: 1px solid #8886; padding: 8px 0; }
  section.changed h3, section.missing-after h3, section.missing-base h3 { color: #c22; }
  table { border-collapse: collapse; } td, th { padding: 2px 8px; border-bottom: 1px solid #8884; text-align: left; }
</style>
<h1>Visual diff</h1>
<p>base <code>${esc(baseDir)}</code><br>after <code>${esc(afterDir)}</code><br>
threshold ${threshold}% · compared ${summary.compared} · <strong>${summary.changed} above threshold</strong> ·
max ${summary.maxDiffPercent.toFixed(3)}% · mean ${summary.meanDiffPercent.toFixed(3)}%</p>
${failedCaptures.length ? `<h2>Failed captures</h2><ul>${failedCaptures.map((f) => `<li>[${esc(f.run)}] ${esc(f.file)}: ${esc(f.status)} ${esc(f.error ?? '')}</li>`).join('')}</ul>` : ''}
<h2>Above threshold (${changed.length})</h2>
${changed.map(card).join('') || '<p>None.</p>'}
<details><summary><h2 style="display:inline">All (${rows.length})</h2></summary>
<table><tr><th>Screenshot</th><th>Diff %</th></tr>
${rows.map((r) => `<tr><td>${esc(r.rel)}</td><td>${r.diffPercent?.toFixed(3) ?? '-'}</td></tr>`).join('')}
</table></details>`;
  fs.writeFileSync(path.join(reportDir, 'report.html'), html);

  return { summary, rows, failedCaptures };
}
