import { CheckCircleFilled, WarningFilled } from '@ant-design/icons';
import { Alert, Button, Popconfirm, Select } from 'antd';
import React, { useMemo, useState } from 'react';
import { FormattedMessage, Link, useIntl } from 'umi';
import './index.css';
import {
  TEMPLATE_LANGUAGES,
  coverage,
  languageName,
  missingLanguages,
  universalTemplate,
} from './languages';

export type TemplateLanguagesProps = {
  /** Templates of this event, in every language, as the list endpoint returns them. */
  templates: API.Template[];
  event?: string;
  /** Id of the template being edited; absent while a new one is created. */
  currentId?: number;
  /** Where a new template of the same type is created (`/configuration/templates/email`). */
  basePath: string;
  /** Called with the template chosen as the source of a copy; the page fills its form with it. */
  onCopy?: (source: API.Template) => void;
};

/**
 * The languages of one event: which have a template, which are missing, a way to create a missing one
 * (starting as a copy of the template without a language, or of the English one) and a helper to copy the
 * content of another template into the form. Status is text, not colour alone.
 */
export const TemplateLanguages: React.FC<TemplateLanguagesProps> = ({
  templates,
  event,
  currentId,
  basePath,
  onCopy,
}) => {
  const intl = useIntl();
  const [source, setSource] = useState<number | undefined>();
  const served = useMemo(() => coverage(templates, event), [templates, event]);
  const missing = useMemo(() => missingLanguages(templates, event), [templates, event]);
  const universal = useMemo(() => universalTemplate(templates, event), [templates, event]);
  const others = templates.filter(
    (template) => template.event === event && template.id !== currentId,
  );
  const copySource = universal ?? served.en ?? others[0];

  if (!event) {
    return null;
  }

  const createHref = (language: string) => {
    const query = new URLSearchParams({ event, locale: language });
    if (copySource) {
      query.set('copy', String(copySource.id));
    }
    return `${basePath}/new?${query.toString()}`;
  };

  return (
    <section className="template-languages" aria-labelledby="template-languages-title">
      <h3 id="template-languages-title">
        <FormattedMessage id="templates.lang_title" />
      </h3>
      <p className="template-languages__hint">
        <FormattedMessage id="templates.lang_hint" />
      </p>
      <ul className="template-languages__list">
        {TEMPLATE_LANGUAGES.map((language) => {
          const template = served[language];
          const name = languageName(language);
          return (
            <li key={language} className="template-languages__item" data-language={language}>
              <span lang={language} className="template-languages__name">
                {name}
              </span>
              {template ? (
                <>
                  <span className="template-languages__badge template-languages__badge--ok">
                    <CheckCircleFilled aria-hidden />{' '}
                    {intl.formatMessage({ id: 'templates.lang_present' })}
                  </span>
                  {template.id === currentId ? (
                    <span aria-current="true" className="template-languages__current">
                      <FormattedMessage id="templates.lang_current" />
                    </span>
                  ) : (
                    <Link
                      to={`${basePath}/${template.id}`}
                      aria-label={intl.formatMessage(
                        { id: 'templates.lang_edit_in' },
                        { language: name },
                      )}
                    >
                      <FormattedMessage id="templates.lang_edit" />
                    </Link>
                  )}
                </>
              ) : (
                <>
                  <span className="template-languages__badge template-languages__badge--missing">
                    <WarningFilled aria-hidden />{' '}
                    {intl.formatMessage({ id: 'templates.lang_missing' })}
                  </span>
                  <Link
                    to={createHref(language)}
                    aria-label={intl.formatMessage(
                      { id: 'templates.lang_create_in' },
                      { language: name },
                    )}
                  >
                    <FormattedMessage id="templates.lang_create" />
                  </Link>
                </>
              )}
            </li>
          );
        })}
      </ul>
      {missing.length > 0 ? (
        <Alert
          type="warning"
          showIcon
          role="status"
          message={intl.formatMessage(
            { id: 'templates.lang_missing_summary' },
            { languages: missing.map(languageName).join(', ') },
          )}
        />
      ) : (
        <p className="template-languages__complete">
          <FormattedMessage id="templates.lang_complete" />
        </p>
      )}
      {universal && universal.id !== currentId && (
        <p className="template-languages__universal">
          <FormattedMessage id="templates.lang_universal" />{' '}
          <Link to={`${basePath}/${universal.id}`}>
            <FormattedMessage id="templates.lang_edit" />
          </Link>
        </p>
      )}
      {onCopy && others.length > 0 && (
        <div
          className="template-languages__copy"
          role="group"
          aria-labelledby="template-languages-copy"
        >
          <label id="template-languages-copy">
            <FormattedMessage id="templates.lang_copy_label" />
          </label>
          <Select
            value={source}
            onChange={setSource}
            style={{ minWidth: 260 }}
            placeholder={intl.formatMessage({ id: 'templates.lang_copy_placeholder' })}
            aria-labelledby="template-languages-copy"
            options={others.map((template) => ({
              value: template.id,
              label: `${
                languageName(template.locale) || intl.formatMessage({ id: 'templates.lang_none' })
              } - ${template.name}`,
            }))}
          />
          <Popconfirm
            title={<FormattedMessage id="templates.lang_copy_confirm" />}
            okText={<FormattedMessage id="yes" />}
            cancelText={<FormattedMessage id="no" />}
            disabled={source === undefined}
            onConfirm={() => {
              const chosen = others.find((template) => template.id === source);
              if (chosen) {
                onCopy(chosen);
              }
            }}
          >
            <Button disabled={source === undefined}>
              <FormattedMessage id="templates.lang_copy_button" />
            </Button>
          </Popconfirm>
        </div>
      )}
    </section>
  );
};

/** Compact per-language status for a row of the list: the code plus a word, never colour alone. */
export const TranslationTags: React.FC<{ templates: API.Template[]; event?: string }> = ({
  templates,
  event,
}) => {
  const intl = useIntl();
  const served = coverage(templates, event);
  return (
    <span className="template-languages__tags">
      {TEMPLATE_LANGUAGES.map((language) => {
        const present = !!served[language];
        return (
          <span
            key={language}
            className={`template-languages__badge template-languages__badge--${
              present ? 'ok' : 'missing'
            }`}
          >
            <span aria-hidden>
              {language.toUpperCase()} {present ? '\u2713' : '!'}
            </span>
            <span className="template-languages__sr">
              {`${languageName(language)}: ${intl.formatMessage({
                id: present ? 'templates.lang_present' : 'templates.lang_missing',
              })}`}
            </span>
          </span>
        );
      })}
    </span>
  );
};

export default TemplateLanguages;
