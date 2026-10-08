import { useState } from 'react';
import { getLocale } from 'umi';

import { useTokenChangeListener } from '@/hooks/useTokenChangeListener';

declare const REACT_APP_API_URL: string;

/*
 * H5P (GPL) runs only in the separate H5P service (api/h5p). The admin frames
 * its embed pages and talks to them over postMessage; see api/h5p/README.md
 * ("Embedding") for the protocol.
 */

export type H5PEmbedToParent =
  | { type: 'ulams-h5p:ready'; mode: 'play' | 'edit'; contentId: string }
  | { type: 'ulams-h5p:loaded'; contentId: string; title?: string; library?: string }
  | { type: 'ulams-h5p:resize'; height: number }
  | { type: 'ulams-h5p:xapi'; statement: Record<string, any>; contentId: string }
  | { type: 'ulams-h5p:saved'; contentId: string; metadata?: { title?: string } }
  | { type: 'ulams-h5p:error'; message: string; code?: string };

export type H5PParentToEmbed =
  | { type: 'ulams-h5p:token'; token: string | null }
  | { type: 'ulams-h5p:style'; css?: string; urls?: string[] }
  | { type: 'ulams-h5p:save' };

export const apiUrl = () => window.REACT_APP_API_URL || REACT_APP_API_URL || '';

export const h5pEmbedOrigin = () => new URL(apiUrl() || '/', window.location.href).origin;

/** "pl-PL" → "pl" */
export const h5pLanguage = (locale: string = getLocale()) => (locale || 'en').split('-')[0];

export const h5pEmbedUrl = (
  mode: 'play' | 'edit',
  id: string | number,
  params: Record<string, string | undefined> = {},
) => {
  const query = new URLSearchParams();
  Object.entries(params).forEach(([k, v]) => v && query.set(k, v));
  const qs = query.toString();
  return `${apiUrl()}/h5p/embed/${mode}/${encodeURIComponent(String(id))}${qs ? `?${qs}` : ''}`;
};

export const isH5PEmbedMessage = (data: unknown): data is H5PEmbedToParent =>
  !!data &&
  typeof data === 'object' &&
  typeof (data as { type?: unknown }).type === 'string' &&
  (data as { type: string }).type.startsWith('ulams-h5p:');

/** Current access token; follows refreshes (`token_change` window event). */
export const useAccessToken = () => {
  const [token, setToken] = useState<string | null>(() => localStorage.getItem('TOKEN'));
  useTokenChangeListener(setToken);
  return token;
};
