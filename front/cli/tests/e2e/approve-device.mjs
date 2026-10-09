// Plays the person in the device-login e2e: opens the approval page in a real browser as the demo
// admin and approves (or denies) the code. Playwright comes from the front/web workspace.
//
//   node approve-device.mjs <approval-url-with-code> <api-origin> [approve|deny]
//
// The demo admin's token comes from POST /api/demo/login; it is stored in the BFF session cookie
// exactly as the web app's own login does, so no password is involved.
import { createRequire } from "node:module";
import { resolve, dirname } from "node:path";
import { fileURLToPath } from "node:url";

const here = dirname(fileURLToPath(import.meta.url));
const require = createRequire(resolve(here, "../../../web/package.json"));
const { chromium } = require("@playwright/test");

const [approvalUrl, apiOrigin, decision = "approve"] = process.argv.slice(2);
if (!approvalUrl || !apiOrigin) {
  console.error("usage: approve-device.mjs <approval-url> <api-origin> [approve|deny]");
  process.exit(2);
}

const demo = await fetch(`${apiOrigin}/api/demo/login`, {
  method: "POST",
  headers: { "Content-Type": "application/json", Accept: "application/json" },
  body: JSON.stringify({ role: "admin" }),
}).then((r) => r.json());
const token = demo?.data?.token;
if (!token) throw new Error("the demo admin login returned no token");

const url = new URL(approvalUrl);
const browser = await chromium.launch();
try {
  const context = await browser.newContext();
  await context.addCookies([{ name: "ulams_session", value: token, url: url.origin }]);
  const page = await context.newPage();
  const done = decision === "deny" ? "Denied" : "Approved";
  // The approval endpoints allow 5 calls a minute per user; wait out the throttle instead of failing.
  for (let attempt = 1; ; attempt++) {
    await page.goto(approvalUrl);
    const throttled = page.getByText(/too many attempts/i);
    const first = await Promise.race([
      page.getByRole("heading", { level: 1, name: /authorize the ulams cli/i }).waitFor().then(() => "page"),
      throttled.waitFor().then(() => "throttled"),
    ]);
    if (first === "throttled") {
      if (attempt >= 4) throw new Error("the approval page stayed throttled");
      await page.waitForTimeout(62_000);
      continue;
    }
    await page.getByRole("button", { name: decision === "deny" ? "Deny" : "Approve" }).click();
    const result = await Promise.race([
      page.getByRole("heading", { name: done }).waitFor().then(() => "done"),
      throttled.waitFor().then(() => "throttled"),
    ]);
    if (result === "done") break;
    if (attempt >= 4) throw new Error("the approval page stayed throttled");
    await page.waitForTimeout(62_000);
  }
  console.log(JSON.stringify({ ok: true, decision }));
} finally {
  await browser.close();
}
