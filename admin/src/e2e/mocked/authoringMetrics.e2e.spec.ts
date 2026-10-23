import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';
import { expect, test } from '@playwright/test';
import { mockApi, type MockMetric } from './mockApi';

/**
 * Analytics > Authoring in a real browser against the mocked API: axe (WCAG 2.2 AA) with rows, empty, a refusal and an
 * error, the date filter and the file export by keyboard, "not recorded" for author time, recorded author time, and the
 * Polish and Chinese strings.
 */

const URL = '/analytics/authoring';
const WCAG = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
const COURSES: MockMetric[] = [
  { course_id: 12, course_title: 'Coffee brewing basics' },
  { course_id: 13, course_title: 'Cupping for beginners', author_minutes: 135, hours: 2.38 },
  { course_id: 14, course_title: null, ai_elements: 0, accepted_unchanged: 0 },
  { course_id: 15, course_title: 'Before provenance', accepted_unchanged: 0, unknown: 59 },
];

const scan = async (page: Page) => {
  const result = await new AxeBuilder({ page })
    .include('.authoring-metrics')
    .withTags(WCAG)
    .analyze();
  return result.violations.map((v) => ({
    id: v.id,
    impact: v.impact,
    nodes: v.nodes.slice(0, 3).map((n) => `${n.target.join(' ')} | ${n.html.slice(0, 120)}`),
  }));
};

const open = async (page: Page, options: Parameters<typeof mockApi>[1]) => {
  const state = await mockApi(page, options);
  await page.goto(URL);
  return state;
};

