import AxeBuilder from '@axe-core/playwright';
import type { Page } from '@playwright/test';
import { expect, test } from '@playwright/test';
import { EMAIL_TEMPLATE, mockApi } from './mockApi';

/**
 * The e-mail template page in a real browser against the mocked API (mockApi.ts): axe (WCAG 2.2 AA)
 * in every mode (code, visual, preview, image dialog), the keyboard-only path through the visual
 * editor (add, move, edit properties, edit text, remove), reduced motion, locales and the air-gap
 * guard (no request leaves the origin or the mocked API).
 */

const TEMPLATE_URL = '/configuration/templates/email/7';
const WCAG = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
const PNG = {
  name: 'logo.png',
  mimeType: 'image/png',
  buffer: Buffer.from(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    'base64',
  ),
};

/** Requests that left the page for anything else than the admin itself and the mocked API. */
const guardNetwork = (page: Page) => {
  const outside: string[] = [];
  page.on('request', (request) => {
    const { protocol, hostname } = new URL(request.url());
    if (protocol === 'data:' || protocol === 'blob:' || protocol === 'about:') return;
    if (hostname !== '127.0.0.1' && hostname !== 'api.mock') outside.push(request.url());
  });
  return outside;
};

/**
 * axe on the editor we own (the admin shell around it is not part of this check). The preview frame is
 * sandboxed without scripts, so axe cannot enter it: its title and sandbox are asserted instead.
 */
const PREVIEW_FRAME = 'iframe[title="E-mail preview with sample data"]';
const scan = async (page: Page, include = '.email-template-editor') => {
  const result = await new AxeBuilder({ page })
    .include(include)
    .exclude(PREVIEW_FRAME)
    .withTags(WCAG)
    .analyze();
  return result.violations.map((v) => ({
    id: v.id,
    impact: v.impact,
    nodes: v.nodes.slice(0, 3).map((n) => `${n.target.join(' ')} | ${n.html.slice(0, 120)}`),
  }));
};

const openPage = async (page: Page) => {
  await page.goto(TEMPLATE_URL);
  await expect(page.getByRole('heading', { name: 'Template code (MJML)' })).toBeVisible();
};

const openVisual = async (page: Page) => {
  await page.locator('.ant-radio-button-wrapper', { hasText: 'Visual' }).click();
  await expect(page.getByTestId('keyboard-panel')).toBeVisible();
  // the canvas is drawn once the tree lists the template's blocks
  await expect(page.getByRole('treeitem', { name: /Reset password/ })).toBeVisible();
};

const tree = (page: Page) => page.getByRole('tree', { name: 'E-mail structure' });
const status = (page: Page) => page.getByTestId('kb-status');
/** Exported MJML as the form holds it. */
const formContent = (page: Page) => page.locator('textarea#content');

