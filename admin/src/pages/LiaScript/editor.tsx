import { PageContainer } from '@ant-design/pro-layout';
import {
  Alert,
  Button,
  Col,
  Input,
  List,
  Popconfirm,
  Row,
  Space,
  Spin,
  Tag,
  Typography,
  message,
} from 'antd';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { FormattedMessage, useIntl, useParams } from 'umi';

import type { LiaScriptDocument, LiaScriptVersion } from '@/services/ulams/liascript';
import {
  addLiaScriptVersion,
  liascriptDocument,
  liascriptSource,
  liascriptVersions,
  renameLiaScript,
  restoreLiaScriptVersion,
} from '@/services/ulams/liascript';

const errorText = (error: any) =>
  error?.data?.message ||
  Object.values(error?.data?.errors ?? {})?.flat?.()?.[0] ||
  error?.message ||
  String(error);

/** Line-based diff summary between two versions: added and removed lines. */
const diffLines = (before: string, after: string) => {
  const a = before.split('\n');
  const b = after.split('\n');
  const inA = new Map<string, number>();
  a.forEach((l) => inA.set(l, (inA.get(l) ?? 0) + 1));
  const added: string[] = [];
  b.forEach((l) => {
    const n = inA.get(l) ?? 0;
    if (n > 0) inA.set(l, n - 1);
    else added.push(l);
  });
  const removed: string[] = [];
  inA.forEach((n, l) => {
    for (let i = 0; i < n; i++) removed.push(l);
  });
  return { added: added.filter((l) => l.trim()), removed: removed.filter((l) => l.trim()) };
};

/**
 * LiaScript editor: Markdown source, save as a new version with a note, version history with the
 * changes of each version and restore. Learners see the course in lessons of type LiaScript.
 */
