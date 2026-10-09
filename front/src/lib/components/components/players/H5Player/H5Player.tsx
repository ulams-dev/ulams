import React, { useCallback, useContext, useEffect, useMemo, useRef, useState } from "react";
import { useTranslation } from "react-i18next";

import * as API from "@ulams/sdk/types";
import { UlamsContext } from "@ulams/sdk/react/context";
import {
  h5pEmbedOrigin,
  h5pEmbedPlayUrl,
  isH5PEmbedMessage,
} from "@ulams/sdk/services/h5p";
import { Spin } from "../../atoms/Spin/Spin";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { useThemeTokens } from "../../../theme/applyTheme";
import { buildH5PThemeCss } from "./h5pThemeCss";
import styles from "./H5Player.module.css";

export interface H5PProps extends ExtendableStyledComponent {
  /** H5P content id in the H5P service (`topic.topicable.value`) */
  contentId?: string | number;
  loading?: boolean;
  /** every xAPI statement of this content (and its sub-content) */
  onXAPI?: (e: API.H5PXAPIEvent) => void;
  /** called on a statement with `result.success === true` */
  onTopicEnd?: () => void;
  onInitialized?: (contentId: string) => void;
  onError?: (error: Error) => void;
  /** file in front/public applied to the content; `null` disables it */
  overwriteFileName?: string | null;
  hideActionButtons?: boolean;
  /** defaults to the current i18next language */
  language?: string;
  contextId?: string;
  readOnlyState?: boolean;
  /** iframe height before the first resize message */
  initialHeight?: number;
}

/**
 * H5PFrame: frames the H5P service's player page (`/h5p/embed/play/:id`).
 * H5P itself (GPL) runs only inside the service; this wrapper does the
 * postMessage handshake (token + styles), forwards xAPI statements and
 * resizes the iframe. Messages are accepted only from the iframe's window
 * and the API origin, and sent only to that origin.
 */
export const H5Player: React.FC<H5PProps> = ({
  contentId,
  onXAPI,
  onTopicEnd,
  onInitialized,
  onError,
  overwriteFileName = "h5p_overwrite.css",
  loading = false,
  className = "",
  hideActionButtons,
  language,
  contextId,
  readOnlyState,
  initialHeight = 200,
}) => {
  const { apiUrl, token } = useContext(UlamsContext);
  const themeContext = useThemeTokens();
  const { i18n } = useTranslation();
  const lang = (language ?? i18n?.language ?? "en").split("-")[0];

  const iframeRef = useRef<HTMLIFrameElement>(null);
  const [height, setHeight] = useState(initialHeight);
  const [initializing, setInitializing] = useState(true);
  const [error, setError] = useState<string>();
  const [connected, setConnected] = useState(false);

  const origin = useMemo(() => h5pEmbedOrigin(apiUrl), [apiUrl]);
  const src = useMemo(
    () =>
      contentId === undefined || contentId === null || contentId === ""
        ? undefined
        : h5pEmbedPlayUrl(apiUrl, contentId, {
            language: lang,
            contextId,
            readOnlyState,
            hideActions: hideActionButtons,
          }),
    [apiUrl, contentId, lang, contextId, readOnlyState, hideActionButtons]
  );

  const style = useMemo(
    () => ({
      css: themeContext
        ? buildH5PThemeCss(themeContext, hideActionButtons)
        : undefined,
      urls:
        overwriteFileName && typeof window !== "undefined"
          ? [`${window.location.origin}/${overwriteFileName}`]
          : [],
    }),
    [themeContext, hideActionButtons, overwriteFileName]
  );

  const tokenRef = useRef(token);
  tokenRef.current = token;
  const styleRef = useRef(style);
  styleRef.current = style;
  const callbacksRef = useRef({ onXAPI, onTopicEnd, onInitialized, onError });
  callbacksRef.current = { onXAPI, onTopicEnd, onInitialized, onError };

  const send = useCallback(
    (message: API.H5PParentToEmbed) => {
      iframeRef.current?.contentWindow?.postMessage(message, origin);
    },
    [origin]
  );

  useEffect(() => {
    setConnected(false);
    setInitializing(true);
    setError(undefined);
    setHeight(initialHeight);
  }, [src, initialHeight]);

  useEffect(() => {
    const onMessage = (event: MessageEvent) => {
      if (
        event.origin !== origin ||
        !iframeRef.current ||
        event.source !== iframeRef.current.contentWindow ||
        !isH5PEmbedMessage(event.data)
      ) {
        return;
      }
      const message = event.data;
      switch (message.type) {
        case "ulams-h5p:ready":
          // style first: the page applies it to the content it loads next
          send({ type: "ulams-h5p:style", ...styleRef.current });
          send({ type: "ulams-h5p:token", token: tokenRef.current ?? null });
          setConnected(true);
          break;
        case "ulams-h5p:loaded":
          setInitializing(false);
          callbacksRef.current.onInitialized?.(message.contentId);
          break;
        case "ulams-h5p:resize":
          if (Number.isFinite(message.height) && message.height > 0) {
            setHeight(Math.ceil(message.height));
          }
          break;
        case "ulams-h5p:xapi": {
          const statement = message.statement;
          callbacksRef.current.onXAPI?.({
            statement,
            context: { contentId: message.contentId },
          });
          const result = statement?.result as { success?: boolean } | undefined;
          if (result?.success) {
            callbacksRef.current.onTopicEnd?.();
          }
          break;
        }
        case "ulams-h5p:error":
          setInitializing(false);
          setError(message.message);
          callbacksRef.current.onError?.(new Error(message.message));
          break;
        default:
          break;
      }
    };
    window.addEventListener("message", onMessage);
    return () => window.removeEventListener("message", onMessage);
  }, [origin, send]);

  // Token refreshed (or logged in/out): hand it to the page.
  useEffect(() => {
    if (connected) {
      send({ type: "ulams-h5p:token", token: token ?? null });
    }
  }, [token, connected, send]);

  // Theme changed after load
  useEffect(() => {
    if (connected) {
      send({ type: "ulams-h5p:style", ...style });
    }
  }, [style, connected, send]);

  return (
    <div className={`${styles.root} ulams-component ${className}`}>
      {(initializing || loading) && !error && (
        <div className={`${styles.loading} h5p-loading`}>
          <Spin />
        </div>
      )}
      {error && <p className="h5p-error">{error}</p>}
      {src && (
        <iframe
          key={src}
          ref={iframeRef}
          src={src}
          title="H5P"
          style={{ height }}
          allow="fullscreen; autoplay; encrypted-media"
          allowFullScreen
          referrerPolicy="no-referrer"
        />
      )}
    </div>
  );
};

/** Alias that names what the component is: an iframe onto the H5P service. */
export const H5PFrame = H5Player;

export default H5Player;
