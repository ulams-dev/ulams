import type { AgentAuditEntry, ApiToken } from '@/services/ulams/tokens';
import { createToken, listTokens, revokeToken, tokenAudit } from '@/services/ulams/tokens';
import { PlusOutlined } from '@ant-design/icons';
import { ModalForm, ProFormDigit, ProFormSelect, ProFormText } from '@ant-design/pro-form';
import { PageContainer } from '@ant-design/pro-layout';
import type { ActionType, ProColumns } from '@ant-design/pro-table';
import ProTable from '@ant-design/pro-table';
import { Alert, Button, Drawer, Input, Popconfirm, Tag, Typography, message } from 'antd';
import React, { useRef, useState } from 'react';
import { FormattedMessage, useIntl } from 'umi';

const PRESETS = ['@read-only', '@author', '@admin', '@learner', '@ci'];
const AREAS = [
  'courses',
  'users',
  'enrolments',
  'settings',
  'events',
  'certificates',
  'commerce',
  'reports',
  'lti',
  'builder',
  'living-course',
  'learner',
  'tokens',
];
const SCOPE_OPTIONS = [
  ...PRESETS.map((p) => ({ label: `${p} (preset)`, value: p })),
  { label: '* (everything)', value: '*' },
  ...AREAS.flatMap((a) => [
    { label: `${a}:read`, value: `${a}:read` },
    { label: `${a}:write`, value: `${a}:write` },
  ]),
];

