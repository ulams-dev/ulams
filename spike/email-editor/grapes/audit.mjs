import { chromium } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { readFileSync, writeFileSync } from 'node:fs';
import { serve } from './csp-server.mjs';

const src = readFileSync('../fixtures/1-reset-password.mjml', 'utf8');
const out = {};
const browser = await chromium.launch();

// 1. axe on the editor page (top document + iframes), with a template loaded
{
  const ctx = await browser.newContext(); const page = await ctx.newPage();
  await page.goto("http://localhost:5199/");
  await page.evaluate(() => window.__ready);
  await page.evaluate(async (m) => { const ed = window.__make(m); await new Promise(r => ed.on("load", r)); await new Promise(r => setTimeout(r, 800)); }, src);
  const res = await new AxeBuilder({ page }).include('#gjs').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
  out.axe = res.violations.map(v => ({ id: v.id, impact: v.impact, nodes: v.nodes.length, help: v.help, sample: v.nodes[0].target.join(' ') }));
  out.axeCounts = { violations: res.violations.length, nodes: res.violations.reduce((a, v) => a + v.nodes.length, 0), bySeverity: res.violations.reduce((a, v) => (a[v.impact] = (a[v.impact] || 0) + 1, a), {}) };
  // 2. keyboard operability
  const info = await page.evaluate(() => {
    const root = document.querySelector('#gjs');
    const sel = 'a[href],button,input,select,textarea,[tabindex]:not([tabindex="-1"])';
    const tabbable = [...root.querySelectorAll(sel)];
    const blocks = [...root.querySelectorAll('.gjs-block')];
    return {
      tabbable: tabbable.length,
      blocks: blocks.length,
      blocksWithTabindex: blocks.filter(b => b.hasAttribute('tabindex')).length,
      blocksDraggableOnly: blocks.filter(b => b.getAttribute('draggable') === 'true').length,
      iconOnlyButtonsWithoutName: [...root.querySelectorAll('.gjs-pn-btn,.gjs-toolbar-item')].filter(b => !(b.getAttribute('aria-label') || b.getAttribute('title') || b.textContent.trim())).length,
      panelButtons: root.querySelectorAll('.gjs-pn-btn').length,
      panelButtonsWithAriaLabel: [...root.querySelectorAll('.gjs-pn-btn')].filter(b => b.getAttribute('aria-label')).length,
      panelButtonsWithRole: [...root.querySelectorAll('.gjs-pn-btn')].filter(b => b.getAttribute('role')).length,
      canvasIframeTitle: document.querySelector('.gjs-frame')?.getAttribute('title'),
    };
  });
  out.keyboard = info;
  // tab through 60 stops and record what receives focus inside #gjs
  await page.focus('body');
  const stops = [];
  for (let i = 0; i < 60; i++) {
    await page.keyboard.press('Tab');
    stops.push(await page.evaluate(() => { const a = document.activeElement; return a ? (a.tagName + '.' + (a.className || '').toString().split(' ')[0] + ((a.closest && a.closest('#gjs')) ? '' : ' [outside]')) : null; }));
  }
  out.tabStops = stops.slice(0, 20);
  out.tabStopsInsideEditor = stops.filter(s => s && !s.includes('[outside]') && !s.startsWith('BODY')).length;
  // Can a block be added via keyboard? focus the first block, press Enter / Space
  const before = await page.evaluate(() => window.__grapesjs.editors[0].getWrapper().find('mj-text').length);
  const firstBlock = await page.$('.gjs-block');
  let tabindexOnBlock = null;
  if (firstBlock) { await firstBlock.focus().catch(() => {}); await page.keyboard.press('Enter'); await page.keyboard.press('Space'); tabindexOnBlock = await firstBlock.getAttribute('tabindex'); }
  const after = await page.evaluate(() => window.__grapesjs.editors[0].getWrapper().find('mj-text').length);
  out.keyboardAddBlock = { mjTextBefore: before, mjTextAfter: after, firstBlockTabindex: tabindexOnBlock };
  await page.close();
}

// 3. CSP: strict policy, collect violations; then which directives are needed
async function cspRun(label, csp, port) {
  const s = await serve(csp, port);
  const page = await browser.newPage();
  const violations = [];
  const errs = [];
  await page.addInitScript(() => { window.__v = []; document.addEventListener('securitypolicyviolation', e => window.__v.push(e.effectiveDirective + ' | ' + (e.blockedURI || '').slice(0, 40) + ' | ' + (e.sample || '').slice(0, 40))); });
  page.on('pageerror', e => errs.push(e.message.slice(0, 160)));
  page.on('console', m => { if (m.type() === 'error') errs.push(m.text().slice(0, 160)); });
  await page.goto(`http://localhost:${port}/`);
  let works = false;
  try {
    await page.evaluate(() => window.__ready);
    works = await page.evaluate(async (m) => { const ed = window.__make(m); await new Promise(r => ed.on('load', r)); await new Promise(r => setTimeout(r, 800)); return ed.getHtml().includes('mj-button') && !!document.querySelector('.gjs-frame'); }, src);
  } catch (e) { errs.push(String(e).slice(0, 160)); }
  const v = await page.evaluate(() => window.__v);
  const uniq = [...new Set(v)];
  await page.close(); s.close();
  return { csp, editorWorks: works, violations: uniq, errors: [...new Set(errs)].slice(0, 5) };
}
out.csp = {};
out.csp.strict = await cspRun('strict', "default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; worker-src 'none'; frame-src 'self'; base-uri 'none'; form-action 'none'", 5301);
out.csp.styleInline = await cspRun('style-inline', "default-src 'none'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; worker-src 'none'; frame-src 'self'", 5302);
out.csp.styleInlineEval = await cspRun('style-inline+eval', "default-src 'none'; script-src 'self' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; worker-src 'none'; frame-src 'self'", 5303);

// 4. offline: block every non-localhost request, record attempts
{
  const page = await browser.newPage();
  const external = [];
  await page.route('**/*', (route) => { const u = new URL(route.request().url()); if (u.hostname !== 'localhost') { external.push(route.request().url().slice(0, 100)); return route.abort(); } route.continue(); });
  await page.goto('http://localhost:5199/');
  await page.evaluate(() => window.__ready);
  const m3 = readFileSync('../fixtures/3b-branded-explicit-close.mjml', 'utf8');
  await page.evaluate(async (m) => { const ed = window.__make(m); await new Promise(r => ed.on('load', r)); await new Promise(r => setTimeout(r, 800)); }, m3);
  out.offline = { externalRequestsAttempted: [...new Set(external)] };
  await page.close();
}
await browser.close();
writeFileSync('out/audit.json', JSON.stringify(out, null, 2));
console.log(JSON.stringify(out, null, 2));
