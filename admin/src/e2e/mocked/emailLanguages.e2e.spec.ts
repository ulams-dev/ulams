import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';
import { expect, test } from '@playwright/test';
import { EMAIL_TEMPLATE, mockApi, type MockSibling } from './mockApi';

/**
 * Templates per language (M6) in a real browser against the mocked API: the language strip with its
 * missing-translation indicator (axe, WCAG 2.2 AA), switching between the templates of an event, creating a
 * missing translation as a copy, the copy helper, the per-language test send and the translated strings.
 */

const WCAG = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
const TEMPLATE_URL = '/configuration/templates/email/7';
const ZH_TEMPLATE = EMAIL_TEMPLATE.replace(
  'Hello @VarUserName, you asked',
  '你好 @VarUserName，你申请了',
);
const ZH: MockSibling = { id: 8, locale: 'zh', name: 'Password reset (zh)', content: ZH_TEMPLATE };

const scan = async (page: Page, include: string) => {
  const result = await new AxeBuilder({ page }).include(include).withTags(WCAG).analyze();
  return result.violations.map((v) => ({
    id: v.id,
    impact: v.impact,
    nodes: v.nodes.slice(0, 3).map((n) => `${n.target.join(' ')} | ${n.html.slice(0, 120)}`),
  }));
};

const strip = (page: Page) => page.locator('section.template-languages');
const item = (page: Page, language: string) =>
  strip(page).locator(`li[data-language="${language}"]`);
const formContent = (page: Page) => page.locator('textarea#content');

const open = async (page: Page, url = TEMPLATE_URL) => {
  await page.goto(url);
  await expect(strip(page).getByRole('heading', { name: 'Languages' })).toBeVisible();
};

