import { Alert, Button, Input, InputNumber, Radio, Select, Space, Typography } from 'antd';
import React, { useEffect, useMemo, useState } from 'react';
import { FormattedMessage, useIntl } from 'umi';

import type {
  CodeCompletionRule,
  CodeExerciseTopicable,
  CodeLanguage,
  ExerciseFile,
} from '@/services/ulams/codeExercise';
import {
  asFiles,
  exerciseFields,
  exercisePreviewHref,
  exerciseProblems,
  LANGUAGES,
  LIMITS,
  STARTER,
} from '@/services/ulams/codeExercise';

const MONO = { fontFamily: 'ui-monospace, SFMono-Regular, Menlo, monospace', fontSize: 13 };

/** A list of files: a path and the text of each, with add and remove. Every control has its own label. */
const FileList: React.FC<{
  idPrefix: string;
  label: React.ReactNode;
  files: ExerciseFile[];
  max: number;
  onChange: (files: ExerciseFile[]) => void;
}> = ({ idPrefix, label, files, max, onChange }) => {
  const intl = useIntl();
  const set = (index: number, patch: Partial<ExerciseFile>) =>
    onChange(files.map((f, i) => (i === index ? { ...f, ...patch } : f)));

  return (
    <fieldset style={{ border: 0, padding: 0, margin: 0 }}>
      <legend style={{ fontWeight: 600, marginBottom: 8 }}>{label}</legend>
      <Space direction="vertical" style={{ width: '100%' }} size="middle">
        {files.map((file, index) => (
          <div key={index} style={{ border: '1px solid #d9d9d9', borderRadius: 6, padding: 12 }}>
            <Space direction="vertical" style={{ width: '100%' }} size={6}>
              <Space align="end" style={{ width: '100%' }} wrap>
                <Space direction="vertical" size={2}>
                  <label htmlFor={`${idPrefix}-path-${index}`}>
                    <FormattedMessage id="codeexercise.path" defaultMessage="File path" />
                  </label>
                  <Input
                    id={`${idPrefix}-path-${index}`}
                    value={file.path}
                    onChange={(e) => set(index, { path: e.target.value })}
                    style={{ ...MONO, minWidth: 260 }}
                    spellCheck={false}
                  />
                </Space>
                <Button
                  danger
                  onClick={() => onChange(files.filter((_, i) => i !== index))}
                  disabled={files.length <= 1}
                  aria-label={intl.formatMessage(
                    { id: 'codeexercise.remove_file', defaultMessage: 'Remove the file {path}' },
                    { path: file.path || `#${index + 1}` },
                  )}
                >
                  <FormattedMessage id="codeexercise.remove" defaultMessage="Remove" />
                </Button>
              </Space>
              <label htmlFor={`${idPrefix}-content-${index}`}>
                <FormattedMessage id="codeexercise.content" defaultMessage="Content" />
              </label>
              <Input.TextArea
                id={`${idPrefix}-content-${index}`}
                rows={Math.min(18, Math.max(5, file.content.split('\n').length + 1))}
                value={file.content}
                onChange={(e) => set(index, { content: e.target.value })}
                style={MONO}
                spellCheck={false}
                wrap="off"
              />
            </Space>
          </div>
        ))}
        <Button
          onClick={() => onChange([...files, { path: '', content: '' }])}
          disabled={files.length >= max}
        >
          <FormattedMessage id="codeexercise.add_file" defaultMessage="Add a file" />
        </Button>
      </Space>
    </fieldset>
  );
};

/**
 * Code exercise topic (ADR 0170): the language, the files the learner starts from, the visible tests, the time
 * limit, how the topic completes and the task text. Learners edit and run the files in the browser sandbox; results
 * are practice grades (unverified) and never count for certificates. The files are checked here with the limits of
 * the API while you type; the API checks them again when the topic is saved.
 */