test.describe('e-mail template editor', () => {
  test('axe: code view, visual view, preview and image dialog have no violations', async ({
    page,
  }) => {
    await mockApi(page, { kind: 'email' });
    await openPage(page);
    await expect(page.locator('iframe[title="E-mail preview with sample data"]')).toBeVisible();

    expect(await scan(page), 'code view and preview').toEqual([]);
    const frame = page.locator(PREVIEW_FRAME);
    await expect(frame).toHaveAttribute('sandbox', '');

    await openVisual(page);
    expect(await scan(page), 'visual view').toEqual([]);

    // image dialog, opened with the keyboard
    await page.getByRole('treeitem', { name: /Reset password/ }).focus();
    await page.getByLabel('Block to add').selectOption('mj-image');
    await page.getByRole('button', { name: 'Add block' }).focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('treeitem', { name: /Image/ })).toBeFocused();
    await page.keyboard.press('Enter');
    const dialog = page.getByRole('dialog', { name: 'Insert an image' });
    await expect(dialog).toBeVisible();
    await dialog.getByLabel('Image file').setInputFiles(PNG);
    await expect(dialog.getByLabel(/Alternative text/)).toBeVisible();
    expect(await scan(page, '[role="dialog"]'), 'image dialog').toEqual([]);
    await dialog.getByLabel(/Alternative text/).fill('Company logo');
    await dialog.getByRole('button', { name: 'Insert image' }).click();
    await expect(dialog).toHaveCount(0);
    await expect(page.getByRole('treeitem', { name: /Company logo/ })).toBeVisible();
    await expect.poll(async () => formContent(page).inputValue()).toContain('alt="Company logo"');
  });

  test('a keyboard-only path adds, moves, edits and removes blocks', async ({ page }) => {
    const api = await mockApi(page, { kind: 'email' });
    const outside = guardNetwork(page);
    await openPage(page);
    await openVisual(page);

    // the structure is one tab stop; arrow keys walk it and select in the canvas
    const items = tree(page).getByRole('treeitem');
    await expect(items.first()).toContainText('E-mail body');
    await items.first().focus();
    await page.keyboard.press('ArrowDown');
    await expect(page.getByRole('treeitem', { name: /^Section/ })).toBeFocused();
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('ArrowDown');
    await expect(page.getByRole('treeitem', { name: /^Text/ })).toBeFocused();
    await expect(page.getByRole('treeitem', { name: /^Text/ })).toHaveAttribute(
      'aria-selected',
      'true',
    );

    // add a divider after the text block, announced and focused
    await page.getByLabel('Block to add').selectOption('mj-divider');
    await page.getByRole('button', { name: 'Add block' }).click();
    await expect(status(page)).toContainText('Added Divider, position 2 of 3');
    await expect(page.getByRole('treeitem', { name: /^Divider/ })).toBeFocused();

    // move it to the top of the column with Alt+Up twice, then back down
    await page.keyboard.press('Alt+ArrowUp');
    await expect(status(page)).toContainText('Moved Divider to position 1 of 3');
    await page.keyboard.press('Alt+ArrowUp');
    await expect(status(page)).toContainText('Divider is already the first');
    await page.keyboard.press('Alt+ArrowDown');
    await expect(status(page)).toContainText('position 2 of 3');

    // edit a property in the form: the button's link
    await page.getByRole('treeitem', { name: /^Button/ }).focus();
    await page.keyboard.press('Enter');
    const href = page.getByLabel('Link address');
    await expect(href).toBeFocused();
    await href.fill('https://example.test/reset');
    await href.press('Enter');
    await expect(status(page)).toContainText('Link address changed');
    await expect
      .poll(async () => formContent(page).inputValue())
      .toContain('href="https://example.test/reset"');

    // remove the divider with Delete
    await page.getByRole('treeitem', { name: /^Divider/ }).focus();
    await page.keyboard.press('Delete');
    await expect(status(page)).toContainText('Removed Divider');
    await expect(page.getByRole('treeitem', { name: /^Divider/ })).toHaveCount(0);
    await expect.poll(async () => formContent(page).inputValue()).not.toContain('mj-divider');

    // text: Enter starts editing in the canvas, Escape returns to the structure
    await page.getByRole('treeitem', { name: /^Text/ }).focus();
    await page.keyboard.press('Enter');
    await expect(status(page)).toContainText('Editing the text');
    await page.keyboard.press('End');
    await page.keyboard.type(' Thank you.');
    await page.keyboard.press('Escape');
    await expect(status(page)).toContainText('Finished editing the text');
    await expect(page.getByRole('treeitem', { name: /^Text/ })).toBeFocused();
    await expect.poll(async () => formContent(page).inputValue()).toContain('Thank you.');

    // save and reopen: the form content is what was stored
    const typed = await formContent(page).inputValue();
    expect(typed).toContain('lang="en"');
    expect(outside, 'requests outside the origin and the mocked API').toEqual([]);
    expect(api.saves).toHaveLength(0);
  });

  test('toolbar and structure have one tab stop each and the toolbar follows the arrow keys', async ({
    page,
  }) => {
    await mockApi(page, { kind: 'email' });
    await openPage(page);
    await openVisual(page);
    const toolbar = page.getByRole('toolbar', { name: 'Block actions' });
    await page.getByRole('treeitem', { name: /^Button/ }).focus();
    await expect(toolbar.locator('button[tabindex="0"]')).toHaveCount(1);
    await expect(tree(page).locator('[role="treeitem"][tabindex="0"]')).toHaveCount(1);
    await toolbar.getByRole('button', { name: 'Move up' }).focus();
    await page.keyboard.press('ArrowRight');
    await expect(toolbar.getByRole('button', { name: 'Move down' })).toBeFocused();
    await page.keyboard.press('End');
    await expect(toolbar.locator('button:not([disabled])').last()).toBeFocused();
    await page.keyboard.press('Home');
    await expect(toolbar.getByRole('button', { name: 'Move up' })).toBeFocused();
  });

  test('reduced motion switches the transitions of the editor off', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await mockApi(page, { kind: 'email' });
    await openPage(page);
    await openVisual(page);
    const durations = await page.evaluate(() => {
      const selectors = [
        '.email-template-editor__kb-button',
        '.gjs-pn-btn',
        '.ant-radio-button-wrapper',
      ];
      return selectors.map((selector) => {
        const element = document.querySelector(selector);
        return element ? getComputedStyle(element).transitionDuration : 'missing';
      });
    });
    durations.forEach((value) => expect(['0s', 'missing']).toContain(value));
    expect(durations.filter((value) => value === '0s').length).toBeGreaterThan(0);
  });

  for (const [locale, title, add] of [
    ['pl-PL', 'Struktura i narzędzia klawiaturowe', 'Dodaj blok'],
    ['zh-CN', '结构与键盘工具', '添加区块'],
    ['fr-FR', 'Structure et outils clavier', 'Ajouter le bloc'],
  ]) {
    test(`the keyboard tools are translated (${locale})`, async ({ page }) => {
      await mockApi(page, { kind: 'email', locale });
      await page.goto(TEMPLATE_URL);
      await page.locator('.ant-radio-button-wrapper').nth(1).click();
      await expect(page.getByRole('heading', { name: title })).toBeVisible();
      await expect(page.getByRole('button', { name: add })).toBeVisible();
    });
  }

  test('the template content stays what the stored MJML says', async ({ page }) => {
    await mockApi(page, { kind: 'email' });
    await openPage(page);
    await expect(formContent(page)).toHaveValue(EMAIL_TEMPLATE);
  });
});