test.describe('e-mail templates per language', () => {
  test('axe: the language strip marks the missing translation in words and has no violations', async ({
    page,
  }) => {
    await mockApi(page, { kind: 'email', templateLocale: 'en', siblings: [ZH] });
    await open(page);

    await expect(item(page, 'en')).toContainText('This template');
    await expect(item(page, 'zh')).toContainText('Has a template');
    await expect(item(page, 'pl')).toContainText('Missing translation');
    await expect(strip(page).getByRole('status')).toContainText('Missing translations: Polski.');
    expect(await scan(page, '.template-languages'), 'language strip').toEqual([]);
  });

  test('every language present: no warning, a complete message', async ({ page }) => {
    await mockApi(page, {
      kind: 'email',
      templateLocale: 'en',
      siblings: [ZH, { id: 9, locale: 'pl', name: 'Password reset (pl)' }],
    });
    await open(page);

    await expect(strip(page).getByText('Every language has a template.')).toBeVisible();
    await expect(strip(page).getByRole('status')).toHaveCount(0);
    expect(await scan(page, '.template-languages'), 'complete strip').toEqual([]);
  });

  test('switching language opens the template of that language', async ({ page }) => {
    await mockApi(page, { kind: 'email', templateLocale: 'en', siblings: [ZH] });
    await open(page);
    await expect(page.getByRole('combobox', { name: 'Language' })).toBeVisible();

    await strip(page).getByRole('link', { name: 'Edit the 中文 template' }).click();

    await expect(page).toHaveURL(/\/configuration\/templates\/email\/8$/);
    await expect(item(page, 'zh')).toContainText('This template');
    await expect(formContent(page)).toHaveValue(ZH_TEMPLATE);
  });

  test('a missing translation is created as a copy of the template and saved in that language', async ({
    page,
  }) => {
    const api = await mockApi(page, { kind: 'email', templateLocale: 'en', siblings: [ZH] });
    await open(page);

    await strip(page).getByRole('link', { name: 'Create the Polski translation' }).click();

    await expect(page).toHaveURL(/\/configuration\/templates\/email\/new\?.*locale=pl/);
    await expect(page.getByLabel('Name', { exact: true })).toHaveValue('Password reset (pl)');
    await expect(
      page.locator('.ant-select-selection-item', { hasText: 'Polski' }).first(),
    ).toBeVisible();
    await expect(formContent(page)).toHaveValue(EMAIL_TEMPLATE);

    await page.getByRole('button', { name: /submit/i }).click();

    await expect.poll(() => api.creates.length).toBe(1);
    expect(api.creates[0]).toMatchObject({
      locale: 'pl',
      name: 'Password reset (pl)',
      default: true,
    });
    expect(api.creates[0].sections.find((s: { key: string }) => s.key === 'content').content).toBe(
      EMAIL_TEMPLATE,
    );
    // locale is a field of the template, never saved as a section
    expect(api.creates[0].sections.map((s: { key: string }) => s.key).sort()).toEqual([
      'content',
      'title',
    ]);
    await expect(page).toHaveURL(/\/configuration\/templates\/email\/101$/);
  });

  test('the copy helper replaces the content of the form after a confirmation', async ({
    page,
  }) => {
    await mockApi(page, { kind: 'email', templateLocale: 'en', siblings: [ZH] });
    await open(page);
    await expect(formContent(page)).toHaveValue(EMAIL_TEMPLATE);

    await page.getByRole('combobox', { name: 'Copy content from' }).click();
    await page.getByTitle(/中文 - Password reset \(zh\)/).click();
    await page.getByRole('button', { name: 'Copy content' }).click();
    await page.getByRole('button', { name: 'Yes' }).click();

    await expect(formContent(page)).toHaveValue(ZH_TEMPLATE);
    expect(await scan(page, '.template-languages'), 'copy helper').toEqual([]);
  });

  test('the test e-mail and the preview are rendered in the language of the template', async ({
    page,
  }) => {
    const api = await mockApi(page, { kind: 'email', templateLocale: 'pl', siblings: [ZH] });
    await open(page);
    await expect(page.getByText('The sample values and the test e-mail use Polski.')).toBeVisible();

    await page.getByRole('button', { name: 'Send a test e-mail' }).click();

    await expect.poll(() => api.testSends.length).toBe(1);
    expect(api.testSends[0]).toMatchObject({ locale: 'pl' });
    await expect.poll(() => api.renders.at(-1)).toMatchObject({ locale: 'pl' });
    await expect(page.getByText('Test e-mail sent to admin@example.test.')).toBeVisible();
  });

  test('a template without a language sends no language', async ({ page }) => {
    const api = await mockApi(page, { kind: 'email' });
    await open(page);

    await page.getByRole('button', { name: 'Send a test e-mail' }).click();

    await expect.poll(() => api.testSends.length).toBe(1);
    expect(api.testSends[0]).not.toHaveProperty('locale');
  });

  test('the list shows the language and the missing translations of each event', async ({
    page,
  }) => {
    await mockApi(page, { kind: 'email', templateLocale: 'en', siblings: [ZH] });
    await page.goto('/configuration/templates/email');

    const row = page.locator('tr', { hasText: 'Password reset' }).first();
    await expect(row.locator('.template-languages__tags')).toBeVisible();
    await expect(row.getByText('Polski: Missing translation')).toBeAttached();
    await expect(row.getByText('English: Has a template')).toBeAttached();
    expect(await scan(page, 'tr .template-languages__tags'), 'translation tags').toEqual([]);
  });

  for (const [locale, title, missing] of [
    ['pl-PL', 'Języki', 'Brak tłumaczenia'],
    ['zh-CN', '语言', '缺少翻译'],
    ['fr-FR', 'Langues', 'Traduction manquante'],
  ]) {
    test(`the language strip is translated (${locale})`, async ({ page }) => {
      await mockApi(page, { kind: 'email', templateLocale: 'en', siblings: [ZH], locale });
      await page.goto(TEMPLATE_URL);
      await expect(strip(page).getByRole('heading', { name: title })).toBeVisible();
      await expect(item(page, 'pl')).toContainText(missing);
      expect(await scan(page, '.template-languages'), `strip in ${locale}`).toEqual([]);
    });
  }
});
