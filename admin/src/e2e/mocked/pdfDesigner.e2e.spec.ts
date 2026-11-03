import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';
import { expect, test } from '@playwright/test';
import { EVENT, LEGACY_TEMPLATE, mockApi } from './mockApi';

/**
 * The pdfme designer in the admin, against the mocked API (mockApi.ts): open a template, add and
 * edit fields, preview through the renderer endpoint, save and reopen. Plus the legacy ReportBro
 * banner and axe (WCAG 2.2 AA) on the screens we own.
 */

const TEMPLATE_URL = '/configuration/templates/pdf/7';
const WCAG = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

const openDesigner = async (page: Page) => {
  await page.goto(TEMPLATE_URL);
  await expect(page.getByText('Edit pdf Template')).toBeVisible();
  // the designer is mounted once the field list shows the template's fields
  await expect(page.getByText('Field List')).toBeVisible();
};

const fieldList = (page: Page) => page.locator('.pdf-editor__designer');

const saved = (api: { content: () => string }) =>
  (JSON.parse(api.content()).schemas[0] as { name: string; fontSize?: number }[]).map(
    (field) => field,
  );

test.describe('pdfme designer', () => {
  test('opens a template, adds and edits fields, previews, saves and reopens', async ({ page }) => {
    const api = await mockApi(page);
    await openDesigner(page);

    // the template's field is listed, its required variable counts as used
    await expect(fieldList(page).getByText('VarUserName')).toBeVisible();
    await expect(page.getByText('Required variables')).toHaveCount(0);

    // add a field from the variables panel: pointer, then keyboard
    await page.getByRole('button', { name: 'Add a field for @VarCourseTitle' }).click();
    await expect(fieldList(page).getByText('@VarCourseTitle')).toBeVisible();
    await page.getByRole('button', { name: 'Add a field for @VarFinishDate' }).focus();
    await page.keyboard.press('Enter');
    await expect(fieldList(page).getByText('@VarFinishDate')).toBeVisible();

    // edit a field: select it and change its font size in the detail panel
    await fieldList(page).getByText('VarUserName').click();
    const fontSize = fieldList(page).locator('input#fontSize');
    await expect(fontSize).toHaveValue('28');
    await fontSize.fill('32');
    await fontSize.press('Tab');
    await expect(fontSize).toHaveValue('32');
    // the designer reports the change to the form (the hidden template field) before we preview or save
    await expect(page.locator('textarea#content')).toHaveValue(/"fontSize":32/);

    // preview: the renderer endpoint gets the event and the edited template
    await page.getByRole('button', { name: 'Preview' }).click();
    await expect(page.getByRole('dialog').locator('iframe[title="PDF preview"]')).toBeVisible();
    expect(api.previews).toHaveLength(1);
    expect(api.previews[0].event).toBe(EVENT);
    const previewed = api.previews[0].content.schemas[0] as { name: string; fontSize?: number }[];
    expect(previewed.map((field) => field.name)).toEqual([
      '@VarUserName',
      '@VarCourseTitle',
      '@VarFinishDate',
    ]);
    expect(previewed[0].fontSize).toBe(32);
    await page.getByRole('dialog').getByRole('button', { name: 'Close' }).click();
    await expect(page.getByRole('dialog')).toHaveCount(0);

    // save from the designer: one save request with the whole template
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect.poll(() => api.saves.length).toBe(1);
    expect(api.saves[0].event).toBe(EVENT);
    expect(saved(api).map((field) => field.name)).toEqual([
      '@VarUserName',
      '@VarCourseTitle',
      '@VarFinishDate',
    ]);
    expect(saved(api)[0].fontSize).toBe(32);

    // reopen: the saved template comes back from the API
    await page.reload();
    await expect(page.getByText('Field List')).toBeVisible();
    for (const name of ['VarUserName', '@VarCourseTitle', '@VarFinishDate']) {
      await expect(fieldList(page).getByText(name)).toBeVisible();
    }
    await fieldList(page).getByText('VarUserName').click();
    await expect(fieldList(page).locator('input#fontSize')).toHaveValue('32');
  });

  test('a variable that already has a field is not added twice', async ({ page }) => {
    const api = await mockApi(page);
    await openDesigner(page);
    await page.getByRole('button', { name: 'Add a field for @VarUserName' }).click();
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect.poll(() => api.saves.length).toBe(1);
    expect(saved(api)).toHaveLength(1);
  });

  test('the variables panel and the toolbar have no WCAG 2.2 AA violations', async ({ page }) => {
    await mockApi(page);
    await openDesigner(page);
    const results = await new AxeBuilder({ page })
      .withTags(WCAG)
      .include('.pdf-editor__variables')
      .analyze();
    expect(
      results.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target).join(' | ')}`),
    ).toEqual([]);
  });
});

test.describe('legacy ReportBro template', () => {
  test('shows the banner, is not saved unconverted and can start over from the default', async ({
    page,
  }) => {
    const api = await mockApi(page, { template: LEGACY_TEMPLATE });
    await page.goto(TEMPLATE_URL);
    const banner = page.getByRole('alert').filter({ hasText: 'ReportBro' });
    await expect(banner).toBeVisible();
    await expect(page.getByText('Field List')).toHaveCount(0);

    // submitting the form with the unconverted content is refused by the form validation
    await page.getByRole('button', { name: 'Submit' }).click();
    await expect(page.getByText('No required variable used')).toBeAttached();
    expect(api.saves).toHaveLength(0);

    // accessible banner
    const results = await new AxeBuilder({ page }).withTags(WCAG).include('.ant-alert').analyze();
    expect(results.violations.map((v) => v.id)).toEqual([]);

    // start over: the designer opens with the default template and the form can be saved
    await page.getByRole('button', { name: 'Start from the default template' }).click();
    await expect(page.getByText('Field List')).toBeVisible();
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect.poll(() => api.saves.length).toBe(1);
    expect(JSON.parse(api.content()).schemas).toBeDefined();
  });
});
