import { chromium } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { writeFileSync, readFileSync, existsSync } from 'node:fs';
import http from 'node:http';
import { extname, join } from 'node:path';
const types = { '.html': 'text/html', '.js': 'text/javascript', '.css': 'text/css', '.svg': 'image/svg+xml', '.png': 'image/png' };
const serve = (csp, port) => new Promise(r => { const s = http.createServer((q, res) => { const p = join('dist', q.url === '/' ? 'index.html' : q.url.split('?')[0]); if (!existsSync(p)) { res.writeHead(404); return res.end(); } const h = { 'content-type': types[extname(p)] ?? 'application/octet-stream' }; if (csp) h['content-security-policy'] = csp; res.writeHead(200, h); res.end(readFileSync(p)); }); s.listen(port, () => r(s)); });
const browser = await chromium.launch();
const out = {};
{
  const s0 = await serve(null, 5313); const ctx = await browser.newContext(); const page = await ctx.newPage();
  const external = []; const errs = [];
  await page.route('**/*', (route) => { const u = new URL(route.request().url()); if (u.hostname !== 'localhost') { external.push(route.request().url().slice(0, 100)); return route.abort(); } route.continue(); });
  page.on('pageerror', e => errs.push(e.message.slice(0, 150)));
  await page.goto('http://localhost:5313/'); await page.waitForTimeout(2500);
  out.offline = { externalRequestsAttempted: [...new Set(external)], pageErrors: errs };
  const res = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
  out.axe = res.violations.map(v => ({ id: v.id, impact: v.impact, nodes: v.nodes.length, sample: v.nodes[0].target.join(' ') }));
  out.axeCounts = { violations: res.violations.length, nodes: res.violations.reduce((a, v) => a + v.nodes.length, 0), bySeverity: res.violations.reduce((a, v) => (a[v.impact] = (a[v.impact] || 0) + 1, a), {}) };
  out.dom = await page.evaluate(() => ({ lang: document.documentElement.lang, title: document.title, tabbable: document.querySelectorAll('a[href],button,input,select,textarea,[tabindex]:not([tabindex="-1"])').length, iconButtonsNoName: [...document.querySelectorAll('button')].filter(b => !(b.getAttribute('aria-label') || b.textContent.trim() || b.getAttribute('title'))).length, headings: document.querySelectorAll('h1,h2,h3').length }));
  const seen = new Set(); for (let i = 0; i < 60; i++) { await page.keyboard.press('Tab'); seen.add(await page.evaluate(() => { const a = document.activeElement; return a.tagName + ':' + (a.getAttribute('aria-label') || a.textContent.trim().slice(0, 20)); })); }
  out.tabDistinctStops60 = seen.size;
  await page.screenshot({ path: 'editor.png' });
  await ctx.close(); s0.close();
}
for (const [k, csp, port] of [['strict', "default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; worker-src 'none'; frame-src 'self'", 5311], ['styleInline', "default-src 'none'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; worker-src 'none'; frame-src 'self'", 5312]]) {
  const s = await serve(csp, port); const page = await browser.newPage(); const errs = [];
  await page.addInitScript(() => { window.__v = []; document.addEventListener('securitypolicyviolation', e => window.__v.push(e.effectiveDirective + ' ' + (e.blockedURI||'').slice(0,50))); });
  page.on('console', m => { if (m.type() === 'error') errs.push(m.text().slice(0, 100)); });
  await page.goto(`http://localhost:${port}/`); await page.waitForTimeout(2500);
  out['csp_' + k] = { violations: [...new Set(await page.evaluate(() => window.__v))], rendered: await page.evaluate(() => document.querySelectorAll('button').length > 10), iframes: await page.evaluate(() => document.querySelectorAll('iframe').length) };
  await page.close(); s.close();
}
await browser.close();
writeFileSync('audit.json', JSON.stringify(out, null, 2)); console.log(JSON.stringify(out, null, 2));
