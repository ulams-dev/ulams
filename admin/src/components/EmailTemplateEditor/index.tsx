import AutoCompleteArea from '@/components/AutoCompleteArea';
import { renderEmailDraft, testSendEmailDraft } from '@/services/ulams/templates';
import { Alert, Button, Col, Radio, Row, Spin, Tag } from 'antd';
import React, { Suspense, useCallback, useRef, useState } from 'react';
import { FormattedMessage, useIntl } from 'umi';
import { insertAtSelection, requestErrorMessage, type DraftRequest } from './draft';
import './index.css';
import { analyseMjml, type Analysis } from './roundTrip';
import { useDraftPreview } from './useDraftPreview';
import { BlockedBanner, ConvertDialog, type VisualBlock } from './VisualNotice';

// The editor (GrapesJS and the MJML plugin) is a separate chunk, fetched only when the author asks
// for Visual mode. Nothing of it is imported statically.
const VisualEditor = React.lazy(() => import('./VisualEditor'));

type Mode = 'code' | 'visual';

/** Loads the editor chunk and runs the representability check of plan 4.6. */
export const defaultAnalyse = async (content: string, locale?: string): Promise<Analysis> => {
  const engine = await import('./visual/engine');
  return analyseMjml(content, (source) => engine.exportOnce(source, locale));
};

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
  /** Operator switch `templates.email.visual_editor`, on by default (plan Q7). */
  visualEnabled?: boolean;
  /** Editor language, for example `pl-PL`. */
  locale?: string;
  /** Replaceable for tests. */
  analyse?: (content: string, locale?: string) => Promise<Analysis>;
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
  visualEnabled = true,
  locale,
  analyse = defaultAnalyse,
}) => {
  const intl = useIntl();
  const textarea = useRef<HTMLTextAreaElement | null>(null);
  const [sending, setSending] = useState(false);
  const [sendResult, setSendResult] = useState<
    { type: 'success' | 'error'; message: string } | undefined
  >();

  // Code is the default view for everybody: the visual mode is beta and not yet keyboard operable.
  const [requestedMode, setRequestedMode] = useState<Mode>('code');
  const [checking, setChecking] = useState(false);
  const [block, setBlock] = useState<VisualBlock | undefined>();
  const [reviewing, setReviewing] = useState(false);
  const [visualSource, setVisualSource] = useState('');
  const mode: Mode = visualEnabled ? requestedMode : 'code';

  const openVisual = useCallback(
    async (content: string) => {
      setBlock(undefined);
      setChecking(true);
      try {
        const analysis = await analyse(content, locale);
        if (analysis.kind === 'representable') {
          setVisualSource(analysis.source);
          setRequestedMode('visual');
        } else if (analysis.kind === 'html') {
          setBlock({ kind: 'html' });
        } else {
          setBlock({
            kind: 'lossy',
            lost: analysis.lost,
            added: analysis.added,
            diff: analysis.diff,
            exported: analysis.exported,
          });
        }
      } catch (e) {
        setBlock({
          kind: 'error',
          message: requestErrorMessage(
            e,
            intl.formatMessage({ id: 'templates.visual_load_failed' }),
          ),
        });
      } finally {
        setChecking(false);
      }
    },
    [analyse, locale, intl],
  );

  const onModeChange = (next: Mode) => {
    if (next === 'code') {
      setRequestedMode('code');
    } else {
      openVisual(value);
    }
  };

  // The only way to open a template the editor cannot represent: after the diff, on confirmation.
  const confirmConversion = () => {
    if (block?.kind !== 'lossy') return;
    onChange(block.exported);
    setVisualSource(block.exported);
    setBlock(undefined);
    setReviewing(false);
    setRequestedMode('visual');
  };

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
      {visualEnabled && (
        <div className="email-template-editor__modes">
          <Radio.Group
            optionType="button"
            buttonStyle="solid"
            value={mode}
            disabled={checking}
            onChange={(e) => onModeChange(e.target.value as Mode)}
            aria-label={intl.formatMessage({ id: 'templates.editor_mode' })}
          >
            <Radio.Button value="code">
              <FormattedMessage id="templates.editor_mode_code" />
            </Radio.Button>
            <Radio.Button value="visual" aria-describedby="email-editor-beta">
              <FormattedMessage id="templates.editor_mode_visual" />{' '}
              <Tag color="orange" className="email-template-editor__beta">
                <FormattedMessage id="templates.editor_beta" />
              </Tag>
            </Radio.Button>
          </Radio.Group>
          <span id="email-editor-beta" className="email-template-editor__hint">
            <FormattedMessage id="templates.visual_beta_notice" />
          </span>
          <div aria-live="polite">
            {checking && (
              <span>
                <Spin size="small" /> <FormattedMessage id="templates.visual_checking" />
              </span>
            )}
          </div>
          {block && <BlockedBanner block={block} onConvert={() => setReviewing(true)} />}
          <ConvertDialog
            diff={reviewing && block?.kind === 'lossy' ? block.diff : undefined}
            title={intl.formatMessage({ id: 'templates.visual_diff_title' })}
            confirmText={intl.formatMessage({ id: 'templates.visual_diff_confirm' })}
            cancelText={intl.formatMessage({ id: 'templates.visual_diff_cancel' })}
            hint={<FormattedMessage id="templates.visual_diff_hint" />}
            onConfirm={confirmConversion}
            onCancel={() => setReviewing(false)}
          />
        </div>
      )}
      <Row gutter={[16, 16]}>
        <Col xs={24} xl={mode === 'visual' ? 24 : 12}>
          {mode === 'visual' ? (
            <section aria-labelledby="email-editor-visual">
              <h3 id="email-editor-visual">
                <FormattedMessage id="templates.editor_mode_visual" />
              </h3>
              <Suspense
                fallback={
                  <span>
                    <Spin size="small" /> <FormattedMessage id="templates.visual_loading" />
                  </span>
                }
              >
                <VisualEditor
                  initial={visualSource}
                  onChange={onChange}
                  locale={locale}
                  label={intl.formatMessage({ id: 'templates.visual_canvas' })}
                />
              </Suspense>
            </section>
          ) : (
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
          )}
          <section aria-labelledby="email-editor-variables">
            <h3 id="email-editor-variables">
              <FormattedMessage id="templates.editor_variables" />
            </h3>
            <p className="email-template-editor__hint">
              <FormattedMessage
                id={
                  mode === 'visual'
                    ? 'templates.visual_variables_hint'
                    : 'templates.editor_variables_hint'
                }
              />
            </p>
            <ul className="email-template-editor__variables">
              {variables.map((token) => {
                const required = requiredVariables.includes(token);
                return (
                  <li key={token}>
                    <Button
                      size="small"
                      // The list inserts at the caret of the code area; visual mode has none (M4).
                      disabled={mode === 'visual'}
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
        <Col xs={24} xl={mode === 'visual' ? 24 : 12}>
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
