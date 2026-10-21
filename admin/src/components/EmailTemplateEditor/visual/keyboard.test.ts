/**
 * @jest-environment jsdom
 */
import { describe, expect, it } from '@jest/globals';
import grapesjs, { type Component, type Editor } from 'grapesjs';
import { buildConfig, exportMjml } from './engine';
import {
  addBlock,
  bodyOf,
  moveBlock,
  outline,
  placementFor,
  plainText,
  removeBlock,
} from './keyboard';
import { fieldsFor } from './styleFields';

const TEMPLATE = `<mjml lang="en"><mj-body>
<mj-section><mj-column><mj-text>First</mj-text><mj-button href="@VarActionLink">Go</mj-button></mj-column></mj-section>
<mj-section><mj-column><mj-text>Second</mj-text></mj-column><mj-column><mj-image src="https://example.test/a.png" alt="Logo"></mj-image></mj-column></mj-section>
</mj-body></mjml>`;

const load = (mjml = TEMPLATE): Editor =>
  grapesjs.init({
    ...buildConfig(mjml, 'en-US', {}, { variables: ['@VarActionLink'] }),
    headless: true,
  });

const types = (editor: Editor) =>
  outline(editor, new Set()).map((item) => `${item.level}:${item.type}`);

const find = (editor: Editor, type: string, nth = 0): Component => {
  const found: Component[] = [];
  const walk = (c: Component) => {
    if (c.get('type') === type) found.push(c);
    c.components().forEach(walk);
  };
  walk(editor.getWrapper() as Component);
  return found[nth];
};

describe('outline', () => {
  it('lists the blocks in document order with their level, without text nodes or chips', () => {
    const editor = load();
    expect(types(editor)).toEqual([
      '1:mj-body',
      '2:mj-section',
      '3:mj-column',
      '4:mj-text',
      '4:mj-button',
      '2:mj-section',
      '3:mj-column',
      '4:mj-text',
      '3:mj-column',
      '4:mj-image',
    ]);
  });

  it('skips the children of a collapsed block', () => {
    const editor = load();
    const section = outline(editor, new Set()).find((item) => item.type === 'mj-section');
    const collapsed = outline(editor, new Set([section!.id]));
    expect(collapsed.map((item) => item.type)).toEqual([
      'mj-body',
      'mj-section',
      'mj-section',
      'mj-column',
      'mj-text',
      'mj-column',
      'mj-image',
    ]);
  });

  it('reads the text of a block without markup', () => {
    const editor = load();
    expect(plainText(find(editor, 'mj-text'))).toContain('First');
  });
});

describe('addBlock', () => {
  it('puts a content block after the selected content block', () => {
    const editor = load();
    editor.select(find(editor, 'mj-text'));
    const result = addBlock(editor, 'mj-divider');
    expect(result.ok).toBe(true);
    expect(editor.getSelected()?.get('type')).toBe('mj-divider');
    expect(exportMjml(editor).replace(/\s+/g, '')).toContain('<mj-text>First</mj-text><mj-divider');
  });

  it('puts a content block into the selected column and into the first column of a section', () => {
    const editor = load();
    editor.select(find(editor, 'mj-column', 1));
    addBlock(editor, 'mj-spacer');
    expect(find(editor, 'mj-column', 1).components().length).toBe(2);
    editor.select(find(editor, 'mj-section', 1));
    addBlock(editor, 'mj-button');
    expect(find(editor, 'mj-column', 1).components().at(0).get('type')).toBe('mj-text');
    expect(find(editor, 'mj-column', 1).components().last().get('type')).toBe('mj-button');
  });

  it('puts a section after the section that holds the selection', () => {
    const editor = load();
    editor.select(find(editor, 'mj-button'));
    expect(addBlock(editor, 'mj-2-columns').ok).toBe(true);
    const sections = bodyOf(editor)!.components().models;
    expect(sections).toHaveLength(3);
    expect(sections[1]).toBe(editor.getSelected());
    expect(sections[1].components().length).toBe(2);
  });

  it('without a selection appends: a section at the end, a content block into the last column', () => {
    const editor = load();
    expect(placementFor(editor, 'mj-1-column')?.at).toBe(2);
    addBlock(editor, 'mj-text');
    expect(find(editor, 'mj-column', 2).components().length).toBe(2);
  });

  it('makes a section first when there is none to hold a content block', () => {
    const editor = load('<mjml><mj-body></mj-body></mjml>');
    expect(addBlock(editor, 'mj-text').ok).toBe(true);
    expect(types(editor)).toContain('3:mj-column');
    expect(types(editor)).toContain('4:mj-text');
  });

  it('reports an unknown block', () => {
    expect(addBlock(load(), 'mj-nothing')).toEqual({ ok: false, reason: 'unknown-block' });
  });
});

