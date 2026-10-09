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
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { FormattedMessage, useIntl, useParams } from 'umi';

import type { LiaScriptDocument, LiaScriptVersion } from '@/services/ulams/liascript';
import {
  addLiaScriptVersion,
  liascriptDocument,
  liascriptSource,
  liascriptVersions,
  previewLiaScript,
  renameLiaScript,
  restoreLiaScriptVersion,
} from '@/services/ulams/liascript';

/** Pause in typing after which an open preview refreshes. */
const PREVIEW_DELAY_MS = 1500;

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
 * LiaScript editor: Markdown source with a live preview of the unsaved text (played from the
 * tenant content origin, refreshed when typing pauses), save as a new version with a note,
 * version history with the changes of each version and restore. Learners see the saved course in
 * lessons of type LiaScript.
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
  const [previewOpen, setPreviewOpen] = useState(false);
  const [preview, setPreview] = useState<{ url: string; warnings: string[] }>();
  const [previewError, setPreviewError] = useState<string>();
  const [previewing, setPreviewing] = useState(false);
  const previewed = useRef<string>();

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

  const refreshPreview = useCallback(
    async (text: string) => {
      if (!text.trim()) return;
      setPreviewing(true);
      try {
        const response = await previewLiaScript(id, text);
        previewed.current = text;
        setPreview({ url: response.data.url, warnings: response.data.warnings ?? [] });
        setPreviewError(undefined);
      } catch (e) {
        setPreviewError(errorText(e));
      } finally {
        setPreviewing(false);
      }
    },
    [id],
  );

  // live preview: refresh when typing pauses
  useEffect(() => {
    if (!previewOpen || previewed.current === source) return undefined;
    const timer = window.setTimeout(() => refreshPreview(source), PREVIEW_DELAY_MS);
    return () => window.clearTimeout(timer);
  }, [previewOpen, source, refreshPreview]);

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
            <Button
              onClick={() => {
                const next = !previewOpen;
                setPreviewOpen(next);
                if (next) refreshPreview(source);
              }}
              aria-expanded={previewOpen}
              aria-controls="liascript-preview"
            >
              {previewOpen ? (
                <FormattedMessage id="liascript.preview_close" defaultMessage="Close preview" />
              ) : (
                <FormattedMessage id="liascript.preview_open" defaultMessage="Preview" />
              )}
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
              defaultMessage="The preview shows the text as typed, with the current version's files, and records no progress. Learners see the saved version in lessons of type LiaScript."
            />
          </Typography.Paragraph>
          {previewOpen && (
            <section id="liascript-preview" aria-live="polite" aria-busy={previewing}>
              {previewError && (
                <Alert type="error" showIcon message={previewError} style={{ marginBottom: 8 }} />
              )}
              {(preview?.warnings ?? []).map((w) => (
                <Alert key={w} type="warning" showIcon message={w} style={{ marginBottom: 8 }} />
              ))}
              {preview ? (
                <iframe
                  key={preview.url}
                  src={preview.url}
                  title={intl.formatMessage({
                    id: 'liascript.preview_title',
                    defaultMessage: 'Preview of the unsaved course text',
                  })}
                  style={{ width: '100%', height: 640, border: '1px solid #d9d9d9' }}
                  allow="fullscreen; autoplay; clipboard-write"
                  sandbox="allow-scripts allow-same-origin allow-popups allow-forms"
                />
              ) : (
                <Spin />
              )}
            </section>
          )}
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