export const CodeExerciseTopicForm: React.FC<{
  topicable?: CodeExerciseTopicable;
  learnerUrl?: string | null;
  courseId?: number;
  topicId?: number;
  isNew?: boolean;
  onChange: (fields: Record<string, unknown>) => void;
}> = ({ topicable, learnerUrl, courseId, topicId, isNew, onChange }) => {
  const intl = useIntl();
  const [value, setValue] = useState<CodeExerciseTopicable>({
    language: 'javascript',
    timeout_ms: LIMITS.timeout.default,
    completion_rule: 'on_pass',
    pass_score: 100,
    files: STARTER.files,
    visible_tests: STARTER.tests,
    text: '',
    ...topicable,
    ...(topicable?.files ? { files: asFiles(topicable.files) } : {}),
    ...(topicable?.visible_tests ? { visible_tests: asFiles(topicable.visible_tests) } : {}),
  });
  const problems = useMemo(() => exerciseProblems(value), [value]);
  const preview = isNew ? null : exercisePreviewHref(learnerUrl, courseId, topicId);

  // an existing topic is shown as stored and a new one starts from the example: either way the form holds fields
  useEffect(() => {
    onChange(exerciseFields(value));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const update = (patch: Partial<CodeExerciseTopicable>) => {
    setValue((prev) => {
      const next = { ...prev, ...patch };
      onChange(exerciseFields(next));
      return next;
    });
  };

  return (
    <Space direction="vertical" style={{ width: '100%' }} size="middle">
      <Alert
        type="info"
        showIcon
        message={
          <FormattedMessage
            id="codeexercise.about"
            defaultMessage="Learners edit these files and run the tests in their browser. The code runs in an isolated sandbox with no network. A run is a practice attempt: it moves the learner's progress, but it is reported by the learner's browser, so it never counts for certificates or compliance."
          />
        }
      />
      <Space wrap size="large">
        <Space direction="vertical" size={2}>
          <label htmlFor="code-language">
            <FormattedMessage id="codeexercise.language" defaultMessage="Language" />
          </label>
          <Select
            id="code-language"
            style={{ width: 180 }}
            value={value.language}
            onChange={(language: CodeLanguage) => update({ language })}
            options={(Object.keys(LANGUAGES) as CodeLanguage[]).map((key) => ({
              value: key,
              label: LANGUAGES[key].label,
            }))}
          />
        </Space>
        <Space direction="vertical" size={2}>
          <label htmlFor="code-timeout">
            <FormattedMessage id="codeexercise.timeout" defaultMessage="Stop a run after (ms)" />
          </label>
          <InputNumber
            id="code-timeout"
            min={LIMITS.timeout.min}
            max={LIMITS.timeout.max}
            step={500}
            value={value.timeout_ms}
            onChange={(v) => update({ timeout_ms: Number(v ?? LIMITS.timeout.default) })}
          />
        </Space>
        <Space direction="vertical" size={2}>
          <span id="code-completion-label">
            <FormattedMessage id="codeexercise.completion" defaultMessage="The topic completes" />
          </span>
          <Radio.Group
            aria-labelledby="code-completion-label"
            value={value.completion_rule}
            onChange={(e) => update({ completion_rule: e.target.value as CodeCompletionRule })}
          >
            <Radio value="on_pass">
              <FormattedMessage id="codeexercise.on_pass" defaultMessage="after a passing run" />
            </Radio>
            <Radio value="on_attempt">
              <FormattedMessage id="codeexercise.on_attempt" defaultMessage="after any run" />
            </Radio>
          </Radio.Group>
        </Space>
        {value.completion_rule === 'on_pass' && (
          <Space direction="vertical" size={2}>
            <label htmlFor="code-pass-score">
              <FormattedMessage id="codeexercise.pass_score" defaultMessage="Passing tests (%)" />
            </label>
            <InputNumber
              id="code-pass-score"
              min={1}
              max={100}
              value={value.pass_score}
              onChange={(v) => update({ pass_score: Number(v ?? 100) })}
            />
          </Space>
        )}
      </Space>

      <Space direction="vertical" size={2} style={{ width: '100%' }}>
        <label htmlFor="code-text">
          <FormattedMessage id="codeexercise.text" defaultMessage="The task (Markdown)" />
        </label>
        <Input.TextArea
          id="code-text"
          rows={5}
          maxLength={20000}
          value={value.text ?? ''}
          onChange={(e) => update({ text: e.target.value })}
        />
      </Space>

      <FileList
        idPrefix="code-file"
        label={
          <FormattedMessage
            id="codeexercise.files"
            defaultMessage="Starter files (the learner edits these)"
          />
        }
        files={asFiles(value.files)}
        max={LIMITS.maxFiles}
        onChange={(files) => update({ files })}
      />
      <FileList
        idPrefix="code-test"
        label={
          <FormattedMessage
            id="codeexercise.tests"
            defaultMessage="Visible tests (the learner can read these; use test(name, fn) and expect(value))"
          />
        }
        files={asFiles(value.visible_tests)}
        max={LIMITS.maxTestFiles}
        onChange={(visible_tests) => update({ visible_tests })}
      />

      <div role="status" aria-live="polite">
        {problems.length === 0 ? (
          <Alert
            type="success"
            showIcon
            message={
              <FormattedMessage id="codeexercise.valid" defaultMessage="The exercise is valid." />
            }
          />
        ) : (
          <Alert
            type="error"
            showIcon
            message={
              <FormattedMessage
                id="codeexercise.invalid"
                defaultMessage="The exercise has problems:"
              />
            }
            description={
              <ul style={{ margin: 0, paddingLeft: 18 }}>
                {problems.map((problem) => (
                  <li key={problem}>
                    <Typography.Text code>{problem}</Typography.Text>
                  </li>
                ))}
              </ul>
            }
          />
        )}
      </div>

      {preview ? (
        <Button href={preview} target="_blank" rel="noopener noreferrer">
          <FormattedMessage
            id="codeexercise.preview"
            defaultMessage="Preview in the learner site"
          />
        </Button>
      ) : (
        <Typography.Text type="secondary">
          <FormattedMessage
            id="codeexercise.preview_after_save"
            defaultMessage="Save the topic to get a preview link."
          />
        </Typography.Text>
      )}
      <Typography.Text type="secondary">
        {intl.formatMessage({
          id: 'codeexercise.hidden_tests_later',
          defaultMessage:
            'Hidden tests and verified grading arrive with the server runner; for now every test is visible.',
        })}
      </Typography.Text>
    </Space>
  );
};

export default CodeExerciseTopicForm;
