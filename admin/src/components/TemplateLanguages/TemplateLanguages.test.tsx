/**
 * @jest-environment jsdom
 */
import { beforeAll, describe, expect, it, jest } from '@jest/globals';
import '@testing-library/jest-dom';
import { fireEvent, render, screen, within } from '@testing-library/react';

const messages: Record<string, string> = {
  'templates.lang_present': 'Has a template',
  'templates.lang_missing': 'Missing translation',
  'templates.lang_edit_in': 'Edit the {language} template',
  'templates.lang_create_in': 'Create the {language} translation',
  'templates.lang_missing_summary': 'Missing translations: {languages}.',
  'templates.lang_none': 'No language',
};

jest.mock('umi', () => {
  const R = require('react');
  return {
    FormattedMessage: ({ id }: { id: string }) => id,
    useIntl: () => ({
      formatMessage: (d: { id: string }, values?: Record<string, string>) =>
        Object.entries(values ?? {}).reduce(
          (text, [k, v]) => text.replace(`{${k}}`, v),
          messages[d.id] ?? d.id,
        ),
    }),
    Link: ({ to, children, ...rest }: any) => R.createElement('a', { href: to, ...rest }, children),
  };
});

import TemplateLanguages, { TranslationTags } from './index';

const EVENT = 'App\\Events\\PasswordForgotten';
const make = (id: number, locale: string | null, extra: Record<string, unknown> = {}) =>
  ({ id, name: `Template ${id}`, event: EVENT, default: true, locale, ...extra } as API.Template);

const BASE = '/configuration/templates/email';

describe('TemplateLanguages', () => {
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

  it('marks a missing translation in words and links to creating it as a copy', () => {
    render(
      <TemplateLanguages
        templates={[make(1, 'en'), make(3, 'zh')]}
        event={EVENT}
        currentId={1}
        basePath={BASE}
      />,
    );
    const pl = screen.getByText('Polski').closest('li') as HTMLElement;
    expect(within(pl).getByText('Missing translation')).toBeInTheDocument();
    const create = within(pl).getByRole('link', { name: 'Create the Polski translation' });
    const href = create.getAttribute('href') as string;
    expect(href.startsWith(`${BASE}/new?`)).toBe(true);
    const query = new URLSearchParams(href.split('?')[1]);
    expect(query.get('event')).toBe(EVENT);
    expect(query.get('locale')).toBe('pl');
    expect(query.get('copy')).toBe('1');
    expect(screen.getByRole('status')).toHaveTextContent('Missing translations: Polski.');
  });

  it('links to the template of each other language and marks the current one', () => {
    render(
      <TemplateLanguages
        templates={[make(1, 'en'), make(3, 'zh')]}
        event={EVENT}
        currentId={1}
        basePath={BASE}
      />,
    );
    expect(screen.getByRole('link', { name: 'Edit the 中文 template' })).toHaveAttribute(
      'href',
      `${BASE}/3`,
    );
    const en = screen.getByText('English').closest('li') as HTMLElement;
    expect(within(en).getByText('templates.lang_current')).toHaveAttribute('aria-current', 'true');
    expect(within(en).queryByRole('link')).toBeNull();
  });

  it('copies from the template without a language when there is one', () => {
    render(
      <TemplateLanguages
        templates={[make(1, 'en'), make(2, null)]}
        event={EVENT}
        currentId={1}
        basePath={BASE}
      />,
    );
    const href = screen
      .getByRole('link', { name: 'Create the Polski translation' })
      .getAttribute('href') as string;
    expect(new URLSearchParams(href.split('?')[1]).get('copy')).toBe('2');
    expect(screen.getByText('templates.lang_universal')).toBeInTheDocument();
  });

  it('reports a complete set without a warning', () => {
    render(
      <TemplateLanguages
        templates={[make(1, 'en'), make(2, 'pl'), make(3, 'zh')]}
        event={EVENT}
        currentId={1}
        basePath={BASE}
      />,
    );
    expect(screen.getByText('templates.lang_complete')).toBeInTheDocument();
    expect(screen.queryByRole('status')).toBeNull();
  });

  it('offers to create a template while a new one is being written (no current id)', () => {
    render(<TemplateLanguages templates={[make(1, 'en')]} event={EVENT} basePath={BASE} />);
    expect(screen.getByRole('link', { name: 'Create the 中文 translation' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Edit the English template' })).toBeInTheDocument();
  });

  it('renders nothing before an event is chosen', () => {
    const { container } = render(<TemplateLanguages templates={[]} basePath={BASE} />);
    expect(container).toBeEmptyDOMElement();
  });

  it('copies the chosen template after a confirmation', async () => {
    const onCopy = jest.fn();
    render(
      <TemplateLanguages
        templates={[make(1, 'en'), make(3, 'zh')]}
        event={EVENT}
        currentId={1}
        basePath={BASE}
        onCopy={onCopy}
      />,
    );
    const copy = screen.getByRole('button', { name: 'templates.lang_copy_button' });
    expect(copy).toBeDisabled();
    fireEvent.mouseDown(screen.getByRole('combobox'));
    fireEvent.click(await screen.findByText('中文 - Template 3'));
    fireEvent.click(screen.getByRole('button', { name: 'templates.lang_copy_button' }));
    fireEvent.click(await screen.findByRole('button', { name: 'yes' }));
    expect(onCopy).toHaveBeenCalledWith(expect.objectContaining({ id: 3 }));
  });
});

describe('TranslationTags', () => {
  it('says present and missing with words for assistive technology', () => {
    render(<TranslationTags templates={[make(1, 'en')]} event={EVENT} />);
    expect(screen.getByText('English: Has a template')).toBeInTheDocument();
    expect(screen.getByText('Polski: Missing translation')).toBeInTheDocument();
    expect(screen.getByText('中文: Missing translation')).toBeInTheDocument();
  });
});
