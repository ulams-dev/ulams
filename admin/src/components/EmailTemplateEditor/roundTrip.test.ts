/**
 * @jest-environment jsdom
 */
import { describe, expect, it } from '@jest/globals';
import {
  analyseMjml,
  isMjmlDocument,
  lineDiff,
  normalizeSelfClosing,
  parseMarkup,
  treeLines,
} from './roundTrip';

const lines = (mjml: string) => treeLines(parseMarkup(mjml));

describe('normalizeSelfClosing', () => {
  it('opens and closes mj tags written as self-closing', () => {
    expect(
      normalizeSelfClosing('<mj-head><mj-font name="a" href="https://x/y?a=1&b=2" /></mj-head>'),
    ).toBe('<mj-head><mj-font name="a" href="https://x/y?a=1&b=2"></mj-font></mj-head>');
  });

  it('keeps comments, quoted ">" and html tags as they are', () => {
    const src = '<!-- <mj-x /> --><mj-text><br/>a</mj-text><mj-class name="q" css="a>b" />';
    expect(normalizeSelfClosing(src)).toBe(
      '<!-- <mj-x /> --><mj-text><br/>a</mj-text><mj-class name="q" css="a>b"></mj-class>',
    );
  });
});

describe('isMjmlDocument', () => {
  it('accepts an mjml root and rejects html', () => {
    expect(isMjmlDocument('  <!-- c -->\n<mjml><mj-body/></mjml>')).toBe(true);
    expect(isMjmlDocument('<p>Hello</p>')).toBe(false);
    expect(isMjmlDocument('')).toBe(false);
  });
});

describe('treeLines', () => {
  it('ignores formatting, attribute order and self-closing syntax', () => {
    const a =
      '<mjml><mj-body><mj-text color="red" font-size="12px">Hi\n  there</mj-text><mj-spacer /></mj-body></mjml>';
    const b =
      '<mjml>\n<mj-body>\n<mj-text font-size="12px"   color="red">Hi there</mj-text>\n<mj-spacer></mj-spacer></mj-body>\n</mjml>';
    expect(lines(a)).toEqual(lines(b));
  });

  it('treats the tbody of the browser and a trailing semicolon as equal', () => {
    const a = '<mj-raw><table style="width:100%"><tr><td>x</td></tr></table></mj-raw>';
    const b =
      '<mj-raw><table style="width: 100%;"><tbody><tr><td>x</td></tr></tbody></table></mj-raw>';
    expect(lines(a)).toEqual(lines(b));
  });

  it('sees dropped nodes, comments and changed attributes', () => {
    const base = lines('<mj-text css-class="a"><!-- c -->x</mj-text>');
    expect(lines('<mj-text css-class="b"><!-- c -->x</mj-text>')).not.toEqual(base);
    expect(lines('<mj-text css-class="a">x</mj-text>')).not.toEqual(base);
  });

  it('does not parse the content of mj-style as markup', () => {
    expect(lines('<mj-style>a > b { color: red }</mj-style>')).toEqual([
      '<mj-style>',
      'mj-style > #text a > b { color: red }',
    ]);
  });
});

describe('lineDiff', () => {
  it('reports removed and added lines in order', () => {
    expect(lineDiff(['a', 'b', 'c'], ['a', 'x', 'c'])).toEqual([
      { type: 'same', text: 'a' },
      { type: 'del', text: 'b' },
      { type: 'add', text: 'x' },
      { type: 'same', text: 'c' },
    ]);
  });
});

describe('analyseMjml', () => {
  const echo = async (source: string) => source;

  it('sends plain html to Code view without calling the editor', async () => {
    let called = false;
    const analysis = await analyseMjml('<p>hi</p>', async (s) => {
      called = true;
      return s;
    });
    expect(analysis.kind).toBe('html');
    expect(called).toBe(false);
  });

  it('opens a template the editor keeps, with self-closing tags normalised', async () => {
    const analysis = await analyseMjml(
      '<mjml><mj-head><mj-font name="a" href="b" /></mj-head><mj-body></mj-body></mjml>',
      echo,
    );
    expect(analysis.kind).toBe('representable');
    if (analysis.kind === 'representable') expect(analysis.source).toContain('</mj-font>');
  });

  it('reports what the editor would lose', async () => {
    const analysis = await analyseMjml(
      '<mjml><mj-body><mj-section><mj-column><mj-table><tr><td>a</td></tr></mj-table></mj-column></mj-section></mj-body></mjml>',
      async () =>
        '<mjml><mj-body><mj-section><mj-column><mj-table>a</mj-table></mj-column></mj-section></mj-body></mjml>',
    );
    expect(analysis.kind).toBe('lossy');
    if (analysis.kind === 'lossy') {
      expect(analysis.lost.join('\n')).toContain('<tr>');
      expect(analysis.added.join('\n')).toContain('#text a');
      expect(analysis.diff.some((l) => l.type === 'del')).toBe(true);
    }
  });
});
