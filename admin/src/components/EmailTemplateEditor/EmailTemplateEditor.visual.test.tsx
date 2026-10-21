/**
 * @jest-environment jsdom
 */
import { afterEach, beforeAll, describe, expect, it, jest } from '@jest/globals';
import '@testing-library/jest-dom';
import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { readdirSync, readFileSync } from 'fs';
import { join } from 'path';
import React from 'react';
import type { Analysis } from './roundTrip';

jest.mock('umi', () => ({
  FormattedMessage: ({ id }: { id: string }) => id,
  useIntl: () => ({ formatMessage: (d: { id: string }) => d.id }),
  Link: ({ to, children }: { to: string; children: unknown }) =>
    require('react').createElement('a', { href: to }, children),
  request: jest.fn(),
}));
jest.mock('@/services/ulams/templates', () => ({
  renderEmailDraft: jest.fn(),
  testSendEmailDraft: jest.fn(),
  uploadEmailImage: jest.fn(),
}));
const mockInsert = jest.fn<(token: string) => string>();
const mockPicked = jest.fn();
jest.mock('@webscopeio/react-textarea-autocomplete', () => {
  const R = require('react');
  return {
    __esModule: true,
    default: ({ value, onChange, innerRef, ...rest }: any) =>
      R.createElement('textarea', {
        ref: innerRef,
        value,
        onChange,
        'aria-label': rest['aria-label'],
      }),
  };
});
// The real editor needs a canvas; the engine is covered by visual/engine.test.ts.
jest.mock('./VisualEditor', () => {
  const R = require('react');
  return {
    __esModule: true,
    default: R.forwardRef(({ initial, onChange, variables, pickImage }: any, ref: any) => {
      R.useImperativeHandle(ref, () => ({ insertVariable: (token: string) => mockInsert(token) }));
      return R.createElement(
        'div',
        { 'data-testid': 'visual-editor' },
        R.createElement('span', { 'data-testid': 'visual-initial' }, initial),
        R.createElement('span', { 'data-testid': 'visual-variables' }, (variables ?? []).join(',')),
        R.createElement('button', { onClick: () => onChange('<mjml>edited</mjml>') }, 'edit'),
        R.createElement(
          'button',
          { onClick: () => pickImage().then((i: any) => mockPicked(i)) },
          'pick-image',
        ),
      );
    }),
  };
});

import EmailTemplateEditor from './index';

jest.mock('./ImagePicker', () => ({
  __esModule: true,
  default: ({ open, onDone }: any) =>
    open
      ? require('react').createElement(
          'button',
          { onClick: () => onDone({ url: 'u.png', alt: 'A' }) },
          'picker-open',
        )
      : null,
}));

const MJML = '<mjml><mj-body><mj-text>Hi</mj-text></mj-body></mjml>';
const TABLE = '<mjml><mj-body><mj-table><tr><td>a</td></tr></mj-table></mj-body></mjml>';

const representable = (source = MJML): Analysis => ({
  kind: 'representable',
  source,
  exported: source,
});
const lossy = (): Analysis => ({
  kind: 'lossy',
  exported: '<mjml><mj-body><mj-table>a</mj-table></mj-body></mjml>',
  lost: ['mjml > mj-body > mj-table > <tr>'],
  added: ['mjml > mj-body > mj-table > #text a'],
  diff: [
    { type: 'same', text: '<mj-table>' },
    { type: 'del', text: '<tr>' },
    { type: 'add', text: 'a' },
  ],
});

const setup = (
  analysis: Analysis | Error,
  props: Partial<React.ComponentProps<typeof EmailTemplateEditor>> = {},
) => {
  const analyse = jest.fn(async () => {
    if (analysis instanceof Error) throw analysis;
    return analysis;
  });
  const onChange = jest.fn();
  const utils = render(
    <EmailTemplateEditor
      value={MJML}
      onChange={onChange}
      event="App\\Events\\Test"
      title="Subject"
      variables={['@UserName']}
      requiredVariables={[]}
      render={
        (async () => ({
          data: { title: '', html: '<p>x</p>', errors: [], missing_variables: [], warnings: [] },
        })) as any
      }
      analyse={analyse as any}
      {...props}
    />,
  );
  return { analyse, onChange, ...utils };
};

const chooseVisual = async () => {
  await act(async () => {
    fireEvent.click(screen.getByRole('radio', { name: /templates.editor_mode_visual/ }));
  });
};

