import ProCard from '@ant-design/pro-card';
import { PageContainer } from '@ant-design/pro-layout';
import type { ActionType, ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import React, { useCallback, useRef, useState } from 'react';
import { FormattedMessage, Link, history, useIntl, useParams } from 'umi';

import Tags from '@/components/Tags';
import { DAY_FORMAT } from '@/consts/dates';
import { useShowNotification } from '@/hooks/useMessage';
import { deleteWebinar, generateYoutubeToken, webinars } from '@/services/escola-lms/webinars';
import { roundTo } from '@/utils/utils';
import {
  DeleteOutlined,
  DollarOutlined,
  EditOutlined,
  FireOutlined,
  PlusOutlined,
} from '@ant-design/icons';
import { Button, Popconfirm, Select, Tag, Typography, message } from 'antd';
import { format } from 'date-fns';
import EffectivenessAnalysis from '../Consultations/components/EffectivenessAnalysis';
import TokenForm from './components/TokenForm';

export const TableColumns: ProColumns<API.Webinar>[] = [
  {
    title: <FormattedMessage id="ID" />,
    dataIndex: 'id',
    hideInSearch: true,
    width: '80px',
  },
  {
    title: <FormattedMessage id="dateRange" defaultMessage="Date Range" />,
    dataIndex: 'dateRange',
    hideInSearch: false,
    hideInForm: true,
    hideInTable: true,
    valueType: 'dateRange',
    fieldProps: {
      allowEmpty: [true, true],
    },
  },
  {
    title: <FormattedMessage id="name" />,
    dataIndex: 'name',
    sorter: true,
  },
  {
    title: <FormattedMessage id="status" />,
    dataIndex: 'status',
    hideInSearch: true,
    sorter: true,
    renderFormItem: ({ type }) => {
      if (type === 'form') {
        return null;
      }
      return (
        <Select mode="multiple">
          <Select.Option value="draft">
            <Tag>
              <FormattedMessage id="draft" defaultMessage="draft" />
            </Tag>
          </Select.Option>
          <Select.Option value="archived">
            <Tag color="error">
              <FormattedMessage id="archived" defaultMessage="archived" />
            </Tag>
          </Select.Option>
          <Select.Option value="published">
            <Tag color="success">
              <FormattedMessage id="published" defaultMessage="published" />
            </Tag>
          </Select.Option>
        </Select>
      );
    },
    valueEnum: {
      draft: {
        text: (
          <Tag>
            <FormattedMessage id="draft" />
          </Tag>
        ),
      },
      archived: {
        text: (
          <Tag color="error">
            <FormattedMessage id="archived" />
          </Tag>
        ),
      },
      published: {
        text: (
          <Tag color="success">
            <FormattedMessage id="published" />
          </Tag>
        ),
      },
    },
  },
  {
    title: <FormattedMessage id="product" />,
    dataIndex: 'product',
    render: (_, record) =>
      record.product?.price ? (
        <Link to={`/courses/webinars/webinar/${record.id}/product`}>
          <Button type="primary" icon={<DollarOutlined />}>
            {roundTo(record.product.price, 2, 100)}
          </Button>
        </Link>
      ) : (
        <Typography>
          <FireOutlined /> <FormattedMessage id="no_pricing" />
        </Typography>
      ),
  },
  {
    title: <FormattedMessage id="duration" defaultMessage="duration" />,
    dataIndex: 'duration',
    hideInSearch: true,
    sorter: true,
  },
  {
    title: <FormattedMessage id="active_from" defaultMessage="active_from" />,
    dataIndex: 'active_from',
    hideInSearch: true,
    sorter: true,
    render: (_, record) => format(new Date(record.active_from), DAY_FORMAT),
  },
  {
    title: <FormattedMessage id="active_to" defaultMessage="active_to" />,
    dataIndex: 'active_to',
    hideInSearch: true,
    sorter: true,
    render: (_, record) => format(new Date(record.active_to), DAY_FORMAT),
  },
  {
    title: <FormattedMessage id="tags" defaultMessage="Tags" />,
    dataIndex: 'tag',
    key: 'tag',
    sorter: false,
    renderFormItem: (item, { type, defaultRender, ...rest }, form) => {
      if (type === 'form') {
        return null;
      }
      const stateType = form.getFieldValue('state');
      return (
        <Tags
          {...rest}
          state={{
            type: stateType,
          }}
          multiple={true}
        />
      );
    },
    render: (_, record) => (
      <React.Fragment>
        {record.tags?.map((tag) =>
          typeof tag === 'object' ? (
            <Tag key={tag.title}>{tag.title}</Tag>
          ) : (
            <Tag key={tag}>{tag}</Tag>
          ),
        )}
      </React.Fragment>
    ),
  },
];

const Webinars: React.FC = () => {
  const [generateToken, setGenarateToken] = useState(false);
  const actionRef = useRef<ActionType>();
  const [loading, setLoading] = useState(false);
  const intl = useIntl();
  const { showNotification } = useShowNotification();
  const { tab } = useParams<{ tab?: string }>();
  const activeTab = tab || 'list';

  const handleRemove = useCallback(
    async (id: number) => {
      setLoading(true);
      try {
        const response = await deleteWebinar(id);
        if (response.success) showNotification(response);
        actionRef.current?.reload();
      } catch (error) {
        message.error(<FormattedMessage id="error" />);
      } finally {
        setLoading(false);
      }
    },
    [showNotification],
  );

  return (
    <PageContainer>
      <ProCard
        tabs={{
          type: 'card',
          activeKey: activeTab,
          onChange: (key) => {
            history.push(`/courses/webinars/${key}`);
          },
        }}
      >
        <ProCard.TabPane key="list" tab={<FormattedMessage id="list" />}>
          <ProTable<API.Webinar, API.WebinarsParams>
            headerTitle={intl.formatMessage({ id: 'menu.Courses.Webinars' })}
            loading={loading}
            actionRef={actionRef}
            rowKey="id"
            search={{ layout: 'vertical' }}
            toolBarRender={() => [
              <Link key="addnew" to="/courses/webinars/webinar/new">
                <Button type="primary">
                  <PlusOutlined /> <FormattedMessage id="new" />
                </Button>
              </Link>,
            ]}
            request={async (params, sort) => {
              setLoading(true);
              const { name, status, dateRange, pageSize, current, tag } = params;
              const sortArr = sort ? Object.entries(sort)[0] : null;
              const date_from =
                dateRange && dateRange[0] ? format(new Date(dateRange[0]), DAY_FORMAT) : undefined;
              const date_to =
                dateRange && dateRange[1] ? format(new Date(dateRange[1]), DAY_FORMAT) : undefined;

              try {
                const response = await webinars({
                  name,
                  per_page: pageSize,
                  page: current,
                  date_from,
                  date_to,
                  status,
                  tags: tag,
                  order_by: sortArr?.[0],
                  order: sortArr ? (sortArr[1] === 'ascend' ? 'ASC' : 'DESC') : undefined,
                });

                if (response && response.success) {
                  return {
                    data: response.data,
                    total: response.meta.total,
                    success: true,
                  };
                }

                return { data: [], total: 0, success: false };
              } catch (error) {
                try {
                  if (error && typeof error === 'object' && 'response' in error) {
                    const response = (error as any).response;
                    const err = await response?.json();

                    console.error('API Error:', err);

                    if (err?.data?.code === 400 && err?.data?.message?.includes('Youtube')) {
                      message.error(err.data.message);
                      setGenarateToken(true);
                    } else {
                      message.error(
                        intl.formatMessage({
                          id: 'error.fetch',
                          defaultMessage: 'Failed to fetch webinars',
                        }),
                      );
                    }
                  } else {
                    message.error(intl.formatMessage({ id: 'error.fetch' }));
                  }
                } catch (parseError) {
                  console.error('Failed to parse error response', parseError);
                }

                return { data: [], total: 0, success: false };
              } finally {
                setLoading(false);
              }
            }}
            columns={[
              ...TableColumns,
              {
                title: <FormattedMessage id="options" />,
                valueType: 'option',
                width: '10%',
                render: (_, record) => [
                  <Link key="edit" to={`/courses/webinars/webinar/${record.id}`}>
                    <Button type="primary" icon={<EditOutlined />} />
                  </Link>,
                  <Popconfirm
                    key="delete"
                    title={<FormattedMessage id="deleteQuestion" />}
                    onConfirm={() => record.id && handleRemove(record.id)}
                  >
                    <Button type="primary" icon={<DeleteOutlined />} danger />
                  </Popconfirm>,
                ],
              },
            ]}
          />
        </ProCard.TabPane>

        <ProCard.TabPane
          key="effectiveness-analysis"
          tab={<FormattedMessage id="effectiveness_analysis" />}
        >
          <EffectivenessAnalysis modelType="webinar" />
        </ProCard.TabPane>
      </ProCard>

      <TokenForm
        visible={generateToken}
        onVisibleChange={(value) => {
          return value === false && setGenarateToken(false);
        }}
        onFinish={async (value) => {
          try {
            const request = await generateYoutubeToken({ email: value.email });
            if (request.url) {
              window.open(request.url, '_blank');
              setGenarateToken(false);
            }
          } catch (error) {
            console.error(error);
          }
        }}
      />
    </PageContainer>
  );
};

export default Webinars;
