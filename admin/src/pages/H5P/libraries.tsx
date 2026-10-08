import SecureUpload from '@/components/SecureUpload';
import {
  H5P_LIBRARY_UPLOAD_URL,
  h5pContentTypeCacheStatus,
  h5pLibraries,
  libraryUbername,
  removeH5PLibrary,
  setH5PLibraryRestricted,
  updateH5PContentTypeCache,
} from '@/services/ulams/h5p';
import { CloudSyncOutlined, DeleteOutlined } from '@ant-design/icons';
import { PageContainer } from '@ant-design/pro-layout';
import type { ActionType, ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Popconfirm, Space, Switch, Tag, Tooltip, Typography, message } from 'antd';
import { format } from 'date-fns';
import React, { useCallback, useEffect, useRef, useState } from 'react';
import { FormattedMessage, useAccess, useIntl } from 'umi';

import { DATETIME_FORMAT } from '@/consts/dates';

type Item = API.H5PLibraryAdministrationItem & { ubername: string };

const errorMessage = (error: any) =>
  error?.response?.data?.message ?? error?.data?.message ?? error?.message;

/**
 * H5P library administration (Lumi routers of the H5P service):
 * GET/POST /h5p/libraries, PATCH/DELETE /h5p/libraries/:ubername,
 * GET/POST /h5p/content-type-cache/update.
 */
