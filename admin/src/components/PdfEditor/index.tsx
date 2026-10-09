import { pdfFonts, previewPdf } from '@/services/ulams/pdfs';
import type { Font, Template } from '@pdfme/common';
import type { Designer as PdfmeDesigner } from '@pdfme/ui';
import { Alert, Button, message, Modal, Space, Spin, Tag, Tooltip, Typography } from 'antd';
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { FormattedMessage, useIntl } from 'umi';
import './index.css';
import {
  addVariableField,
  BLANK_A4,
  isPdfmeTemplate,
  isReportBroTemplate,
  parseTemplate,
  usedVariables,
  type PdfmeTemplate,
} from './template';

declare const REACT_APP_API_URL: string;

const apiUrl = () => window.REACT_APP_API_URL || REACT_APP_API_URL;

/**
 * Fonts bundled with the PDF renderer, loaded from the API so the designer
 * measures text exactly like the rendered PDF.
 */
const loadFonts = async (): Promise<Font | undefined> => {
  try {
    const response = await pdfFonts();
    if (!response.success || !response.data?.length) {
      return undefined;
    }
    return Object.fromEntries(
      response.data.map((font) => [
        font.name,
        { data: `${apiUrl()}/api/pdfs/fonts/${font.file}`, fallback: font.fallback },
      ]),
    );
  } catch {
    return undefined;
  }
};

export type PdfEditorProps = {
  /** pdfme template as JSON (the template's `content` section). */
  value?: string;
  onChange?: (value: string) => void;
  /** Save the whole template form (designer Ctrl+S and the Save button). */
  onSave?: () => void;
  /** Variables of the template's event, e.g. "@VarUserName". */
  variables: string[];
  requiredVariables: string[];
  /** Content of a new template of this event (used to start over from a legacy template). */
  defaultContent?: string;
  /** Event class, needed to preview with sample data. */
  event?: string;
};

/**
 * pdfme designer (MIT) for PDF templates: drag fields on the page, add a field
 * per template variable, preview with sample data rendered by the API.
 */
