// Full-page screenshot capture for a list of targets x roles x pages x viewports.
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';

// Kills motion and blinking carets; hides scrollbars so overflow changes do not shift layout.
const FREEZE_CSS = `
*, *::before, *::after {
  animation-duration: 0s !important; animation-delay: 0s !important;
  animation-iteration-count: 1 !important; animation-play-state: paused !important;
  transition-duration: 0s !important; transition-delay: 0s !important;
  scroll-behavior: auto !important; caret-color: transparent !important;
}
html { scrollbar-width: none !important; }
::-webkit-scrollbar { display: none !important; }
video, iframe[src*="youtube"], iframe[src*="vimeo"] { visibility: hidden !important; }
`;

const log = (...a) => console.log(new Date().toISOString().slice(11, 19), ...a);

async function settle(page, { extraWaitMs = 0, waitFor } = {}) {
  await page.waitForLoadState('load').catch(() => {});
  await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
  if (waitFor) await page.locator(waitFor).first().waitFor({ timeout: 10000 }).catch(() => {});
  // Loading skeletons and spinners must be gone (best effort).
  await page
    .waitForFunction(
      () =>
        !document.querySelector(
          '.react-loading-skeleton, .ant-spin-spinning, .ant-skeleton-active, [aria-busy="true"]',
        ),
      null,
      { timeout: 10000 },
    )
    .catch(() => {});
  // Scroll through the page so lazy images and intersection-observer content render.
  await page.evaluate(async () => {
    const step = window.innerHeight;
    for (let y = 0; y < document.documentElement.scrollHeight; y += step) {
      window.scrollTo(0, y);
      await new Promise((r) => setTimeout(r, 60));
    }
    window.scrollTo(0, 0);
  });
  await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});
  await page.evaluate(async () => {
    await document.fonts.ready;
    await Promise.all(
      [...document.images]
        .filter((img) => !img.complete)
        .map((img) => new Promise((r) => { img.onload = img.onerror = r; setTimeout(r, 5000); })),
    );
  });
  await page.waitForTimeout(400 + extraWaitMs);
}

function safeName(s) {
  return s.replace(/[^a-z0-9._-]+/gi, '-').replace(/^-|-$/g, '');
}

/**
 * Logs a role in once per target and returns a Playwright storageState object.
 * role.login(page, target) performs the login; it must leave the token in storage.
 */
async function loginState(browser, target, role, opts) {
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: opts.locale });
  const page = await context.newPage();
  try {
    await role.login(page, target);
    return await context.storageState();
  } finally {
    await context.close();
  }
}

/**
 * config: { targets: [{ name, baseUrl, roles: { [role]: { login? } }, pages: [{ name, path, roles, ... }] }] }
 * Each page: name, path (appended to baseUrl), roles: ['guest','student'], optional
 * mask: [selectors], waitFor: selector, extraWaitMs, hide: [selectors], before(page, target).
 */
export async function captureAll(config, { outDir, viewports, only, targets: targetFilter, locale, fixedTime }) {
  fs.mkdirSync(outDir, { recursive: true });
  const browser = await chromium.launch();
  const results = [];
  const onlyRe = only ? new RegExp(only, 'i') : null;
  try {
    for (const target of config.targets) {
      if (targetFilter && !targetFilter.includes(target.name)) continue;
      const states = {};
      for (const [roleName, role] of Object.entries(target.roles)) {
        if (!role.login) { states[roleName] = undefined; continue; }
        const wanted = target.pages.some(
          (p) => p.roles.includes(roleName) && (!onlyRe || onlyRe.test(`${target.name}/${roleName}/${p.name}`)),
        );
        if (!wanted) continue;
        try {
          states[roleName] = await loginState(browser, target, role, { locale });
          log(`login ok   ${target.name}/${roleName}`);
        } catch (e) {
          states[roleName] = null;
          log(`login FAIL ${target.name}/${roleName}: ${e.message.split('\n')[0]}`);
        }
      }
      for (const pg of target.pages) {
        for (const roleName of pg.roles) {
          const id = `${target.name}/${roleName}/${pg.name}`;
          if (onlyRe && !onlyRe.test(id)) continue;
          for (const vp of viewports) {
            const file = path.join(target.name, roleName, `${safeName(pg.name)}@${vp.name}.png`);
            const rec = { file, target: target.name, role: roleName, page: pg.name, viewport: vp.name, url: null };
            if (states[roleName] === null) {
              results.push({ ...rec, status: 'skipped', error: 'login failed' });
              continue;
            }
            const context = await browser.newContext({
              viewport: { width: vp.width, height: vp.height },
              deviceScaleFactor: 1,
              isMobile: vp.isMobile ?? false,
              hasTouch: vp.isMobile ?? false,
              locale,
              timezoneId: 'UTC',
              colorScheme: 'light',
              reducedMotion: 'reduce',
              storageState: states[roleName],
            });
            const page = await context.newPage();
            try {
              if (fixedTime) await page.clock.setFixedTime(new Date(fixedTime));
              await page.addInitScript(({ css, ls }) => {
                try {
                  for (const [k, v] of Object.entries(ls)) if (localStorage.getItem(k) === null) localStorage.setItem(k, v);
                } catch {}
                const add = () => {
                  const s = document.createElement('style');
                  s.setAttribute('data-visual-freeze', '');
                  s.textContent = css;
                  document.head.appendChild(s);
                };
                if (document.head) add();
                else document.addEventListener('DOMContentLoaded', add);
                Math.random = (() => { let x = 42; return () => ((x = (x * 16807) % 2147483647) / 2147483647); })();
              }, { css: FREEZE_CSS, ls: target.init?.localStorage ?? {} });
              const pagePath = typeof pg.path === 'function' ? await pg.path(target) : pg.path;
              const url = new URL(pagePath, target.baseUrl).toString();
              rec.url = url;
              const resp = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
              if (pg.before) await pg.before(page, target);
              await settle(page, pg);
              const hide = [...(config.hide ?? []), ...(target.hide ?? []), ...(pg.hide ?? [])];
              if (hide.length) await page.addStyleTag({ content: `${hide.join(',')} { visibility: hidden !important; }` });
              const masks = [...(config.mask ?? []), ...(target.mask ?? []), ...(pg.mask ?? [])];
              const abs = path.join(outDir, file);
              fs.mkdirSync(path.dirname(abs), { recursive: true });
              await page.screenshot({
                path: abs,
                fullPage: true,
                animations: 'disabled',
                caret: 'hide',
                scale: 'css',
                mask: masks.map((m) => page.locator(m)),
                maskColor: '#FF00FF',
              });
              rec.finalUrl = page.url();
              rec.httpStatus = resp?.status();
              rec.title = await page.title();
              rec.status = 'ok';
              log(`ok   ${file}  -> ${rec.finalUrl}`);
            } catch (e) {
              rec.status = 'error';
              rec.error = e.message.split('\n')[0];
              log(`FAIL ${file}: ${rec.error}`);
            } finally {
              results.push(rec);
              await context.close();
            }
          }
        }
      }
    }
  } finally {
    await browser.close();
  }
  fs.writeFileSync(
    path.join(outDir, 'manifest.json'),
    JSON.stringify({ capturedAt: new Date().toISOString(), viewports, results }, null, 2),
  );
  return results;
}
