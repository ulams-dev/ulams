// A manual check on the running local stack: opens a lesson in the learner front, waits for the package
// to start, prints console problems and failed requests, and saves a screenshot.
//   node tests/try-lesson.mjs <tenant> <course-id> <topic-id> <out.png> [reduced]
import { chromium } from "@playwright/test";

const [tenant, course, topic, out, reduced] = process.argv.slice(2);
if (!tenant || !course || !topic || !out) {
  console.error("usage: node tests/try-lesson.mjs <tenant> <course-id> <topic-id> <out.png> [reduced]");
  process.exit(1);
}
const browser = await chromium.launch({ args: ["--use-angle=swiftshader", "--enable-unsafe-swiftshader", "--ignore-gpu-blocklist"] });
const context = await browser.newContext({ viewport: { width: 1280, height: 800 }, reducedMotion: reduced ? "reduce" : "no-preference" });
const page = await context.newPage();
page.on("console", (m) => ["error", "warning"].includes(m.type()) && !/GPU stall/.test(m.text()) && console.log("console:", m.type(), m.text().slice(0, 200)));
page.on("pageerror", (e) => console.log("pageerror:", e.message));
page.on("requestfailed", (r) => console.log("request failed:", r.url().slice(0, 120), r.failure()?.errorText));
await page.goto(`http://${tenant}.app.localhost:4321/learn/${course}/${topic}`, { waitUntil: "domcontentloaded" });
await page.waitForTimeout(9000);
await page.screenshot({ path: out });
console.log("frames:", page.frames().map((f) => f.url().slice(0, 110)));
await browser.close();
