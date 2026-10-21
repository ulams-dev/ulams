// Load each fixture into GrapesJS+grapesjs-mjml, export, compare normalised trees, and render both
// with the production-equivalent server (mjml 4.17.1) to compare HTML.
import { chromium } from '@playwright/test';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { parseDocument } from 'htmlparser2';
import mjml2html from 'mjml';

const fixtures = ['1-reset-password', '2-training-due-soon', '3-branded-handwritten', '3b-branded-explicit-close', '4-stress-constructs'];
mkdirSync('out', { recursive: true });

function norm(node, path = '', out = []) {
  const pad = path ? path + ' > ' : '';
  for (const n of node.children ?? []) {
    if (n.type === 'tag') {
      const attrs = Object.entries(n.attribs).sort(([a], [b]) => a.localeCompare(b))
        .map(([k, v]) => `${k}="${v.replace(/\s+/g, ' ').trim()}"`).join(' ');
      out.push(`${pad}<${n.name}${attrs ? ' ' + attrs : ''}>`);
      norm(n, pad + n.name, out);
    } else if (n.type === 'text') {
      const t = n.data.replace(/\s+/g, ' ').trim();
      if (t) out.push(`${pad}#text ${t}`);
    } else if (n.type === 'comment') {
      out.push(`${pad}#comment ${n.data.replace(/\s+/g, ' ').trim()}`);
    } else if (n.type === 'script' || n.type === 'style') {
      out.push(`${pad}<${n.name}>`); norm(n, pad + n.name, out);
    } else if (n.type === 'cdata') { out.push(`${pad}#cdata`); }
  }
  return out;
}
const lines = (s) => norm(parseDocument(s, { lowerCaseTags: false, lowerCaseAttributeNames: false, recognizeSelfClosing: true }));
function diff(a, b) {
  const A = new Set(a), B = new Set(b);
  const lost = a.filter((l) => !B.has(l)), added = b.filter((l) => !A.has(l));
  return { lost, added };
}
const htmlNorm = (h) => h.replace(/<!--[\s\S]*?-->/g, '').replace(/\s+/g, ' ').replace(/> </g, '><').trim();

const browser = await chromium.launch();
const results = {};
for (const f of fixtures) {
  const src = readFileSync(`../fixtures/${f}.mjml`, 'utf8');
  const page = await browser.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
  await page.goto('http://localhost:5199/');
  await page.evaluate(() => window.__ready);
  const exported = await page.evaluate(async (mjml) => {
    const ed = window.__make(mjml);
    await new Promise((r) => ed.on('load', r));
    await new Promise((r) => setTimeout(r, 800));
    return { mjml: ed.getHtml(), css: ed.getCss?.() ?? null };
  }, src);
  writeFileSync(`out/${f}.exported.mjml`, exported.mjml);
  const a = lines(src), b = lines(exported.mjml);
  const d = diff(a, b);
  const r1 = mjml2html(src, { validationLevel: 'soft', ignoreIncludes: true });
  let r2 = null, same = null;
  try { r2 = mjml2html(exported.mjml, { validationLevel: 'soft', ignoreIncludes: true }); same = htmlNorm(r1.html) === htmlNorm(r2.html); } catch (e) { r2 = { errors: [String(e)] }; }
  results[f] = { srcBytes: src.length, exportedBytes: exported.mjml.length, treeEqual: d.lost.length === 0 && d.added.length === 0, lost: d.lost, added: d.added, htmlEqual: same, mjmlErrors: { src: r1.errors.length, exported: r2.errors?.length }, pageErrors: errors };
  writeFileSync(`out/${f}.src.html`, r1.html); if (r2.html) writeFileSync(`out/${f}.exported.html`, r2.html);
  await page.close();
}
await browser.close();
writeFileSync('out/roundtrip.json', JSON.stringify(results, null, 2));
console.log(JSON.stringify(results, null, 2));
