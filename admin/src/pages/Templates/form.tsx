import PdfList from '@/components/Pdf/list';
import TemplateFields from '@/components/TemplateFields';
import TemplateLanguages from '@/components/TemplateLanguages';
import {
  NO_LANGUAGE,
  TEMPLATE_LANGUAGES,
  languageName,
  translationDraft,
} from '@/components/TemplateLanguages/languages';
import TemplateManuallyTrigger from '@/components/TemplateManuallyTrigger';
import TemplateManuallyTriggerProduct from '@/components/TemplateManuallyTrigger/product';
import { TemplateChannelValue, TemplateEvents } from '@/services/ulams/enums';
import {
  createTemplate,
  template as fetchTemplate,
  templates as fetchTemplates,
  variables as fetchVariables,
  updateTemplate,
} from '@/services/ulams/templates';
import ProCard from '@ant-design/pro-card';
import ProForm, { ProFormCheckbox, ProFormSelect, ProFormText } from '@ant-design/pro-form';
import { PageContainer } from '@ant-design/pro-layout';
import { Collapse, Divider, Space, Spin, Tag, Typography, message } from 'antd';
import React, { useCallback, useEffect, useState } from 'react';
import { FormattedMessage, history, useIntl, useParams } from 'umi';
import PreviewButton from './components/PreviewButton';

const { Panel } = Collapse;

// creates sections collections for post template
const createEntries = (data: Record<string, string>): API.TemplateSections[] => {
  return Object.entries(data).map((entry) => {
    return {
      key: entry[0],
      content: entry[1],
    };
  });
};

const objectFlatten = (data: Record<string, string>[]): Record<string, string> =>
  Object.assign({}, ...data);

// helper function that throws away unnecessary keys to create a sections collection

const filterNotAllowedKeys = (values: Partial<API.Template>) => {
  const notAllowedKeys = ['name', 'event', 'default', 'locale'];
  return Object.keys(values)
    .filter((key) => !notAllowedKeys.includes(key))
    .reduce((obj, key) => {
      obj[key] = values[key as keyof typeof values];
      return obj;
    }, {} as Record<string, string>);
};

type Tokens = {
  assignableClass: string | null;
  class: string;
  required_variables: string[];
  variables: string[];
  sections: Record<string, API.TemplateField>;
};

const channels = TemplateChannelValue;

