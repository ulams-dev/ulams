import { PageContainer } from '@ant-design/pro-layout';
import {
  Alert,
  Button,
  Col,
  Descriptions,
  Input,
  message,
  Row,
  Select,
  Space,
  Spin,
  Table,
  Tag,
  Typography,
} from 'antd';
import React, { useCallback, useEffect, useState } from 'react';
import { FormattedMessage, useIntl, useParams } from 'umi';

import type {
  InteractivePackage,
  InteractiveStep,
  InteractiveVersion,
} from '@/services/ulams/interactive';
import {
  addInteractiveVersion,
  formatBytes,
  interactivePackage,
  interactiveVersions,
  localised,
  previewInteractive,
  renameInteractivePackage,
} from '@/services/ulams/interactive';
import { errorText, UploadDialog } from './upload';

/**
 * The frame of the admin preview: scripts only, no allow-same-origin, like the learner's frame
 * (SANDBOX_INTERACTIVE in front/sdk/src/frames.ts). The admin cannot import the sdk constant.
 * The package runs in an opaque origin and nothing is tracked.
 */
const PREVIEW_SANDBOX = 'allow-scripts allow-popups allow-popups-to-escape-sandbox';

/** One interactive package: manifest, steps, versions, a preview and the upload of a new version. */
const InteractiveEditor: React.FC = () => {
  const intl = useIntl();
  const { id } = useParams<{ id: string }>();
  const packageId = Number(id);
  const [item, setItem] = useState<InteractivePackage | null>(null);
  const [versions, setVersions] = useState<InteractiveVersion[]>([]);
  const [title, setTitle] = useState('');
  const [uploading, setUploading] = useState(false);
  const [locale, setLocale] = useState('en');
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [previewVersion, setPreviewVersion] = useState<number | undefined>();
  const [loading, setLoading] = useState(true);

  const load = useCallback(() => {
    setLoading(true);
    Promise.all([interactivePackage(packageId), interactiveVersions(packageId)])
      .then(([p, v]) => {
        setItem(p.data);
        setTitle(p.data.title);
        setLocale(p.data.manifest?.defaultLocale ?? 'en');
        setVersions(v.data);
      })
      .catch((e) => message.error(errorText(e)))
      .finally(() => setLoading(false));
  }, [packageId]);
  useEffect(load, [load]);

  const preview = (version?: number) => {
    previewInteractive(packageId, version)
      .then((r) => {
        setPreviewVersion(r.data.version);
        setPreviewUrl(r.data.url);
      })
      .catch((e) => message.error(errorText(e)));
  };

  if (loading && !item) return <Spin />;
  if (!item) return null;
  const manifest = item.manifest;

  return (
    <PageContainer
      title={item.title}
      extra={
        <Button type="primary" onClick={() => setUploading(true)}>
          <FormattedMessage id="interactive.new_version" defaultMessage="Upload new version" />
        </Button>
      }
    >
      <Row gutter={[24, 24]}>
        <Col xs={24} lg={12}>
          <Space direction="vertical" style={{ width: '100%' }} size="large">
            <Space.Compact style={{ width: '100%' }}>
              <Input value={title} onChange={(e) => setTitle(e.target.value)} aria-label="Title" />
              <Button
                disabled={!title || title === item.title}
                onClick={() =>
                  renameInteractivePackage(packageId, title)
                    .then(load)
                    .catch((e) => message.error(errorText(e)))
                }
              >
                <FormattedMessage id="interactive.rename" defaultMessage="Rename" />
              </Button>
            </Space.Compact>

            {manifest && (
              <Descriptions bordered size="small" column={1}>
                <Descriptions.Item label="id">{manifest.id}</Descriptions.Item>
                <Descriptions.Item
                  label={intl.formatMessage({
                    id: 'interactive.licence',
                    defaultMessage: 'Licence',
                  })}
                >
                  <Tag>{manifest.licence}</Tag>
                </Descriptions.Item>
                <Descriptions.Item
                  label={intl.formatMessage({
                    id: 'interactive.current',
                    defaultMessage: 'Current version',
                  })}
                >
                  v{item.current_version} ({manifest.version})
                </Descriptions.Item>
                <Descriptions.Item
                  label={intl.formatMessage({
                    id: 'interactive.locales',
                    defaultMessage: 'Languages',
                  })}
                >
                  {manifest.locales.join(', ')}
                </Descriptions.Item>
                <Descriptions.Item
                  label={intl.formatMessage({
                    id: 'interactive.requires',
                    defaultMessage: 'Needs',
                  })}
                >
                  {(manifest.requires ?? []).join(', ') || '-'}
                </Descriptions.Item>
                {manifest.attribution && (
                  <Descriptions.Item
                    label={intl.formatMessage({
                      id: 'interactive.credits',
                      defaultMessage: 'Credits',
                    })}
                  >
                    {manifest.attribution}
                  </Descriptions.Item>
                )}
                {manifest.source?.url && (
                  <Descriptions.Item
                    label={intl.formatMessage({
                      id: 'interactive.source',
                      defaultMessage: 'Source',
                    })}
                  >
                    <a href={manifest.source.url} target="_blank" rel="noopener noreferrer">
                      {manifest.source.url}
                    </a>
                  </Descriptions.Item>
                )}
                <Descriptions.Item
                  label={intl.formatMessage({
                    id: 'interactive.a11y',
                    defaultMessage: 'Accessibility',
                  })}
                >
                  {[manifest.a11y?.keyboard, manifest.a11y?.notes].filter(Boolean).join(' ') || '-'}
                </Descriptions.Item>
              </Descriptions>
            )}

            {(item.network?.length ?? 0) > 0 && (
              <Alert
                type={item.network_allowed ? 'info' : 'warning'}
                showIcon
                message={
                  item.network_allowed ? (
                    <FormattedMessage
                      id="interactive.network_on"
                      defaultMessage="This package may call these origins:"
                    />
                  ) : (
                    <FormattedMessage
                      id="interactive.network_off"
                      defaultMessage="This package asks to call these origins, but the tenant setting ulams_interactive.allow_network is off, so it cannot:"
                    />
                  )
                }
                description={item.network?.map((o) => (
                  <div key={o}>
                    <code>{o}</code>
                  </div>
                ))}
              />
            )}

            <div>
              <Space style={{ marginBottom: 8 }}>
                <Typography.Title level={5} style={{ margin: 0 }}>
                  <FormattedMessage id="interactive.steps" defaultMessage="Steps" />
                </Typography.Title>
                {manifest && manifest.locales.length > 1 && (
                  <Select
                    size="small"
                    value={locale}
                    onChange={setLocale}
                    options={manifest.locales.map((l) => ({ value: l, label: l }))}
                    aria-label="Language"
                  />
                )}
              </Space>
              <Table<InteractiveStep>
                rowKey="id"
                size="small"
                pagination={false}
                dataSource={manifest?.steps ?? []}
                columns={[
                  { title: 'id', dataIndex: 'id', width: 160, render: (v) => <code>{v}</code> },
                  {
                    title: intl.formatMessage({ id: 'title', defaultMessage: 'Title' }),
                    key: 'title',
                    render: (_, s) => localised(s.title, locale, manifest?.defaultLocale ?? 'en'),
                  },
                  {
                    title: intl.formatMessage({
                      id: 'interactive.text_alt',
                      defaultMessage: 'Text alternative',
                    }),
                    key: 'text',
                    render: (_, s) => localised(s.text, locale, manifest?.defaultLocale ?? 'en'),
                  },
                  {
                    title: intl.formatMessage({
                      id: 'interactive.poster',
                      defaultMessage: 'Poster',
                    }),
                    key: 'poster',
                    width: 140,
                    render: (_, s) => (s.poster ? <code>{s.poster}</code> : '-'),
                  },
                ]}
              />
            </div>

            <div>
              <Typography.Title level={5}>
                <FormattedMessage id="interactive.versions" defaultMessage="Versions" />
              </Typography.Title>
              <Table<InteractiveVersion>
                rowKey="version"
                size="small"
                pagination={false}
                dataSource={[...versions].reverse()}
                columns={[
                  { title: 'v', dataIndex: 'version', width: 50 },
                  {
                    title: intl.formatMessage({
                      id: 'interactive.manifest_version',
                      defaultMessage: 'Manifest',
                    }),
                    dataIndex: 'manifest_version',
                    width: 90,
                  },
                  {
                    title: intl.formatMessage({
                      id: 'interactive.change_note',
                      defaultMessage: 'Change note',
                    }),
                    dataIndex: 'change_note',
                  },
                  {
                    title: intl.formatMessage({ id: 'interactive.size', defaultMessage: 'Size' }),
                    dataIndex: 'total_bytes',
                    width: 90,
                    render: formatBytes,
                  },
                  {
                    title: '',
                    key: 'preview',
                    width: 90,
                    render: (_, v) => (
                      <Button size="small" onClick={() => preview(v.version)}>
                        <FormattedMessage id="interactive.preview" defaultMessage="Preview" />
                      </Button>
                    ),
                  },
                ]}
              />
            </div>
          </Space>
        </Col>
        <Col xs={24} lg={12}>
          {previewUrl ? (
            <Space direction="vertical" style={{ width: '100%' }}>
              <Typography.Text type="secondary">
                <FormattedMessage
                  id="interactive.preview_note"
                  defaultMessage="Preview of version {version}. Nothing is tracked, and the package runs in a sandbox without access to this panel."
                  values={{ version: previewVersion }}
                />
              </Typography.Text>
              <iframe
                title={item.title}
                src={previewUrl}
                sandbox={PREVIEW_SANDBOX}
                referrerPolicy="no-referrer"
                style={{ width: '100%', height: 560, border: '1px solid #d9d9d9', borderRadius: 8 }}
              />
            </Space>
          ) : (
            <Button onClick={() => preview()}>
              <FormattedMessage
                id="interactive.preview_current"
                defaultMessage="Preview the current version"
              />
            </Button>
          )}
        </Col>
      </Row>
      <UploadDialog
        open={uploading}
        title={
          <FormattedMessage id="interactive.new_version" defaultMessage="Upload new version" />
        }
        onClose={() => setUploading(false)}
        submit={async (file, { note, acceptNetwork }) => {
          await addInteractiveVersion(packageId, file, note, acceptNetwork);
          load();
        }}
      />
    </PageContainer>
  );
};

export default InteractiveEditor;
