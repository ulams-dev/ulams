import { chromium } from '@playwright/test';
import { readFileSync, writeFileSync } from 'node:fs';
const src = readFileSync('../fixtures/1-reset-password.mjml', 'utf8');
const b = await chromium.launch(); const out = {};
for (const loc of ['en', 'pl', 'zh']) {
  const p = await b.newPage(); await p.goto('http://localhost:5199/'); await p.evaluate(() => window.__ready);
  out[loc] = await p.evaluate(async ([m, loc]) => {
    const ed = window.__make(m, loc); await new Promise(r => ed.on('load', r)); await new Promise(r => setTimeout(r, 600)); ed.runCommand('open-blocks'); await new Promise(r => setTimeout(r, 300));
    const labels = [...document.querySelectorAll('.gjs-block-label')].map(e => e.textContent.trim());
    const titles = [...document.querySelectorAll('.gjs-pn-btn')].map(e => e.getAttribute('title')).filter(Boolean);
    const sm = [...document.querySelectorAll('.gjs-sm-sector-title')].map(e => e.textContent.trim());
    return { blockLabels: labels, panelTitles: titles, styleSectors: sm };
  }, [src, loc]);
  await p.close();
}
await b.close(); writeFileSync('out/i18n.json', JSON.stringify(out, null, 2)); console.log(JSON.stringify(out));
