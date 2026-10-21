/**
 * @jest-environment jsdom
 */
import { describe, expect, it } from '@jest/globals';
import { readFileSync } from 'fs';
import { join } from 'path';
import { analyseMjml, parseMarkup, treeLines } from '../roundTrip';
import { buildConfig, exportOnce } from './engine';
import { editorLocale, editorMessages, zhPlugin } from './locales';

const fixture = (name: string) =>
  readFileSync(join(__dirname, '..', '__fixtures__', `${name}.mjml`), 'utf8');
const analyse = (name: string) =>
  analyseMjml(fixture(name), (source) => exportOnce(source, 'en-US'));

describe('round trip through GrapesJS and grapesjs-mjml (the fixtures of the M0 spike)', () => {
  it('keeps the reset password template (1)', async () => {
    expect((await analyse('1-reset-password')).kind).toBe('representable');
  });

  it('keeps the training due soon template (2)', async () => {
    expect((await analyse('2-training-due-soon')).kind).toBe('representable');
  });

  it('keeps the hand-written branded template with explicit close tags (3b)', async () => {
    expect((await analyse('3b-branded-explicit-close')).kind).toBe('representable');
  });

  it('keeps the branded template with self-closing head tags (3) after normalising them', async () => {
    const analysis = await analyse('3-branded-handwritten');
    expect(analysis.kind).toBe('representable');
    if (analysis.kind === 'representable') {
      // The head is the structure that was corrupted without the normalisation (spike, fixture 3).
      const head = treeLines(parseMarkup(analysis.exported)).filter((l) =>
        l.startsWith('mjml > mj-head'),
      );
      expect(head).toEqual(
        treeLines(parseMarkup(fixture('3b-branded-explicit-close'))).filter((l) =>
          l.startsWith('mjml > mj-head'),
        ),
      );
    }
  });

  it('would corrupt the head of template 3 without the normalisation (why it exists)', async () => {
    const raw = fixture('3-branded-handwritten');
    const exported = await exportOnce(raw, 'en-US');
    expect(treeLines(parseMarkup(exported))).not.toEqual(treeLines(parseMarkup(raw)));
  });

  it('does not keep the mj-table of the stress template (4) and says what is lost', async () => {
    const analysis = await analyse('4-stress-constructs');
    expect(analysis.kind).toBe('lossy');
    if (analysis.kind === 'lossy') {
      expect(analysis.lost.some((line) => line.includes('<tr'))).toBe(true);
    }
  });

  it('exporting does not change the stored text of what it loads', async () => {
    const source = fixture('1-reset-password');
    const first = await exportOnce(source);
    expect(treeLines(parseMarkup(first))).toEqual(treeLines(parseMarkup(source)));
  });
});

describe('editor configuration', () => {
  const config = buildConfig('<mjml></mjml>', 'pl-PL');

  it('has no remote icon stylesheet, no storage and no locale detection', () => {
    expect(config.cssIcons).toBe('');
    expect(config.storageManager).toBe(false);
    expect(config.i18n?.detectLocale).toBe(false);
    expect(config.i18n?.locale).toBe('pl');
  });

  it('refuses scripts and unsafe attributes in the same-origin canvas', () => {
    expect(config.parser?.optionsHtml).toEqual({
      allowScripts: false,
      allowUnsafeAttr: false,
      allowUnsafeAttrValue: false,
    });
  });
});

describe('locales', () => {
  it('maps the admin locale and falls back to English', () => {
    expect(editorLocale('pl-PL')).toBe('pl');
    expect(editorLocale('zh-CN')).toBe('zh');
    expect(editorLocale('en-US')).toBe('en');
    expect(editorLocale('ja-JP')).toBe('en');
    expect(editorLocale(undefined)).toBe('en');
  });

  it('has the plugin strings for Chinese, which the plugin does not ship', () => {
    const pl = editorMessages.pl['grapesjs-mjml'] as Record<string, unknown>;
    const zh = editorMessages.zh['grapesjs-mjml'] as Record<string, unknown>;
    expect(Object.keys(zh)).toEqual(Object.keys(pl));
    expect(JSON.stringify(zhPlugin)).toMatch(/撤销/);
    expect(JSON.stringify(editorMessages.zh['grapesjs-mjml'])).not.toMatch(/Undo|Redo|Import/);
  });
});
