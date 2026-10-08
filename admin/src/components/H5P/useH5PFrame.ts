import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

import type { H5PEmbedToParent, H5PParentToEmbed } from './utils';
import { h5pEmbedOrigin, isH5PEmbedMessage, useAccessToken } from './utils';

/**
 * Handshake + messaging with one H5P embed iframe. Accepts messages only from
 * that iframe's window and the H5P service origin; sends only to that origin.
 */
export function useH5PFrame(src: string | undefined, onMessage: (m: H5PEmbedToParent) => void) {
  const iframeRef = useRef<HTMLIFrameElement>(null);
  const [connected, setConnected] = useState(false);
  const [height, setHeight] = useState<number>(300);
  const token = useAccessToken();
  const origin = useMemo(() => h5pEmbedOrigin(), []);

  const tokenRef = useRef(token);
  tokenRef.current = token;
  const onMessageRef = useRef(onMessage);
  onMessageRef.current = onMessage;

  const send = useCallback(
    (message: H5PParentToEmbed) => {
      iframeRef.current?.contentWindow?.postMessage(message, origin);
    },
    [origin],
  );

  useEffect(() => setConnected(false), [src]);

  useEffect(() => {
    const listener = (event: MessageEvent) => {
      if (
        event.origin !== origin ||
        !iframeRef.current ||
        event.source !== iframeRef.current.contentWindow ||
        !isH5PEmbedMessage(event.data)
      ) {
        return;
      }
      const message = event.data;
      if (message.type === 'ulams-h5p:ready') {
        send({ type: 'ulams-h5p:token', token: tokenRef.current ?? null });
        setConnected(true);
      } else if (message.type === 'ulams-h5p:resize') {
        if (Number.isFinite(message.height) && message.height > 0) {
          setHeight(Math.ceil(message.height));
        }
      }
      onMessageRef.current(message);
    };
    window.addEventListener('message', listener);
    return () => window.removeEventListener('message', listener);
  }, [origin, send]);

  // refreshed token → page swaps it into H5P's AJAX URLs
  useEffect(() => {
    if (connected) {
      send({ type: 'ulams-h5p:token', token: token ?? null });
    }
  }, [token, connected, send]);

  return { iframeRef, height, connected, send };
}
