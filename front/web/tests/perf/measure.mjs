/**
 * Measures the production build with Chromium: LCP, CLS, JS transferred (gzip) and
 * total transfer per page, cold (new context) and warm (second visit). Prints a table.
 *
 *   yarn workspace @ulams/web build && yarn workspace @ulams/web start &
 *   yarn workspace @ulams/web perf
 */
import { chromium } from "@playwright/test";

const port = process.env.WEB_BASE_PORT ?? "4321";
const pages = [
  ["coffee landing", `http://coffee.app.localhost:${port}/`],
  ["coffee course", `http://coffee.app.localhost:${port}/courses/1`],
  ["coffee lesson", `http://coffee.app.localhost:${port}/learn/1/11`],
  ["oncall landing", `http://oncall.app.localhost:${port}/`],
  ["oncall course", `http://oncall.app.localhost:${port}/courses/1`],
  ["oncall lesson", `http://oncall.app.localhost:${port}/learn/1/4`],
  ["nightsky landing", `http://nightsky.app.localhost:${port}/`],
  ["nightsky course", `http://nightsky.app.localhost:${port}/courses/2`],
  ["nightsky lesson", `http://nightsky.app.localhost:${port}/learn/2/16`],
];

const browser = await chromium.launch();

async function measure(url, context) {
  const page = await context.newPage();
  const cdp = await context.newCDPSession(page);
  await cdp.send("Network.enable");
  let js = 0;
  let total = 0;
  const types = new Map();
  cdp.on("Network.responseReceived", (e) => types.set(e.requestId, e.type));
  cdp.on("Network.loadingFinished", (e) => {
    total += e.encodedDataLength;
    if (types.get(e.requestId) === "Script") js += e.encodedDataLength;
  });
  await page.addInitScript(() => {
    window.__lcp = 0;
    window.__cls = 0;
    new PerformanceObserver((l) => {
      for (const e of l.getEntries()) window.__lcp = e.startTime;
    }).observe({ type: "largest-contentful-paint", buffered: true });
    new PerformanceObserver((l) => {
      for (const e of l.getEntries()) if (!e.hadRecentInput) window.__cls += e.value;
    }).observe({ type: "layout-shift", buffered: true });
  });
  const t0 = Date.now();
  await page.goto(url, { waitUntil: "load" });
  const loadMs = Date.now() - t0;
  await page.waitForTimeout(1500);
  // scroll through the page so below-the-fold shifts are counted too
  await page.evaluate(async () => {
    for (let y = 0; y < document.body.scrollHeight; y += 700) {
      window.scrollTo({ top: y, behavior: "instant" });
      await new Promise((r) => setTimeout(r, 80));
    }
  });
  await page.waitForTimeout(500);
  const { lcp, cls, ttfb } = await page.evaluate(() => ({
    lcp: window.__lcp,
    cls: window.__cls,
    ttfb: performance.getEntriesByType("navigation")[0]?.responseStart ?? 0,
  }));
  await page.close();
  return { lcp: Math.round(lcp), cls: Number(cls.toFixed(3)), ttfb: Math.round(ttfb), loadMs, jsKB: +(js / 1024).toFixed(1), totalKB: +(total / 1024).toFixed(0) };
}

const rows = [];
for (const [name, url] of pages) {
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  await measure(url, context); // warm the server cache and the session
  const cold = await measure(url, await browser.newContext({ viewport: { width: 1440, height: 900 } }));
  const warm = await measure(url, context);
  rows.push({ page: name, ...Object.fromEntries(Object.entries(cold).map(([k, v]) => [`cold ${k}`, v])), "warm lcp": warm.lcp, "warm jsKB": warm.jsKB });
  await context.close();
}
console.table(rows);
await browser.close();
