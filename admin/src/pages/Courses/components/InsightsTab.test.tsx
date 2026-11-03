/**
 * @jest-environment jsdom
 */
import mockMessages from '@/locales/en-US/insights';
import { beforeEach, describe, expect, it, jest } from '@jest/globals';
import '@testing-library/jest-dom';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

const mockSummary = jest.fn<(...args: unknown[]) => Promise<unknown>>();
const mockElements = jest.fn<(...args: unknown[]) => Promise<unknown>>();
const mockEnabled = jest.fn<() => Promise<boolean>>();

jest.mock('umi', () => ({
  useIntl: () => ({
    formatMessage: ({ id }: { id: string }, values?: Record<string, string | number>) =>
      Object.entries(values ?? {}).reduce(
        (text, [k, v]) => text.replace(`{${k}}`, String(v)),
        (mockMessages as Record<string, string>)[id] ?? id,
      ),
    formatNumber: (n: number) => `${Math.round(n * 100)}%`,
  }),
}));
jest.mock('@/services/learnerInsights', () => ({
  courseInsightsSummary: (...args: unknown[]) => mockSummary(...args),
  courseInsightsElements: (...args: unknown[]) => mockElements(...args),
  learnerInsightsEnabled: () => mockEnabled(),
  insightsStudioPath: (id: number | string) => `/studio/courses/${id}/insights`,
}));
jest.mock('@/services/studio', () => ({
  studioUrl: (path: string) => `http://coffee.app.localhost:4321${path}`,
}));

import InsightsTab from './InsightsTab';

// antd's grid asks the browser for media queries, which jsdom does not have
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: (media: string) => ({
    matches: false,
    media,
    addListener: () => undefined,
    removeListener: () => undefined,
    addEventListener: () => undefined,
    removeEventListener: () => undefined,
    dispatchEvent: () => false,
  }),
});

const summary = {
  courseId: 7,
  periodDays: 30,
  minGroupSize: 10,
  enrolled: 48,
  smallGroup: false,
  learners: { enrolled: 48, active: 31, completed: 12, completionRate: 0.25 },
  statuses: { on_track: 36, struggling: 8, at_risk: 4 },
};

const element = (n: number, over: Record<string, unknown> = {}) => ({
  elementKey: `topic:${n}`,
  label: `Section ${n}`,
  hidden: false,
  activeLearners: 20 + n,
  struggleRate: 0.5 - n / 100,
  ...over,
});

const elements = (list: unknown[]) => ({
  data: { courseId: 7, periodDays: 30, minGroupSize: 10, elements: list },
});

beforeEach(() => {
  mockSummary.mockReset().mockResolvedValue({ data: summary });
  mockElements
    .mockReset()
    .mockResolvedValue(elements([1, 2, 3, 4, 5, 6, 7].map((n) => element(n))));
  mockEnabled.mockReset().mockResolvedValue(true);
});

describe('course Insights tab', () => {
  it('summarises completion, statuses and the top five confusing sections, and links to the studio', async () => {
    render(<InsightsTab courseId={7} />);

    await screen.findByText('Who needs support');
    expect(screen.getByText('Completion rate').closest('.ant-statistic')).toHaveTextContent('25%');
    expect(
      screen.getByText('On track').closest('tr, .ant-descriptions-row, .ant-descriptions-item'),
    ).toHaveTextContent('36');
    const sections = screen.getByTestId('insights-top-sections');
    expect(sections.querySelectorAll('li')).toHaveLength(5);
    expect(sections.querySelector('li')).toHaveTextContent(
      'Section 1: 49% of 21 active learners struggled',
    );
    const link = screen.getByRole('link', { name: /Open full insights/ });
    expect(link).toHaveAttribute(
      'href',
      'http://coffee.app.localhost:4321/studio/courses/7/insights',
    );
    expect(link).toHaveAttribute('target', '_blank');
    expect(link).toHaveAttribute('rel', expect.stringContaining('noopener'));
    expect(screen.getByText(/Groups smaller than 10 learners are hidden/)).toBeInTheDocument();
    expect(mockSummary).toHaveBeenCalledWith(7, 30);
  });

  it('counts the sections the server withheld without showing any figure for them', async () => {
    mockElements.mockResolvedValue(
      elements([element(1), { elementKey: 'topic:9', label: 'Secret section', hidden: true }]),
    );
    render(<InsightsTab courseId={7} />);

    await screen.findByText(/1 more sections are hidden/);
    expect(screen.queryByText(/Secret section/)).toBeNull();
  });

  it('explains a course below the minimum group and shows no figures', async () => {
    mockSummary.mockResolvedValue({
      data: { courseId: 7, periodDays: 30, minGroupSize: 10, enrolled: 6, smallGroup: true },
    });
    render(<InsightsTab courseId={7} />);

    await screen.findByText(/Not enough learners yet: 6 enrolled/);
    expect(screen.queryByText('Completion rate')).toBeNull();
  });

  it('says so when Learner Insights is off and does not ask for data', async () => {
    mockEnabled.mockResolvedValue(false);
    render(<InsightsTab courseId={7} />);

    await screen.findByText(/Learner Insights is off for this academy/);
    expect(mockSummary).not.toHaveBeenCalled();
  });

  it('explains a refusal without a retry', async () => {
    mockSummary.mockRejectedValue({ response: { status: 403 } });
    render(<InsightsTab courseId={7} />);

    await screen.findByText(/You can see insights for courses you author/);
    expect(screen.queryByRole('button', { name: 'Try again' })).toBeNull();
  });

  it('offers a retry after an error and recovers', async () => {
    mockSummary.mockRejectedValueOnce({ response: { status: 500 } });
    render(<InsightsTab courseId={7} />);

    await screen.findByText('The insights could not be loaded.');
    fireEvent.click(screen.getByRole('button', { name: 'Try again' }));
    await waitFor(() => expect(screen.getByText('Who needs support')).toBeInTheDocument());
  });
});
