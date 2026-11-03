import { describe, expect, it } from '@jest/globals';
import {
  CHIP_ATTR,
  PARTIAL_ATTR,
  fromVisual,
  lockedParts,
  missingRequired,
  toVisual,
  usesVariable,
} from './variables';

const VARS = ['@VarUserName', '@VarUser', '@VarActionLink', '@GlobalSettingsMailFooter'];
const wrap = (inner: string) =>
  `<mjml><mj-body><mj-section><mj-column>${inner}</mj-column></mj-section></mj-body></mjml>`;

describe('toVisual', () => {
  it('turns a registered variable inside mj-text into a chip, in both forms', () => {
    const out = toVisual(wrap('<mj-text>Hi @VarUserName and ${VarUserName}</mj-text>'), VARS);
    expect(out.match(new RegExp(CHIP_ATTR, 'g'))).toHaveLength(2);
    expect(out).toContain(`${CHIP_ATTR}="@VarUserName"`);
    expect(out).toContain(`${CHIP_ATTR}="\${VarUserName}"`);
  });

  it('does not match the start of a longer name', () => {
    const out = toVisual(wrap('<mj-text>@VarUserNameX @VarUser</mj-text>'), VARS);
    expect(out).toContain('@VarUserNameX');
    expect(out.match(new RegExp(CHIP_ATTR, 'g'))).toHaveLength(1);
    expect(out).toContain(`${CHIP_ATTR}="@VarUser"`);
  });

  it('leaves tokens in attributes, other elements and unregistered tokens alone', () => {
    const source = wrap(
      '<mj-button href="@VarActionLink">@VarUserName</mj-button><mj-text><a href="@VarActionLink">go</a> @VarUnknown</mj-text><!-- @VarUserName -->',
    );
    const out = toVisual(source, VARS);
    expect(out).toContain('<mj-button href="@VarActionLink">@VarUserName</mj-button>');
    expect(out).toContain('<a href="@VarActionLink">go</a> @VarUnknown');
    expect(out).toContain('<!-- @VarUserName -->');
  });

  it('locks a shared part that stands alone in the layout', () => {
    const source =
      '<mjml><mj-body><mj-section><mj-column><mj-text>x</mj-text></mj-column></mj-section>\n@GlobalSettingsMailFooter\n</mj-body></mjml>';
    const out = toVisual(source, VARS, (token) => `Shared ${token}`);
    expect(out).toContain(`<mj-raw ${PARTIAL_ATTR}="@GlobalSettingsMailFooter">`);
    expect(out).toContain('Shared @GlobalSettingsMailFooter');
    // inside a column too
    expect(toVisual(wrap('@GlobalSettingsMailFooter'), VARS)).toContain(PARTIAL_ATTR);
  });

  it('does not lock a shared part used as a value inside text', () => {
    const out = toVisual(wrap('<mj-text>Call @GlobalSettingsMailFooter</mj-text>'), VARS);
    expect(out).not.toContain(PARTIAL_ATTR);
    expect(out).toContain(`${CHIP_ATTR}="@GlobalSettingsMailFooter"`);
  });

  it('is a no-op for documents without variables and without a list', () => {
    const source = wrap('<mj-text>Hello</mj-text>');
    expect(toVisual(source, [])).toBe(source);
    expect(toVisual(source, VARS)).toBe(source);
  });
});

describe('fromVisual', () => {
  const sources = [
    wrap(
      '<mj-text>Hi @VarUserName, ${VarUserName}!</mj-text><mj-button href="@VarActionLink">Go</mj-button>',
    ),
    '<mjml><mj-body><mj-section><mj-column><mj-text>a</mj-text></mj-column></mj-section>\n@GlobalSettingsMailFooter\n</mj-body></mjml>',
    wrap('<mj-text><p>@VarUser <b>@VarUserName</b></p></mj-text>'),
    wrap('<mj-text>nothing here & "quotes" <i>x</i></mj-text>'),
  ];

  it.each(sources)('restores the exact text that toVisual decorated (%#)', (source) => {
    const visual = toVisual(source, VARS, (t) => `Shared ${t}`);
    expect(fromVisual(visual)).toBe(source.replace(/\n(?=@Global)/, '\n'));
  });

  it('turns a chip that was moved or edited back into its token', () => {
    const visual = `<mj-text>a<span class="x" ${CHIP_ATTR}="@VarUserName">changed text</span>b</mj-text>`;
    expect(fromVisual(visual)).toBe('<mj-text>a@VarUserNameb</mj-text>');
  });

  it('puts the original text back even if the label of a shared part was changed', () => {
    const visual = `<mj-raw ${PARTIAL_ATTR}="@GlobalSettingsMailFooter"><div>edited label</div></mj-raw>`;
    expect(fromVisual(visual)).toBe('@GlobalSettingsMailFooter');
  });
});

describe('decorative images', () => {
  const source = wrap(
    '<mj-image src="https://e.test/a.png" alt=""></mj-image><mj-image src="b" alt="Logo" />',
  );

  it('keeps an explicit empty alt through the editor, which drops empty attributes', () => {
    const visual = toVisual(source, []);
    expect(visual).not.toContain('alt=""');
    expect(visual).toContain('data-ulams-decorative="true"');
    expect(visual).toContain('alt="Logo"');
    expect(fromVisual(visual)).toBe(source);
  });
});

describe('lockedParts, usesVariable and missingRequired', () => {
  it('lists the shared parts that stand alone, once each', () => {
    const source =
      '<mjml><mj-body>@GlobalSettingsMailHeader<mj-section><mj-column><mj-text>@GlobalSettingsX</mj-text></mj-column></mj-section>@GlobalSettingsMailFooter @GlobalSettingsMailFooter</mj-body></mjml>';
    expect(lockedParts(source)).toEqual(['@GlobalSettingsMailHeader', '@GlobalSettingsMailFooter']);
  });

  it('uses the rule of the server: the @Var or the ${Var} form', () => {
    expect(usesVariable('hi @VarUserName', '@VarUserName')).toBe(true);
    expect(usesVariable('hi ${VarUserName}', '@VarUserName')).toBe(true);
    expect(usesVariable('hi @VarUserNameX', '@VarUserName')).toBe(false);
    expect(usesVariable('hi', '@VarUserName')).toBe(false);
  });

  it('reports the required variables that are not used yet', () => {
    expect(missingRequired('<p>@VarA ${VarB}</p>', ['@VarA', '@VarB', '@VarC'])).toEqual(['@VarC']);
    expect(missingRequired('', [])).toEqual([]);
  });
});
