import { UploadOutlined } from '@ant-design/icons';
import { PageContainer } from '@ant-design/pro-layout';
import { Button, Form, Input, Modal, Popconfirm, Space, Table, Tabs, Upload, message } from 'antd';
import React, { useCallback, useEffect, useState } from 'react';
import { FormattedMessage, history, useIntl } from 'umi';

import type { LiaScriptDocument } from '@/services/ulams/liascript';
import { createLiaScript, deleteLiaScript, liascriptDocuments } from '@/services/ulams/liascript';

const STARTER = `<!--
author:
language: en
-->

# Course title

## First section

Write the lesson here.

    [( )] A wrong answer
    [(X)] The right answer
`;

const errorText = (error: any) =>
  error?.data?.message || error?.response?.data?.message || error?.message || String(error);

/** LiaScript sources: versioned Markdown courses (api/packages/liascript). */
const LiaScriptList: React.FC = () => {
  const intl = useIntl();
  const [documents, setDocuments] = useState<LiaScriptDocument[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [loading, setLoading] = useState(false);
  const [creating, setCreating] = useState(false);
  const [file, setFile] = useState<File | null>(null);
  const [form] = Form.useForm();

  const load = useCallback(() => {
    setLoading(true);
    liascriptDocuments({ current: page, pageSize: 25 })
      .then((r) => {
        setDocuments(r.data);
        setTotal(r.meta?.total ?? r.data.length);
      })
      .catch((e) => message.error(errorText(e)))
      .finally(() => setLoading(false));
  }, [page]);
  useEffect(load, [load]);

  const create = async (mode: 'markdown' | 'file') => {
    const values = await form.validateFields();
    try {
      const response = await createLiaScript(
        mode === 'file' && file
          ? { title: values.title, file }
          : { title: values.title, markdown: values.markdown },
      );
      response.data.warnings?.forEach((w) => message.warning(w));
      setCreating(false);
      history.push(`/courses/liascript/${response.data.id}`);
    } catch (e) {
      message.error(errorText(e));
    }
  };

  return (
    <PageContainer
      content={
        <FormattedMessage
          id="liascript.intro"
          defaultMessage="Courses written in LiaScript Markdown. Every save adds a version you can restore."
        />
      }
      extra={
        <Button type="primary" onClick={() => setCreating(true)}>
          <FormattedMessage id="liascript.new" defaultMessage="New LiaScript course" />
        </Button>
      }
    >
      <Table<LiaScriptDocument>
        rowKey="id"
        loading={loading}
        dataSource={documents}
        pagination={{ current: page, total, pageSize: 25, onChange: setPage }}
        columns={[
          { title: 'ID', dataIndex: 'id', width: 80 },
          {
            title: intl.formatMessage({ id: 'title', defaultMessage: 'Title' }),
            dataIndex: 'title',
          },
          {
            title: intl.formatMessage({ id: 'version', defaultMessage: 'Version' }),
            dataIndex: 'current_version',
            width: 100,
          },
          {
            title: '',
            key: 'actions',
            render: (_, doc) => (
              <Space>
                <Button
                  size="small"
                  type="primary"
                  onClick={() => history.push(`/courses/liascript/${doc.id}`)}
                >
                  <FormattedMessage id="edit" defaultMessage="Edit" />
                </Button>
                <Popconfirm
                  title={
                    <FormattedMessage
                      id="deleteQuestion"
                      defaultMessage="Are you sure to delete this record?"
                    />
                  }
                  onConfirm={() =>
                    deleteLiaScript(doc.id)
                      .then(load)
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
        title={<FormattedMessage id="liascript.new" defaultMessage="New LiaScript course" />}
        destroyOnClose
      >
        <Form form={form} layout="vertical" initialValues={{ markdown: STARTER }}>
          <Form.Item name="title" label={<FormattedMessage id="title" defaultMessage="Title" />}>
            <Input
              placeholder={intl.formatMessage({
                id: 'liascript.title_from_heading',
                defaultMessage: 'Taken from the first heading when empty',
              })}
            />
          </Form.Item>
          <Tabs
            items={[
              {
                key: 'markdown',
                label: 'Markdown',
                children: (
                  <>
                    <Form.Item name="markdown">
                      <Input.TextArea
                        rows={16}
                        style={{ fontFamily: 'monospace' }}
                        spellCheck={false}
                      />
                    </Form.Item>
                    <Button type="primary" onClick={() => create('markdown')}>
                      <FormattedMessage id="create" defaultMessage="Create" />
                    </Button>
                  </>
                ),
              },
              {
                key: 'file',
                label: intl.formatMessage({
                  id: 'liascript.upload',
                  defaultMessage: 'Upload .md or .zip',
                }),
                children: (
                  <Space direction="vertical">
                    <Upload
                      accept=".md,.markdown,.zip"
                      maxCount={1}
                      beforeUpload={(f) => {
                        setFile(f);
                        return false;
                      }}
                      onRemove={() => setFile(null)}
                    >
                      <Button icon={<UploadOutlined />}>
                        <FormattedMessage
                          id="liascript.choose_file"
                          defaultMessage="Choose a file (a .zip needs README.md at its root)"
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

export default LiaScriptList;
