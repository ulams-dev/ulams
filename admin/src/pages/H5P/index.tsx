import AuthenticatedLinkButton from '@/components/AuthenticatedLinkButton';
import UploadH5P from '@/components/H5P/upload';
import { h5p, h5pDownloadUrl, removeH5P, removeUnusedH5P } from '@/services/ulams/h5p';
import { createTableOrderObject } from '@/utils/utils';
import {
  AppstoreOutlined,
  BookOutlined,
  ClearOutlined,
  DeleteOutlined,
  EditOutlined,
  ExportOutlined,
  PlusOutlined,
} from '@ant-design/icons';
import { PageContainer } from '@ant-design/pro-layout';
import type { ActionType, ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Button, Popconfirm, Tag, Tooltip, message } from 'antd';
import React, { useCallback, useRef, useState } from 'react';
import { FormattedMessage, Link, useAccess, useIntl } from 'umi';

const slug = (text: string) =>
  text
    .split(' ')
    .join('-')
    .replace(/[^a-zA-Z0-9-_]/g, '')
    .toLocaleLowerCase() || 'h5p';

const TableList: React.FC = () => {
  const [loading, setLoading] = useState<boolean>(false);
  const actionRef = useRef<ActionType>();
  const intl = useIntl();
  const access = useAccess();

  const handleRemove = useCallback(
    async (id: number | string) => {
      setLoading(true);
      const hide = message.loading(<FormattedMessage id="loading" defaultMessage="loading" />);
      try {
        await removeH5P(id);
        hide();
        message.success(<FormattedMessage id="success" defaultMessage="success" />);
        actionRef.current?.reload();
        return true;
      } catch (error) {
        hide();
        message.error(<FormattedMessage id="error" defaultMessage="error" />);
        return false;
      } finally {
        setLoading(false);
      }
    },
    [actionRef],
  );

  const handleRemoveUnused = useCallback(async () => {
    setLoading(true);
    try {
      const response = await removeUnusedH5P();
      if (response.success) {
        message.success(response.message || intl.formatMessage({ id: 'success' }));
      }
      actionRef.current?.reload();
    } catch (error) {
      message.error(<FormattedMessage id="error" defaultMessage="error" />);
    } finally {
      setLoading(false);
    }
  }, [actionRef, intl]);

  const columns: ProColumns<API.H5PContentListItem>[] = [
    {
      title: <FormattedMessage id="ID" defaultMessage="ID" />,
      dataIndex: 'id',
      sorter: true,
      search: false,
      width: '80px',
    },
    {
      title: <FormattedMessage id="newH5P" defaultMessage="newH5P" />,
      dataIndex: 'upload',
      hideInSearch: false,
      hideInTable: true,
      renderFormItem: () => [
        <UploadH5P
          key={'upload'}
          hideLabel
          onSuccess={() => {
            actionRef.current?.reload();
            message.success(
              <FormattedMessage id="H5P_uploaded" defaultMessage="new H5P uploaded successfully" />,
            );
          }}
          onError={(errorMessage) =>
            message.error(errorMessage || <FormattedMessage id="error" defaultMessage="error" />)
          }
        />,
      ],
    },
    {
      title: <FormattedMessage id="title" defaultMessage="title" />,
      dataIndex: 'title',
      sorter: true,
    },
    {
      title: <FormattedMessage id="library" defaultMessage="library" />,
      dataIndex: 'library',
      sorter: true,
      search: false,
      render: (_, entity) => <Tag>{entity.library || entity.main_library}</Tag>,
    },
    {
      title: <FormattedMessage id="count_h5p" defaultMessage="count_h5p" />,
      dataIndex: 'count_h5p',
      search: false,
    },
    {
      title: <FormattedMessage id="updated_at" defaultMessage="updated_at" />,
      dataIndex: 'updated_at',
      valueType: 'dateTime',
      sorter: true,
      search: false,
    },
    {
      title: <FormattedMessage id="options" defaultMessage="options" />,
      dataIndex: 'option',
      valueType: 'option',
      render: (_, record) => [
        <Link key={'edit'} to={`/courses/h5ps/${record.id}`}>
          <Tooltip title={<FormattedMessage id="edit" defaultMessage="edit" />}>
            <Button type="primary" icon={<EditOutlined />} />
          </Tooltip>
        </Link>,

        <Popconfirm
          key={'delete'}
          disabled={record.count_h5p !== 0}
          title={
            <FormattedMessage
              id="deleteQuestion"
              defaultMessage="Are you sure to delete this record?"
            />
          }
          onConfirm={() => record.id && handleRemove(record.id)}
          okText={<FormattedMessage id="yes" defaultMessage="Yes" />}
          cancelText={<FormattedMessage id="no" defaultMessage="No" />}
        >
          <Tooltip title={<FormattedMessage id="delete" defaultMessage="delete" />}>
            <Button
              disabled={record.count_h5p !== 0}
              type="primary"
              icon={<DeleteOutlined />}
              danger
            />
          </Tooltip>
        </Popconfirm>,
        <Link key={'preview'} to={`/courses/h5ps/preview/${record.id}`}>
          <Tooltip title={<FormattedMessage id="preview" defaultMessage="preview" />}>
            <Button icon={<BookOutlined />} />
          </Tooltip>
        </Link>,
        <Tooltip title={<FormattedMessage id="export" defaultMessage="export" />} key={'export'}>
          <AuthenticatedLinkButton
            url={h5pDownloadUrl(record.id)}
            filename={`${slug(record.title)}-${record.id}.h5p`}
            icon={<ExportOutlined />}
          />
        </Tooltip>,
      ],
    },
  ];

  return (
    <PageContainer>
      <ProTable<API.H5PContentListItem, API.H5PListParams>
        loading={loading}
        headerTitle={intl.formatMessage({
          id: 'menu.Courses.H5Ps',
          defaultMessage: 'H5Ps List',
        })}
        actionRef={actionRef}
        rowKey="id"
        search={{
          layout: 'vertical',
        }}
        toolBarRender={() => [
          access.h5pLibraryListPermission && (
            <Link to="/courses/h5ps/libraries" key={'libraries'}>
              <Button icon={<AppstoreOutlined />}>
                <FormattedMessage id="H5P_libraries" defaultMessage="Libraries" />
              </Button>
            </Link>
          ),
          <Popconfirm
            key={'unused'}
            title={
              <FormattedMessage
                id="H5P_remove_unused_question"
                defaultMessage="Delete every H5P content that no topic uses?"
              />
            }
            onConfirm={handleRemoveUnused}
            okText={<FormattedMessage id="yes" defaultMessage="Yes" />}
            cancelText={<FormattedMessage id="no" defaultMessage="No" />}
          >
            <Button danger icon={<ClearOutlined />}>
              <FormattedMessage id="H5P_remove_unused" defaultMessage="Remove unused" />
            </Button>
          </Popconfirm>,
          <Link to="/courses/h5ps/new" key={'new'}>
            <Button type="primary" key="primary">
              <PlusOutlined /> <FormattedMessage id="new" defaultMessage="new" />
            </Button>
          </Link>,
        ]}
        request={({ pageSize, current, title }, sort) => {
          setLoading(true);

          return h5p({
            title,
            per_page: pageSize,
            page: current,
            ...createTableOrderObject(sort),
          })
            .then((response) => {
              if (response.success) {
                return {
                  data: response.data,
                  total: response.meta.total,
                  success: true,
                };
              }
              return { data: [], total: 0, success: false };
            })
            .finally(() => setLoading(false));
        }}
        columns={columns}
      />
    </PageContainer>
  );
};

export default TableList;
