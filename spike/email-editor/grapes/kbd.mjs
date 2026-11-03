import { chromium } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { readFileSync, writeFileSync } from 'node:fs';
const src = readFileSync('../fixtures/1-reset-password.mjml', 'utf8');
const browser = await chromium.launch();
const ctx = await browser.newContext(); const page = await ctx.newPage();
await page.goto('http://localhost:5199/');
await page.evaluate(() => window.__ready);
await page.evaluate(async (m) => { const ed = window.__make(m); await new Promise(r => ed.on('load', r)); await new Promise(r => setTimeout(r, 800)); ed.runCommand('open-blocks'); await new Promise(r => setTimeout(r, 300)); }, src);
const out = {};
out.info = await page.evaluate(() => {
  const root = document.querySelector('#gjs');
  const sel = 'a[href],button,input,select,textarea,[tabindex]:not([tabindex="-1"])';
  const t = [...root.querySelectorAll(sel)];
  const count = {}; t.forEach(e => { const k = e.tagName + '.' + (e.className.toString().split(' ')[0] || ''); count[k] = (count[k] || 0) + 1; });
  const btns = [...root.querySelectorAll('.gjs-pn-btn')];
  const blocks = [...root.querySelectorAll('.gjs-block')];
  return { tabbableByKind: count, panelButtons: btns.length, panelButtonTags: [...new Set(btns.map(b => b.tagName))], panelButtonsTabbable: btns.filter(b => b.tabIndex >= 0).length, panelButtonsWithAriaLabel: btns.filter(b => b.getAttribute('aria-label')).length, panelButtonsWithTitle: btns.filter(b => b.getAttribute('title')).length, blocks: blocks.length, blockTags: [...new Set(blocks.map(b => b.tagName))], blocksTabbable: blocks.filter(b => b.tabIndex >= 0).length, blocksDraggable: blocks.filter(b => b.draggable).length, blocksWithRole: blocks.filter(b => b.getAttribute('role')).length };
});
const res = await new AxeBuilder({ page }).include('#gjs').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
out.axe = res.violations.map(v => ({ id: v.id, impact: v.impact, nodes: v.nodes.length, sample: v.nodes[0].target.join(' ') }));
// keyboard: tab from the first panel button, 40 stops, record whether focus ever lands on a panel button or a block
await page.evaluate(() => document.querySelector('.gjs-pn-btn')?.focus());
const reached = { panelBtn: 0, block: 0, input: 0, iframe: 0, other: 0 };
for (let i = 0; i < 80; i++) { await page.keyboard.press('Tab'); const k = await page.evaluate(() => { const a = document.activeElement; if (!a) return 'other'; if (a.classList.contains('gjs-pn-btn')) return 'panelBtn'; if (a.classList.contains('gjs-block')) return 'block'; if (a.tagName === 'IFRAME') return 'iframe'; if (['INPUT','SELECT','TEXTAREA'].includes(a.tagName)) return 'input'; return 'other'; }); reached[k]++; }
out.tabReached80 = reached;
// Try to add a block with keyboard only: focus first block and press Enter/Space
const before = await page.evaluate(() => window.__grapesjs.editors[0].getWrapper().find('mj-text').length);
await page.evaluate(() => document.querySelector('.gjs-block')?.focus());
await page.keyboard.press('Enter'); await page.keyboard.press('Space');
const after = await page.evaluate(() => window.__grapesjs.editors[0].getWrapper().find('mj-text').length);
out.keyboardAddBlock = { before, after, focusedIsBlock: await page.evaluate(() => document.activeElement?.classList.contains('gjs-block')) };
await page.screenshot({ path: 'out/editor.png' });
await browser.close();
writeFileSync('out/kbd.json', JSON.stringify(out, null, 2)); console.log(JSON.stringify(out, null, 2));
