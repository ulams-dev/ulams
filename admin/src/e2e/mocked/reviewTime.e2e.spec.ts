import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';
import { expect, test } from '@playwright/test';
import { mockApi } from './mockApi';

/**
 * Settings > Authoring metrics in a real browser against the mocked API (ADR 0194, AM-5): the notice that says what
 * review time records before an admin turns it on (axe, WCAG 2.2 AA), the two-step deletion of everything recorded, a
 * refusal, and the Polish and Chinese strings.
 */

const URL = '/configuration/settings/authoring_metrics';
const WCAG = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

const scan = async (page: Page) => {
  const result = await new AxeBuilder({ page })
    .include('.review-time-notice')
    .withTags(WCAG)
    .analyze();
  return result.violations.map((v) => ({
    id: v.id,
    impact: v.impact,
    nodes: v.nodes.slice(0, 3).map((n) => `${n.target.join(' ')} | ${n.html.slice(0, 120)}`),
  }));
};

const open = async (page: Page, options: Parameters<typeof mockApi>[1] = { reviewTime: {} }) => {
  const state = await mockApi(page, options);
  await page.goto(URL);
  await expect(page.locator('.review-time-notice')).toBeVisible();
  return state;
};

test.describe('Settings > Authoring metrics', () => {
  test('axe: the notice names what is measured, stored, not stored, shown and kept', async ({
    page,
  }) => {
    await open(page);
    const notice = page.locator('.review-time-notice');
    await expect(notice.getByRole('heading', { name: 'Review time' })).toBeVisible();
    await expect(notice).toContainText('Measured: whole minutes of active screen time');
    await expect(notice).toContainText(
      'Not stored: a name or account, an address, a browser or device',
    );
    await expect(notice).toContainText('never per person');
    await expect(notice).toContainText('deleted after 7 days');
    await expect(notice).toContainText('works council');
    expect(await scan(page)).toEqual([]);
  });

  test('deleting asks first, then deletes and says how much', async ({ page }) => {
    const state = await open(page);
    const notice = page.locator('.review-time-notice');
    await notice.getByRole('button', { name: 'Delete recorded review time' }).focus();
    await page.keyboard.press('Enter');
    await expect(notice.getByRole('group', { name: 'Delete recorded review time' })).toContainText(
      'It cannot be undone.',
    );
    expect(state.reviewTimeDeletes()).toBe(0);
    expect(await scan(page), 'confirming').toEqual([]);

    await notice.getByRole('button', { name: 'Cancel' }).click();
    expect(state.reviewTimeDeletes()).toBe(0);

    await notice.getByRole('button', { name: 'Delete recorded review time' }).click();
    await notice.getByRole('button', { name: 'Yes, delete it' }).click();
    await expect(notice.getByRole('status')).toContainText(
      'Deleted 42 minutes and 6 daily totals.',
    );
    expect(state.reviewTimeDeletes()).toBe(1);
    expect(await scan(page), 'deleted').toEqual([]);
  });

  test('a refusal is a sentence', async ({ page }) => {
    await open(page, { reviewTime: { deleteStatus: 403 } });
    const notice = page.locator('.review-time-notice');
    await notice.getByRole('button', { name: 'Delete recorded review time' }).click();
    await notice.getByRole('button', { name: 'Yes, delete it' }).click();
    await expect(notice.getByRole('status')).toContainText('Review time could not be deleted.');
    expect(await scan(page)).toEqual([]);
  });

  test('Polish and Chinese strings', async ({ page }) => {
    await open(page, { reviewTime: {}, locale: 'pl-PL' });
    await expect(page.locator('.review-time-notice')).toContainText('Czas przeglądu');
    await expect(page.locator('.review-time-notice')).toContainText('usuwane po 7 dniach');
    expect(await scan(page), 'pl').toEqual([]);
  });

  test('Chinese strings', async ({ page }) => {
    await open(page, { reviewTime: {}, locale: 'zh-CN' });
    await expect(page.locator('.review-time-notice')).toContainText('审阅时间');
    await expect(page.locator('.review-time-notice')).toContainText('7 天后删除');
    expect(await scan(page), 'zh').toEqual([]);
  });
});
