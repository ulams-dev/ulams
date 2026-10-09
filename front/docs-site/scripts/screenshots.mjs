// Captures the admin panel and learner screenshots used by the guides, from a running demo
// tenant, and saves them as WebP in src/assets/screens (names: see src/components/Screenshot.astro).
//
//   yarn workspace @ulams/docs screenshots                 all screens
//   yarn workspace @ulams/docs screenshots admin-users     only names containing "admin-users"
//
// Requires the demo stack (`yarn dev:api`, demo tenants, `make -C api demo-mode-on`): demo mode
// logs the admin panel in as the tenant admin and the learner site in as the demo student, so
// no password is needed. Override the hosts with DOCS_SHOTS_ADMIN, DOCS_SHOTS_LEARNER and
// DOCS_SHOTS_API. Only pages are opened; nothing is saved or deleted.
import { mkdirSync } from "node:fs";
import { join } from "node:path";
import { chromium } from "@playwright/test";
import sharp from "sharp";
import { SITE_DIR } from "./lib.mjs";
import { inventory } from "./coverage.mjs";

const ADMIN = process.env.DOCS_SHOTS_ADMIN ?? "http://coffee.admin.localhost";
const LEARNER = process.env.DOCS_SHOTS_LEARNER ?? "http://coffee.app.localhost";
const API = process.env.DOCS_SHOTS_API ?? "http://coffee.localhost";
const OUT = join(SITE_DIR, "src/assets/screens");
const filter = process.argv[2] ?? "";
const VIEWPORT = { width: 1440, height: 900 };

const slug = (prefix, route) =>
  `${prefix}-${
    route
      .replace(/[:[\]]|\.\.\./g, "")
      .replace(/^\/|\/$/g, "")
      .replace(/[^a-zA-Z0-9]+/g, "-")
      .toLowerCase() || "home"
  }`;

async function api(token, path) {
  const res = await fetch(`${API}${path}`, { headers: { Authorization: `Bearer ${token}`, Accept: "application/json" } });
  if (!res.ok) return null;
  return (await res.json())?.data ?? null;
}
const firstId = (data) => (Array.isArray(data) ? data[0]?.id : (data?.data?.[0]?.id ?? null));

async function save(page, name) {
  const png = await page.screenshot({ fullPage: false });
  await sharp(png).resize({ width: 1280 }).webp({ quality: 78, effort: 6 }).toFile(join(OUT, `${name}.webp`));
  console.log(`saved ${name}.webp`);
}

async function settle(page) {
  await page.waitForLoadState("networkidle", { timeout: 20_000 }).catch(() => {});
  // Ant Design skeletons and spinners
  await page
    .waitForFunction(() => !document.querySelector(".ant-spin-spinning, .ant-skeleton-active, .ant-pro-page-container-loading"), null, {
      timeout: 15_000,
    })
    .catch(() => {});
  await page.waitForTimeout(600);
}

