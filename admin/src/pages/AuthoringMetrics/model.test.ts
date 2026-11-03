import type { CourseMetric } from '@/services/authoringMetrics';
import { describe, expect, it } from '@jest/globals';
import { duration, elementSplit, usd } from './model';

const messages: Record<string, string> = {
  authoring_metrics_duration_seconds: '{s} s',
  authoring_metrics_duration_minutes: '{m} min {s} s',
  authoring_metrics_duration_hours: '{h} h {m} min',
};
const t = (id: string, values: Record<string, string | number> = {}) =>
  Object.entries(values).reduce(
    (text, [k, v]) => text.replace(`{${k}}`, String(v)),
    messages[id] ?? id,
  );

const metric = (elements: Partial<CourseMetric['elements']>): CourseMetric =>
  ({
    elements: {
      ai_elements: 0,
      accepted_unchanged: 0,
      revised_on_request: 0,
      edited_by_author: 0,
      unknown: 0,
      author_written: 0,
      ...elements,
    },
  } as CourseMetric);

describe('duration', () => {
  it.each([
    [24, '24 s'],
    [417, '6 min 57 s'],
    [464, '7 min 44 s'],
    [3725, '1 h 2 min'],
    [-1, '0 s'],
  ])('%s s is %s', (seconds, text) => expect(duration(seconds, t)).toBe(text));
});

describe('usd', () => {
  it('shows four places under a dollar and two above', () => {
    expect(usd(284100, 'en-US')).toBe('$0.2841');
    expect(usd(1520000, 'en-US')).toBe('$1.52');
  });
});

describe('elementSplit', () => {
  it('shares the kept elements of the AI elements', () => {
    expect(
      elementSplit(
        metric({
          ai_elements: 50,
          accepted_unchanged: 40,
          revised_on_request: 5,
          edited_by_author: 5,
        }),
      ),
    ).toMatchObject({
      total: 50,
      kept: 40,
      revised: 5,
      edited: 5,
      keptShare: 0.8,
    });
  });
  it('has no share when the AI wrote nothing', () => {
    expect(elementSplit(metric({})).keptShare).toBeNull();
  });
});
