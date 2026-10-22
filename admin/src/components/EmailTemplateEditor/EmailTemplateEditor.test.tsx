/**
 * @jest-environment jsdom
 */
import { afterEach, beforeAll, beforeEach, describe, expect, it, jest } from '@jest/globals';
import '@testing-library/jest-dom';
import { act, fireEvent, render, screen } from '@testing-library/react';
import React from 'react';
import type { EmailDraft } from './draft';

const messages: Record<string, string> = {
  'templates.insert': 'Insert {token}',
  'templates.insert_required': 'Insert {token} (required)',
  'templates.test_sent': 'Test sent to {recipient}',
};

jest.mock('umi', () => ({
  FormattedMessage: ({ id }: { id: string }) => id,
  useIntl: () => ({
    formatMessage: (d: { id: string }, values?: Record<string, string>) =>
      Object.entries(values ?? {}).reduce(
        (text, [k, v]) => text.replace(`{${k}}`, v),
        messages[d.id] ?? d.id,
      ),
  }),
  Link: ({ children }: { children: unknown }) => children,
  request: jest.fn(),
}));
jest.mock('@/services/ulams/templates', () => ({
  renderEmailDraft: jest.fn(),
  testSendEmailDraft: jest.fn(),
  uploadEmailImage: jest.fn(),
}));
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

import EmailTemplateEditor from './index';

const EVENT = 'App\\Events\\Test';

const draft = (patch: Partial<EmailDraft> = {}): EmailDraft => ({
  title: 'Hi Sample',
  html: '<html><body><p>Hello Sample</p></body></html>',
  errors: [],
  missing_variables: [],
  warnings: [],
  ...patch,
});

const flush = async () => {
  await act(async () => {
    jest.advanceTimersByTime(900);
  });
};

const setup = (props: Partial<React.ComponentProps<typeof EmailTemplateEditor>> = {}) => {
  const renderFn = jest.fn(async () => ({ data: draft() }));
  const testSend = jest.fn(async () => ({
    data: draft({ sent: true, recipient: 'me@example.test' }),
  }));
  const onChange = jest.fn();
  const utils = render(
    <EmailTemplateEditor
      value="<mjml><mj-body></mj-body></mjml>"
      onChange={onChange}
      event={EVENT}
      title="Subject"
      variables={['@UserName', '@Link']}
      requiredVariables={['@Link']}
      render={renderFn as any}
      testSend={testSend as any}
      {...props}
    />,
  );
  return { renderFn, testSend, onChange, ...utils };
};

describe('EmailTemplateEditor', () => {
  beforeAll(() => {
    // jsdom has no matchMedia, antd's grid needs it.
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
  beforeEach(() => {
    jest.useFakeTimers();
  });
  afterEach(() => {
    jest.useRealTimers();
  });

  it('renders the unsaved draft into a sandboxed iframe without scripts', async () => {
    const { renderFn } = setup();
    expect(renderFn).not.toHaveBeenCalled();
    await flush();
    expect(renderFn).toHaveBeenCalledWith({
      event: EVENT,
      title: 'Subject',
      content: '<mjml><mj-body></mj-body></mjml>',
    });
    const frame = await screen.findByTitle('templates.preview_frame');
    expect(frame.getAttribute('sandbox')).toBe('');
    expect(frame.getAttribute('srcdoc')).toContain('Hello Sample');
  });

  it('debounces typing into one request', async () => {
    const { renderFn, rerender, onChange } = setup();
    await flush();
    renderFn.mockClear();
    for (const content of ['<mjml>a', '<mjml>ab', '<mjml>abc']) {
      rerender(
        <EmailTemplateEditor
          value={content}
          onChange={onChange}
          event={EVENT}
          title="Subject"
          variables={[]}
          requiredVariables={[]}
          render={renderFn as any}
        />,
      );
      await act(async () => {
        jest.advanceTimersByTime(200);
      });
    }
    expect(renderFn).not.toHaveBeenCalled();
    await flush();
    expect(renderFn).toHaveBeenCalledTimes(1);
  });

  it('lists MJML errors, missing variables and accessibility warnings', async () => {
    setup({
      render: (async () => ({
        data: draft({
          errors: ['Line 3: unknown tag'],
          missing_variables: ['@Link'],
          warnings: [{ code: 'img-alt', message: 'Images need alt text', count: 2 }],
        }),
      })) as any,
    });
    await flush();
    expect(await screen.findByText('Line 3: unknown tag')).toBeInTheDocument();
    expect(screen.getByTestId('missing-variables')).toHaveTextContent('@Link');
    expect(screen.getByTestId('a11y-warnings')).toHaveTextContent('Images need alt text (2)');
  });

  it('shows a request failure and keeps the last good preview', async () => {
    const renderFn = jest
      .fn<any>()
      .mockResolvedValueOnce({ data: draft() })
      .mockRejectedValueOnce({ response: { data: { message: 'Renderer down' } } });
    const { rerender, onChange } = setup({ render: renderFn });
    await flush();
    rerender(
      <EmailTemplateEditor
        value="<mjml>changed"
        onChange={onChange}
        event={EVENT}
        variables={[]}
        requiredVariables={[]}
        render={renderFn}
      />,
    );
    await flush();
    expect(await screen.findByText('Renderer down')).toBeInTheDocument();
    expect(screen.getByTitle('templates.preview_frame')).toBeInTheDocument();
  });

  it('inserts a variable at the cursor', async () => {
    const { onChange } = setup({ value: 'Hello !' });
    const area = screen.getByRole('textbox', {
      name: 'templates.editor_code',
    }) as HTMLTextAreaElement;
    area.setSelectionRange(6, 6);
    fireEvent.click(screen.getByRole('button', { name: 'Insert @UserName' }));
    expect(onChange).toHaveBeenCalledWith('Hello @UserName!');
  });

  it('names required variables in text, not only by colour', () => {
    setup();
    expect(screen.getByRole('button', { name: 'Insert @Link (required)' })).toBeInTheDocument();
  });

  it('sends a test e-mail with the unsaved content and reports the recipient', async () => {
    const { testSend } = setup();
    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: /templates.send_test$/ }));
    });
    expect(testSend).toHaveBeenCalledWith({
      event: EVENT,
      title: 'Subject',
      content: '<mjml><mj-body></mj-body></mjml>',
    });
    expect(await screen.findByText('Test sent to me@example.test')).toBeInTheDocument();
  });

  it('does not render or enable test send without content', async () => {
    const { renderFn } = setup({ value: '' });
    await flush();
    expect(renderFn).not.toHaveBeenCalled();
    expect(screen.getByRole('button', { name: /templates.send_test$/ })).toBeDisabled();
    expect(screen.queryByTitle('templates.preview_frame')).toBeNull();
  });

  it('renders and test-sends in the language the template is written for', async () => {
    const { renderFn, testSend } = setup({ templateLocale: 'pl' });
    await flush();
    expect(renderFn).toHaveBeenCalledWith({
      event: EVENT,
      title: 'Subject',
      content: '<mjml><mj-body></mj-body></mjml>',
      locale: 'pl',
    });
    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: /templates.send_test$/ }));
    });
    expect(testSend).toHaveBeenCalledWith({
      event: EVENT,
      title: 'Subject',
      content: '<mjml><mj-body></mj-body></mjml>',
      locale: 'pl',
    });
  });

  it('sends no language for a template without one', async () => {
    const { renderFn } = setup();
    await flush();
    expect(renderFn).toHaveBeenCalledTimes(1);
    expect(renderFn).not.toHaveBeenCalledWith(
      expect.objectContaining({ locale: expect.anything() }),
    );
  });
});