const TokensPage: React.FC = () => {
  const actionRef = useRef<ActionType>();
  const intl = useIntl();
  const [createOpen, setCreateOpen] = useState(false);
  const [secret, setSecret] = useState<string | null>(null);
  const [auditFor, setAuditFor] = useState<ApiToken | null>(null);
  const [audit, setAudit] = useState<AgentAuditEntry[]>([]);

  const openAudit = async (token: ApiToken) => {
    setAuditFor(token);
    const res = await tokenAudit(token.id, { per_page: 100 });
    setAudit(res.success ? res.data : []);
  };

  const columns: ProColumns<ApiToken>[] = [
    {
      title: <FormattedMessage id="name" defaultMessage="name" />,
      dataIndex: 'name',
      search: false,
    },
    { title: 'User', dataIndex: 'user_id', valueType: 'digit', width: 80 },
    {
      title: 'Kind',
      dataIndex: 'kind',
      valueType: 'select',
      valueEnum: { cli: 'cli', agent: 'agent', ci: 'ci', integration: 'integration' },
      render: (_, r) => (
        <>
          <Tag>{r.kind}</Tag>
          {r.agent_name ? <Typography.Text type="secondary">{r.agent_name}</Typography.Text> : null}
        </>
      ),
    },
    {
      title: 'Scopes',
      dataIndex: 'scopes',
      search: false,
      render: (_, r) => r.scopes.map((s) => <Tag key={s}>{s}</Tag>),
    },
    { title: 'Created via', dataIndex: 'created_via', search: false, width: 100 },
    { title: 'Expires', dataIndex: 'expires_at', valueType: 'date', search: false },
    { title: 'Last used', dataIndex: 'last_used_at', valueType: 'dateTime', search: false },
    {
      title: 'Status',
      dataIndex: 'revoked',
      search: false,
      render: (_, r) =>
        r.revoked ? (
          <Tag color="red">revoked</Tag>
        ) : new Date(r.expires_at) < new Date() ? (
          <Tag>expired</Tag>
        ) : (
          <Tag color="green">active</Tag>
        ),
    },
    {
      title: <FormattedMessage id="pages.searchTable.titleOption" />,
      valueType: 'option',
      render: (_, r) => [
        <Button key="audit" onClick={() => openAudit(r)}>
          Audit
        </Button>,
        r.revoked ? null : (
          <Popconfirm
            key="revoke"
            title="Revoke this token? Clients using it stop working immediately."
            onConfirm={async () => {
              const res = await revokeToken(r.id);
              if (res.success) {
                message.success('Token revoked');
                actionRef.current?.reload();
              }
            }}
            okText={<FormattedMessage id="yes" />}
            cancelText={<FormattedMessage id="no" />}
          >
            <Button danger>Revoke</Button>
          </Popconfirm>
        ),
      ],
    },
  ];

  return (
    <PageContainer>
      <ProTable<ApiToken>
        headerTitle={intl.formatMessage({ id: 'apiTokens', defaultMessage: 'API tokens' })}
        actionRef={actionRef}
        rowKey="id"
        search={{ layout: 'vertical' }}
        toolBarRender={() => [
          <Button type="primary" key="new" onClick={() => setCreateOpen(true)}>
            <PlusOutlined /> <FormattedMessage id="new" defaultMessage="new" />
          </Button>,
        ]}
        request={async ({ pageSize, current, kind, user_id }) => {
          const res = await listTokens({
            per_page: pageSize,
            page: current,
            kind,
            user_id,
            include_revoked: true,
          });
          return { data: res.data ?? [], total: res.meta?.total ?? 0, success: !!res.success };
        }}
        columns={columns}
      />

      <ModalForm
        title="New API token (for your own account)"
        open={createOpen}
        onOpenChange={setCreateOpen}
        modalProps={{ destroyOnClose: true }}
        initialValues={{ expires_in_days: 90, kind: 'cli', scopes: ['@author'] }}
        onFinish={async (values) => {
          const res = await createToken({
            name: values.name,
            scopes: values.scopes,
            expires_in_days: values.expires_in_days,
            kind: values.kind,
          });
          if (res.success) {
            setSecret(res.data.token);
            actionRef.current?.reload();
            return true;
          }
          return false;
        }}
      >
        <ProFormText
          name="name"
          label="Name"
          rules={[{ required: true, max: 100 }]}
          placeholder="ulams-cli on my laptop"
        />
        <ProFormSelect
          name="scopes"
          label="Scopes"
          mode="multiple"
          options={SCOPE_OPTIONS}
          rules={[{ required: true }]}
          extra="Scopes only narrow what your own permissions allow. Prefer the narrowest set that works."
        />
        <ProFormSelect
          name="kind"
          label="Kind"
          options={['cli', 'agent', 'ci', 'integration'].map((k) => ({ label: k, value: k }))}
        />
        <ProFormDigit name="expires_in_days" label="Expires in (days)" min={1} max={365} />
      </ModalForm>

      <Drawer
        open={secret !== null}
        title="Copy your token now"
        width={560}
        onClose={() => setSecret(null)}
      >
        <Alert
          type="warning"
          showIcon
          message="This is the only time the token is shown. Store it in a secret manager; it cannot be recovered, only revoked."
        />
        <Input.TextArea
          readOnly
          rows={5}
          value={secret ?? ''}
          style={{ marginTop: 16, fontFamily: 'monospace' }}
          aria-label="API token"
        />
        <Button
          style={{ marginTop: 12 }}
          onClick={() => {
            navigator.clipboard?.writeText(secret ?? '');
            message.success('Copied');
          }}
        >
          Copy
        </Button>
      </Drawer>

      <Drawer
        open={auditFor !== null}
        title={`Audit: ${auditFor?.name ?? ''}`}
        width={720}
        onClose={() => setAuditFor(null)}
      >
        <ProTable<AgentAuditEntry>
          rowKey="id"
          search={false}
          options={false}
          pagination={false}
          dataSource={audit}
          columns={[
            { title: 'When', dataIndex: 'created_at', valueType: 'dateTime' },
            { title: 'Method', dataIndex: 'method', width: 80 },
            { title: 'Path', dataIndex: 'path', ellipsis: true },
            { title: 'Status', dataIndex: 'status', width: 70 },
            { title: 'Client', dataIndex: 'client', width: 70 },
            { title: 'IP', dataIndex: 'ip', width: 120 },
          ]}
        />
      </Drawer>
    </PageContainer>
  );
};

export default TokensPage;
