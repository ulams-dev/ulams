import AutoCompleteArea from '@/components/AutoCompleteArea';
import { renderEmailDraft, testSendEmailDraft } from '@/services/ulams/templates';
import { Alert, Button, Col, Row, Spin } from 'antd';
import React, { useCallback, useRef, useState } from 'react';
import { FormattedMessage, useIntl } from 'umi';
import { insertAtSelection, requestErrorMessage, type DraftRequest } from './draft';
import './index.css';
import { useDraftPreview } from './useDraftPreview';

export type EmailTemplateEditorProps = {
  value?: string;
  onChange: (value: string) => void;
  /** Event class of the template, the variables and sample data depend on it. */
  event?: string;
  /** Subject, rendered with the sample values together with the content. */
  title?: string;
  variables: string[];
  requiredVariables: string[];
  error?: string[];
  /** Replaceable for tests. */
  render?: DraftRequest;
  testSend?: DraftRequest;
};

const EmailTemplateEditor: React.FC<EmailTemplateEditorProps> = ({
  value = '',
  onChange,
  event = '',
  title = '',
  variables,
  requiredVariables,
  error = [],
  render = renderEmailDraft as DraftRequest,
  testSend = testSendEmailDraft as DraftRequest,
}) => {
  const intl = useIntl();
  const textarea = useRef<HTMLTextAreaElement | null>(null);
  const [sending, setSending] = useState(false);
  const [sendResult, setSendResult] = useState<
    { type: 'success' | 'error'; message: string } | undefined
  >();

  const preview = useDraftPreview(
    { event, title, content: value },
    render,
    intl.formatMessage({ id: 'templates.preview_failed' }),
  );
  const draft = preview.status === 'idle' ? undefined : preview.draft;
  const loading = preview.status === 'loading';

  const insertVariable = useCallback(
    (token: string) => {
      const area = textarea.current;
      const { value: next, caret } = insertAtSelection(
        value,
        area?.selectionStart,
        area?.selectionEnd,
        token,
      );
      onChange(next);
      // Put the caret after the token once React has rendered the new value.
      setTimeout(() => {
        if (area) {
          area.focus();
          area.setSelectionRange(caret, caret);
        }
      }, 0);
    },
    [value, onChange],
  );

  const onTestSend = useCallback(() => {
    setSending(true);
    setSendResult(undefined);
    testSend({ event, title, content: value })
      .then((response) =>
        setSendResult({
          type: 'success',
          message: intl.formatMessage(
            { id: 'templates.test_sent' },
            { recipient: response.data.recipient ?? '' },
          ),
        }),
      )
      .catch((e) =>
        setSendResult({
          type: 'error',
          message: requestErrorMessage(e, intl.formatMessage({ id: 'templates.test_failed' })),
        }),
      )
      .finally(() => setSending(false));
  }, [event, title, value, testSend, intl]);

  const canRender = !!event && !!value;

  return (
    <div className="email-template-editor">
      <Row gutter={[16, 16]}>
        <Col xs={24} xl={12}>
          <section aria-labelledby="email-editor-code">
            <h3 id="email-editor-code">
              <FormattedMessage id="templates.editor_code" />
            </h3>
            <AutoCompleteArea
              requiredVariables={requiredVariables}
              variables={variables}
              value={value}
              onChange={onChange}
              error={error}
              textareaRef={(el) => {
                textarea.current = el;
              }}
              aria-label={intl.formatMessage({ id: 'templates.editor_code' })}
              styles={{ width: '100%', height: '50vh', fontFamily: 'monospace' }}
            />
            <p className="email-template-editor__hint">
              <FormattedMessage id="code.snippet" />
            </p>
          </section>
          <section aria-labelledby="email-editor-variables">
            <h3 id="email-editor-variables">
              <FormattedMessage id="templates.editor_variables" />
            </h3>
            <p className="email-template-editor__hint">
              <FormattedMessage id="templates.editor_variables_hint" />
            </p>
            <ul className="email-template-editor__variables">
              {variables.map((token) => {
                const required = requiredVariables.includes(token);
                return (
                  <li key={token}>
                    <Button
                      size="small"
                      onClick={() => insertVariable(token)}
                      aria-label={intl.formatMessage(
                        { id: required ? 'templates.insert_required' : 'templates.insert' },
                        { token },
                      )}
                    >
                      {token}
                      {required && (
                        <span className="email-template-editor__required" aria-hidden="true">
                          {' '}
                          *
                        </span>
                      )}
                    </Button>
                  </li>
                );
              })}
            </ul>
          </section>
        </Col>
        <Col xs={24} xl={12}>
          <section aria-labelledby="email-editor-preview">
            <h3 id="email-editor-preview">
              <FormattedMessage id="templates.editor_preview" />
            </h3>
            <div className="email-template-editor__actions">
              <Button onClick={onTestSend} loading={sending} disabled={!canRender || sending}>
                <FormattedMessage id="templates.send_test" />
              </Button>
              <span className="email-template-editor__hint">
                <FormattedMessage id="templates.send_test_hint" />
              </span>
            </div>
            <div aria-live="polite" className="email-template-editor__status">
              {sendResult && (
                <Alert
                  type={sendResult.type}
                  showIcon
                  message={sendResult.message}
                  role={sendResult.type === 'error' ? 'alert' : 'status'}
                />
              )}
              {loading && (
                <span>
                  <Spin size="small" /> <FormattedMessage id="templates.preview_updating" />
                </span>
              )}
              {preview.status === 'error' && (
                <Alert type="error" showIcon message={preview.message} />
              )}
              {!canRender && (
                <span>
                  <FormattedMessage id="templates.preview_empty" />
                </span>
              )}
              {draft && draft.errors.length > 0 && (
                <Alert
                  type="error"
                  showIcon
                  message={<FormattedMessage id="templates.mjml_errors" />}
                  description={
                    <ul data-testid="mjml-errors">
                      {draft.errors.map((e, i) => (
                        // eslint-disable-next-line react/no-array-index-key
                        <li key={`${i}-${e}`}>{e}</li>
                      ))}
                    </ul>
                  }
                />
              )}
              {draft && draft.missing_variables.length > 0 && (
                <Alert
                  type="warning"
                  showIcon
                  message={<FormattedMessage id="templates.missing_variables" />}
                  description={
                    <ul data-testid="missing-variables">
                      {draft.missing_variables.map((v) => (
                        <li key={v}>{v}</li>
                      ))}
                    </ul>
                  }
                />
              )}
              {draft && draft.warnings.length > 0 && (
                <Alert
                  type="warning"
                  showIcon
                  message={<FormattedMessage id="templates.a11y_warnings" />}
                  description={
                    <ul data-testid="a11y-warnings">
                      {draft.warnings.map((w) => (
                        <li key={w.code}>
                          {w.message}
                          {w.count && w.count > 1 ? ` (${w.count})` : ''}
                        </li>
                      ))}
                    </ul>
                  }
                />
              )}
            </div>
            {draft && canRender && (
              <iframe
                className="email-template-editor__frame"
                title={intl.formatMessage({ id: 'templates.preview_frame' })}
                sandbox=""
                srcDoc={draft.html}
              />
            )}
          </section>
        </Col>
      </Row>
    </div>
  );
};

export default EmailTemplateEditor;
