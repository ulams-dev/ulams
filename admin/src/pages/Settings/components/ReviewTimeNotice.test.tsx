/**
 * @jest-environment jsdom
 */
import mockMessages from '@/locales/en-US/authoringMetrics';
import { beforeEach, describe, expect, it, jest } from '@jest/globals';
import '@testing-library/jest-dom';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

const mockDelete = jest.fn<(...args: unknown[]) => Promise<unknown>>();

jest.mock('umi', () => ({
  useIntl: () => ({
    formatMessage: ({ id }: { id: string }, values?: Record<string, string | number>) =>
      Object.entries(values ?? {}).reduce(
        (text, [k, v]) => text.replace(`{${k}}`, String(v)),
        (mockMessages as Record<string, string>)[id] ?? id,
      ),
  }),
}));
jest.mock('@/services/authoringMetrics', () => ({ deleteRecordedReviewTime: () => mockDelete() }));

import ReviewTimeNotice from './ReviewTimeNotice';

beforeEach(() => mockDelete.mockReset());

describe('ReviewTimeNotice', () => {
  it('says what is measured, stored, shown and kept', () => {
    render(<ReviewTimeNotice />);
    for (const word of ['Measured:', 'Stored:', 'Shown:', 'Kept:', 'works council']) {
      expect(screen.getByText(new RegExp(word))).toBeInTheDocument();
    }
  });

  it('asks before it deletes, and cancel deletes nothing', () => {
    render(<ReviewTimeNotice />);
    fireEvent.click(screen.getByRole('button', { name: 'Delete recorded review time' }));
    expect(screen.getByText(/cannot be undone/)).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
    expect(mockDelete).not.toHaveBeenCalled();
    expect(screen.queryByText(/cannot be undone/)).toBeNull();
  });

  it('deletes after the confirmation and reports how much', async () => {
    mockDelete.mockResolvedValue({ data: { minutes: 42, days: 6 } });
    render(<ReviewTimeNotice />);
    fireEvent.click(screen.getByRole('button', { name: 'Delete recorded review time' }));
    fireEvent.click(screen.getByRole('button', { name: 'Yes, delete it' }));
    await waitFor(() => expect(screen.getByText('Deleted 42 minutes and 6 daily totals.')).toBeInTheDocument());
    expect(mockDelete).toHaveBeenCalledTimes(1);
  });

  it('says when it could not delete', async () => {
    mockDelete.mockRejectedValue({ response: { status: 403 } });
    render(<ReviewTimeNotice />);
    fireEvent.click(screen.getByRole('button', { name: 'Delete recorded review time' }));
    fireEvent.click(screen.getByRole('button', { name: 'Yes, delete it' }));
    expect(await screen.findByText('Review time could not be deleted.')).toBeInTheDocument();
  });
});
