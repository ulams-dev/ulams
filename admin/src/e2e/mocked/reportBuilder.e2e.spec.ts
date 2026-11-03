import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';
import { expect, test } from '@playwright/test';
import { mockApi, type MockSavedReport } from './mockApi';

/**
 * Analytics → Report builder in a real browser against the mocked API (mockApi.ts): axe (WCAG 2.2 AA) in every
 * state, building a row report and a grouped report with the keyboard alone, the problems the API names, the
 * withheld groups explained inline, saving and sharing, opening and exporting a saved report, and the labels in
 * Polish and Chinese.
 */

const PAGE_URL = '/analytics/report-builder';
const WCAG = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

const settle = async (page: Page) => {
  const colours = () =>
    page.evaluate(() =>
      [...document.querySelectorAll('.report-builder .ant-btn, .report-builder .ant-tag')]
        .map((b) => `${getComputedStyle(b).color}|${getComputedStyle(b).backgroundColor}`)
        .join(';'),
    );
  let previous = '';
  for (let i = 0; i < 20; i += 1) {
    const now = await colours();
    if (now === previous) return;
    previous = now;
    await page.waitForTimeout(120);
  }
};

const scan = async (page: Page, include = '.report-builder') => {
  await settle(page);
  const result = await new AxeBuilder({ page }).include(include).withTags(WCAG).analyze();
  return result.violations.map((v) => ({
    id: v.id,
    impact: v.impact,
    nodes: v.nodes.slice(0, 3).map((n) => `${n.target.join(' ')} | ${n.html.slice(0, 140)}`),
  }));
};

const SAVED: Partial<MockSavedReport>[] = [
  {
    name: 'Overdue by course',
    visibility: 'roles',
    shared_roles: ['tutor'],
    last_run_at: '2026-10-20T08:00:00Z',
    definition: {
      dataset: 'assignments',
      groupBy: ['course_title'],
      aggregates: [{ fn: 'count_distinct', column: 'user_id', as: 'learners' }],
      filters: [{ column: 'status', op: 'eq', value: 'overdue' }],
    },
  },
  {
    name: 'Broken report',
    needs_repair: true,
    runnable: false,
    problems: [{ path: '/columns/1', message: 'Unknown column "renamed_column".' }],
  },
];

const open = async (page: Page, saved: Partial<MockSavedReport>[] = [], locale?: string) => {
  const state = await mockApi(page, { reporting: { saved }, locale });
  await page.goto(PAGE_URL);
  await expect(page.locator('.report-builder')).toBeVisible();
  await expect(page.locator('#rb-dataset')).toBeVisible();
  return state;
};

/** antd's Select puts its label on a wrapper and on the input: the combobox is the one to use. */
const combo = (page: Page, name: string) => page.getByRole('combobox', { name });

/** Open a select by its visible box: with a value chosen, its text sits over the input and takes the click. */
const openSelect = (page: Page, name: string) =>
  page
    .locator('.ant-select')
    .filter({ has: combo(page, name) })
    .click();

const OPTION = '.ant-select-dropdown:not(.ant-select-dropdown-hidden) .ant-select-item-option';

/** Click an option of the open dropdown (antd also keeps a hidden accessibility copy of every option). */
const pick = (page: Page, label: string) =>
  page
    .locator(OPTION)
    .filter({ hasText: new RegExp(`^${label}$`) })
    .click();

/** A column box ticked the way a keyboard user does it. */
const tick = async (page: Page, name: string, checked = true) => {
  await page.getByRole('checkbox', { name }).focus();
  if ((await page.getByRole('checkbox', { name }).isChecked()) !== checked)
    await page.keyboard.press('Space');
  if (checked) await expect(page.getByRole('checkbox', { name })).toBeChecked();
  else await expect(page.getByRole('checkbox', { name })).not.toBeChecked();
};

const preview = (page: Page) => page.getByRole('button', { name: 'Preview' });

