/**
 * @jest-environment jsdom
 */
import { describe, expect, it, jest } from '@jest/globals';
import { readFileSync } from 'fs';
import grapesjs, { type Component } from 'grapesjs';
import { join } from 'path';
import { analyseMjml, parseMarkup, treeLines } from '../roundTrip';
import { buildConfig, exportMjml, exportOnce } from './engine';
import { editorLocale, editorMessages, zhPlugin } from './locales';
import { applyImage, insertVariable } from './ulams';

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

const VARIABLES = [
  '@VarUserName',
  '@VarActionLink',
  '@VarActionLinkExpiration',
  '@VarAcademyUrl',
  '@GlobalSettingsMailFooterMjml',
];
const analyseWithVariables = (name: string) =>
  analyseMjml(fixture(name), (source) => exportOnce(source, 'en-US', { variables: VARIABLES }));

describe('chips and locked parts on the spike fixtures (M4)', () => {
  it.each([
    '1-reset-password',
    '2-training-due-soon',
    '3b-branded-explicit-close',
    '3-branded-handwritten',
  ])('does not change the stored MJML of %s when the variables open as chips', async (name) => {
    const withChips = await analyseWithVariables(name);
    const without = await analyse(name);
    expect(withChips.kind).toBe('representable');
    expect(without.kind).toBe('representable');
    if (withChips.kind === 'representable' && without.kind === 'representable') {
      expect(treeLines(parseMarkup(withChips.exported))).toEqual(
        treeLines(parseMarkup(withChips.source)),
      );
      // the decoration is invisible in the stored text
      expect(withChips.exported).not.toMatch(/ulams-|data-ulams/);
    }
  });

  const load = (mjml: string, variables = VARIABLES) =>
    grapesjs.init({ ...buildConfig(mjml, 'en-US', {}, { variables }), headless: true });
  const all = (editor: ReturnType<typeof load>) => {
    const found: Component[] = [];
    const walk = (c: Component) => {
      found.push(c);
      c.components().forEach(walk);
    };
    walk(editor.getWrapper() as Component);
    return found;
  };

  it('opens variables of a text block as non-editable chips and exports the plain token', () => {
    const editor = load(fixture('3b-branded-explicit-close'));
    const chips = all(editor).filter((c) => c.get('type') === 'ulams-variable');
    expect(chips.map((c) => c.getAttributes()['data-ulams-variable'])).toEqual(['${VarUserName}']);
    expect(chips[0].get('editable')).toBe(false);
    const out = exportMjml(editor);
    expect(out).toContain('Cześć ${VarUserName}!');
    expect(out).not.toContain('ulams-chip');
    editor.destroy();
  });

  it('opens the shared footer as a locked block that cannot be moved, removed, copied or edited', () => {
    const editor = load(fixture('3b-branded-explicit-close'));
    const parts = all(editor).filter((c) => c.get('type') === 'ulams-partial');
    expect(parts).toHaveLength(1);
    const [part] = parts;
    expect(part.getAttributes()['data-ulams-partial']).toBe('@GlobalSettingsMailFooterMjml');
    ['removable', 'draggable', 'copyable', 'droppable', 'editable'].forEach((flag) =>
      expect(part.get(flag)).toBe(false),
    );
    // the label inside cannot be selected or edited either
    const inside: Component[] = [];
    part.onAll((child: Component) => child !== part && inside.push(child));
    expect(inside.length).toBeGreaterThan(0);
    inside.forEach((child) => {
      expect(child.get('editable')).toBe(false);
      expect(child.get('selectable')).toBe(false);
    });
    // and it exports as the plain token of the stored template, not expanded
    expect(exportMjml(editor)).toContain('@GlobalSettingsMailFooterMjml</mj-body>');
    editor.destroy();
  });

  it('keeps the shared footer when everything else is edited', () => {
    const editor = load(fixture('3b-branded-explicit-close'));
    const text = all(editor).find((c) => c.get('type') === 'mj-text') as Component;
    text.components('<p>Changed</p>');
    const out = exportMjml(editor);
    expect(out).toContain('Changed');
    expect(out).toContain('@GlobalSettingsMailFooterMjml');
    editor.destroy();
  });

  it('inserts a chip into the selected text block, nowhere else', () => {
    const editor = load(fixture('1-reset-password'));
    const nodes = all(editor);
    expect(insertVariable(editor, '@VarUserName')).toBe('no-target');
    editor.select(nodes.find((c) => c.get('type') === 'mj-button'));
    expect(insertVariable(editor, '@VarUserName')).toBe('no-target');
    editor.select(nodes.find((c) => c.get('type') === 'mj-text'));
    expect(insertVariable(editor, '@VarUserName')).toBe('inserted');
    const out = exportMjml(editor);
    expect(out).toContain('@VarUserName</mj-text>');
    expect(out).not.toContain('data-ulams');
    expect(all(editor).some((c) => c.get('type') === 'ulams-variable')).toBe(true);
    editor.destroy();
  });

  it('writes a variable into the link of a button or an image from the link picker', () => {
    const editor = load(fixture('1-reset-password'));
    const button = all(editor).find((c) => c.get('type') === 'mj-button') as Component;
    const traits = button.getTraits().map((t) => t.getName());
    expect(traits).toContain('ulams-href-variable');
    button.set('ulams-href-variable' as never, '@VarAcademyUrl' as never);
    expect(exportMjml(editor)).toMatch(/<mj-button[^>]*href="@VarAcademyUrl"/);
    expect(button.get('ulams-href-variable' as never)).toBe('');
    editor.destroy();
  });

  it('puts an uploaded image into mj-image with its alt text, empty for a decorative image', () => {
    const editor = load(
      '<mjml><mj-body><mj-section><mj-column><mj-image src="https://e.test/a.png" alt="old"></mj-image></mj-column></mj-section></mj-body></mjml>',
    );
    const image = all(editor).find((c) => c.get('type') === 'mj-image') as Component;
    applyImage(image, { url: 'https://cdn.test/email-images/x.png', alt: 'Academy logo' });
    let out = exportMjml(editor);
    expect(out).toContain('src="https://cdn.test/email-images/x.png"');
    expect(out).toContain('alt="Academy logo"');
    applyImage(image, { url: 'https://cdn.test/email-images/y.png', alt: '' });
    out = exportMjml(editor);
    expect(out).toContain('src="https://cdn.test/email-images/y.png"');
    expect(out).toMatch(/alt=""/);
    editor.destroy();
  });

  it('opens the image dialog for the selected image and applies the result', async () => {
    const pickImage = jest.fn(async () => ({
      url: 'https://cdn.test/email-images/z.png',
      alt: 'Z',
    }));
    const editor = grapesjs.init({
      ...buildConfig(
        '<mjml><mj-body><mj-section><mj-column><mj-image src="https://e.test/a.png"></mj-image></mj-column></mj-section></mj-body></mjml>',
        'en-US',
        {},
        { variables: [], pickImage },
      ),
      headless: true,
    });
    const image = all(editor).find((c) => c.get('type') === 'mj-image') as Component;
    editor.select(image);
    editor.runCommand('open-assets', { target: image });
    await Promise.resolve();
    await Promise.resolve();
    expect(pickImage).toHaveBeenCalledTimes(1);
    expect(exportMjml(editor)).toContain('src="https://cdn.test/email-images/z.png"');
    editor.destroy();
  });
});

describe('strings of the additions', () => {
  it('exist in English, Polish, Chinese and French with the same keys', () => {
    const keys = Object.keys((editorMessages.en as { ulams: object }).ulams).sort();
    ['pl', 'zh', 'fr'].forEach((locale) =>
      expect(Object.keys((editorMessages[locale] as { ulams: object }).ulams).sort()).toEqual(keys),
    );
    expect(JSON.stringify(editorMessages.zh)).toMatch(/共享部分/);
  });
});