describe('EmailTemplateEditor visual mode', () => {
  beforeAll(() => {
    Object.defineProperty(window, 'matchMedia', {
      writable: true,
      value: (query: string) => ({
        matches: false,
        media: query,
        addListener: () => undefined,
        removeListener: () => undefined,
        addEventListener: () => undefined,
        removeEventListener: () => undefined,
        dispatchEvent: () => false,
      }),
    });
  });
  afterEach(() => {
    jest.clearAllMocks();
  });

  it('opens in Code view and carries no beta label', () => {
    const { analyse } = setup(representable());
    expect(screen.getByRole('radio', { name: /templates.editor_mode_code/ })).toBeChecked();
    expect(screen.getByRole('textbox', { name: 'templates.editor_code' })).toBeInTheDocument();
    expect(screen.queryByTestId('visual-editor')).toBeNull();
    expect(screen.queryByText('templates.editor_beta')).toBeNull();
    expect(screen.getByText('templates.visual_keyboard_hint')).toBeInTheDocument();
    expect(analyse).not.toHaveBeenCalled();
  });

  it('offers no visual mode when the operator switched it off', () => {
    setup(representable(), { visualEnabled: false });
    expect(screen.queryByRole('radio')).toBeNull();
    expect(screen.getByRole('textbox', { name: 'templates.editor_code' })).toBeInTheDocument();
  });

  it('checks the template first and opens the editor with the checked source', async () => {
    const source = `${MJML}<!-- normalised -->`;
    const { analyse, onChange } = setup(representable(source), { locale: 'pl-PL' });
    await chooseVisual();
    expect(analyse).toHaveBeenCalledWith(MJML, 'pl-PL', ['@UserName']);
    expect(await screen.findByTestId('visual-editor')).toBeInTheDocument();
    expect(screen.getByTestId('visual-initial')).toHaveTextContent(source);
    expect(screen.queryByRole('textbox', { name: 'templates.editor_code' })).toBeNull();
    // Opening the editor never rewrites the template.
    expect(onChange).not.toHaveBeenCalled();
  });

  it('passes edits of the editor to the form and returns to the code of the same MJML', async () => {
    const { onChange, rerender } = setup(representable());
    await chooseVisual();
    fireEvent.click(await screen.findByText('edit'));
    expect(onChange).toHaveBeenCalledWith('<mjml>edited</mjml>');
    fireEvent.click(screen.getByRole('radio', { name: /templates.editor_mode_code/ }));
    expect(screen.getByRole('textbox', { name: 'templates.editor_code' })).toBeInTheDocument();
    expect(screen.queryByTestId('visual-editor')).toBeNull();
    expect(rerender).toBeDefined();
  });

  it('hands the registered variables to the editor so they open as chips', async () => {
    setup(representable());
    await chooseVisual();
    expect(await screen.findByTestId('visual-variables')).toHaveTextContent('@UserName');
  });

  it('inserts a variable from the list into the visual editor and says what happened', async () => {
    mockInsert.mockReturnValue('inserted');
    setup(representable());
    await chooseVisual();
    await screen.findByTestId('visual-editor');
    const button = screen.getByRole('button', { name: 'templates.insert' });
    expect(button).toBeEnabled();
    fireEvent.click(button);
    expect(mockInsert).toHaveBeenCalledWith('@UserName');
    expect(screen.getByTestId('variable-status')).toHaveTextContent('templates.visual_inserted');
  });

  it('tells the author to select a text block when there is nothing to insert into', async () => {
    mockInsert.mockReturnValue('no-target');
    setup(representable());
    await chooseVisual();
    await screen.findByTestId('visual-editor');
    fireEvent.click(screen.getByRole('button', { name: 'templates.insert' }));
    expect(screen.getByTestId('variable-status')).toHaveTextContent('templates.visual_select_text');
  });

  it('lists required variables the content does not use yet, in both modes, and updates live', async () => {
    const { rerender } = setup(representable(), { requiredVariables: ['@UserName', '@Link'] });
    expect(screen.getByTestId('required-missing')).toHaveTextContent('@UserName, @Link');
    await chooseVisual();
    await screen.findByTestId('visual-editor');
    expect(screen.getByTestId('required-missing')).toHaveTextContent('@UserName, @Link');
    rerender(
      <EmailTemplateEditor
        value={'<mjml><mj-body><mj-text>@UserName ${Link}</mj-text></mj-body></mjml>'}
        onChange={jest.fn()}
        variables={['@UserName']}
        requiredVariables={['@UserName', '@Link']}
        visualEnabled
        analyse={(async () => representable()) as any}
      />,
    );
    expect(screen.getByTestId('required-missing')).toBeEmptyDOMElement();
  });

  it('lists the shared parts of the template with a link to Settings, in Visual only', async () => {
    const value = `${MJML.replace('</mj-body>', '@GlobalSettingsMailFooter</mj-body>')}`;
    setup(representable(value), { value });
    expect(screen.queryByTestId('shared-parts')).toBeNull();
    await chooseVisual();
    await screen.findByTestId('visual-editor');
    const list = screen.getByTestId('shared-parts');
    expect(list).toHaveTextContent('@GlobalSettingsMailFooter');
    expect(within(list).getByRole('link')).toHaveAttribute('href', '/configuration/settings');
  });

  it('opens the image dialog on request of the editor and returns the chosen image', async () => {
    setup(representable());
    await chooseVisual();
    fireEvent.click(await screen.findByText('pick-image'));
    fireEvent.click(await screen.findByText('picker-open'));
    await waitFor(() => expect(mockPicked).toHaveBeenCalledWith({ url: 'u.png', alt: 'A' }));
    expect(screen.queryByText('picker-open')).toBeNull();
  });

  it('keeps a template the editor cannot represent in Code view, unchanged, with a banner', async () => {
    const { onChange } = setup(lossy(), { value: TABLE });
    await chooseVisual();
    expect(await screen.findByText('templates.visual_blocked')).toBeInTheDocument();
    expect(screen.getByTestId('visual-lost')).toHaveTextContent('mj-table > <tr>');
    expect(screen.getByTestId('visual-added')).toHaveTextContent('#text a');
    expect(screen.queryByTestId('visual-editor')).toBeNull();
    expect(screen.getByRole('textbox', { name: 'templates.editor_code' })).toBeInTheDocument();
    expect(screen.getByRole('radio', { name: /templates.editor_mode_code/ })).toBeChecked();
    expect(onChange).not.toHaveBeenCalled();
  });

  it('converts only after a diff and a confirmation', async () => {
    const analysis = lossy();
    const { onChange } = setup(analysis, { value: TABLE });
    await chooseVisual();
    fireEvent.click(await screen.findByRole('button', { name: 'templates.visual_convert' }));
    const diff = await screen.findByTestId('mjml-diff');
    expect(diff).toHaveTextContent('- <tr>');
    expect(diff).toHaveTextContent('+ a');
    // Nothing happened yet.
    expect(onChange).not.toHaveBeenCalled();
    expect(screen.queryByTestId('visual-editor')).toBeNull();

    fireEvent.click(screen.getByRole('button', { name: 'templates.visual_diff_confirm' }));
    expect(onChange).toHaveBeenCalledWith((analysis as { exported: string }).exported);
    expect(await screen.findByTestId('visual-editor')).toBeInTheDocument();
  });

  it('leaves the template byte for byte as it was when the conversion is cancelled', async () => {
    const { onChange } = setup(lossy(), { value: TABLE });
    await chooseVisual();
    fireEvent.click(await screen.findByRole('button', { name: 'templates.visual_convert' }));
    await screen.findByTestId('mjml-diff');
    fireEvent.click(screen.getByRole('button', { name: 'templates.visual_diff_cancel' }));
    await waitFor(() => expect(screen.queryByTestId('mjml-diff')).toBeNull());
    expect(onChange).not.toHaveBeenCalled();
    expect(screen.queryByTestId('visual-editor')).toBeNull();
    expect(screen.getByRole('textbox', { name: 'templates.editor_code' })).toBeInTheDocument();
  });

  it('keeps plain HTML templates in Code view', async () => {
    setup({ kind: 'html' }, { value: '<p>plain</p>' });
    await chooseVisual();
    expect(await screen.findByText('templates.visual_blocked_html')).toBeInTheDocument();
    expect(screen.queryByTestId('visual-editor')).toBeNull();
  });

  it('stays in Code view when the editor fails to load', async () => {
    setup(new Error('Loading chunk failed'));
    await chooseVisual();
    const alert = await screen.findByText('templates.visual_load_failed');
    expect(
      within(alert.closest('[role="status"]') as HTMLElement).getByText('Loading chunk failed'),
    ).toBeInTheDocument();
    expect(screen.getByRole('textbox', { name: 'templates.editor_code' })).toBeInTheDocument();
  });
});

