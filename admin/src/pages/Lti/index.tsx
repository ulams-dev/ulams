import { PageContainer } from '@ant-design/pro-layout';
import {
  Alert,
  Button,
  Descriptions,
  Drawer,
  Form,
  Input,
  Popconfirm,
  Select,
  Space,
  Switch,
  Table,
  Tabs,
  Tag,
  Typography,
  message,
} from 'antd';
import React, { useCallback, useEffect, useState } from 'react';
import { FormattedMessage, useIntl } from 'umi';

import type { LtiEndpoints, LtiPlatform, LtiTool } from '@/services/ulams/lti';
import {
  deleteLtiPlatform,
  deleteLtiTool,
  ltiEndpoints,
  ltiPlatforms,
  ltiTools,
  saveLtiPlatform,
  saveLtiTool,
} from '@/services/ulams/lti';

const Copy: React.FC<{ value?: string | null }> = ({ value }) =>
  value ? (
    <Typography.Text copyable code>
      {value}
    </Typography.Text>
  ) : (
    <Typography.Text type="secondary">–</Typography.Text>
  );

const errorText = (error: any) =>
  error?.data?.message || error?.response?.data?.message || error?.message || String(error);

/** Customs as "key=value" lines in the form, an object in the API. */
const customToText = (custom?: Record<string, string> | null) =>
  Object.entries(custom ?? {})
    .map(([k, v]) => `${k}=${v}`)
    .join('\n');
const textToCustom = (text?: string) => {
  const out: Record<string, string> = {};
  (text ?? '')
    .split('\n')
    .map((line) => line.trim())
    .filter(Boolean)
    .forEach((line) => {
      const at = line.indexOf('=');
      if (at > 0) out[line.slice(0, at).trim()] = line.slice(at + 1).trim();
    });
  return Object.keys(out).length ? out : null;
};