const H5PLibraries: React.FC = () => {
  const intl = useIntl();
  const access = useAccess();
  const actionRef = useRef<ActionType>();
  const [cacheUpdate, setCacheUpdate] = useState<string | null>();
  const [cacheLoading, setCacheLoading] = useState(false);

  useEffect(() => {
    h5pContentTypeCacheStatus()
      .then((r) => setCacheUpdate(r.lastUpdate))
      .catch(() => setCacheUpdate(undefined));
  }, []);

  const onUpdateCache = useCallback(async () => {
    setCacheLoading(true);
    try {
      const r = await updateH5PContentTypeCache();
      setCacheUpdate(r.lastUpdate);
      message.success(<FormattedMessage id="success" defaultMessage="success" />);
    } catch (error) {
      message.error(errorMessage(error) ?? intl.formatMessage({ id: 'error' }));
    } finally {
      setCacheLoading(false);
    }
  }, [intl]);

  const onRestrict = useCallback(async (item: Item, restricted: boolean) => {
    try {
      await setH5PLibraryRestricted(item.ubername, restricted);
      actionRef.current?.reload();
    } catch (error) {
      message.error(errorMessage(error) ?? intl.formatMessage({ id: 'error' }));
    }
  }, []);

  const onDelete = useCallback(async (item: Item) => {
    try {
      await removeH5PLibrary(item.ubername);
      message.success(<FormattedMessage id="success" defaultMessage="success" />);
      actionRef.current?.reload();
    } catch (error) {
      message.error(errorMessage(error) ?? intl.formatMessage({ id: 'error' }));
    }
  }, []);

  const columns: ProColumns<Item>[] = [
    {
      title: <FormattedMessage id="title" defaultMessage="title" />,
      dataIndex: 'title',
      sorter: (a, b) => a.title.localeCompare(b.title),
      render: (_, item) => (
        <Space>
          {item.title}
          {item.runnable && (
            <Tag color="blue">
              <FormattedMessage id="H5P_library_runnable" />
            </Tag>
          )}
          {item.isAddon && (
            <Tag>
              <FormattedMessage id="H5P_library_addon" />
            </Tag>
          )}
        </Space>
      ),
    },
    {
      title: <FormattedMessage id="H5P_library_machine_name" />,
      dataIndex: 'machineName',
      sorter: (a, b) => a.machineName.localeCompare(b.machineName),
    },
    {
      title: <FormattedMessage id="H5P_library_version" />,
      dataIndex: 'version',
      render: (_, item) => `${item.majorVersion}.${item.minorVersion}.${item.patchVersion}`,
    },
    {
      title: <FormattedMessage id="H5P_library_instances" />,
      dataIndex: 'instancesCount',
      sorter: (a, b) => a.instancesCount - b.instancesCount,
      render: (_, item) =>
        item.instancesAsDependencyCount
          ? `${item.instancesCount} (+${item.instancesAsDependencyCount})`
          : item.instancesCount,
    },
    {
      title: <FormattedMessage id="H5P_library_dependents" />,
      dataIndex: 'dependentsCount',
      sorter: (a, b) => a.dependentsCount - b.dependentsCount,
    },
    {
      title: <FormattedMessage id="H5P_library_restricted" />,
      dataIndex: 'restricted',
      render: (_, item) => (
        <Switch
          size="small"
          checked={item.restricted}
          disabled={!access.h5pLibraryUpdatePermission || !item.runnable}
          onChange={(checked) => onRestrict(item, checked)}
        />
      ),
    },
    {
      title: <FormattedMessage id="options" defaultMessage="options" />,
      dataIndex: 'option',
      valueType: 'option',
      hideInTable: !access.h5pLibraryDeletePermission,
      render: (_, item) => [
        <Popconfirm
          key="delete"
          disabled={!item.canBeDeleted}
          title={<FormattedMessage id="H5P_library_delete_question" />}
          onConfirm={() => onDelete(item)}
          okText={<FormattedMessage id="yes" defaultMessage="Yes" />}
          cancelText={<FormattedMessage id="no" defaultMessage="No" />}
        >
          <Tooltip title={<FormattedMessage id="delete" defaultMessage="delete" />}>
            <Button danger type="primary" icon={<DeleteOutlined />} disabled={!item.canBeDeleted} />
          </Tooltip>
        </Popconfirm>,
      ],
    },
  ];

  return (
    <PageContainer
      content={
        access.h5pLibraryUpdatePermission && (
          <Space direction="vertical" style={{ width: '100%' }}>
            <SecureUpload<{ installed: number; updated: number }>
              url={H5P_LIBRARY_UPLOAD_URL}
              name="file"
              accept=".h5p"
              title={intl.formatMessage({ id: 'H5P_library_upload' })}
              clearListAfterUpload
              onChange={(info) => {
                if (info.file.status === 'done') {
                  const result = info.file.response as unknown as {
                    installed: number;
                    updated: number;
                  };
                  message.success(
                    intl.formatMessage(
                      { id: 'H5P_library_uploaded' },
                      { installed: result?.installed ?? 0, updated: result?.updated ?? 0 },
                    ),
                  );
                  actionRef.current?.reload();
                }
                if (info.file.status === 'error') {
                  message.error(
                    errorMessage(info.file.error) ?? intl.formatMessage({ id: 'error' }),
                  );
                }
              }}
            />
            <Space>
              <Button icon={<CloudSyncOutlined />} loading={cacheLoading} onClick={onUpdateCache}>
                <FormattedMessage id="H5P_update_content_type_cache" />
              </Button>
              <Typography.Text type="secondary">
                <FormattedMessage id="H5P_content_type_cache_last_update" />:{' '}
                {cacheUpdate ? (
                  format(new Date(cacheUpdate), DATETIME_FORMAT)
                ) : (
                  <FormattedMessage id="H5P_content_type_cache_never" />
                )}
              </Typography.Text>
            </Space>
          </Space>
        )
      }
    >
      <ProTable<Item>
        headerTitle={intl.formatMessage({ id: 'menu.Courses.H5PLibraries' })}
        actionRef={actionRef}
        rowKey="ubername"
        search={false}
        pagination={{ defaultPageSize: 50 }}
        request={async () => {
          const libraries = await h5pLibraries();
          const data = (Array.isArray(libraries) ? libraries : []).map((lib) => ({
            ...lib,
            ubername: libraryUbername(lib),
          }));
          return { data, total: data.length, success: true };
        }}
        columns={columns}
      />
    </PageContainer>
  );
};

export default H5PLibraries;
