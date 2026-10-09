// A manual check on the running local stack: answers one quiz of the gravity or poland courses correctly through the
// learner front, as the demo student, using the answer key in the course's own GIFT text, and prints the result.
//   node tests/try-quiz.mjs <tenant> <course-id> <topic-id> [out.png]
import { readdirSync, readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { chromium } from "@playwright/test";
import { parseModule } from "./lib/course-text.mjs";

const [tenant, courseId, topicId, shot] = process.argv.slice(2);
const content = join(dirname(fileURLToPath(import.meta.url)), "..", "..", "api", "database", "seeds", "Demo", "content");
const api = `http://${tenant}.localhost`;
const front = `http://${tenant}.app.localhost`;

const login = await (await fetch(`${api}/api/demo/login`, { method: "POST", headers: { "Content-Type": "application/json", Accept: "application/json" }, body: JSON.stringify({ role: "student" }) })).json();
const headers = { Authorization: `Bearer ${login.data.token}`, Accept: "application/json" };
const program = (await (await fetch(`${api}/api/courses/${courseId}/program`, { headers })).json()).data;
const topic = program.lessons.flatMap((l) => l.topics).find((t) => String(t.id) === topicId);
const language = program.language;

// the answer key: the quiz block with the topic's title
const dir = language === "pl" ? "poland/pl" : tenant === "gravity" ? "gravity" : "poland/en";
let key = null;
for (const file of readdirSync(join(content, dir, "modules")))
  for (const b of parseModule(readFileSync(join(content, dir, "modules", file), "utf8")).blocks)
    if (b.kind === "quiz" && b.attrs.title === topic.title) key = b.body;
if (!key) throw new Error(`no quiz titled "${topic.title}" in ${dir}`);
const questions = key
  .split("\n")
  .filter((l) => !l.trimStart().startsWith("//"))
  .join("\n")
  .split(/\n\s*\n/)
  .map((q) => q.trim())
  .filter(Boolean)
  .map((q) => ({ text: q.replace(/^::.*?::\s*/, "").replace(/\s*\{[\s\S]*\}\s*$/, ""), answer: q.slice(q.indexOf("{") + 1, q.lastIndexOf("}")).trim() }));

const browser = await chromium.launch();
const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
const pings = [];
page.on("response", (r) => /progress\/\d+\/ping/.test(r.url()) && pings.push(r.status()));
await page.goto(`${front}/learn/${courseId}/${topicId}`, { waitUntil: "load" });
await page.click("[data-start]");
await page.locator(".u-quiz__q legend").waitFor({ timeout: 20000 });

for (let i = 0; i < questions.length; i++) {
  const { answer } = questions[i];
  const legend = (await page.locator(".u-quiz__q legend").innerText()).trim();
  if (answer.startsWith("#")) await page.fill(".u-quiz__field--num", answer.slice(1).split(":")[0]);
  else if (/^(T|F)$/.test(answer)) await page.getByLabel(answer === "T" ? "True" : "False", { exact: true }).check();
  else if (answer.includes("->")) {
    for (const pair of answer.split("=").map((s) => s.trim()).filter(Boolean)) {
      const [sub, sup] = pair.split("->").map((s) => s.trim());
      await page.locator(".u-quiz__pair", { hasText: sub }).first().locator("select").selectOption({ label: sup });
    }
  } else if (answer.includes("~%")) {
    for (const m of answer.matchAll(/~%(-?\d+)%([^~]+)/g)) if (Number(m[1]) > 0) await page.getByLabel(m[2].trim(), { exact: true }).check();
  } else {
    const right = answer.match(/=([^~]+)/)[1].trim();
    await page.getByLabel(right, { exact: true }).check();
  }
  const last = i === questions.length - 1;
  await page.getByRole("button", { name: last ? /finish/i : /next/i }).click();
  console.log(`  answered ${i + 1}/${questions.length}: ${legend.slice(0, 50)}`);
}
await page.locator("[state=done], .u-quiz__result").first().waitFor({ timeout: 20000 }).catch(() => {});
await page.waitForTimeout(1500);
const result = await page.locator("ulams-quiz").innerText();
console.log(result.replace(/\s+/g, " ").slice(0, 300));
if (shot) await page.screenshot({ path: shot });
console.log("progress pings:", pings.length ? pings.join(",") : "none");
await browser.close();
