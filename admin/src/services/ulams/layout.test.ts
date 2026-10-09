import {
  LAYOUT_STARTER,
  checkLayoutDocument,
  checkLayoutText,
  layoutComponents,
  layoutFields,
  layoutPreviewHref,
  layoutText,
} from './layout';

describe('layout service', () => {
  it('offers the nine approved layout components', () => {
    expect(layoutComponents().sort()).toEqual(
      [
        'Callout',
        'CodeBlock',
        'ComparisonTable',
        'FlipCards',
        'H5PFrame',
        'LiaScriptLesson',
        'PracticeActivity',
        'Steps',
        'Timeline',
      ].sort(),
    );
  });

  it('accepts the starter document and returns it', () => {
    const result = checkLayoutDocument(LAYOUT_STARTER);
    expect(result.errors).toEqual([]);
    expect(result.document).toHaveLength(2);
    expect(checkLayoutText(JSON.stringify(LAYOUT_STARTER)).document).toHaveLength(2);
  });

  it('reports bad JSON and an empty editor', () => {
    expect(checkLayoutText('').errors[0]).toMatch(/empty/);
    expect(checkLayoutText('{nope').errors[0]).toMatch(/not valid JSON/);
    expect(checkLayoutText('{}').errors[0]).toMatch(/non-empty list/);
    expect(checkLayoutText('[]').errors[0]).toMatch(/non-empty list/);
  });

  it('rejects components outside the approved set, unknown keys and bad ids', () => {
    const bad = (node: unknown) => checkLayoutDocument([node]).errors.join('\n');
    expect(bad({ component: 'Hero', props: {} })).toMatch(/not an approved layout component/);
    expect(bad({ props: { text: 'x' } })).toMatch(/\/0\/component: missing/);
    expect(bad({ component: 'Callout', props: { text: 'x' }, children: [] })).toMatch(/unknown key children/);
    expect(bad({ component: 'Callout', props: { text: 'x' }, id: 'a b' })).toMatch(/\/0\/id/);
    expect(bad('text')).toMatch(/must be an object/);
    expect(bad({ component: 'Callout', props: ['x'] })).toMatch(/\/0\/props: must be an object/);
  });

  it('names the node and the prop path of a problem', () => {
    const result = checkLayoutDocument([
      LAYOUT_STARTER[0],
      { component: 'Timeline', props: { items: 'nope' } },
      { component: 'Callout', props: { text: 'x', onclick: 'alert(1)' } },
    ]);
    expect(result.document).toBeUndefined();
    expect(result.errors.some((e) => e.startsWith('/1/props/items'))).toBe(true);
    expect(result.errors.some((e) => e.startsWith('/2/props/onclick'))).toBe(true);
  });

  it('rejects unsafe links', () => {
    const errors = checkLayoutDocument([
      { component: 'LiaScriptLesson', props: { src: 'javascript:alert(1)', title: 'x' } },
    ]).errors;
    expect(errors[0]).toMatch(/^\/0\/props\/src/);
  });

  it('limits the number of nodes', () => {
    const many = Array.from({ length: 81 }, () => LAYOUT_STARTER[0]);
    expect(checkLayoutDocument(many).errors[0]).toMatch(/at most 80/);
  });

  it('shows the document as pretty JSON and sends it as JSON text', () => {
    expect(layoutText(undefined)).toBe('');
    expect(layoutText('[]')).toBe('[]');
    expect(layoutText(LAYOUT_STARTER)).toBe(JSON.stringify(LAYOUT_STARTER, null, 2));
    expect(layoutFields('[1]', 'Fallback')).toEqual({ document: '[1]', markdown_fallback: 'Fallback' });
  });

  it('builds the preview link only for a saved topic', () => {
    expect(layoutPreviewHref('http://coffee.app.localhost:4321/', 3, 9)).toBe(
      'http://coffee.app.localhost:4321/preview/courses/3/9',
    );
    expect(layoutPreviewHref(null, 3, 9)).toBeNull();
    expect(layoutPreviewHref('http://x', 3, undefined)).toBeNull();
    expect(layoutPreviewHref('http://x', undefined, 9)).toBeNull();
  });
});