const LiaScriptEditor: React.FC = () => {
  const intl = useIntl();
  const params = useParams<{ id: string }>();
  const id = Number(params.id);
  const [document, setDocument] = useState<LiaScriptDocument>();
  const [versions, setVersions] = useState<LiaScriptVersion[]>([]);
  const [source, setSource] = useState('');
  const [saved, setSaved] = useState('');
  const [note, setNote] = useState('');
  const [title, setTitle] = useState('');
  const [selected, setSelected] = useState<{ version: number; text: string }>();
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      const [doc, list, text] = await Promise.all([
        liascriptDocument(id),
        liascriptVersions(id),
        liascriptSource(id),
      ]);
      setDocument(doc.data);
      setTitle(doc.data.title);
      setVersions([...list.data].reverse());
      setSource(String(text));
      setSaved(String(text));
    } catch (e) {
      message.error(errorText(e));
    }
  }, [id]);
  useEffect(() => {
    load();
  }, [load]);

  const dirty = source !== saved;
  useEffect(() => {
    const warn = (event: BeforeUnloadEvent) => {
      if (dirty) event.preventDefault();
    };
    window.addEventListener('beforeunload', warn);
    return () => window.removeEventListener('beforeunload', warn);
  }, [dirty]);

  const save = async () => {
    setBusy(true);
    try {
      const response = await addLiaScriptVersion(id, source, note);
      response.data.warnings?.forEach((w) => message.warning(w));
      message.success(
        intl.formatMessage(
          { id: 'liascript.saved', defaultMessage: 'Saved as version {version}' },
          { version: response.data.current_version },
        ),
      );
      setNote('');
      await load();
    } catch (e) {
      message.error(errorText(e));
    } finally {
      setBusy(false);
    }
  };

  const showVersion = async (version: number) => {
    try {
      setSelected({ version, text: String(await liascriptSource(id, version)) });
    } catch (e) {
      message.error(errorText(e));
    }
  };

  const restore = async (version: number) => {
    setBusy(true);
    try {
      await restoreLiaScriptVersion(id, version);
      setSelected(undefined);
      await load();
    } catch (e) {
      message.error(errorText(e));
    } finally {
      setBusy(false);
    }
  };

  const changes = useMemo(
    () => (selected ? diffLines(selected.text, saved) : null),
    [selected, saved],
  );

  if (!document) {
    return <Spin />;
  }

  return (
    <PageContainer
      title={
        <Typography.Text
          editable={{
            onChange: (value) => {
              if (value && value !== title) {
                renameLiaScript(id, value)
                  .then(() => setTitle(value))
                  .catch((e) => message.error(errorText(e)));
              }
            },
          }}
        >
          {title}
        </Typography.Text>
      }
      subTitle={
        <Tag>
          <FormattedMessage id="version" defaultMessage="Version" /> {document.current_version}
        </Tag>
      }
    >
      {(document.warnings ?? []).map((w) => (
        <Alert key={w} type="warning" showIcon message={w} style={{ marginBottom: 12 }} />
      ))}
      <Row gutter={24}>
        <Col xs={24} lg={16}>
          <label htmlFor="liascript-source" className="ant-typography">
            <FormattedMessage id="liascript.source" defaultMessage="Markdown source" />
          </label>
          <Input.TextArea
            id="liascript-source"
            value={source}
            onChange={(e) => setSource(e.target.value)}
            autoSize={{ minRows: 24, maxRows: 48 }}
            style={{ fontFamily: 'monospace', marginTop: 8 }}
            spellCheck={false}
          />
          <Space style={{ marginTop: 12 }} wrap>
            <Input
              style={{ width: 320 }}
              value={note}
              maxLength={500}
              onChange={(e) => setNote(e.target.value)}
              placeholder={intl.formatMessage({
                id: 'liascript.change_note',
                defaultMessage: 'What changed (optional)',
              })}
              aria-label={intl.formatMessage({
                id: 'liascript.change_note',
                defaultMessage: 'What changed (optional)',
              })}
            />
            <Button type="primary" onClick={save} disabled={!dirty} loading={busy}>
              <FormattedMessage id="liascript.save_version" defaultMessage="Save as new version" />
            </Button>
            {dirty && (
              <Typography.Text type="warning">
                <FormattedMessage id="liascript.unsaved" defaultMessage="Unsaved changes" />
              </Typography.Text>
            )}
          </Space>
          <Typography.Paragraph type="secondary" style={{ marginTop: 12 }}>
            <FormattedMessage
              id="liascript.preview_hint"
              defaultMessage="Learners see the saved version in lessons of type LiaScript; open such a lesson in the course preview to check it."
            />
          </Typography.Paragraph>
        </Col>
        <Col xs={24} lg={8}>
          <Typography.Title level={5}>
            <FormattedMessage id="liascript.versions" defaultMessage="Versions" />
          </Typography.Title>
          <List<LiaScriptVersion>
            size="small"
            bordered
            dataSource={versions}
            renderItem={(v) => (
              <List.Item
                actions={[
                  <Button key="show" size="small" onClick={() => showVersion(v.version)}>
                    <FormattedMessage id="show" defaultMessage="Show" />
                  </Button>,
                  v.version !== document.current_version ? (
                    <Popconfirm
                      key="restore"
                      title={
                        <FormattedMessage
                          id="liascript.restore_question"
                          defaultMessage="Restore this version as a new version?"
                        />
                      }
                      onConfirm={() => restore(v.version)}
                    >
                      <Button size="small" loading={busy}>
                        <FormattedMessage id="restore" defaultMessage="Restore" />
                      </Button>
                    </Popconfirm>
                  ) : (
                    <Tag key="current" color="green">
                      <FormattedMessage id="current" defaultMessage="current" />
                    </Tag>
                  ),
                ]}
              >
                <List.Item.Meta
                  title={`v${v.version}`}
                  description={
                    v.restored_from
                      ? intl.formatMessage(
                          { id: 'liascript.restored_from', defaultMessage: 'Restored from v{v}' },
                          { v: v.restored_from },
                        )
                      : v.change_note ||
                        (v.created_at ? new Date(v.created_at).toLocaleString() : '')
                  }
                />
              </List.Item>
            )}
          />
          {selected && changes && (
            <div style={{ marginTop: 16 }}>
              <Typography.Title level={5}>
                <FormattedMessage
                  id="liascript.compare"
                  defaultMessage="v{version} compared with the current version"
                  values={{ version: selected.version }}
                />
              </Typography.Title>
              {changes.added.length === 0 && changes.removed.length === 0 ? (
                <Typography.Text type="secondary">
                  <FormattedMessage id="liascript.same" defaultMessage="Same text" />
                </Typography.Text>
              ) : (
                <pre style={{ maxHeight: 320, overflow: 'auto', fontSize: 12 }}>
                  {changes.removed.map((l, i) => (
                    <div key={`r${i}`} style={{ background: '#fdecea' }}>
                      - {l}
                    </div>
                  ))}
                  {changes.added.map((l, i) => (
                    <div key={`a${i}`} style={{ background: '#e6f4ea' }}>
                      + {l}
                    </div>
                  ))}
                </pre>
              )}
            </div>
          )}
        </Col>
      </Row>
    </PageContainer>
  );
};

export default LiaScriptEditor;
