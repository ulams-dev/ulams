import { useEffect, useRef, useState } from 'react';
import { requestErrorMessage, type DraftInput, type DraftRequest, type EmailDraft } from './draft';

export type DraftPreviewState =
  | { status: 'idle' }
  | { status: 'loading'; draft?: EmailDraft }
  | { status: 'ready'; draft: EmailDraft }
  | { status: 'error'; message: string; draft?: EmailDraft };

export const PREVIEW_DELAY_MS = 800;

/**
 * Renders the unsaved draft through the server after the author stops typing. Stale answers are
 * dropped, the last good draft stays visible while a new one loads or fails.
 */
export const useDraftPreview = (
  input: DraftInput,
  render: DraftRequest,
  fallbackMessage: string,
  delay = PREVIEW_DELAY_MS,
): DraftPreviewState => {
  const [state, setState] = useState<DraftPreviewState>({ status: 'idle' });
  const lastDraft = useRef<EmailDraft | undefined>(undefined);
  const counter = useRef(0);
  const { event, title, content, locale } = input;

  useEffect(() => {
    if (!event || !content) {
      counter.current += 1;
      lastDraft.current = undefined;
      setState({ status: 'idle' });
      return undefined;
    }
    const current = ++counter.current;
    const timer = setTimeout(() => {
      setState({ status: 'loading', draft: lastDraft.current });
      render(locale ? { event, title, content, locale } : { event, title, content })
        .then((response) => {
          if (current !== counter.current) return;
          lastDraft.current = response.data;
          setState({ status: 'ready', draft: response.data });
        })
        .catch((error) => {
          if (current !== counter.current) return;
          setState({
            status: 'error',
            message: requestErrorMessage(error, fallbackMessage),
            draft: lastDraft.current,
          });
        });
    }, delay);
    return () => clearTimeout(timer);
  }, [event, title, content, locale, delay]);

  return state;
};
