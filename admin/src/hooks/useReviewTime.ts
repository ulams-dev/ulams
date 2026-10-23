import { useEffect } from 'react';
import { request } from 'umi';
import { startReviewTime, type ReviewEnvironment } from '../../../front/web/src/studio/review-time';

const ENDPOINT = '/api/admin/authoring-metrics/activity';

/** The review time rules of the studio (front/web/src/studio/review-time-model.ts), with this app's API client. */
const environment = (): ReviewEnvironment => ({
  now: () => Date.now(),
  visible: () => document.visibilityState === 'visible',
  focused: () => document.hasFocus(),
  channel: (name) =>
    typeof BroadcastChannel === 'undefined'
      ? { postMessage: () => undefined, close: () => undefined, onmessage: null }
      : new BroadcastChannel(name),
  // the API needs a bearer token a beacon cannot carry: what is pending when the page closes is lost (one minute at most)
  beacon: () => false,
  fetch: (async (url: string, init?: RequestInit) => {
    try {
      const response = await request(url, {
        method: 'POST',
        data: JSON.parse(String(init?.body ?? '{}')),
        getResponse: true,
        skipErrorHandler: true,
      });
      return { status: response.response.status, ok: true };
    } catch (error) {
      const status = (error as { response?: { status?: number } }).response?.status ?? 0;
      return { status, ok: false };
    }
  }) as unknown as typeof fetch,
});

/**
 * Anonymous review time of the course being edited (ADR 0194, AM-5): counts only when the academy records it, and the
 * API refuses (403, 404) when the person may not report for this course or the course was not built with the Course
 * Builder; then nothing is counted. No user is sent or stored.
 */
export default function useReviewTime(courseId: number | undefined): void {
  useEffect(() => {
    if (!courseId || !Number.isInteger(courseId)) return undefined;
    let stop: (() => void) | undefined;
    let cancelled = false;
    void (async () => {
      try {
        const status = await request<{ data?: { enabled?: boolean } }>(`${ENDPOINT}/status`, {
          method: 'GET',
          skipErrorHandler: true,
        });
        if (cancelled || status.data?.enabled !== true) return;
        stop = startReviewTime(courseId, environment(), 'admin', ENDPOINT).stop;
      } catch {
        // not recorded, or not allowed: nothing is counted
      }
    })();

    return () => {
      cancelled = true;
      stop?.();
    };
  }, [courseId]);
}
