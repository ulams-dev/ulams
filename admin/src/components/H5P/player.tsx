import { Alert, Divider, Spin, Typography } from 'antd';
import React, { useMemo, useState } from 'react';
import ReactJson from 'react-json-view';

import { useH5PFrame } from './useH5PFrame';
import { h5pEmbedUrl, h5pLanguage } from './utils';

const { Title } = Typography;

export type H5PLoadedInfo = { title?: string; library?: string };

/**
 * H5PFrame: the H5P service's player page in an iframe. Without `onXAPI` the
 * xAPI statements are listed below the content (admin preview).
 */
export const Player: React.FC<{
  id: string | number;
  onXAPI?: (event: API.H5PXAPIEvent) => void;
  onLoaded?: (info: H5PLoadedInfo) => void;
}> = ({ id, onXAPI, onLoaded }) => {
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string>();
  const [XAPIEvents, setXAPIEvents] = useState<API.H5PXAPIEvent['statement'][]>([]);
  const lang = h5pLanguage();

  const src = useMemo(
    () => (id && id !== 'new' ? h5pEmbedUrl('play', id, { language: lang }) : undefined),
    [id, lang],
  );

  const { iframeRef, height } = useH5PFrame(src, (message) => {
    switch (message.type) {
      case 'ulams-h5p:loaded':
        setLoading(false);
        onLoaded?.({ title: message.title, library: message.library });
        break;
      case 'ulams-h5p:xapi':
        if (onXAPI) {
          onXAPI({ statement: message.statement, context: { contentId: message.contentId } });
        } else {
          setXAPIEvents((prev) => [...prev, message.statement]);
        }
        break;
      case 'ulams-h5p:error':
        setLoading(false);
        setError(message.message);
        break;
      default:
        break;
    }
  });

  return (
    <React.Fragment>
      {error && <Alert message={error} type="error" />}
      {loading && !error && <Spin />}
      {src && (
        <iframe
          key={src}
          ref={iframeRef}
          src={src}
          title="H5P"
          style={{ width: '100%', height, border: 0, display: 'block' }}
          allow="fullscreen; autoplay; encrypted-media"
          allowFullScreen
          referrerPolicy="no-referrer"
        />
      )}

      {!onXAPI && (
        <React.Fragment>
          <Divider />
          <div style={{ overflow: 'auto', maxHeight: '400px' }}>
            <Title level={5}>XAPI Events</Title>
            <ReactJson src={XAPIEvents} />
          </div>
        </React.Fragment>
      )}
    </React.Fragment>
  );
};

export const H5PFrame = Player;

export default Player;