export default () => {
  const intl = useIntl();
  const params = useParams<{ template: 'email' | 'pdf' | 'sms'; id: string }>();

  const { template, id } = params;

  const isNew = id === 'new';

  const [saved, setSaved] = useState<boolean>(false);
  const [form] = ProForm.useForm();
  const [variables, setVariables] = useState<API.TemplateVariables>();
  const [tokens, setTokens] = useState<Tokens | undefined>(undefined);
  const [previewData, setPreviewData] = useState<any>();
  const [isManuallyTriggered, setIsManuallyTriggered] = useState<boolean>(false);
  const [currentEvent, setCurrentEvent] = useState<string | undefined>();
  const [siblings, setSiblings] = useState<API.Template[]>([]);
  const supportsLanguages = template === 'email';

  useEffect(() => {
    fetchVariables().then((response) => {
      setVariables(response.success ? response.data : {});
    });
  }, []);

  useEffect(() => {
    const values = form.getFieldsValue();

    const defaultValues =
      tokens &&
      Object.keys(tokens.sections).map((section) => {
        return {
          [section]: tokens.sections[section].default_content,
        };
      });

    if (defaultValues && isNew) {
      form.setFieldsValue({
        ...values,
        ...objectFlatten(defaultValues),
      });
    }
  }, [tokens]);

  const handleSetTokens = useCallback(
    (event: string) => {
      if (event && template) {
        const _tokens = variables && (variables[event][channels[template]] as unknown);

        setTokens(_tokens as Tokens);
      }
      setIsManuallyTriggered(event === TemplateEvents.ManuallyTriggeredEvent);
      setCurrentEvent(event || undefined);
    },
    [variables, id],
  );

  const fetchData = useCallback(async () => {
    const response = await fetchTemplate(Number(id));
    if (response.success) {
      const map =
        response.data.sections &&
        response.data.sections.map((item) => {
          return {
            [item.key]: item.content,
          };
        });

      const obj = map && objectFlatten(map);

      form.setFieldsValue({
        ...response.data,
        ...obj,
        locale: response.data.locale ?? NO_LANGUAGE,
      });
      handleSetTokens(String(response.data.event));
      setSaved(true);
    }
  }, [id, variables]);

  const onFormFinish = useCallback(
    async (values: Partial<API.Template>) => {
      let response: API.DefaultResponse<API.Template>;

      const postData: Partial<API.Template> = {
        ...values,
        channel: channels[template as keyof typeof TemplateChannelValue] as API.Template['channel'],
        sections: createEntries(filterNotAllowedKeys(values)),
      };

      if (isNew) {
        response = await createTemplate(postData);
        if (response.success) {
          history.push(`/configuration/templates/${template}/${response.data.id}`);
        }
      } else {
        response = await updateTemplate(Number(id), postData);
      }
      setSaved(true);
      message.success(response.message);
    },
    [variables, id],
  );

  useEffect(() => {
    if (isNew) {
      return;
    }

    fetchData();
  }, [id, variables]);

  // Every template of the event, in every language: the language strip and the copy helper read them.
  useEffect(() => {
    if (!supportsLanguages || !currentEvent) {
      setSiblings([]);
      return;
    }
    let cancelled = false;
    fetchTemplates({
      channel: channels[template as keyof typeof TemplateChannelValue] as string,
      event: currentEvent,
      per_page: 100,
    } as API.TemplatesParams).then((response) => {
      if (!cancelled && response.success) {
        setSiblings(response.data);
      }
    });
    return () => {
      cancelled = true;
    };
  }, [currentEvent, id, supportsLanguages]);

  const copyInto = useCallback(
    (source: API.Template) => {
      const draft = translationDraft(
        { name: source.name, event: source.event, sections: source.sections },
        form.getFieldValue('locale') || NO_LANGUAGE,
      );
      // Only the content moves: the name, event, language and default flag of this form stay.
      const content = Object.fromEntries(
        Object.entries(draft).filter(
          ([key]) => !['name', 'event', 'locale', 'default'].includes(key),
        ),
      );
      form.setFieldsValue(content);
      setSaved(false);
      message.info(intl.formatMessage({ id: 'templates.lang_copied' }, { name: source.name }));
    },
    [form, intl],
  );

  // A new template for a missing language starts from the query: ?event=…&locale=pl&copy=<id>
  const query = new URLSearchParams(history.location.search);
  const queryEvent = query.get('event');
  const queryLocale = query.get('locale');
  const queryCopy = Number(query.get('copy'));
  useEffect(() => {
    if (!isNew || !variables || !queryEvent) {
      return;
    }
    form.setFieldsValue({
      event: queryEvent,
      locale: queryLocale ?? NO_LANGUAGE,
      default: true,
    });
    handleSetTokens(queryEvent);
    if (queryCopy) {
      fetchTemplate(queryCopy).then((response) => {
        if (response.success) {
          const draft = translationDraft(response.data as API.Template, queryLocale ?? NO_LANGUAGE);
          form.setFieldsValue(draft);
          message.info(
            intl.formatMessage({ id: 'templates.lang_copied' }, { name: response.data.name }),
          );
        }
      });
    }
  }, [isNew, variables, queryEvent, queryLocale, queryCopy]);

  const variablesForChannel = useCallback(
    (channel: string): Record<string, string> => {
      return variables
        ? Object.keys(variables).reduce((acc, curr) => {
            if (variables[curr][channel]) {
              return { ...acc, [curr]: intl.formatMessage({ id: curr, defaultMessage: curr }) };
            }
            return acc;
          }, {})
        : {};
    },
    [variables],
  );

  return (
    <PageContainer
      title={
        isNew ? (
          <span>
            <FormattedMessage id="new" /> {template} <FormattedMessage id="template" />
          </span>
        ) : (
          <span>
            <FormattedMessage id="edit" /> {template} <FormattedMessage id="template" />
          </span>
        )
      }
    >
      <ProCard size="small">
        <ProForm
          initialValues={{}}
          onFinish={onFormFinish}
          form={form}
          onValuesChange={() => {
            const values = form.getFieldsValue();
            handleSetTokens(values.event);
            setSaved(false);
          }}
        >
          <ProForm.Group>
            <ProFormText
              width="sm"
              name="name"
              label={<FormattedMessage id="name" />}
              placeholder={intl.formatMessage({
                id: 'name',
              })}
              rules={[{ required: true, message: <FormattedMessage id="select" /> }]}
            />
            <ProFormSelect
              name="event"
              width="lg"
              label={<FormattedMessage id="event" />}
              valueEnum={
                variables
                  ? variablesForChannel(channels[template as keyof typeof TemplateChannelValue])
                  : {}
              }
              placeholder={intl.formatMessage({
                id: 'event',
              })}
              rules={[{ required: true, message: <FormattedMessage id="select" /> }]}
            />

            {supportsLanguages && (
              <ProFormSelect
                name="locale"
                width="sm"
                label={<FormattedMessage id="templates.lang_field" />}
                tooltip={<FormattedMessage id="templates.lang_field_tooltip" />}
                allowClear={false}
                initialValue={NO_LANGUAGE}
                options={[
                  ...TEMPLATE_LANGUAGES.map((language) => ({
                    value: language,
                    label: languageName(language),
                  })),
                  { value: NO_LANGUAGE, label: intl.formatMessage({ id: 'templates.lang_none' }) },
                ]}
              />
            )}

            <ProForm.Item label={<FormattedMessage id="templates.set_as_default_template" />}>
              <ProFormCheckbox name="default" />
            </ProForm.Item>
          </ProForm.Group>

          {supportsLanguages && (
            <TemplateLanguages
              templates={siblings}
              event={currentEvent}
              currentId={isNew ? undefined : Number(id)}
              basePath={`/configuration/templates/${template}`}
              onCopy={copyInto}
            />
          )}

          {!isNew && template !== 'pdf' && (
            <ProForm.Group>
              <ProForm.Item label={<FormattedMessage id="preview" />}>
                <PreviewButton
                  disabled={!saved}
                  id={Number(id)}
                  type={template}
                  onPreviewData={(data) => {
                    if (data && data.data && data.data.content) {
                      setPreviewData(data.data.content);
                    }
                  }}
                />
              </ProForm.Item>

              {isManuallyTriggered && (
                <ProForm.Item
                  label={<FormattedMessage id="generate_pdf" />}
                  tooltip={<FormattedMessage id="generate_pdf_tooltip" />}
                >
                  <TemplateManuallyTrigger templateId={Number(id)} />
                </ProForm.Item>
              )}

              {isManuallyTriggered && (
                <ProForm.Item
                  label={<FormattedMessage id="generate_pdf" />}
                  tooltip={<FormattedMessage id="generate_pdf_tooltip" />}
                >
                  <TemplateManuallyTriggerProduct templateId={Number(id)} />
                </ProForm.Item>
              )}
            </ProForm.Group>
          )}

          {channels[template as keyof typeof TemplateChannelValue] === TemplateChannelValue.pdf &&
            !isNew && (
              <Collapse ghost destroyInactivePanel defaultActiveKey={[-1]}>
                <Panel header={<FormattedMessage id="generated_pdfs" />} key={0}>
                  <PdfList template_id={Number(id)} />
                </Panel>
              </Collapse>
            )}

          {!tokens && !isNew ? (
            <Spin />
          ) : (
            tokens &&
            tokens.sections &&
            Object.keys(tokens.sections).map((section, index) => {
              const fieldItem = tokens.sections && tokens.sections[section];

              return (
                <React.Fragment key={section}>
                  {index === 0 && template !== 'pdf' && (
                    <React.Fragment>
                      <Divider>
                        <FormattedMessage id="templates.tokens" />
                      </Divider>
                      <Space>
                        <Typography>
                          {tokens.variables.map((token) => (
                            <Tag color="orange" key={token}>
                              {token}
                            </Tag>
                          ))}
                        </Typography>
                      </Space>
                    </React.Fragment>
                  )}
                  <Divider />
                  <TemplateFields name={section} field={fieldItem} variables={tokens.variables} />
                </React.Fragment>
              );
            })
          )}
          <Divider />
        </ProForm>
      </ProCard>
      {previewData && <p>fabric preview</p>}
    </PageContainer>
  );
};