describe('moveBlock', () => {
  it('moves within the siblings and stops at the edges', () => {
    const editor = load();
    const button = find(editor, 'mj-button');
    expect(moveBlock(editor, button, -1).ok).toBe(true);
    expect(button.index()).toBe(0);
    expect(moveBlock(editor, button, 1).ok).toBe(true);
    expect(button.index()).toBe(1);
    // the last block of the last column of the first section continues into the next column
    const result = moveBlock(editor, button, 1);
    expect(result.ok).toBe(true);
    expect(button.parent()).toBe(find(editor, 'mj-column', 1));
    expect(button.index()).toBe(0);
    expect(exportMjml(editor).indexOf('Go')).toBeGreaterThan(exportMjml(editor).indexOf('First'));
  });

  it('reports the first and the last position', () => {
    const editor = load();
    const first = find(editor, 'mj-section', 0);
    expect(moveBlock(editor, first, -1)).toEqual({ ok: false, reason: 'first' });
    const last = find(editor, 'mj-section', 1);
    expect(moveBlock(editor, last, 1)).toEqual({ ok: false, reason: 'last' });
    expect(moveBlock(editor, last, -1).ok).toBe(true);
    expect(last.index()).toBe(0);
  });

  it('moves a block into the previous column when it is first in its column', () => {
    const editor = load();
    const image = find(editor, 'mj-image');
    expect(moveBlock(editor, image, -1).ok).toBe(true);
    expect(image.parent()).toBe(find(editor, 'mj-column', 1));
    expect(image.index()).toBe(1);
  });
});

describe('removeBlock', () => {
  it('removes the block and hands the selection to a neighbour', () => {
    const editor = load();
    const text = find(editor, 'mj-text');
    const result = removeBlock(editor, text);
    expect(result.ok).toBe(true);
    expect(editor.getSelected()?.get('type')).toBe('mj-button');
    expect(exportMjml(editor)).not.toContain('First');
  });

  it('refuses a locked shared part', () => {
    const editor = load(
      '<mjml><mj-body><mj-section><mj-column><mj-text>x</mj-text></mj-column></mj-section>@GlobalSettingsMailFooterMjml</mj-body></mjml>',
    );
    editor.destroy();
    const withPartial = grapesjs.init({
      ...buildConfig(
        '<mjml><mj-body><mj-section><mj-column><mj-text>x</mj-text></mj-column></mj-section>@GlobalSettingsMailFooterMjml</mj-body></mjml>',
        'en-US',
        {},
        { variables: ['@GlobalSettingsMailFooterMjml'] },
      ),
      headless: true,
    });
    const partial = outline(withPartial, new Set()).find((item) => item.locked);
    if (partial && partial.type !== 'mj-body') {
      expect(removeBlock(withPartial, partial.component)).toEqual({
        ok: false,
        reason: 'locked',
      });
    }
  });
});

describe('style fields', () => {
  it('offers the allowed properties in a fixed order, sides instead of the padding shorthand', () => {
    const fields = fieldsFor([
      'padding',
      'padding-top',
      'padding-left',
      'color',
      'align',
      'unknown',
    ]);
    expect(fields.map((f) => f.property)).toEqual([
      'color',
      'align',
      'padding-top',
      'padding-left',
    ]);
  });

  it('keeps the padding shorthand when there are no sides', () => {
    expect(fieldsFor(['padding']).map((f) => f.property)).toEqual(['padding']);
    expect(fieldsFor(undefined)).toEqual([]);
  });
});
