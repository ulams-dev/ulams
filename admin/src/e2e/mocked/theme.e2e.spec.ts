import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';
import { expect, test } from '@playwright/test';
import { mockApi, PNG, type MockTheme } from './mockApi';

/**
 * Settings → Theme in a real browser against the mocked API (mockApi.ts): axe (WCAG 2.2 AA) in every state,
 * the contrast report and the save block, the logo (client checks, the API's refusal, remove), the brand
 * import as a diff to approve, keyboard use, Polish, and the air-gap guard.
 */

const THEME_URL = '/configuration/settings/theme_settings';
const WCAG = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
const STORED: MockTheme = {
  theme: 'poland',
  accent: '#c8102e',
  logo: 'http://api.mock/storage/theme/logo-1.png',
  logoAlt: 'Acme Coffee logo',
};

const guardNetwork = (page: Page) => {
  const outside: string[] = [];
  page.on('request', (request) => {
    const { protocol, hostname } = new URL(request.url());
    if (protocol === 'data:' || protocol === 'blob:' || protocol === 'about:') return;
    if (hostname !== '127.0.0.1' && hostname !== 'api.mock') outside.push(request.url());
  });
  return outside;
};

/** antd fades buttons between states: axe has to see the colours they settle on, not the middle of the fade. */
const settle = async (page: Page) => {
  const colours = () =>
    page.evaluate(() =>
      [...document.querySelectorAll('.theme-settings .ant-btn')]
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

const scan = async (page: Page) => {
  await settle(page);
  const result = await new AxeBuilder({ page }).include('.theme-settings').withTags(WCAG).analyze();
  return result.violations.map((v) => ({
    id: v.id,
    impact: v.impact,
    nodes: v.nodes.slice(0, 3).map((n) => `${n.target.join(' ')} | ${n.html.slice(0, 120)}`),
  }));
};

const open = async (page: Page, theme: MockTheme | undefined = STORED, locale?: string) => {
  const state = await mockApi(page, { theme: theme ?? {}, locale });
  await page.goto(THEME_URL);
  await expect(page.locator('.theme-settings')).toBeVisible();
  return state;
};

const accent = (page: Page) => page.getByLabel('Accent colour (hex)');
const save = (page: Page) => page.getByRole('button', { name: 'Save theme' });
const row = (page: Page, id: string) => page.locator(`tr[data-check="${id}"]`);

test.describe('admin theme screen', () => {
  test('axe: the stored theme, a failing accent, the logo error and the brand diff have no violations', async ({
    page,
  }) => {
    const outside = guardNetwork(page);
    await open(page);
    expect(await scan(page), 'stored theme').toEqual([]);

    await accent(page).fill('#f5c06a');
    await expect(page.getByText(/checks? fails? WCAG 2\.2 AA/)).toBeVisible();
    expect(await scan(page), 'failing accent report').toEqual([]);
    await save(page).click();
    await expect(page.getByRole('alert').filter({ hasText: 'not saved' })).toBeVisible();
    expect(await scan(page), 'save blocked').toEqual([]);

    await page
      .locator('#theme-logo-file')
      .setInputFiles({ name: 'logo.gif', mimeType: 'image/gif', buffer: Buffer.from('GIF89a') });
    await expect(page.getByRole('alert').filter({ hasText: 'PNG or SVG' })).toBeVisible();
    expect(await scan(page), 'logo error').toEqual([]);

    await page.getByLabel(/Brand colours/).fill('{"primary":"#a84521","background":"#ffffff"}');
    await page.getByRole('button', { name: 'Propose a theme' }).click();
    await expect(page.getByTestId('brand-diff')).toBeVisible();
    expect(await scan(page), 'brand diff').toEqual([]);
    expect(outside).toEqual([]);
  });

  test('shows every preset as a labelled radio and changes it with the keyboard', async ({
    page,
  }) => {
    await open(page);
    const radios = page.getByRole('radio');
    await expect(radios).toHaveCount(6);
    await expect(page.getByRole('radio', { name: /Poland, Measured/ })).toBeChecked();
    await page.getByRole('radio', { name: /Poland, Measured/ }).focus();
    await page.keyboard.press('ArrowRight');
    await expect(page.getByRole('radio', { name: /The Scottish Book/ })).toBeChecked();
    await expect(save(page)).toBeEnabled();
  });

  test('reports the ratios of the chosen colours on the preset', async ({ page }) => {
    await open(page);
    await expect(page.getByText('Every check passes WCAG 2.2 AA.')).toBeVisible();
    await expect(row(page, 'accent-text-page')).toContainText('#c8102e');
    await expect(row(page, 'accent-text-page')).toContainText('4.5:1 (1.4.3)');
    await expect(row(page, 'accent-ui-page')).toContainText('3:1 (1.4.11)');
    await expect(row(page, 'accent-text-page').locator('td').last()).toHaveText('Pass');
    // the same accent on a dark preset: the report is judged on that preset's background
    await page.getByRole('radio', { name: /Night Sky Explorers/ }).check();
    await expect(row(page, 'accent-text-page')).toContainText('#13153a');
  });

  test('says exactly what fails, blocks the save and saves the readable shade it suggests', async ({
    page,
  }) => {
    const state = await open(page);
    await accent(page).fill('#f5c06a');
    const text = row(page, 'accent-text-page');
    await expect(text).toHaveAttribute('data-pass', 'false');
    await expect(text.locator('td').last()).toHaveText('Fail');
    await expect(page.locator('.theme-fail')).toContainText('checks fail WCAG 2.2 AA');

    await save(page).click();
    const alert = page.getByRole('alert').filter({ hasText: 'not saved' });
    await expect(alert).toContainText('Accent #f5c06a as text on the page background is');
    await expect(alert).toContainText('below the 4.5:1 that WCAG 2.2 AA (1.4.3) asks for');
    await expect(alert).toContainText('1.4.11');
    expect(state.themeWrites).toEqual([]);

    await page
      .getByRole('button', { name: /Use #[0-9a-f]{6}, the nearest readable shade/ })
      .click();
    await expect(page.getByText('Every check passes WCAG 2.2 AA.')).toBeVisible();
    await save(page).click();
    await expect(page.getByText('Theme saved.')).toBeVisible();
    expect(state.themeWrites).toHaveLength(1);
    expect(state.themeWrites[0]).toMatchObject({ method: 'PUT', key: 'accent' });
    expect(state.theme().accent).toMatch(/^#[0-9a-f]{6}$/);
    expect(state.theme().accent).not.toBe('#f5c06a');
  });

  test('refuses an accent that is not a hex colour', async ({ page }) => {
    const state = await open(page);
    await accent(page).fill('red');
    await expect(
      page.getByText('The accent must be a six digit hex colour such as #c8102e.').first(),
    ).toBeVisible();
    await save(page).click();
    await expect(page.getByRole('alert').filter({ hasText: 'not saved' })).toBeVisible();
    expect(state.themeWrites).toEqual([]);
  });

  test('saves a new preset and accent, creating the settings that did not exist', async ({
    page,
  }) => {
    const state = await open(page, {});
    await page.getByRole('radio', { name: /Gravity Lab/ }).check();
    await accent(page).fill('#3dd6f5');
    await save(page).click();
    await expect(page.getByText('Theme saved.')).toBeVisible();
    expect(state.themeWrites.map((w) => `${w.method} ${w.key} ${w.value}`)).toEqual([
      'POST theme gravity',
      'POST accent #3dd6f5',
    ]);
    expect(state.theme()).toEqual({ theme: 'gravity', accent: '#3dd6f5' });
    await expect(save(page)).toBeDisabled();
  });

  test('uploads a PNG logo with its alt text and shows it', async ({ page }) => {
    const state = await open(page, { theme: 'coffee' });
    await expect(
      page.getByText('No logo: the learner header shows the company name as text.'),
    ).toBeVisible();
    await page
      .locator('#theme-logo-file')
      .setInputFiles({ name: 'logo.png', mimeType: 'image/png', buffer: PNG });
    await page.getByLabel('Text alternative of the logo').fill('Acme Coffee, home');
    await expect(page.getByTestId('logo-preview')).toHaveAttribute('alt', 'Acme Coffee, home');
    await save(page).click();
    await expect(page.getByText('Theme saved.')).toBeVisible();
    expect(state.logoUploads).toEqual([
      expect.objectContaining({ name: 'logo.png', type: 'image/png', alt: 'Acme Coffee, home' }),
    ]);
    await expect(page.getByTestId('logo-preview')).toHaveAttribute(
      'src',
      /storage\/theme\/logo-1\.png/,
    );
  });

  test('refuses a wrong type or a file over 200 KB before sending it', async ({ page }) => {
    const state = await open(page, { theme: 'coffee' });
    await page
      .locator('#theme-logo-file')
      .setInputFiles({ name: 'logo.jpg', mimeType: 'image/jpeg', buffer: PNG });
    await expect(
      page.getByRole('alert').filter({ hasText: 'The logo must be a PNG or SVG file.' }),
    ).toBeVisible();
    await page
      .locator('#theme-logo-file')
      .setInputFiles({
        name: 'big.png',
        mimeType: 'image/png',
        buffer: Buffer.alloc(200 * 1024 + 1),
      });
    await expect(
      page.getByRole('alert').filter({ hasText: 'The logo may not be larger than 200 KB.' }),
    ).toBeVisible();
    await expect(save(page)).toBeDisabled();
    expect(state.logoUploads).toEqual([]);
  });

  test("shows the API's reason when it refuses an SVG", async ({ page }) => {
    const state = await open(page, {
      theme: 'coffee',
      logoRefusal: 'The element <script> is not allowed in a logo.',
    });
    await page
      .locator('#theme-logo-file')
      .setInputFiles({
        name: 'logo.svg',
        mimeType: 'image/svg+xml',
        buffer: Buffer.from('<svg xmlns="http://www.w3.org/2000/svg"><script/></svg>'),
      });
    await save(page).click();
    await expect(
      page.getByRole('alert').filter({ hasText: 'The element <script> is not allowed in a logo.' }),
    ).toBeVisible();
    expect(state.logoUploads).toEqual([]);
    expect(state.themeWrites).toEqual([]);
  });

  test('removes the logo on save', async ({ page }) => {
    const state = await open(page);
    await expect(page.getByTestId('logo-preview')).toBeVisible();
    await page.getByRole('button', { name: 'Remove the logo' }).click();
    await expect(page.getByText('The logo is removed when you save.')).toBeVisible();
    await save(page).click();
    await expect(page.getByText('Theme saved.')).toBeVisible();
    expect(state.logoRemovals()).toBe(1);
    await expect(page.getByTestId('logo-preview')).toHaveCount(0);
  });

  test('changes only the alt text of the stored logo', async ({ page }) => {
    const state = await open(page);
    await page.getByLabel('Text alternative of the logo').fill('Acme, the coffee people');
    await save(page).click();
    await expect(page.getByText('Theme saved.')).toBeVisible();
    expect(state.logoUploads).toEqual([]);
    expect(state.themeWrites).toEqual([
      { method: 'PUT', key: 'logoAlt', value: 'Acme, the coffee people' },
    ]);
  });

  test('imports brand colours as a diff that changes nothing until it is approved', async ({
    page,
  }) => {
    const state = await open(page, { theme: 'oncall', accent: '#58a6ff' });
    await page
      .getByLabel(/Brand colours/)
      .fill('{"primary":"#a84521","background":"#ffffff","text":"#1a1a1a"}');
    await page.getByRole('button', { name: 'Propose a theme' }).click();
    const diff = page.getByTestId('brand-diff');
    await expect(diff.getByRole('row', { name: /Preset/ })).toContainText('On-Call');
    await expect(diff.getByRole('row', { name: /Preset/ })).toContainText('The Coffee Atlas');
    await expect(diff.getByRole('row', { name: /Accent colour/ })).toContainText('#58a6ff');
    await expect(diff.getByRole('row', { name: /Accent colour/ })).toContainText('#a84521');
    expect(state.themeWrites).toEqual([]);

    await diff.getByRole('button', { name: 'Discard' }).click();
    await expect(diff).toHaveCount(0);
    expect(state.themeWrites).toEqual([]);

    await page.getByRole('button', { name: 'Propose a theme' }).click();
    await page.getByTestId('brand-diff').getByRole('button', { name: 'Approve and save' }).click();
    await expect(page.getByText('Theme saved.')).toBeVisible();
    expect(state.theme()).toEqual({ theme: 'coffee', accent: '#a84521' });
  });

  test('proposes the readable shade when the brand colour is too weak, and says so', async ({
    page,
  }) => {
    const state = await open(page, { theme: 'coffee', accent: '#a84521' });
    await page.getByLabel(/Brand colours/).fill('{"primary":"#f5c06a","background":"#ffffff"}');
    await page.getByRole('button', { name: 'Propose a theme' }).click();
    const diff = page.getByTestId('brand-diff');
    await expect(diff).toContainText(
      'The brand colour #f5c06a is too weak for text on this preset',
    );
    const after = (await diff
      .getByRole('row', { name: /Accent colour/ })
      .locator('td')
      .last()
      .textContent())!;
    expect(after).toMatch(/^#[0-9a-f]{6}$/);
    expect(after).not.toBe('#f5c06a');
    await diff.getByRole('button', { name: 'Approve and save' }).click();
    await expect(page.getByText('Theme saved.')).toBeVisible();
    expect(state.theme().accent).toBe(after);
  });

  test('says when there is no brand colour in the text, and when the proposal changes nothing', async ({
    page,
  }) => {
    await open(page, { theme: 'coffee', accent: '#a84521' });
    await page.getByLabel(/Brand colours/).fill('We love coffee');
    await page.getByRole('button', { name: 'Propose a theme' }).click();
    await expect(page.getByText('No brand colour was found in that text.')).toBeVisible();
    await page.getByLabel(/Brand colours/).fill('{"primary":"#a84521","background":"#ffffff"}');
    await page.getByRole('button', { name: 'Propose a theme' }).click();
    await expect(
      page.getByText('The proposal matches the current theme: nothing to approve.'),
    ).toBeVisible();
    await expect(page.getByRole('button', { name: 'Approve and save' })).toBeDisabled();
  });

  test('is in Polish with no violations', async ({ page }) => {
    await open(page, STORED, 'pl-PL');
    await expect(page.getByRole('button', { name: 'Zapisz motyw' })).toBeVisible();
    await expect(page.getByText('Wszystkie sprawdzenia spełniają WCAG 2.2 AA.')).toBeVisible();
    expect(await scan(page)).toEqual([]);
  });
});
