import { chromium } from '@playwright/test';
import { readFileSync } from 'node:fs';
const src = readFileSync('../fixtures/1-reset-password.mjml', 'utf8');
const b = await chromium.launch();
for (const loc of ['en','pl','zh']) {
  const p = await b.newPage(); await p.goto('http://localhost:5199/'); await p.evaluate(() => window.__ready);
  console.log(loc, await p.evaluate(async ([m, loc]) => { const ed = window.__make(m, loc); await new Promise(r => ed.on('load', r)); ed.I18n.setLocale(loc); await new Promise(r => setTimeout(r, 500));
    return { locale: ed.I18n.getLocale(), core: [ed.I18n.t('assetManager.addButton'), ed.I18n.t('styleManager.empty'), ed.I18n.t('panels.buttons.titles.preview')], plugin: [ed.I18n.t('grapesjs-mjml.panels.buttons.undo'), ed.I18n.t('grapesjs-mjml.panels.import.title')], hasPluginKeys: Object.keys(ed.I18n.getMessages()[loc] ?? {}).includes('grapesjs-mjml') }; }, [src, loc]));
  await p.close();
}
await b.close();
