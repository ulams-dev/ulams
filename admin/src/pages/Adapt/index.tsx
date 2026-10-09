import { UploadOutlined } from '@ant-design/icons';
import { PageContainer } from '@ant-design/pro-layout';
import {
  Alert,
  Button,
  Form,
  Input,
  message,
  Modal,
  Popconfirm,
  Result,
  Space,
  Table,
  Tabs,
  Upload,
} from 'antd';
import React, { useCallback, useEffect, useState } from 'react';
import { FormattedMessage, history, Link, useIntl } from 'umi';

import type { AdaptSource } from '@/services/ulams/adapt';
import {
  ADAPT_POLL_MS,
  adaptSources,
  buildAdaptSource,
  createAdaptSource,
  deleteAdaptSource,
  isAdaptDisabled,
  isBuilding,
} from '@/services/ulams/adapt';
import { AdaptStatusTag, errorText } from './status';

/** Adapt sources (Path B): versioned Adapt JSON built into SCORM packages. */
const AdaptList: React.FC = () => {
  const intl = useIntl();
  const [sources, setSources] = useState<AdaptSource[]>([]);
  const [disabled, setDisabled] = useState(false);
  const [loading, setLoading] = useState(false);
  const [creating, setCreating] = useState(false);
  const [file, setFile] = useState<File | null>(null);
  const [form] = Form.useForm();

  const load = useCallback((quiet = false) => {
    if (!quiet) setLoading(true);
    adaptSources()
      .then((r) => setSources(r.data))
      .catch((e) => {
        if (isAdaptDisabled(e)) setDisabled(true);
        else message.error(errorText(e));
      })
      .finally(() => setLoading(false));
  }, []);
  useEffect(() => load(), [load]);

  // keep polling while a build runs
  const anyBuilding = sources.some(isBuilding);
  useEffect(() => {
    if (!anyBuilding) return undefined;
    const timer = window.setInterval(() => load(true), ADAPT_POLL_MS);
    return () => window.clearInterval(timer);
  }, [anyBuilding, load]);

  const create = async (mode: 'paste' | 'file') => {
    const values = await form.validateFields(mode === 'paste' ? ['title', 'json'] : ['title']);
    try {
      const text = mode === 'file' && file ? await file.text() : values.json;
      const response = await createAdaptSource(text, values.title);
      setCreating(false);
      history.push(`/courses/adapt/${response.data.id}`);
    } catch (e) {
      message.error(e instanceof SyntaxError ? `JSON: ${e.message}` : errorText(e));
    }
  };

  const build = (id: number) =>
    buildAdaptSource(id)
      .then(() => load(true))
      .catch((e) => message.error(errorText(e)));

  if (disabled) {
    return (
      <PageContainer>
        <Result
          status="info"
          title={
            <FormattedMessage
              id="adapt.disabled"
              defaultMessage="Adapt sources are not enabled on this platform"
            />
          }
          subTitle={
            <FormattedMessage
              id="adapt.disabled_hint"
              defaultMessage="An operator turns them on with ADAPT_SOURCE_ENABLED=true and the adapt compose profile."
            />
          }
        />
      </PageContainer>
    );
  }

  return (
    <PageContainer
      content={
        <FormattedMessage
          id="adapt.intro"
          defaultMessage="Adapt Learning courses written as JSON. Every save adds a version; a build turns the current version into a SCORM package."
        />
      }
      extra={
        <Button type="primary" onClick={() => setCreating(true)}>
          <FormattedMessage id="adapt.new" defaultMessage="New Adapt source" />
        </Button>
      }
    >
      <Table<AdaptSource>
        rowKey="id"
        loading={loading}
        dataSource={sources}
        pagination={false}
        columns={[
          { title: 'ID', dataIndex: 'id', width: 80 },
          {
            title: intl.formatMessage({ id: 'title', defaultMessage: 'Title' }),
            dataIndex: 'title',
          },
          {
            title: intl.formatMessage({ id: 'status', defaultMessage: 'Status' }),
            dataIndex: 'status',
            render: (_, s) => (
              <Space direction="vertical" size={0}>
                <AdaptStatusTag status={s.status} />
                {s.status === 'failed' && s.last_error && (
                  <span style={{ color: '#cf1322', maxWidth: 360 }}>{s.last_error}</span>
                )}
              </Space>
            ),
          },
          {
            title: intl.formatMessage({ id: 'version', defaultMessage: 'Version' }),
            dataIndex: 'current_version',
            width: 100,
          },
          {
            title: intl.formatMessage({
              id: 'adapt.built_version',
              defaultMessage: 'Built version',
            }),
            dataIndex: 'built_version',
            width: 120,
            render: (v) => v ?? '–',
          },
          {
            title: 'SCORM',
            dataIndex: 'scorm_id',
            width: 100,
            render: (v) => (v ? <Link to="/courses/scorms">#{v}</Link> : '–'),
          },
          {
            title: '',
            key: 'actions',
            render: (_, s) => (
              <Space>
                <Button
                  size="small"
                  type="primary"
                  onClick={() => history.push(`/courses/adapt/${s.id}`)}
                >
                  <FormattedMessage id="edit" defaultMessage="Edit" />
                </Button>
                <Button size="small" disabled={isBuilding(s)} onClick={() => build(s.id)}>
                  <FormattedMessage id="adapt.build" defaultMessage="Build" />
                </Button>
                <Popconfirm
                  title={
                    <FormattedMessage
                      id="deleteQuestion"
                      defaultMessage="Are you sure to delete this record?"
                    />
                  }
                  onConfirm={() =>
                    deleteAdaptSource(s.id)
                      .then(() => load())
                      .catch((e) => message.error(errorText(e)))
                  }
                >
                  <Button size="small" danger>
                    <FormattedMessage id="delete" defaultMessage="Delete" />
                  </Button>
                </Popconfirm>
              </Space>
            ),
          },
        ]}
      />
      <Modal
        width={760}
        open={creating}
        onCancel={() => setCreating(false)}
        footer={null}
        title={<FormattedMessage id="adapt.new" defaultMessage="New Adapt source" />}
        destroyOnClose
      >
        <Form form={form} layout="vertical">
          <Form.Item name="title" label={<FormattedMessage id="title" defaultMessage="Title" />}>
            <Input
              placeholder={intl.formatMessage({
                id: 'adapt.title_from_course',
                defaultMessage: 'Taken from the course title when empty',
              })}
            />
          </Form.Item>
          <Tabs
            items={[
              {
                key: 'paste',
                label: intl.formatMessage({ id: 'adapt.paste', defaultMessage: 'Paste JSON' }),
                children: (
                  <>
                    <Alert
                      type="info"
                      showIcon
                      style={{ marginBottom: 12 }}
                      message={
                        <FormattedMessage
                          id="adapt.format"
                          defaultMessage="One object with course, config, contentObjects, articles, blocks and components."
                        />
                      }
                    />
                    <Form.Item name="json" rules={[{ required: true }]}>
                      <Input.TextArea
                        rows={14}
                        style={{ fontFamily: 'monospace' }}
                        spellCheck={false}
                      />
                    </Form.Item>
                    <Button type="primary" onClick={() => create('paste')}>
                      <FormattedMessage id="create" defaultMessage="Create" />
                    </Button>
                  </>
                ),
              },
              {
                key: 'file',
                label: intl.formatMessage({ id: 'adapt.upload', defaultMessage: 'Upload .json' }),
                children: (
                  <Space direction="vertical">
                    <Upload
                      accept=".json,application/json"
                      maxCount={1}
                      beforeUpload={(f) => {
                        setFile(f);
                        return false;
                      }}
                      onRemove={() => setFile(null)}
                    >
                      <Button icon={<UploadOutlined />}>
                        <FormattedMessage
                          id="adapt.choose_file"
                          defaultMessage="Choose a JSON file"
                        />
                      </Button>
                    </Upload>
                    <Button type="primary" disabled={!file} onClick={() => create('file')}>
                      <FormattedMessage id="create" defaultMessage="Create" />
                    </Button>
                  </Space>
                ),
              },
            ]}
          />
        </Form>
      </Modal>
    </PageContainer>
  );
};

export default AdaptList;
