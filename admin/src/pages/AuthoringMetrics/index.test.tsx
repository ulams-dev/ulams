/**
 * @jest-environment jsdom
 */
import mockMessages from '@/locales/en-US/authoringMetrics';
import { beforeEach, describe, expect, it, jest } from '@jest/globals';
import '@testing-library/jest-dom';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

const mockList = jest.fn<(...args: unknown[]) => Promise<unknown>>();
const mockExport = jest.fn<(...args: unknown[]) => Promise<unknown>>();

jest.mock('umi', () => ({
  useIntl: () => ({
    locale: 'en-US',
    formatMessage: ({ id }: { id: string }, values?: Record<string, string | number>) =>
      Object.entries(values ?? {}).reduce(
        (text, [k, v]) => text.replace(`{${k}}`, String(v)),
        (mockMessages as Record<string, string>)[id] ?? id,
      ),
    formatNumber: (n: number) => `${Math.round(n * 100)}%`,
  }),
  Link: ({ to, children }: { to: string; children: React.ReactNode }) => <a href={to}>{children}</a>,
}));
jest.mock('@/services/authoringMetrics', () => ({
  courseMetrics: (...args: unknown[]) => mockList(...args),
  exportCourseMetrics: (...args: unknown[]) => mockExport(...args),
}));
jest.mock('@ant-design/pro-layout', () => ({
  PageContainer: ({ title, children }: { title: string; children: React.ReactNode }) => (
    <main>
      <h1>{title}</h1>
      {children}
    </main>
  ),
}));

import AuthoringMetrics from './index';

const row = (over: Record<string, unknown> = {}) => ({
  course_id: 12,
  course_title: 'Coffee brewing basics',
  time: { generation_seconds: 441, ai_edit_seconds: 23, interview_seconds: 18, model_seconds: 900, author_minutes: null },
  cost: { build_micro_usd: 284100, maintenance_micro_usd: 1520000, llm_calls: 27, llm_calls_failed: 1 },
  counts: { lessons: 6, questions: 18, blocks: 35, cited_blocks: 35, learner_minutes_estimate: 60 },
  elements: { ai_elements: 59, accepted_unchanged: 49, revised_on_request: 4, edited_by_author: 6, unknown: 0, author_written: 2 },
  ...over,
});

beforeEach(() => {
  mockList.mockReset();
  mockExport.mockReset();
});

describe('AuthoringMetrics', () => {
  it('shows a row per course with every figure as text', async () => {
    mockList.mockResolvedValue({ data: [row()] });
    render(<AuthoringMetrics />);

    expect(await screen.findByRole('link', { name: 'Coffee brewing basics' })).toHaveAttribute('href', '/courses/list/12');
    expect(screen.getByText('7 min 44 s')).toBeInTheDocument();
    expect(screen.getByText('Not recorded')).toBeInTheDocument();
    expect(screen.getByText('$0.2841')).toBeInTheDocument();
    expect(screen.getByText('$1.52')).toBeInTheDocument();
    expect(screen.getByText('Kept: 49 (83%), revised on request: 4, edited: 6, unknown: 0')).toBeInTheDocument();
  });

  it('writes recorded author time and names a course without a title', async () => {
    mockList.mockResolvedValue({ data: [row({ course_title: null, time: { ...row().time, author_minutes: 135 } })] });
    render(<AuthoringMetrics />);

    expect(await screen.findByRole('link', { name: 'Course 12' })).toBeInTheDocument();
    expect(screen.getByText('2 h 15 min')).toBeInTheDocument();
    expect(screen.queryByText('Not recorded')).toBeNull();
  });

  it('says so when the AI wrote nothing yet and when there are no courses', async () => {
    mockList.mockResolvedValueOnce({ data: [row({ elements: { ...row().elements, ai_elements: 0 } })] });
    const { unmount } = render(<AuthoringMetrics />);
    expect(await screen.findByText('Nothing yet')).toBeInTheDocument();
    unmount();

    mockList.mockResolvedValueOnce({ data: [] });
    render(<AuthoringMetrics />);
    expect(await screen.findByText('No course built with the Course Builder has figures in this period yet.')).toBeInTheDocument();
  });

  it('sends the dates to the API', async () => {
    mockList.mockResolvedValue({ data: [] });
    render(<AuthoringMetrics />);
    await screen.findByText(/No course built/);

    fireEvent.change(screen.getByLabelText('Courses created from'), { target: { value: '2026-10-01' } });

    await waitFor(() => expect(mockList).toHaveBeenLastCalledWith({ from: '2026-10-01' }));
  });

  it.each([
    [403, 'You need the permission to read authoring metrics'],
    [500, 'The figures could not be loaded.'],
  ])('answers %s with a sentence', async (status, words) => {
    mockList.mockRejectedValue({ response: { status } });
    render(<AuthoringMetrics />);

    expect(await screen.findByRole('alert')).toHaveTextContent(words);
  });

  it('retries after an error', async () => {
    mockList.mockRejectedValueOnce({ response: { status: 500 } }).mockResolvedValueOnce({ data: [row()] });
    render(<AuthoringMetrics />);

    fireEvent.click(await screen.findByRole('button', { name: 'Try again' }));

    expect(await screen.findByRole('link', { name: 'Coffee brewing basics' })).toBeInTheDocument();
  });

  it('downloads the files the API builds and announces it', async () => {
    mockList.mockResolvedValue({ data: [row()] });
    mockExport.mockResolvedValue(new Blob(['course_id\n12\n'], { type: 'text/csv' }));
    URL.createObjectURL = jest.fn(() => 'blob:x') as unknown as typeof URL.createObjectURL;
    URL.revokeObjectURL = jest.fn() as unknown as typeof URL.revokeObjectURL;
    render(<AuthoringMetrics />);
    await screen.findByRole('link', { name: 'Coffee brewing basics' });

    fireEvent.click(screen.getByRole('button', { name: 'Download CSV' }));

    await waitFor(() => expect(screen.getByText('Downloaded authoring-metrics.csv')).toBeInTheDocument());
    expect(mockExport).toHaveBeenCalledWith('csv', {});
  });

  it('says when the file could not be downloaded', async () => {
    mockList.mockResolvedValue({ data: [row()] });
    mockExport.mockRejectedValue(new Error('offline'));
    render(<AuthoringMetrics />);
    await screen.findByRole('link', { name: 'Coffee brewing basics' });

    fireEvent.click(screen.getByRole('button', { name: 'Download JSON lines' }));

    expect(await screen.findByText('The file could not be downloaded.')).toBeInTheDocument();
  });
});
