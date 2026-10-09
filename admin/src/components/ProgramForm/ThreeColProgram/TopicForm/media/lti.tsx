import { Alert, Button, Input, InputNumber, Radio, Select, Space, Typography, message } from 'antd';
import React, { useCallback, useEffect, useRef, useState } from 'react';
import { FormattedMessage, Link, useIntl } from 'umi';

import type { LtiTool } from '@/services/ulams/lti';
import { ltiTools, startLtiDeepLink } from '@/services/ulams/lti';

export type LtiTopicable = {
  lti_tool_id?: number;
  url?: string | null;
  custom?: Record<string, string> | null;
  presentation?: 'iframe' | 'window';
  score_maximum?: number;
};

/** Values for the topic form data; customs are sent as custom[key] fields. */
const toFields = (value: LtiTopicable, previousCustomKeys: string[]) => {
  const fields: Record<string, unknown> = {
    lti_tool_id: value.lti_tool_id,
    url: value.url || undefined,
    presentation: value.presentation ?? 'iframe',
    score_maximum: value.score_maximum ?? 100,
  };
  previousCustomKeys.forEach((key) => {
    fields[`custom[${key}]`] = undefined;
  });
  Object.entries(value.custom ?? {}).forEach(([key, v]) => {
    fields[`custom[${key}]`] = v;
  });
  return fields;
};

/**
 * External tool (LTI 1.3) topic: the tool, an optional target link, custom parameters and how it
 * opens. "Pick content from the tool" runs LTI deep linking: the tool creates the topics in this
 * lesson and the program reloads.
 */
export const LtiTopicForm: React.FC<{
  topicable?: LtiTopicable;
  lessonId?: number;
  isNew?: boolean;
  onChange: (fields: Record<string, unknown>) => void;
  onDeepLinked?: () => void;
}> = ({ topicable, lessonId, isNew, onChange, onDeepLinked }) => {
  const intl = useIntl();
  const [tools, setTools] = useState<LtiTool[]>([]);
  const [value, setValue] = useState<LtiTopicable>({
    presentation: 'iframe',
    score_maximum: 100,
    ...topicable,
  });
  const [customText, setCustomText] = useState(
    Object.entries(topicable?.custom ?? {})
      .map(([k, v]) => `${k}=${v}`)
      .join('\n'),
  );
  const customKeys = useRef<string[]>(Object.keys(topicable?.custom ?? {}));

  useEffect(() => {
    ltiTools()
      .then((r) => setTools(r.data.filter((t) => t.enabled)))
      .catch(() => setTools([]));
  }, []);

  const update = useCallback(
    (patch: Partial<LtiTopicable>) => {
      setValue((prev) => {
        const next = { ...prev, ...patch };
        onChange(toFields(next, customKeys.current));
        customKeys.current = Object.keys(next.custom ?? {});
        return next;
      });
    },
    [onChange],
  );

  useEffect(() => {
    if (topicable?.lti_tool_id) update({});
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    const listener = (event: MessageEvent) => {
      if (event.data?.type === 'ulams:lti:deep-link') {
        message.success(
          intl.formatMessage(
            { id: 'lti.deep_linked', defaultMessage: '{count} items added to the lesson' },
            { count: event.data.topics?.length ?? 0 },
          ),
        );
        onDeepLinked?.();
      }
    };
    window.addEventListener('message', listener);
    return () => window.removeEventListener('message', listener);
  }, [intl, onDeepLinked]);

  const pickContent = async () => {
    if (!value.lti_tool_id || !lessonId) return;
    try {
      const response = await startLtiDeepLink(value.lti_tool_id, lessonId);
      window.open(response.data.url, 'ulams-lti-deep-link', 'width=1000,height=760');
    } catch (e: any) {
      message.error(e?.data?.message || e?.message || String(e));
    }
  };

  const selectedTool = tools.find((t) => t.id === value.lti_tool_id);

  if (tools.length === 0) {
    return (
      <Alert
        type="info"
        showIcon
        message={
          <FormattedMessage
            id="lti.no_tools"
            defaultMessage="No external tools are registered yet."
          />
        }
        description={
          <Link to="/integrations/lti">
            <FormattedMessage id="lti.register_tool" defaultMessage="Register a tool" />
          </Link>
        }
      />
    );
  }

  return (
    <Space direction="vertical" style={{ width: '100%' }}>
      <label htmlFor="lti-tool">
        <FormattedMessage id="lti.tool" defaultMessage="External tool" />
      </label>
      <Select
        id="lti-tool"
        style={{ width: '100%' }}
        value={value.lti_tool_id}
        onChange={(id) => update({ lti_tool_id: Number(id) })}
        options={tools.map((t) => ({ value: t.id, label: t.name }))}
      />
      {selectedTool?.deep_linking_url && lessonId && (
        <Space direction="vertical">
          <Button onClick={pickContent}>
            <FormattedMessage id="lti.pick_content" defaultMessage="Pick content from the tool" />
          </Button>
          {isNew && (
            <Typography.Text type="secondary">
              <FormattedMessage
                id="lti.pick_content_hint"
                defaultMessage="The tool adds one lesson item per selected content; this empty item can then be deleted."
              />
            </Typography.Text>
          )}
        </Space>
      )}
      <label htmlFor="lti-url">
        <FormattedMessage id="lti.target_link" defaultMessage="Link inside the tool (optional)" />
      </label>
      <Input
        id="lti-url"
        value={value.url ?? ''}
        placeholder={selectedTool?.launch_url}
        onChange={(e) => update({ url: e.target.value })}
      />
      <label htmlFor="lti-custom">
        <FormattedMessage id="lti.custom" defaultMessage="Custom parameters (key=value per line)" />
      </label>
      <Input.TextArea
        id="lti-custom"
        rows={3}
        value={customText}
        onChange={(e) => {
          setCustomText(e.target.value);
          const custom: Record<string, string> = {};
          e.target.value
            .split('\n')
            .map((l) => l.trim())
            .forEach((line) => {
              const at = line.indexOf('=');
              if (at > 0) custom[line.slice(0, at).trim()] = line.slice(at + 1).trim();
            });
          update({ custom });
        }}
      />
      <Space size="large" wrap>
        <Radio.Group
          value={value.presentation}
          onChange={(e) => update({ presentation: e.target.value })}
        >
          <Radio value="iframe">
            <FormattedMessage id="lti.in_page" defaultMessage="In the page" />
          </Radio>
          <Radio value="window">
            <FormattedMessage id="lti.new_window" defaultMessage="New window" />
          </Radio>
        </Radio.Group>
        <Space>
          <label htmlFor="lti-max">
            <FormattedMessage id="lti.max_score" defaultMessage="Maximum score" />
          </label>
          <InputNumber
            id="lti-max"
            min={0.01}
            value={value.score_maximum}
            onChange={(v) => update({ score_maximum: Number(v) || 100 })}
          />
        </Space>
      </Space>
    </Space>
  );
};

export default LtiTopicForm;