const ToolsTab: React.FC<{ endpoints?: LtiEndpoints }> = ({ endpoints }) => {
  const intl = useIntl();
  const [tools, setTools] = useState<LtiTool[]>([]);
  const [loading, setLoading] = useState(false);
  const [editing, setEditing] = useState<Partial<LtiTool> | null>(null);
  const [form] = Form.useForm();

  const load = useCallback(() => {
    setLoading(true);
    ltiTools()
      .then((r) => setTools(r.data))
      .catch((e) => message.error(errorText(e)))
      .finally(() => setLoading(false));
  }, []);
  useEffect(load, [load]);

  const open = (tool: Partial<LtiTool>) => {
    setEditing(tool);
    form.resetFields();
    form.setFieldsValue({
      share_name: false,
      share_email: false,
      nrps_enabled: false,
      enabled: true,
      ...tool,
      custom: customToText(tool.custom),
      redirect_uris: (tool.redirect_uris ?? []).join('\n'),
    });
  };

  const save = async () => {
    const values = await form.validateFields();
    try {
      await saveLtiTool({
        ...values,
        id: editing?.id,
        custom: textToCustom(values.custom),
        redirect_uris: String(values.redirect_uris ?? '')
          .split('\n')
          .map((s: string) => s.trim())
          .filter(Boolean),
        public_key: values.public_key || null,
        jwks_url: values.jwks_url || null,
        deep_linking_url: values.deep_linking_url || null,
      });
      message.success(intl.formatMessage({ id: 'success', defaultMessage: 'Saved' }));
      setEditing(null);
      load();
    } catch (e) {
      message.error(errorText(e));
    }
  };

  return (
    <>
      <Space style={{ marginBottom: 16 }}>
        <Button type="primary" onClick={() => open({})}>
          <FormattedMessage id="lti.add_tool" defaultMessage="Add tool" />
        </Button>
      </Space>
      <Table<LtiTool>
        rowKey="id"
        loading={loading}
        dataSource={tools}
        pagination={false}
        columns={[
          { title: intl.formatMessage({ id: 'name', defaultMessage: 'Name' }), dataIndex: 'name' },
          { title: 'Client ID', dataIndex: 'client_id', render: (v) => <Copy value={v} /> },
          { title: 'Deployment ID', dataIndex: 'deployment_id', render: (v) => <Copy value={v} /> },
          {
            title: intl.formatMessage({ id: 'status', defaultMessage: 'Status' }),
            dataIndex: 'enabled',
            render: (v) =>
              v ? (
                <Tag color="green">
                  <FormattedMessage id="lti.enabled" defaultMessage="Enabled" />
                </Tag>
              ) : (
                <Tag>
                  <FormattedMessage id="lti.disabled" defaultMessage="Disabled" />
                </Tag>
              ),
          },
          {
            title: '',
            key: 'actions',
            render: (_, tool) => (
              <Space>
                <Button size="small" onClick={() => open(tool)}>
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
                    deleteLtiTool(tool.id)
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
      <Drawer
        width={560}
        open={!!editing}
        onClose={() => setEditing(null)}
        title={
          editing?.id ? (
            editing.name
          ) : (
            <FormattedMessage id="lti.add_tool" defaultMessage="Add tool" />
          )
        }
        extra={
          <Button type="primary" onClick={save}>
            <FormattedMessage id="save" defaultMessage="Save" />
          </Button>
        }
      >
        {endpoints && (
          <Alert
            style={{ marginBottom: 16 }}
            type="info"
            message={
              <FormattedMessage id="lti.give_tool" defaultMessage="Give the tool these values" />
            }
            description={
              <Descriptions size="small" column={1}>
                <Descriptions.Item label="Issuer">
                  <Copy value={endpoints.issuer} />
                </Descriptions.Item>
                <Descriptions.Item label="OIDC auth URL">
                  <Copy value={endpoints.platform.oidc_auth_url} />
                </Descriptions.Item>
                <Descriptions.Item label="Token URL">
                  <Copy value={endpoints.platform.token_url} />
                </Descriptions.Item>
                <Descriptions.Item label="JWKS URL">
                  <Copy value={endpoints.jwks_url} />
                </Descriptions.Item>
                {editing?.client_id && (
                  <Descriptions.Item label="Client ID">
                    <Copy value={editing.client_id} />
                  </Descriptions.Item>
                )}
                {editing?.deployment_id && (
                  <Descriptions.Item label="Deployment ID">
                    <Copy value={editing.deployment_id} />
                  </Descriptions.Item>
                )}
              </Descriptions>
            }
          />
        )}
        <Form form={form} layout="vertical">
          <Form.Item
            name="name"
            label={<FormattedMessage id="name" defaultMessage="Name" />}
            rules={[{ required: true }]}
          >
            <Input />
          </Form.Item>
          <Form.Item
            name="oidc_login_url"
            label="OIDC login URL"
            rules={[{ required: true, type: 'url' }]}
          >
            <Input />
          </Form.Item>
          <Form.Item name="launch_url" label="Launch URL" rules={[{ required: true, type: 'url' }]}>
            <Input />
          </Form.Item>
          <Form.Item name="deep_linking_url" label="Deep linking URL" rules={[{ type: 'url' }]}>
            <Input />
          </Form.Item>
          <Form.Item
            name="redirect_uris"
            label={
              <FormattedMessage
                id="lti.redirect_uris"
                defaultMessage="Other redirect URIs (one per line)"
              />
            }
          >
            <Input.TextArea rows={2} />
          </Form.Item>
          <Form.Item name="jwks_url" label="JWKS URL" rules={[{ type: 'url' }]}>
            <Input />
          </Form.Item>
          <Form.Item
            name="public_key"
            label={
              <FormattedMessage
                id="lti.public_key"
                defaultMessage="Public key (PEM), if the tool has no JWKS URL"
              />
            }
          >
            <Input.TextArea rows={3} placeholder="-----BEGIN PUBLIC KEY-----" />
          </Form.Item>
          <Form.Item
            name="custom"
            label={
              <FormattedMessage
                id="lti.custom"
                defaultMessage="Custom parameters (key=value per line)"
              />
            }
          >
            <Input.TextArea rows={2} />
          </Form.Item>
          <Space size="large">
            <Form.Item
              name="share_name"
              valuePropName="checked"
              label={<FormattedMessage id="lti.share_name" defaultMessage="Share names" />}
            >
              <Switch />
            </Form.Item>
            <Form.Item
              name="share_email"
              valuePropName="checked"
              label={<FormattedMessage id="lti.share_email" defaultMessage="Share e-mails" />}
            >
              <Switch />
            </Form.Item>
            <Form.Item
              name="nrps_enabled"
              valuePropName="checked"
              tooltip={
                <FormattedMessage
                  id="lti.nrps_enabled.tooltip"
                  defaultMessage="Lets the tool read the member list of courses it is linked in (Names and Role Provisioning). Names and e-mails follow the two switches on the left."
                />
              }
              label={<FormattedMessage id="lti.nrps_enabled" defaultMessage="Member list (NRPS)" />}
            >
              <Switch />
            </Form.Item>
            <Form.Item
              name="enabled"
              valuePropName="checked"
              label={<FormattedMessage id="lti.enabled" defaultMessage="Enabled" />}
            >
              <Switch />
            </Form.Item>
          </Space>
        </Form>
      </Drawer>
    </>
  );
};

const PlatformsTab: React.FC<{ endpoints?: LtiEndpoints }> = ({ endpoints }) => {
  const intl = useIntl();
  const [platforms, setPlatforms] = useState<LtiPlatform[]>([]);
  const [loading, setLoading] = useState(false);
  const [editing, setEditing] = useState<Partial<LtiPlatform> | null>(null);
  const [form] = Form.useForm();

  const load = useCallback(() => {
    setLoading(true);
    ltiPlatforms()
      .then((r) => setPlatforms(r.data))
      .catch((e) => message.error(errorText(e)))
      .finally(() => setLoading(false));
  }, []);
  useEffect(load, [load]);

  const open = (platform: Partial<LtiPlatform>) => {
    setEditing(platform);
    form.resetFields();
    form.setFieldsValue({ enabled: true, deployment_ids: [], ...platform });
  };

  const save = async () => {
    const values = await form.validateFields();
    try {
      await saveLtiPlatform({
        ...values,
        id: editing?.id,
        default_course_id: values.default_course_id || null,
      });
      message.success(intl.formatMessage({ id: 'success', defaultMessage: 'Saved' }));
      setEditing(null);
      load();
    } catch (e) {
      message.error(errorText(e));
    }
  };

  return (
    <>
      <Space style={{ marginBottom: 16 }}>
        <Button type="primary" onClick={() => open({})}>
          <FormattedMessage id="lti.add_platform" defaultMessage="Add platform" />
        </Button>
      </Space>
      <Table<LtiPlatform>
        rowKey="id"
        loading={loading}
        dataSource={platforms}
        pagination={false}
        columns={[
          { title: intl.formatMessage({ id: 'name', defaultMessage: 'Name' }), dataIndex: 'name' },
          { title: 'Issuer', dataIndex: 'issuer' },
          { title: 'Client ID', dataIndex: 'client_id' },
          {
            title: 'Deployments',
            dataIndex: 'deployment_ids',
            render: (v: string[]) => v?.join(', '),
          },
          {
            title: '',
            key: 'actions',
            render: (_, platform) => (
              <Space>
                <Button size="small" onClick={() => open(platform)}>
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
                    deleteLtiPlatform(platform.id)
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
      <Drawer
        width={560}
        open={!!editing}
        onClose={() => setEditing(null)}
        title={
          editing?.id ? (
            editing.name
          ) : (
            <FormattedMessage id="lti.add_platform" defaultMessage="Add platform" />
          )
        }
        extra={
          <Button type="primary" onClick={save}>
            <FormattedMessage id="save" defaultMessage="Save" />
          </Button>
        }
      >
        {endpoints && (
          <Alert
            style={{ marginBottom: 16 }}
            type="info"
            message={
              <FormattedMessage
                id="lti.give_platform"
                defaultMessage="Register ulams in the LMS with these values"
              />
            }
            description={
              <Descriptions size="small" column={1}>
                <Descriptions.Item label="Login URL">
                  <Copy value={endpoints.tool.oidc_login_url} />
                </Descriptions.Item>
                <Descriptions.Item label="Launch / redirect URL">
                  <Copy value={endpoints.tool.launch_url} />
                </Descriptions.Item>
                <Descriptions.Item label="Deep linking URL">
                  <Copy value={endpoints.tool.deep_linking_url} />
                </Descriptions.Item>
                <Descriptions.Item label="JWKS URL">
                  <Copy value={endpoints.jwks_url} />
                </Descriptions.Item>
              </Descriptions>
            }
          />
        )}
        <Form form={form} layout="vertical">
          <Form.Item
            name="name"
            label={<FormattedMessage id="name" defaultMessage="Name" />}
            rules={[{ required: true }]}
          >
            <Input />
          </Form.Item>
          <Form.Item name="issuer" label="Issuer (platform ID)" rules={[{ required: true }]}>
            <Input placeholder="https://moodle.school.example" />
          </Form.Item>
          <Form.Item name="client_id" label="Client ID" rules={[{ required: true }]}>
            <Input />
          </Form.Item>
          <Form.Item name="deployment_ids" label="Deployment IDs" rules={[{ required: true }]}>
            <Select mode="tags" tokenSeparators={[',', ' ']} />
          </Form.Item>
          <Form.Item
            name="auth_login_url"
            label="Authentication request URL"
            rules={[{ required: true, type: 'url' }]}
          >
            <Input />
          </Form.Item>
          <Form.Item
            name="auth_token_url"
            label="Access token URL"
            rules={[{ required: true, type: 'url' }]}
          >
            <Input />
          </Form.Item>
          <Form.Item
            name="jwks_url"
            label="Public keyset URL"
            rules={[{ required: true, type: 'url' }]}
          >
            <Input />
          </Form.Item>
          <Form.Item
            name="default_course_id"
            label={
              <FormattedMessage
                id="lti.default_course"
                defaultMessage="Default course ID (links without a course)"
              />
            }
          >
            <Input type="number" />
          </Form.Item>
          <Form.Item
            name="enabled"
            valuePropName="checked"
            label={<FormattedMessage id="lti.enabled" defaultMessage="Enabled" />}
          >
            <Switch />
          </Form.Item>
        </Form>
      </Drawer>
    </>
  );
};

/** Integrations → LTI: tools ulams launches, and platforms that launch ulams. */
const LtiPage: React.FC = () => {
  const [endpoints, setEndpoints] = useState<LtiEndpoints>();
  useEffect(() => {
    ltiEndpoints()
      .then((r) => setEndpoints(r.data))
      .catch(() => undefined);
  }, []);

  return (
    <PageContainer
      content={
        <FormattedMessage
          id="lti.intro"
          defaultMessage="LTI 1.3: add external tools to lessons (with grades and deep linking), and let other LMSs open ulams courses."
        />
      }
    >
      <Tabs
        items={[
          {
            key: 'tools',
            label: <FormattedMessage id="lti.tools" defaultMessage="Tools" />,
            children: <ToolsTab endpoints={endpoints} />,
          },
          {
            key: 'platforms',
            label: <FormattedMessage id="lti.platforms" defaultMessage="Platforms" />,
            children: <PlatformsTab endpoints={endpoints} />,
          },
        ]}
      />
    </PageContainer>
  );
};

export default LtiPage;