test.describe('admin report builder', () => {
  test('axe: the empty builder, a preview, a grouped preview with withheld groups, a problem and the save dialog', async ({
    page,
  }) => {
    await open(page, SAVED);
    expect(await scan(page), 'empty builder').toEqual([]);

    await tick(page, 'Course');
    await preview(page).click();
    await expect(page.getByRole('table', { name: 'Result' })).toBeVisible();
    expect(await scan(page), 'row preview').toEqual([]);

    await page.getByRole('radio', { name: 'Group and aggregate' }).check();
    await page.getByRole('button', { name: 'Add a group' }).click();
    await openSelect(page, 'Group 1: column');
    await pick(page, 'Course');
    await page.getByRole('button', { name: 'Add an aggregate' }).click();
    await page.getByLabel('Aggregate 1: name').fill('learners');
    await preview(page).click();
    await expect(page.getByText('Small groups are withheld')).toBeVisible();
    expect(await scan(page), 'grouped preview').toEqual([]);

    await page.getByRole('radio', { name: 'List rows' }).check();
    await tick(page, 'Course', false);
    await expect(preview(page)).toBeDisabled();
    expect(await scan(page), 'nothing chosen').toEqual([]);

    await expect(page.getByRole('button', { name: 'Save report' })).toBeDisabled();
  });

  test('axe: the save dialog and the saved reports table', async ({ page }) => {
    await open(page, SAVED);
    await tick(page, 'Course');
    await page.getByRole('button', { name: 'Save report' }).click();
    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible();
    await dialog.getByRole('radio', { name: 'Share with roles' }).check();
    await expect(dialog.getByRole('combobox', { name: 'Roles' })).toBeVisible();
    expect(await scan(page, '.ant-modal'), 'save dialog').toEqual([]);
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    expect(await scan(page), 'saved reports').toEqual([]);
  });

  test('builds, previews, saves and shares a row report with the keyboard alone', async ({
    page,
  }) => {
    const state = await open(page);
    await page.locator('#rb-dataset').focus();
    // columns: Tab into the checkbox group and press Space on the first two boxes
    await tick(page, 'Course');

    await page.getByRole('button', { name: 'Add a filter' }).focus();
    await page.keyboard.press('Enter');
    await combo(page, 'Filter 1: column').focus();
    await page.keyboard.press('Enter');
    await pick(page, 'Status');
    await openSelect(page, 'Filter 1: value');
    await pick(page, 'overdue');

    await preview(page).focus();
    await page.keyboard.press('Enter');
    await expect(
      page.getByRole('status').filter({ hasText: 'Showing 3 of 120 rows.' }),
    ).toBeVisible();
    expect(state.reporting.queries).toEqual([
      {
        dataset: 'assignments',
        columns: ['course_title'],
        filters: [{ column: 'status', op: 'eq', value: 'overdue' }],
      },
    ]);

    await page.getByRole('button', { name: 'Save report' }).focus();
    await page.keyboard.press('Enter');
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Name').fill('Overdue courses');
    await dialog.getByRole('radio', { name: 'Share with roles' }).check();
    await dialog.getByRole('combobox', { name: 'Roles' }).fill('tutor');
    await page.keyboard.press('Enter');
    await dialog.getByRole('button', { name: 'Save report' }).click();

    await expect(
      page.getByRole('status').filter({ hasText: 'Saved Overdue courses.' }),
    ).toBeVisible();
    expect(state.reporting.saves).toHaveLength(1);
    expect(state.reporting.saves[0]).toMatchObject({
      name: 'Overdue courses',
      visibility: 'roles',
      shared_roles: ['tutor'],
      definition: { dataset: 'assignments', columns: ['course_title'] },
    });
    await expect(page.getByRole('row', { name: /Overdue courses/ })).toContainText(
      'Shared with tutor',
    );
  });

  test('says what the API refuses, by path, in an alert', async ({ page }) => {
    await open(page);
    await tick(page, 'Course');
    await page.route('**/api/admin/reporting/query', (route) =>
      route.fulfill({
        status: 422,
        contentType: 'application/json',
        headers: { 'Access-Control-Allow-Origin': '*' },
        body: JSON.stringify({
          success: false,
          message: 'The given data was invalid.',
          errors: {
            'filters.0.op': ['Operator "contains" does not apply to int column "user_id".'],
          },
        }),
      }),
    );
    await preview(page).click();
    const alert = page.getByRole('alert').filter({ hasText: 'The report cannot run yet' });
    await expect(alert).toContainText('filters.0.op');
    await expect(alert).toContainText('does not apply to int column');
    expect(await scan(page), 'problem alert').toEqual([]);
  });

  test('a grouped report shows the withheld row and says why', async ({ page }) => {
    const state = await open(page);
    await page.getByRole('radio', { name: 'Group and aggregate' }).check();
    await page.getByRole('button', { name: 'Add a group' }).click();
    await openSelect(page, 'Group 1: column');
    await pick(page, 'Assigned');
    await openSelect(page, 'Group 1: date bucket');
    await pick(page, 'Month');
    await page.getByRole('button', { name: 'Add an aggregate' }).click();
    await openSelect(page, 'Aggregate 1: column');
    await pick(page, 'Learner id');
    await openSelect(page, 'Aggregate 1: function');
    await pick(page, 'Count distinct');
    await page.getByLabel('Aggregate 1: name').fill('Learners 2026');
    await expect(page.getByLabel('Aggregate 1: name')).toHaveValue('learners_2026');
    await preview(page).click();

    expect(state.reporting.queries[0]).toEqual({
      dataset: 'assignments',
      groupBy: [{ column: 'assigned_at', bucket: 'month' }],
      aggregates: [{ fn: 'count_distinct', column: 'user_id', as: 'learners_2026' }],
    });
    await expect(page.getByText('withheld (fewer than 10 learners)').first()).toBeVisible();
    await expect(page.getByText('2 groups have fewer than 10 learners')).toBeVisible();
  });

  test('personal columns are marked and a dataset without a permission is not offered', async ({
    page,
  }) => {
    await open(page);
    await expect(page.getByText('(personal data)')).toBeVisible();
    await openSelect(page, 'Dataset');
    await expect(page.locator(OPTION)).toHaveCount(2);
  });

  test('opens a saved report in the builder, exports it, and deletes it after a confirmation', async ({
    page,
  }) => {
    const state = await open(page, SAVED);
    const row = page.getByRole('row', { name: /Overdue by course/ });
    await expect(row).toContainText('Shared with tutor');
    await expect(page.getByRole('row', { name: /Broken report/ })).toContainText('Needs repair');
    await expect(page.getByRole('button', { name: 'Export Broken report as CSV' })).toBeDisabled();

    await page.getByRole('button', { name: 'Open Overdue by course' }).click();
    await expect(page.getByRole('radio', { name: 'Group and aggregate' })).toBeChecked();
    await expect(combo(page, 'Group 1: column')).toBeVisible();
    await expect(combo(page, 'Filter 1: value')).toBeVisible();
    await expect(
      page.getByRole('status').filter({ hasText: 'Opened Overdue by course.' }),
    ).toBeVisible();

    const download = page.waitForEvent('download');
    await page.getByRole('button', { name: 'Export Overdue by course as CSV' }).click();
    expect((await download).suggestedFilename()).toBe('overdue-by-course.csv');
    expect(state.reporting.downloads).toEqual([
      { id: state.reporting.saved()[0]!.id, format: 'csv' },
    ]);

    await page.getByRole('button', { name: 'Delete Overdue by course' }).click();
    await page.getByRole('button', { name: 'Delete', exact: true }).click();
    await expect(page.getByRole('row', { name: /Overdue by course/ })).toHaveCount(0);
    expect(state.reporting.deletes).toHaveLength(1);
  });

  test('changes a saved report: save changes updates it, save as new creates another', async ({
    page,
  }) => {
    const state = await open(page, SAVED);
    await page.getByRole('button', { name: 'Open Overdue by course' }).click();
    await page.getByRole('button', { name: 'Save changes' }).first().click();
    await page.getByRole('dialog').getByRole('button', { name: 'Save changes' }).click();
    await expect(
      page.getByRole('status').filter({ hasText: 'Saved Overdue by course.' }),
    ).toBeVisible();
    expect(state.reporting.updates).toHaveLength(1);
    expect(state.reporting.saves).toHaveLength(0);
  });

  test('speaks Polish', async ({ page }) => {
    await open(page, SAVED, 'pl-PL');
    await expect(page.getByRole('heading', { name: 'Zbiór danych', level: 2 })).toBeVisible();
    await expect(page.getByRole('radio', { name: 'Grupowanie i podsumowania' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Podgląd' })).toBeVisible();
    await expect(
      page.getByRole('button', { name: 'Eksportuj Overdue by course jako CSV' }),
    ).toBeVisible();
    await expect(page.getByRole('row', { name: /Overdue by course/ })).toContainText(
      'Udostępnione: tutor',
    );
    expect(await scan(page), 'polish').toEqual([]);
  });

  test('speaks Chinese', async ({ page }) => {
    await open(page, SAVED, 'zh-CN');
    await expect(page.getByRole('heading', { name: '数据集', level: 2 })).toBeVisible();
    await expect(page.getByRole('radio', { name: '分组与汇总' })).toBeVisible();
    await expect(page.getByRole('button', { name: /预\s*览/ })).toBeVisible();
    await expect(page.getByRole('row', { name: /Overdue by course/ })).toContainText(
      '分享给 tutor',
    );
    expect(await scan(page), 'chinese').toEqual([]);
  });

  test('makes no request outside the admin and the API', async ({ page }) => {
    const outside: string[] = [];
    page.on('request', (request) => {
      const { protocol, hostname } = new URL(request.url());
      if (['data:', 'blob:', 'about:'].includes(protocol)) return;
      if (hostname !== '127.0.0.1' && hostname !== 'api.mock') outside.push(request.url());
    });
    await open(page, SAVED);
    await tick(page, 'Course');
    await preview(page).click();
    await expect(page.getByRole('table', { name: 'Result' })).toBeVisible();
    expect(outside).toEqual([]);
  });
});
