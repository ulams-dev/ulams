import { PageContainer } from '@ant-design/pro-layout';
import { Alert, Button, Input, message, Popconfirm, Space, Table, Tag } from 'antd';
import React, { useCallback, useEffect, useState } from 'react';
import { FormattedMessage, history, useAccess, useIntl, useModel } from 'umi';

import type { InteractivePackage } from '@/services/ulams/interactive';
import {
  createInteractivePackage,
  deleteInteractivePackage,
  interactivePackages,
  isInteractiveEnabled,
} from '@/services/ulams/interactive';
import { errorText, UploadDialog } from './upload';

/** Interactive packages: uploaded web apps played in a sandbox (api/packages/interactive, ADR 0086). */
const InteractiveList: React.FC = () => {
  const intl = useIntl();
  useAccess();
  const { initialState } = useModel('@@initialState');
  const enabled = isInteractiveEnabled(initialState?.publicConfig);
  const [packages, setPackages] = useState<InteractivePackage[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(false);
  const [uploading, setUploading] = useState(false);

  const load = useCallback(() => {
    setLoading(true);
    interactivePackages({ current: page, pageSize: 25, search })
      .then((r) => {
        setPackages(r.data);
        setTotal(r.meta?.total ?? r.data.length);
      })
      .catch((e) => message.error(errorText(e)))
      .finally(() => setLoading(false));
  }, [page, search]);
  useEffect(load, [load]);

  return (
    <PageContainer
      content={
        <FormattedMessage
          id="interactive.intro"
          defaultMessage="Web apps you upload as a package (a .zip with a manifest): 3D scenes, maps, simulations. Each runs in a sandbox, has a text version for every step and can back many topics."
        />
      }
      extra={
        <Button type="primary" onClick={() => setUploading(true)}>
          <FormattedMessage id="interactive.new" defaultMessage="Upload package" />
        </Button>
      }
    >
      {!enabled && (
        <Alert
          type="warning"
          showIcon
          style={{ marginBottom: 16 }}
          message={
            <FormattedMessage
              id="interactive.disabled"
              defaultMessage="Interactive topics are switched off for this tenant (ulams_interactive.enabled). Learners see the text version."
            />
          }
        />
      )}
      <Input.Search
        allowClear
        style={{ maxWidth: 320, marginBottom: 16 }}
        placeholder={intl.formatMessage({ id: 'search', defaultMessage: 'Search' })}
        onSearch={(value) => {
          setPage(1);
          setSearch(value);
        }}
      />
      <Table<InteractivePackage>
        rowKey="id"
        loading={loading}
        dataSource={packages}
        pagination={{ current: page, total, pageSize: 25, onChange: setPage }}
        columns={[
          { title: 'ID', dataIndex: 'id', width: 80 },
          {
            title: intl.formatMessage({ id: 'title', defaultMessage: 'Title' }),
            dataIndex: 'title',
          },
          {
            title: intl.formatMessage({ id: 'interactive.versions', defaultMessage: 'Versions' }),
            dataIndex: 'versions_count',
            width: 100,
          },
          {
            title: intl.formatMessage({ id: 'interactive.licence', defaultMessage: 'Licence' }),
            dataIndex: 'licence',
            width: 160,
            render: (licence) => (licence ? <Tag>{licence}</Tag> : null),
          },
          {
            title: intl.formatMessage({ id: 'interactive.topics', defaultMessage: 'Topics' }),
            dataIndex: 'topics_count',
            width: 90,
          },
          {
            title: '',
            key: 'actions',
            render: (_, item) => (
              <Space>
                <Button
                  size="small"
                  type="primary"
                  onClick={() => history.push(`/courses/interactive/${item.id}`)}
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
                  disabled={item.topics_count > 0}
                  onConfirm={() =>
                    deleteInteractivePackage(item.id)
                      .then(load)
                      .catch((e) => message.error(errorText(e)))
                  }
                >
                  <Button size="small" danger disabled={item.topics_count > 0}>
                    <FormattedMessage id="delete" defaultMessage="Delete" />
                  </Button>
                </Popconfirm>
              </Space>
            ),
          },
        ]}
      />
      <UploadDialog
        open={uploading}
        withTitle
        title={<FormattedMessage id="interactive.new" defaultMessage="Upload package" />}
        onClose={() => setUploading(false)}
        submit={async (file, { title, note, acceptNetwork }) => {
          const response = await createInteractivePackage(file, title, note, acceptNetwork);
          history.push(`/courses/interactive/${response.data.id}`);
        }}
      />
    </PageContainer>
  );
};

export default InteractiveList;