async function adminShots(browser) {
  const context = await browser.newContext({ viewport: VIEWPORT, colorScheme: "light" });
  const page = await context.newPage();
  await page.goto(`${ADMIN}/`, { waitUntil: "domcontentloaded" });
  // demo mode logs in by itself; wait for the token
  const token = await page
    .waitForFunction(() => localStorage.getItem("TOKEN") || localStorage.getItem("token"), null, { timeout: 30_000 })
    .then((h) => h.jsonValue())
    .catch(() => null);
  if (!token) {
    console.error("admin: no token after 30 s; is demo mode on for this tenant?");
    await context.close();
    return;
  }
  await settle(page);

  const ids = {
    course: firstId(await api(token, "/api/admin/courses?per_page=1")),
    h5p: firstId(await api(token, "/api/admin/hh5p/content?per_page=1")),
    id: null,
    user: firstId(await api(token, "/api/admin/users?per_page=1")),
    group: firstId(await api(token, "/api/admin/user-groups?per_page=1")),
    name: "tutor",
    template: "email",
    webinar: firstId(await api(token, "/api/admin/webinars?per_page=1")),
    consultation: firstId(await api(token, "/api/admin/consultations?per_page=1")),
    page: firstId(await api(token, "/api/admin/pages?per_page=1")),
    task: firstId(await api(token, "/api/admin/tasks?per_page=1")),
    questionnaireId: firstId(await api(token, "/api/admin/questionnaire?per_page=1")),
  };
  const tabs = {
    "/courses/list/:course/:tab": [
      "program",
      "media",
      "categories",
      "product",
      "access",
      "certificates",
      "questionnaires",
      "statistics",
      "user_projects",
      "user_submission",
      "scorm",
    ],
    "/users/:user/:tab": ["user_info", "categories", "settings", "logs"],
    "/my-profile/:tab": ["general", "password"],
    "/configuration/settings/:tab": [],
  };

  const shots = [];
  for (const route of inventory().adminRoutes) {
    if (route.startsWith("/user/")) continue; // login screens: logged-out only
    if (tabs[route]) {
      for (const tab of tabs[route]) {
        const url = route.replace(":course", ids.course).replace(":user", ids.user).replace(":tab", tab);
        shots.push({ name: `${slug("admin", route.replace(":tab", ""))}-${tab.replace(/_/g, "-")}`, url });
      }
      continue;
    }
    const params = [...route.matchAll(/:(\w+)/g)].map((m) => m[1]);
    if (params.some((p) => !ids[p] && p !== "courseTab")) continue;
    if (params.includes("courseTab")) continue;
    const url = route.replace(/:(\w+)/g, (_, p) => ids[p]).replace(/\/$/, "");
    shots.push({ name: slug("admin", route), url });
  }

  for (const shot of shots) {
    if (filter && !shot.name.includes(filter)) continue;
    try {
      await page.goto(`${ADMIN}${shot.url}`, { waitUntil: "domcontentloaded" });
      await settle(page);
      if (await page.locator("text=/403|Sorry, you are not authorized|404/").first().isVisible().catch(() => false)) {
        console.log(`skip ${shot.name}: not available on this tenant`);
        continue;
      }
      await save(page, shot.name);
    } catch (e) {
      console.log(`skip ${shot.name}: ${e.message.split("\n")[0]}`);
    }
  }
  await context.close();
}

async function learnerShots(browser) {
  const context = await browser.newContext({ viewport: VIEWPORT, colorScheme: "light" });
  const page = await context.newPage();
  const courses = await fetch(`${API}/api/courses?per_page=1`).then((r) => r.json()).catch(() => null);
  const course = courses?.data?.[0];
  // the same password-less demo login the learner site does on its first visit
  const login = await fetch(`${API}/api/demo/login`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify({ role: "student" }),
  })
    .then((r) => (r.ok ? r.json() : null))
    .catch(() => null);
  const program =
    course && login?.data?.token
      ? await fetch(`${API}/api/courses/${course.id}/program`, {
          headers: { Authorization: `Bearer ${login.data.token}`, Accept: "application/json" },
        })
          .then((r) => (r.ok ? r.json() : null))
          .catch(() => null)
      : null;
  const lessons = program?.data?.lessons ?? [];
  const topics = lessons.flatMap((l) => l.topics ?? []);
  const shots = [
    { name: "learner-home", url: "/" },
    course && { name: "learner-courses-id", url: `/courses/${course.id}` },
    { name: "learner-events", url: "/events" },
    { name: "learner-login", url: "/login" },
    course && { name: "learner-learn-courseid", url: `/learn/${course.id}` },
    course && topics[0] && { name: "learner-learn-courseid-topicid", url: `/learn/${course.id}/${topics[0].id}` },
    course && { name: "learner-learn-courseid-finish", url: `/learn/${course.id}/finish` },
    { name: "learner-account", url: "/account" },
  ].filter(Boolean);
  // one screen per topic type that the demo course contains
  const seen = new Set();
  for (const t of topics) {
    const type = String(t.topicable_type ?? "").split("\\").pop();
    if (!type || seen.has(type)) continue;
    seen.add(type);
    shots.push({ name: `learner-topic-${type.toLowerCase()}`, url: `/learn/${course.id}/${t.id}` });
  }
  for (const shot of shots) {
    if (filter && !shot.name.includes(filter)) continue;
    try {
      await page.goto(`${LEARNER}${shot.url}`, { waitUntil: "domcontentloaded" });
      await settle(page);
      await save(page, shot.name);
    } catch (e) {
      console.log(`skip ${shot.name}: ${e.message.split("\n")[0]}`);
    }
  }
  await context.close();
}

mkdirSync(OUT, { recursive: true });
const browser = await chromium.launch();
try {
  await adminShots(browser);
  await learnerShots(browser);
} finally {
  await browser.close();
}
