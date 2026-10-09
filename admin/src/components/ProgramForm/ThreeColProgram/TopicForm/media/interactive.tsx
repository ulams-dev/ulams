import {
  Alert,
  Button,
  Checkbox,
  Input,
  InputNumber,
  Radio,
  Select,
  Space,
  Typography,
} from 'antd';
import React, { useEffect, useMemo, useState } from 'react';
import { FormattedMessage, Link, useIntl } from 'umi';

import type {
  CompletionRule,
  DisplayMode,
  InteractivePackage,
  InteractiveTopicable,
} from '@/services/ulams/interactive';
import {
  interactivePackage,
  interactivePackages,
  interactiveVersions,
  needsPassScore,
  rangeProblem,
  stepOptions,
  topicFields,
} from '@/services/ulams/interactive';

/**
 * Interactive topic: pick a package, pin a version (or follow the latest one), choose the steps it
 * plays, how it completes, inline or background, and the lesson text.
 */
export const InteractiveTopicForm: React.FC<{
  topicable?: InteractiveTopicable;
  onChange: (fields: Record<string, unknown>) => void;
}> = ({ topicable, onChange }) => {
  const intl = useIntl();
  const [packages, setPackages] = useState<InteractivePackage[]>([]);
  const [current, setCurrent] = useState<InteractivePackage | null>(null);
  const [versions, setVersions] = useState<number[]>([]);
  const [value, setValue] = useState<InteractiveTopicable>({
    completion_rule: 'on_range_end',
    display: 'inline',
    height: 640,
    ...topicable,
    value: topicable?.value ? Number(topicable.value) : undefined,
  });

  useEffect(() => {
    interactivePackages({ current: 1, pageSize: 100 })
      .then((r) => setPackages(r.data))
      .catch(() => setPackages([]));
  }, []);

  useEffect(() => {
    if (!value.value) return;
    Promise.all([interactivePackage(value.value), interactiveVersions(value.value)])
      .then(([p, v]) => {
        setCurrent(p.data);
        setVersions(v.data.map((x) => x.version));
      })
      .catch(() => setCurrent(null));
  }, [value.value]);

  const update = (patch: Partial<InteractiveTopicable>) => {
    setValue((prev) => {
      const next = { ...prev, ...patch };
      onChange(topicFields(next));
      return next;
    });
  };

  // The manifest to choose steps from: the pinned version's, else the current one's.
  const [pinnedManifest, setPinnedManifest] = useState<InteractivePackage['manifest']>(null);
  useEffect(() => {
    setPinnedManifest(null);
  }, [value.value, value.version]);
  const manifest = pinnedManifest ?? current?.manifest ?? null;
  const options = useMemo(() => stepOptions(manifest, manifest?.defaultLocale), [manifest]);
  const problem = rangeProblem(manifest, value.start_step, value.end_step);
  const pinnedVersion = value.follow_latest ? undefined : value.version ?? current?.current_version;

  return (
    <Space direction="vertical" style={{ width: '100%' }}>
      <label htmlFor="interactive-package">
        <FormattedMessage id="interactive.package" defaultMessage="Interactive package" />
      </label>
      <Select
        id="interactive-package"
        showSearch
        optionFilterProp="label"
        style={{ width: '100%' }}
        value={value.value}
        onChange={(v) =>
          update({ value: Number(v), version: undefined, start_step: null, end_step: null })
        }
        options={packages.map((p) => ({
          value: p.id,
          label: `${p.title} (v${p.current_version})`,
        }))}
      />
      <Space>
        {value.value && (
          <Link to={`/courses/interactive/${value.value}`}>
            <Button size="small">
              <FormattedMessage id="interactive.open_editor" defaultMessage="Open the package" />
            </Button>
          </Link>
        )}
        <Link to="/courses/interactive">
          <Button size="small" type="link">
            <FormattedMessage id="interactive.new" defaultMessage="Upload package" />
          </Button>
        </Link>
      </Space>

      {value.value && (
        <>
          <Checkbox
            checked={Boolean(value.follow_latest)}
            onChange={(e) => update({ follow_latest: e.target.checked })}
          >
            <FormattedMessage
              id="interactive.follow_latest"
              defaultMessage="Follow the latest version (new uploads change what learners see)"
            />
          </Checkbox>
          {!value.follow_latest && (
            <Space>
              <label htmlFor="interactive-version">
                <FormattedMessage id="interactive.pin" defaultMessage="Pinned to version" />
              </label>
              <Select
                id="interactive-version"
                style={{ width: 120 }}
                value={pinnedVersion}
                onChange={(v) => update({ version: Number(v) })}
                options={versions.map((v) => ({ value: v, label: `v${v}` }))}
              />
            </Space>
          )}

          <Space wrap>
            <Space direction="vertical" size={2}>
              <label htmlFor="interactive-start">
                <FormattedMessage id="interactive.start_step" defaultMessage="Start step" />
              </label>
              <Select
                id="interactive-start"
                allowClear
                style={{ minWidth: 260 }}
                value={value.start_step || undefined}
                onChange={(v) => update({ start_step: v ?? null })}
                options={options}
                placeholder={intl.formatMessage({
                  id: 'interactive.first_step',
                  defaultMessage: 'First step',
                })}
              />
            </Space>
            <Space direction="vertical" size={2}>
              <label htmlFor="interactive-end">
                <FormattedMessage id="interactive.end_step" defaultMessage="End step" />
              </label>
              <Select
                id="interactive-end"
                allowClear
                style={{ minWidth: 260 }}
                value={value.end_step || undefined}
                onChange={(v) => update({ end_step: v ?? null })}
                options={options}
                placeholder={intl.formatMessage({
                  id: 'interactive.last_step',
                  defaultMessage: 'Last step',
                })}
              />
            </Space>
          </Space>
          {problem && (
            <Alert
              type="error"
              showIcon
              message={
                problem === 'order' ? (
                  <FormattedMessage
                    id="interactive.range_order"
                    defaultMessage="The end step comes before the start step."
                  />
                ) : (
                  <FormattedMessage
                    id="interactive.range_unknown"
                    defaultMessage="This step does not exist in the played version."
                  />
                )
              }
            />
          )}

          <Space direction="vertical" size={2}>
            <label htmlFor="interactive-completion">
              <FormattedMessage id="interactive.completion" defaultMessage="Completes when" />
            </label>
            <Select
              id="interactive-completion"
              style={{ minWidth: 320 }}
              value={value.completion_rule}
              onChange={(v: CompletionRule) => update({ completion_rule: v })}
              options={[
                {
                  value: 'on_range_end',
                  label: intl.formatMessage({
                    id: 'interactive.on_range_end',
                    defaultMessage: 'The learner reaches the end step',
                  }),
                },
                {
                  value: 'on_complete',
                  label: intl.formatMessage({
                    id: 'interactive.on_complete',
                    defaultMessage: 'The package reports completion',
                  }),
                },
                {
                  value: 'on_score',
                  label: intl.formatMessage({
                    id: 'interactive.on_score',
                    defaultMessage: 'The score reaches the pass score',
                  }),
                },
                {
                  value: 'on_open',
                  label: intl.formatMessage({
                    id: 'interactive.on_open',
                    defaultMessage: 'The learner opens the topic',
                  }),
                },
              ]}
            />
          </Space>
          {needsPassScore(value.completion_rule) && (
            <Space>
              <label htmlFor="interactive-pass">
                <FormattedMessage id="interactive.pass_score" defaultMessage="Pass score (%)" />
              </label>
              <InputNumber
                id="interactive-pass"
                min={0}
                max={100}
                value={value.pass_score ?? undefined}
                onChange={(v) => update({ pass_score: v })}
              />
            </Space>
          )}

          <Space direction="vertical" size={2}>
            <FormattedMessage id="interactive.display" defaultMessage="Display" />
            <Radio.Group
              value={value.display}
              onChange={(e) => update({ display: e.target.value as DisplayMode })}
              options={[
                {
                  value: 'inline',
                  label: intl.formatMessage({
                    id: 'interactive.inline',
                    defaultMessage: 'Inline in the lesson',
                  }),
                },
                {
                  value: 'background',
                  label: intl.formatMessage({
                    id: 'interactive.background',
                    defaultMessage: 'The background of the page',
                  }),
                },
              ]}
            />
          </Space>
          {value.display === 'inline' && (
            <Space>
              <label htmlFor="interactive-height">
                <FormattedMessage id="interactive.height" defaultMessage="Frame height (px)" />
              </label>
              <InputNumber
                id="interactive-height"
                min={240}
                max={2000}
                value={value.height}
                onChange={(v) => update({ height: v ?? 640 })}
              />
            </Space>
          )}

          <label htmlFor="interactive-text">
            <FormattedMessage id="interactive.text" defaultMessage="Lesson text (Markdown)" />
          </label>
          <Input.TextArea
            id="interactive-text"
            rows={6}
            value={value.text ?? ''}
            onChange={(e) => update({ text: e.target.value })}
          />
          <Typography.Text type="secondary">
            <FormattedMessage
              id="interactive.topic_hint"
              defaultMessage="Learners also get a text version of every step in the range. A step outside the range never completes the topic."
            />
          </Typography.Text>
        </>
      )}
    </Space>
  );
};

export default InteractiveTopicForm;
