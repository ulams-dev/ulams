import { Alert, Button, Input, Select, Space, Typography } from 'antd';
import React, { useEffect, useMemo, useState } from 'react';
import { FormattedMessage, useIntl } from 'umi';

import type { LayoutTopicable } from '@/services/ulams/layout';
import {
  LAYOUT_STARTER,
  checkLayoutText,
  layoutComponents,
  layoutFields,
  layoutPreviewHref,
  layoutText,
} from '@/services/ulams/layout';

/** The document text as a list, or null when it is not a JSON list (the author is mid-edit). */
const safeList = (text: string): unknown[] | null => {
  try {
    const parsed = JSON.parse(text);
    return Array.isArray(parsed) ? parsed : null;
  } catch {
    return null;
  }
};

/**
 * Layout topic (ADR 0052): the lesson body as a JSON list of catalogue components (flip cards,
 * timelines, practice activities, ...) plus a Markdown fallback for clients that do not render
 * layouts. The JSON is checked against the learner layout manifest while you type; the API checks
 * it again when the topic is saved. Layouts normally come from the course builder later, so this is
 * the hand-written route.
 */
export const LayoutTopicForm: React.FC<{
  topicable?: LayoutTopicable;
  /** Learner site origin for the preview link; null hides the link. */
  learnerUrl?: string | null;
  courseId?: number;
  topicId?: number;
  isNew?: boolean;
  onChange: (fields: Record<string, unknown>) => void;
}> = ({ topicable, learnerUrl, courseId, topicId, isNew, onChange }) => {
  const intl = useIntl();
  const [text, setText] = useState(layoutText(topicable?.document));
  const [fallback, setFallback] = useState(topicable?.markdown_fallback ?? '');
  const check = useMemo(() => (text.trim() === '' ? null : checkLayoutText(text)), [text]);
  const preview = isNew ? null : layoutPreviewHref(learnerUrl, courseId, topicId);

  // an existing topic is shown as stored; nothing is sent until the author edits it
  useEffect(() => {
    if (topicable?.document) {
      onChange(layoutFields(layoutText(topicable.document), topicable.markdown_fallback ?? ''));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const change = (nextText: string, nextFallback: string) => {
    setText(nextText);
    setFallback(nextFallback);
    onChange(layoutFields(nextText, nextFallback));
  };

  const insert = (name: string) => {
    const example = LAYOUT_STARTER.find((node) => node.component === name);
    const node = JSON.stringify(example ?? { component: name, props: {} }, null, 2);
    const current = text.trim() === '' ? [] : safeList(text);
    if (current === null) {
      return;
    }
    change(JSON.stringify([...current, JSON.parse(node)], null, 2), fallback);
  };

  return (
    <Space direction="vertical" style={{ width: '100%' }} size="middle">
      <Alert
        type="info"
        showIcon
        message={
          <FormattedMessage
            id="layout.about"
            defaultMessage="A layout is a JSON list of components: {components}. Practice must use PracticeActivity. The JSON is validated against the learner layout manifest."
            values={{ components: layoutComponents().join(', ') }}
          />
        }
      />
      <Space direction="vertical" size={2} style={{ width: '100%' }}>
        <label htmlFor="layout-document">
          <FormattedMessage id="layout.document" defaultMessage="Layout document (JSON)" />
        </label>
        <Input.TextArea
          id="layout-document"
          rows={18}
          spellCheck={false}
          value={text}
          onChange={(e) => change(e.target.value, fallback)}
          aria-describedby="layout-validation"
          aria-invalid={check ? check.errors.length > 0 : false}
          style={{ fontFamily: 'ui-monospace, SFMono-Regular, Menlo, monospace', fontSize: 13 }}
          placeholder={JSON.stringify(LAYOUT_STARTER[0])}
        />
      </Space>
      <div id="layout-validation" role="status" aria-live="polite">
        {check && check.errors.length === 0 && (
          <Alert
            type="success"
            showIcon
            message={
              <FormattedMessage
                id="layout.valid"
                defaultMessage="The document is valid ({count} components)."
                values={{ count: check.document?.length ?? 0 }}
              />
            }
          />
        )}
        {check && check.errors.length > 0 && (
          <Alert
            type="error"
            showIcon
            message={
              <FormattedMessage id="layout.invalid" defaultMessage="The document has problems:" />
            }
            description={
              <ul style={{ margin: 0, paddingLeft: 18 }}>
                {check.errors.map((error) => (
                  <li key={error}>
                    <Typography.Text code>{error}</Typography.Text>
                  </li>
                ))}
              </ul>
            }
          />
        )}
      </div>
      <Space wrap>
        <Select
          style={{ minWidth: 220 }}
          placeholder={intl.formatMessage({
            id: 'layout.add',
            defaultMessage: 'Add a component',
          })}
          aria-label={intl.formatMessage({ id: 'layout.add', defaultMessage: 'Add a component' })}
          value={null as unknown as string}
          onChange={insert}
          options={layoutComponents().map((name) => ({ value: name, label: name }))}
        />
        <Button
          onClick={() => change(layoutText(LAYOUT_STARTER), fallback)}
          disabled={text.trim() !== ''}
        >
          <FormattedMessage id="layout.starter" defaultMessage="Start from an example" />
        </Button>
        {preview ? (
          <Button href={preview} target="_blank" rel="noopener noreferrer">
            <FormattedMessage id="layout.preview" defaultMessage="Preview in the learner site" />
          </Button>
        ) : (
          <Typography.Text type="secondary">
            <FormattedMessage
              id="layout.preview_after_save"
              defaultMessage="Save the topic to get a preview link."
            />
          </Typography.Text>
        )}
      </Space>
      <Space direction="vertical" size={2} style={{ width: '100%' }}>
        <label htmlFor="layout-fallback">
          <FormattedMessage id="layout.fallback" defaultMessage="Markdown fallback (required)" />
        </label>
        <Input.TextArea
          id="layout-fallback"
          rows={6}
          value={fallback}
          onChange={(e) => change(text, e.target.value)}
          aria-required
        />
        <Typography.Text type="secondary">
          <FormattedMessage
            id="layout.fallback_help"
            defaultMessage="Shown by clients that do not render layouts, and when the document cannot be rendered. Leave out hints and worked solutions: text cannot hold them back."
          />
        </Typography.Text>
      </Space>
    </Space>
  );
};

export default LayoutTopicForm;