test.describe('Analytics > Authoring', () => {
  test('axe: the table says everything in words and has no violations', async ({ page }) => {
    await open(page, { authoring: { courses: COURSES } });
    await expect(page.getByRole('heading', { name: 'Authoring metrics' })).toBeVisible();
    const table = page.locator('table.authoring-metrics__table');
    await expect(table.locator('tbody tr')).toHaveCount(4);
    await expect(table.locator('tbody tr').first()).toContainText('Coffee brewing basics');
    await expect(table.locator('tbody tr').first()).toContainText('7 min 44 s');
    await expect(table.locator('tbody tr').first()).toContainText('Not recorded');
    await expect(table.locator('tbody tr').first()).toContainText('$0.2841');
    await expect(table.locator('tbody tr').first()).toContainText('$1.52');
    await expect(table.locator('tbody tr').first()).toContainText(
      'Kept: 49 (83%), revised on request: 4, edited: 6, unknown: 0',
    );
    await expect(table.locator('tbody tr').nth(1)).toContainText('2 h 15 min');
    await expect(table.locator('tbody tr').nth(2)).toContainText('Course 14');
    await expect(table.locator('tbody tr').nth(2)).toContainText('Nothing yet');
    await expect(table.locator('tbody tr').nth(3)).toContainText('unknown: 59');
    expect(await scan(page)).toEqual([]);
  });

  test('the table region is focusable and shows a focus indicator', async ({ page }) => {
    await open(page, { authoring: { courses: COURSES } });
    const region = page.getByRole('region', { name: /Authoring metrics by course/ });
    await region.focus();
    await expect(region).toBeFocused();
    const outline = await region.evaluate((el) => getComputedStyle(el).outlineStyle);
    expect(outline).not.toBe('none');
  });

  test('no benchmark appears', async ({ page }) => {
    await open(page, { authoring: { courses: COURSES } });
    await expect(page.locator('table.authoring-metrics__table')).toBeVisible();
    const text = (await page.locator('.authoring-metrics').innerText()).toLowerCase();
    expect(text).toContain('not compared with another academy');
    for (const banned of ['chapman', 'benchmark', 'industry average', '716'])
      expect(text).not.toContain(banned);
    expect(text).not.toMatch(/\b(49|79|184|490|716)\s*(h|hours)\b/);
  });

  test('the date filter is sent to the API and the files are downloaded by keyboard', async ({
    page,
  }) => {
    const state = await open(page, { authoring: { courses: COURSES } });
    await expect(page.locator('table.authoring-metrics__table')).toBeVisible();
    await page.getByLabel('Courses created from').fill('2026-10-01');
    await page.getByLabel('Courses created until').fill('2026-10-31');
    const lastOf = (kind: string) =>
      state.metricRequests.filter((r) => r.startsWith(`${kind}?`) || r === kind).at(-1);
    await expect.poll(() => lastOf('list')).toBe('list?from=2026-10-01&to=2026-10-31');
    await expect.poll(() => lastOf('summary')).toBe('summary?from=2026-10-01&to=2026-10-31');

    const csv = page.getByRole('button', { name: 'Download CSV' });
    await csv.focus();
    const [download] = await Promise.all([
      page.waitForEvent('download'),
      page.keyboard.press('Enter'),
    ]);
    expect(download.suggestedFilename()).toBe('authoring-metrics.csv');
    await expect(
      page.getByRole('status').filter({ hasText: 'Downloaded authoring-metrics.csv' }),
    ).toBeVisible();
    expect(state.metricRequests.at(-1)).toBe('export?from=2026-10-01&to=2026-10-31&format=csv');

    const [lines] = await Promise.all([
      page.waitForEvent('download'),
      page.getByRole('button', { name: 'Download JSON lines' }).click(),
    ]);
    expect(lines.suggestedFilename()).toBe('authoring-metrics.jsonl');
    expect(await scan(page)).toEqual([]);
  });

  test('the academy figure is a median with n, and says why when there is none', async ({
    page,
  }) => {
    await open(page, {
      authoring: {
        courses: COURSES,
        summary: { status: 'ok', n_with_figure: 4, median: 5.2, ownBaseline: 40 },
      },
    });
    const summary = page.locator('.authoring-metrics__summary');
    await expect(summary.getByRole('heading', { name: 'Across the academy' })).toBeVisible();
    await expect(summary.locator('[data-state="median"]')).toHaveText(
      'Median: 5.2 h of work per hour of learner time, over 4 courses with recorded author time.',
    );
    await expect(summary.locator('[data-state="own-baseline"]')).toContainText(
      'your own estimate, not a measurement',
    );
    await expect(summary).toContainText('Maintenance, shown apart');
    await expect(page.locator('tbody tr').nth(1)).toContainText('2.38 h');
    await expect(page.locator('tbody tr').first()).toContainText('Not recorded');
    expect(await scan(page), 'median').toEqual([]);
  });

  test('fewer than three courses is not enough courses yet, and no author time is no figure', async ({
    page,
  }) => {
    await open(page, {
      authoring: { courses: COURSES, summary: { status: 'not_enough_courses', n_with_figure: 2 } },
    });
    await expect(page.locator('[data-state="not_enough_courses"]')).toHaveText(
      'Not enough courses yet: 2 with recorded author time, at least 3 are needed for a median.',
    );
    await expect(page.locator('.authoring-metrics__summary')).not.toContainText('Median');
    expect(await scan(page), 'not enough').toEqual([]);

    await open(page, { authoring: { courses: COURSES } });
    await expect(page.locator('[data-state="author_time_not_recorded"]')).toContainText(
      'author time is not recorded for any course',
    );
  });

  test('an empty period says so', async ({ page }) => {
    await open(page, { authoring: { courses: [] } });
    await expect(page.locator('[data-state="empty"]')).toContainText(
      'No course built with the Course Builder has figures in this period yet.',
    );
    expect(await scan(page)).toEqual([]);
  });

  test('a refusal is a sentence', async ({ page }) => {
    await open(page, { authoring: { status: 403 } });
    await expect(page.getByRole('alert')).toContainText(
      'You need the permission to read authoring metrics',
    );
    expect(await scan(page)).toEqual([]);
  });

  test('an error offers a retry that recovers', async ({ page }) => {
    await open(page, { authoring: { courses: COURSES, failFirst: true } });
    await expect(page.getByRole('alert')).toContainText('The figures could not be loaded.');
    expect(await scan(page), 'error').toEqual([]);
    await page.getByRole('button', { name: 'Try again' }).click();
    await expect(page.locator('table.authoring-metrics__table tbody tr')).toHaveCount(4);
  });

  test('Polish and Chinese strings', async ({ page }) => {
    await open(page, { authoring: { courses: COURSES }, locale: 'pl-PL' });
    await expect(page.getByRole('heading', { name: 'Metryki tworzenia kursów' })).toBeVisible();
    await expect(page.locator('table.authoring-metrics__table tbody tr').first()).toContainText(
      'Nie zapisano',
    );
    expect(await scan(page), 'pl').toEqual([]);
  });

  test('Chinese strings', async ({ page }) => {
    await open(page, { authoring: { courses: COURSES }, locale: 'zh-CN' });
    await expect(page.getByRole('heading', { name: '课程制作指标' })).toBeVisible();
    await expect(page.locator('table.authoring-metrics__table tbody tr').first()).toContainText(
      '未记录',
    );
    expect(await scan(page), 'zh').toEqual([]);
  });
});
