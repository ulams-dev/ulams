import { PageContainer } from '@ant-design/pro-layout';
import { Alert, Button, Col, Input, List, Result, Row, Space, Spin, Tag, message } from 'antd';
import React, { useCallback, useEffect, useState } from 'react';
import { FormattedMessage, Link, useIntl, useParams } from 'umi';

import type { AdaptSource, AdaptVersion } from '@/services/ulams/adapt';
import {
  ADAPT_POLL_MS,
  adaptSource,
  adaptSourceJson,
  adaptVersions,
  addAdaptVersion,
  buildAdaptSource,
  isAdaptDisabled,
  isBuilding,
} from '@/services/ulams/adapt';
import { AdaptStatusTag, errorText } from './status';

const pretty = (value: unknown) => JSON.stringify(value, null, 2);

/**
 * Adapt source editor: the JSON of the current version, save as a new version with a note,
 * version history (open an older version as a starting point), Build (queued, polled every 3 s)
 * and a link to the SCORM package that was built.
 */
const AdaptEditor: React.FC = () => {
  const intl = useIntl();
  const id = Number(useParams<{ id: string }>().id);
  const [source, setSource] = useState<AdaptSource>();
  const [versions, setVersions] = useState<AdaptVersion[]>([]);
  const [text, setText] = useState('');
  const [saved, setSaved] = useState('');
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);
  const [disabled, setDisabled] = useState(false);
  const [problems, setProblems] = useState<string>();

  const load = useCallback(async () => {
    try {
      const [doc, list, json] = await Promise.all([
        adaptSource(id),
        adaptVersions(id),
        adaptSourceJson(id),
      ]);
      setSource(doc.data);
      setVersions(list.data);
      setText(pretty(json));
      setSaved(pretty(json));
    } catch (e) {
      if (isAdaptDisabled(e) && !source) setDisabled(true);
      message.error(errorText(e));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);
  useEffect(() => {
    load();
  }, [load]);

  const building = isBuilding(source);
  useEffect(() => {
    if (!building) return undefined;
    const timer = window.setInterval(() => {
      adaptSource(id)
        .then((r) => setSource(r.data))
        .catch((e) => message.error(errorText(e)));
    }, ADAPT_POLL_MS);
    return () => window.clearInterval(timer);
  }, [building, id]);

  const dirty = text !== saved;

  const save = async () => {
    setBusy(true);
    setProblems(undefined);
    try {
      const response = await addAdaptVersion(id, text, note);
      message.success(
        intl.formatMessage(
          { id: 'liascript.saved', defaultMessage: 'Saved as version {version}' },
          { version: response.data.current_version },
        ),
      );
      setNote('');
      await load();
    } catch (e) {
      setProblems(e instanceof SyntaxError ? `JSON: ${e.message}` : errorText(e));
    } finally {
      setBusy(false);
    }
  };

  const build = async () => {
    try {
      const response = await buildAdaptSource(id);
      setSource(response.data);
    } catch (e) {
      message.error(errorText(e));
    }
  };

  const openVersion = async (version: number) => {
    try {
      setText(pretty(await adaptSourceJson(id, version)));
    } catch (e) {
      message.error(errorText(e));
    }
  };

  if (disabled) {
    return (
      <PageContainer>
        <Result
          status="info"
          title={
            <FormattedMessage
              id="adapt.disabled"
              defaultMessage="Adapt sources are not enabled on this platform"
            />
          }
        />
      </PageContainer>
    );
  }
  if (!source) return <Spin />;

  return (
    <PageContainer
      title={source.title}
      tags={<AdaptStatusTag status={source.status} />}
      extra={
        <Button type="primary" loading={building} disabled={busy || dirty} onClick={build}>
          <FormattedMessage id="adapt.build" defaultMessage="Build" />
        </Button>
      }
    >
      {source.status === 'failed' && source.last_error && (
        <Alert type="error" showIcon style={{ marginBottom: 16 }} message={source.last_error} />
      )}
      {source.status === 'built' && source.scorm_id && (
        <Alert
          type="success"
          showIcon
          style={{ marginBottom: 16 }}
          message={
            <FormattedMessage
              id="adapt.built_message"
              defaultMessage="Version {version} is built as SCORM package #{scorm}."
              values={{ version: source.built_version, scorm: source.scorm_id }}
            />
          }
          description={
            <Space>
              <Link to="/courses/scorms">
                <FormattedMessage id="adapt.open_scorms" defaultMessage="Open the SCORM packages" />
              </Link>
              <FormattedMessage
                id="adapt.use_in_topic"
                defaultMessage="Use it in a topic: add a SCORM topic to a course and select package #{scorm}."
                values={{ scorm: source.scorm_id }}
              />
            </Space>
          }
        />
      )}
      <Row gutter={16}>
        <Col xs={24} lg={16}>
          {problems && (
            <Alert
              type="error"
              showIcon
              style={{ marginBottom: 12, whiteSpace: 'pre-wrap' }}
              message={
                <FormattedMessage id="adapt.invalid" defaultMessage="The source was not saved" />
              }
              description={problems}
            />
          )}
          <Input.TextArea
            aria-label="Adapt JSON"
            rows={28}
            value={text}
            spellCheck={false}
            style={{ fontFamily: 'monospace' }}
            onChange={(e) => setText(e.target.value)}
          />
          <Space style={{ marginTop: 12 }} wrap>
            <Input
              style={{ width: 320 }}
              maxLength={500}
              value={note}
              placeholder={intl.formatMessage({
                id: 'adapt.change_note',
                defaultMessage: 'Change note (optional)',
              })}
              onChange={(e) => setNote(e.target.value)}
            />
            <Button type="primary" loading={busy} disabled={!dirty} onClick={save}>
              <FormattedMessage id="adapt.save_version" defaultMessage="Save as new version" />
            </Button>
          </Space>
        </Col>
        <Col xs={24} lg={8}>
          <List<AdaptVersion>
            header={<FormattedMessage id="adapt.versions" defaultMessage="Versions" />}
            bordered
            dataSource={versions}
            renderItem={(v) => (
              <List.Item
                actions={[
                  <Button key="open" size="small" onClick={() => openVersion(v.version)}>
                    <FormattedMessage id="adapt.open" defaultMessage="Open" />
                  </Button>,
                ]}
              >
                <List.Item.Meta
                  title={
                    <Space>
                      v{v.version}
                      {v.version === source.current_version && (
                        <Tag color="blue">
                          <FormattedMessage id="adapt.current" defaultMessage="current" />
                        </Tag>
                      )}
                      {v.version === source.built_version && (
                        <Tag color="green">
                          <FormattedMessage id="adapt.built" defaultMessage="built" />
                        </Tag>
                      )}
                    </Space>
                  }
                  description={`${v.change_note ?? ''} ${
                    v.created_at ? new Date(v.created_at).toLocaleString() : ''
                  }`}
                />
              </List.Item>
            )}
          />
        </Col>
      </Row>
    </PageContainer>
  );
};

export default AdaptEditor;
