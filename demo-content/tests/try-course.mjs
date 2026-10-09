// A manual check on the running local stack: opens every lesson of every course of a tenant as the demo student, and
// prints the console problems, failed requests, progress pings that did not answer 200 and axe violations (WCAG 2.2 AA)
// for the first topic of each type, with one screenshot per topic type. Needs the stack up and the tenant seeded.
//   node tests/try-course.mjs <tenant> <out-dir> [course-id ...]
import { mkdirSync } from "node:fs";
import { join } from "node:path";
import AxeBuilder from "@axe-core/playwright";
import { chromium } from "@playwright/test";

const [tenant, out, ...only] = process.argv.slice(2);
if (!tenant || !out) {
  console.error("usage: node tests/try-course.mjs <tenant> <out-dir> [course-id ...]");
  process.exit(1);
}
mkdirSync(out, { recursive: true });
const api = `http://${tenant}.localhost`;
const front = `http://${tenant}.app.localhost`;

const login = await (await fetch(`${api}/api/demo/login`, { method: "POST", headers: { "Content-Type": "application/json", Accept: "application/json" }, body: JSON.stringify({ role: "student" }) })).json();
const headers = { Authorization: `Bearer ${login.data.token}`, Accept: "application/json" };
const courses = (await (await fetch(`${api}/api/courses`, { headers })).json()).data.filter((c) => !only.length || only.includes(String(c.id)));

const browser = await chromium.launch({ args: ["--use-angle=swiftshader", "--enable-unsafe-swiftshader", "--ignore-gpu-blocklist"] });
const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
const page = await context.newPage();
let problems = [];
let pings = [];
page.on("console", (m) => ["error"].includes(m.type()) && !/GPU stall|Failed to load resource.*(favicon|404)/.test(m.text()) && problems.push(`console: ${m.text().slice(0, 160)}`));
page.on("pageerror", (e) => problems.push(`pageerror: ${e.message.slice(0, 160)}`));
page.on("requestfailed", (r) => !/\.(woff2?|png|webp|jpg)$/.test(r.url()) && problems.push(`request failed: ${r.url().slice(0, 100)} ${r.failure()?.errorText}`));
page.on("response", (r) => {
  if (/progress\/\d+\/ping/.test(r.url())) pings.push(r.status());
  else if (r.status() >= 400 && !/favicon/.test(r.url())) problems.push(`HTTP ${r.status()} ${r.url().slice(0, 100)}`);
});

const shot = new Set();
let total = 0;
let bad = 0;
for (const course of courses) {
  const program = (await (await fetch(`${api}/api/courses/${course.id}/program`, { headers })).json()).data;
  console.log(`\n${course.id} ${course.title} (${course.language}): ${program.lessons.length} lessons`);
  for (const lesson of program.lessons)
    for (const topic of lesson.topics) {
      const type = topic.topicable_type.split("\\").pop();
      problems = [];
      pings = [];
      await page.goto(`${front}/learn/${course.id}/${topic.id}`, { waitUntil: "domcontentloaded" });
      await page.waitForLoadState("load", { timeout: 15000 }).catch(() => {});
      await page.waitForTimeout(type === "InteractiveTopic" ? 5000 : 800);
      // axe runs on the first topic of each type per course (the page chrome is the same for the others)
      const key = `${course.id}-${type}`;
      const first = !shot.has(key);
      let violations = [];
      if (first) {
        shot.add(key);
        // the package frame is a separate document (tested in demo-content/tests/e2e); the page around it is scanned
        const axe = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"]).exclude("iframe").analyze();
        violations = axe.violations.map((v) => `${v.id} (${v.impact}, ${v.nodes.length})`);
        await page.screenshot({ path: join(out, `${tenant}-c${course.id}-${type}-${topic.id}.png`) });
      }
      total++;
      const wrongPing = pings.filter((s) => s !== 200);
      const line = [violations.length && `axe: ${violations.join(", ")}`, wrongPing.length && `ping ${wrongPing.join(",")}`, ...problems.slice(0, 3)].filter(Boolean);
      if (line.length) bad++;
      console.log(`  ${String(topic.id).padStart(3)} ${type.padEnd(16)} ${topic.title.slice(0, 40).padEnd(40)} ${line.length ? "!! " + line.join(" | ") : "ok"}${pings.length ? ` (ping ${pings.length}x ${pings[0]})` : ""}`);
    }
}
console.log(`\n${total} topics opened, ${bad} with problems`);
await browser.close();
process.exit(bad ? 1 : 0);
