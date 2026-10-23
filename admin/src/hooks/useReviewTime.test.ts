/**
 * @jest-environment jsdom
 */
import { beforeEach, describe, expect, it, jest } from '@jest/globals';
import { renderHook } from '@testing-library/react';

const mockRequest = jest.fn<(...args: unknown[]) => Promise<unknown>>();
const mockStop = jest.fn();
const mockStart = jest.fn((..._args: unknown[]) => ({ stop: mockStop }));

jest.mock('umi', () => ({ request: (...args: unknown[]) => mockRequest(...args) }));
jest.mock('../../../front/web/src/studio/review-time', () => ({ startReviewTime: (...args: unknown[]) => mockStart(...args) }));

import useReviewTime from './useReviewTime';

const settle = () => new Promise((resolve) => setTimeout(resolve, 0));

beforeEach(() => {
  mockRequest.mockReset();
  mockStart.mockClear();
  mockStop.mockClear();
});

describe('useReviewTime', () => {
  it('starts counting for the course on the admin surface when the academy records review time', async () => {
    mockRequest.mockResolvedValue({ data: { enabled: true } });
    const { unmount } = renderHook(() => useReviewTime(12));
    await settle();

    expect(mockRequest).toHaveBeenCalledWith('/api/admin/authoring-metrics/activity/status', expect.objectContaining({ method: 'GET' }));
    expect(mockStart).toHaveBeenCalledWith(12, expect.any(Object), 'admin', '/api/admin/authoring-metrics/activity');
    unmount();
    expect(mockStop).toHaveBeenCalled();
  });

  it('counts nothing when review time is off', async () => {
    mockRequest.mockResolvedValue({ data: { enabled: false } });
    renderHook(() => useReviewTime(12));
    await settle();
    expect(mockStart).not.toHaveBeenCalled();
  });

  it('counts nothing when the status call fails or is refused', async () => {
    mockRequest.mockRejectedValue({ response: { status: 403 } });
    renderHook(() => useReviewTime(12));
    await settle();
    expect(mockStart).not.toHaveBeenCalled();
  });

  it.each([[undefined], [NaN], [0], [1.5]])('asks nothing for the course %s (a new course has no id yet)', async (id) => {
    renderHook(() => useReviewTime(id as number | undefined));
    await settle();
    expect(mockRequest).not.toHaveBeenCalled();
  });

  it('does not start when the page was left before the answer came', async () => {
    let answer: (value: unknown) => void = () => undefined;
    mockRequest.mockReturnValue(new Promise((resolve) => (answer = resolve)));
    const { unmount } = renderHook(() => useReviewTime(12));
    unmount();
    answer({ data: { enabled: true } });
    await settle();
    expect(mockStart).not.toHaveBeenCalled();
  });

  it('reports through the API client: the status and the refusal come back as the tracker expects', async () => {
    mockRequest.mockResolvedValueOnce({ data: { enabled: true } });
    renderHook(() => useReviewTime(12));
    await settle();
    const env = mockStart.mock.calls[0][1] as { fetch: (url: string, init: RequestInit) => Promise<{ status: number; ok: boolean }>; beacon: () => boolean };

    mockRequest.mockResolvedValueOnce({ response: { status: 200 } });
    expect(await env.fetch('/x', { body: '{"a":1}' })).toEqual({ status: 200, ok: true });
    expect(mockRequest).toHaveBeenLastCalledWith('/x', expect.objectContaining({ method: 'POST', data: { a: 1 } }));
    mockRequest.mockRejectedValueOnce({ response: { status: 404 } });
    expect(await env.fetch('/x', { body: '{}' })).toEqual({ status: 404, ok: false });
    mockRequest.mockRejectedValueOnce(new Error('offline'));
    expect(await env.fetch('/x', { body: '{}' })).toEqual({ status: 0, ok: false });
    expect(env.beacon()).toBe(false);
  });
});