export const PdfEditor: React.FC<PdfEditorProps> = ({
  value,
  onChange,
  onSave,
  variables,
  requiredVariables,
  defaultContent,
  event,
}) => {
  const intl = useIntl();
  const container = useRef<HTMLDivElement>(null);
  const designer = useRef<PdfmeDesigner>();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string>();
  const [current, setCurrent] = useState<PdfmeTemplate>();
  const [previewUrl, setPreviewUrl] = useState<string>();
  const [previewing, setPreviewing] = useState(false);

  const parsed = useMemo(() => parseTemplate(value), [value]);
  const legacy = isReportBroTemplate(parsed);

  // latest callbacks, without re-creating the designer
  const onChangeRef = useRef(onChange);
  const onSaveRef = useRef(onSave);
  onChangeRef.current = onChange;
  onSaveRef.current = onSave;
  const lastEmitted = useRef<string>();
  const emit = useCallback((json: string) => {
    lastEmitted.current = json;
    onChangeRef.current?.(json);
  }, []);

  const initialTemplate = (): PdfmeTemplate => {
    if (isPdfmeTemplate(parsed)) {
      return parsed;
    }
    const fallback = parseTemplate(defaultContent);
    return isPdfmeTemplate(fallback) ? fallback : BLANK_A4;
  };

  useEffect(() => {
    if (legacy || !container.current) {
      return undefined;
    }
    let cancelled = false;
    setLoading(true);

    (async () => {
      try {
        // the designer bundle is large: load it only on this page
        const [{ Designer }, { plugins }, font] = await Promise.all([
          import('@pdfme/ui'),
          import('./plugins'),
          loadFonts(),
        ]);
        if (cancelled || !container.current) {
          return;
        }
        const template = initialTemplate();
        const instance = new Designer({
          domContainer: container.current,
          template: template as unknown as Template,
          plugins,
          options: {
            ...(font ? { font } : {}),
            lang: intl.locale?.startsWith('pl') ? 'pl' : 'en',
          },
        });
        instance.onChangeTemplate((updated) => {
          setCurrent(updated as unknown as PdfmeTemplate);
          emit(JSON.stringify(updated));
        });
        instance.onSaveTemplate((updated) => {
          emit(JSON.stringify(updated));
          onSaveRef.current?.();
        });
        designer.current = instance;
        setCurrent(template);
        // store the template the designer starts from (e.g. the default for a new template)
        if (!isPdfmeTemplate(parsed)) {
          emit(JSON.stringify(template));
        }
        setError(undefined);
      } catch (e) {
        setError(e instanceof Error ? e.message : String(e));
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    })();

    return () => {
      cancelled = true;
      designer.current?.destroy();
      designer.current = undefined;
    };
    // the designer owns the template once created; it is re-created only when leaving legacy mode
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [legacy]);

  // a template loaded into the form after the designer was created (not one it emitted itself)
  useEffect(() => {
    if (designer.current && value && value !== lastEmitted.current && isPdfmeTemplate(parsed)) {
      lastEmitted.current = value;
      designer.current.updateTemplate(parsed as unknown as Template);
      setCurrent(parsed);
    }
  }, [value, parsed]);

  useEffect(
    () => () => {
      if (previewUrl) {
        URL.revokeObjectURL(previewUrl);
      }
    },
    [previewUrl],
  );

  const used = useMemo(
    () => (current ? usedVariables(current, variables) : []),
    [current, variables],
  );
  const missingRequired = requiredVariables.filter((variable) => !used.includes(variable));

  const addVariable = useCallback((variable: string) => {
    const instance = designer.current;
    if (!instance) {
      return;
    }
    const template = instance.getTemplate() as unknown as PdfmeTemplate;
    const updated = addVariableField(template, variable, instance.getPageCursor());
    if (updated !== template) {
      instance.updateTemplate(updated as unknown as Template);
      setCurrent(updated);
      emit(JSON.stringify(updated));
    }
  }, []);

  const preview = useCallback(async () => {
    const instance = designer.current;
    if (!instance || !event) {
      return;
    }
    setPreviewing(true);
    try {
      const blob = await previewPdf({ event, content: instance.getTemplate() });
      setPreviewUrl(URL.createObjectURL(blob));
    } catch (e) {
      message.error(
        intl.formatMessage({ id: 'pdf_editor.preview_failed', defaultMessage: 'Preview failed' }),
      );
    } finally {
      setPreviewing(false);
    }
  }, [event]);

  if (legacy) {
    return (
      <Alert
        type="warning"
        showIcon
        message={
          <FormattedMessage
            id="pdf_editor.legacy"
            defaultMessage="This template was made with ReportBro, which is no longer supported."
          />
        }
        description={
          <Space direction="vertical">
            <FormattedMessage
              id="pdf_editor.legacy_description"
              defaultMessage="Start over from the default template of this event (the old layout is not kept), or ask an administrator to run templates-pdf:migrate-reportbro, which converts simple text fields."
            />
            <Button
              onClick={() =>
                onChange?.(
                  defaultContent && isPdfmeTemplate(parseTemplate(defaultContent))
                    ? defaultContent
                    : JSON.stringify(BLANK_A4),
                )
              }
            >
              <FormattedMessage
                id="pdf_editor.start_over"
                defaultMessage="Start from the default template"
              />
            </Button>
          </Space>
        }
      />
    );
  }

  return (
    <div className="pdf-editor">
      <Space direction="vertical" className="pdf-editor__variables">
        <Typography.Text type="secondary">
          <FormattedMessage
            id="pdf_editor.variables_help"
            defaultMessage="Click a variable to add a field for it; it is filled in when the PDF is issued. Variables can also be typed into read-only text, e.g. “Issued by @VarAppName”."
          />
        </Typography.Text>
        <div>
          {variables.map((variable) => (
            <Tooltip
              key={variable}
              title={
                used.includes(variable)
                  ? intl.formatMessage({
                      id: 'pdf_editor.variable_used',
                      defaultMessage: 'Used in the template',
                    })
                  : intl.formatMessage({
                      id: 'pdf_editor.variable_add',
                      defaultMessage: 'Add a field',
                    })
              }
            >
              <Tag
                color={
                  used.includes(variable)
                    ? 'green'
                    : requiredVariables.includes(variable)
                    ? 'red'
                    : 'orange'
                }
                onClick={() => addVariable(variable)}
              >
                {variable}
              </Tag>
            </Tooltip>
          ))}
        </div>
        {missingRequired.length > 0 && (
          <Alert
            type="error"
            showIcon
            message={
              <span>
                <FormattedMessage id="templates.required_variables" />: {missingRequired.join(', ')}
              </span>
            }
          />
        )}
        <Space>
          <Button onClick={preview} loading={previewing} disabled={!event || loading || !!error}>
            <FormattedMessage id="preview" />
          </Button>
          <Button
            type="primary"
            onClick={() => designer.current?.saveTemplate()}
            disabled={loading || !!error}
          >
            <FormattedMessage id="save" defaultMessage="Save" />
          </Button>
        </Space>
      </Space>
      {error && <Alert type="error" showIcon message={error} />}
      <Spin spinning={loading}>
        <div ref={container} className="pdf-editor__designer" />
      </Spin>
      <Modal
        open={!!previewUrl}
        onCancel={() => setPreviewUrl(undefined)}
        footer={null}
        width="80vw"
        title={<FormattedMessage id="preview" />}
        destroyOnClose
      >
        {previewUrl && (
          <iframe
            title="PDF preview"
            src={previewUrl}
            style={{ width: '100%', height: '75vh', border: 0 }}
          />
        )}
      </Modal>
    </div>
  );
};

export default PdfEditor;
