import { SaveOutlined } from '@ant-design/icons';
import { Alert, Button, Col, Row, Spin, message } from 'antd';
import React, { useMemo, useState } from 'react';
import { FormattedMessage } from 'umi';

import type { H5PLoadedInfo } from './player';
import { useH5PFrame } from './useH5PFrame';
import { h5pEmbedUrl, h5pLanguage } from './utils';

/**
 * H5PEditorFrame: the H5P service's editor page (`/h5p/embed/edit/:id|new`)
 * in an iframe. "Save" asks the page to save (`ulams-h5p:save`); it answers
 * with `ulams-h5p:saved` ({contentId, metadata}) or `ulams-h5p:error`.
 */
export const Editor: React.FC<{
  id: 'new' | number | string;
  onSubmitted: (id: string) => void;
  onLoaded?: (info: H5PLoadedInfo) => void;
}> = ({ id, onSubmitted, onLoaded }) => {
  const [loading, setLoading] = useState<boolean>(true);
  const [saving, setSaving] = useState<boolean>(false);
  const [error, setError] = useState<string>();
  const lang = h5pLanguage();

  const src = useMemo(
    () => (id ? h5pEmbedUrl('edit', id, { language: lang }) : undefined),
    [id, lang],
  );

  const { iframeRef, height, connected, send } = useH5PFrame(src, (m) => {
    switch (m.type) {
      case 'ulams-h5p:loaded':
        setLoading(false);
        onLoaded?.({ title: m.title, library: m.library });
        break;
      case 'ulams-h5p:saved':
        setSaving(false);
        setError(undefined);
        message.success(
          <FormattedMessage
            id="h5p_edited"
            defaultMessage="H5P Element edited and saved successfully"
          />,
        );
        onLoaded?.({ title: m.metadata?.title });
        onSubmitted(String(m.contentId));
        break;
      case 'ulams-h5p:error':
        setLoading(false);
        setSaving(false);
        setError(m.message);
        break;
      default:
        break;
    }
  });

  const onSave = () => {
    setSaving(true);
    setError(undefined);
    send({ type: 'ulams-h5p:save' });
  };

  return (
    <React.Fragment>
      {error && <Alert message={error} type="error" style={{ marginBottom: 16 }} />}
      {loading && !error && (
        <Row justify="center" align="middle">
          <Spin />
        </Row>
      )}
      {src && (
        <iframe
          key={src}
          ref={iframeRef}
          src={src}
          title="H5P editor"
          style={{ width: '100%', height: Math.max(height, 400), border: 0, display: 'block' }}
          allow="fullscreen"
          // allow-same-origin is required: the H5P embed page calls its own service with fetch and a
          // bearer token and checks the parent's real origin (see front/sdk/src/frames.ts, SANDBOX_H5P)
          sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox allow-downloads allow-modals allow-presentation"
          allowFullScreen
          referrerPolicy="no-referrer"
        />
      )}
      <Row justify="end" style={{ marginTop: 16 }}>
        <Col>
          <Button
            type="primary"
            icon={<SaveOutlined />}
            loading={saving}
            disabled={!connected || loading}
            onClick={onSave}
          >
            <FormattedMessage id="save" defaultMessage="Save" />
          </Button>
        </Col>
      </Row>
    </React.Fragment>
  );
};

export const H5PEditorFrame = Editor;

export default Editor;