describe('the editor is not part of the main bundle', () => {
  const files = (dir: string): string[] =>
    readdirSync(dir, { withFileTypes: true }).flatMap((entry) =>
      entry.isDirectory() ? files(join(dir, entry.name)) : [join(dir, entry.name)],
    );

  it('imports GrapesJS only in the lazily loaded engine and its locales', () => {
    const sources = files(__dirname).filter(
      (file) => /\.(ts|tsx)$/.test(file) && !/\.test\.tsx?$/.test(file),
    );
    const importers = sources
      .filter((file) =>
        /from '(grapesjs|grapesjs-mjml)(\/[^']*)?'|import\('grapesjs/.test(
          readFileSync(file, 'utf8'),
        ),
      )
      .map((file) => file.slice(__dirname.length + 1))
      .sort();
    // KeyboardPanel and visual/keyboard only import GrapesJS types and sit in the same lazy chunk
    expect(importers).toEqual([
      'KeyboardPanel.tsx',
      'VisualEditor.tsx',
      'visual/engine.ts',
      'visual/keyboard.ts',
      'visual/locales.ts',
      'visual/ulams.ts',
    ]);
  });

  it('loads the editor and the engine with import() only', () => {
    const index = readFileSync(join(__dirname, 'index.tsx'), 'utf8');
    expect(index).not.toMatch(/^import .*['"]\.\/(VisualEditor|visual\/engine)['"]/m);
    expect(index).toMatch(/import\('\.\/VisualEditor'\)/);
    expect(index).toMatch(/import\('\.\/visual\/engine'\)/);
  });
});
