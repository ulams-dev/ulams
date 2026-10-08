// Targets, roles and pages for the visual-regression harness.
//
// Ids (course, lesson, topic, static page) are resolved from the API at run time so the
// same config works against freshly seeded databases. Paths that cannot be resolved are
// reported as failed captures instead of crashing the run.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(here, '../../..');

/** Dev-only demo password: env first, then api/.env.example. Never logged. */
function tenantPassword() {
  if (process.env.TENANT_DEMO_PASSWORD) return process.env.TENANT_DEMO_PASSWORD;
  const file = path.join(repoRoot, 'api/.env.example');
  const line = fs.readFileSync(file, 'utf8').split('\n').find((l) => l.startsWith('TENANT_DEMO_PASSWORD='));
  return line ? line.slice('TENANT_DEMO_PASSWORD='.length).trim().replace(/^"|"$/g, '') : '';
}

const env = (k, d) => process.env[k] ?? d;
export const FRONT_URL = env('VISUAL_FRONT_URL', 'http://localhost:3000');
export const ADMIN_URL = env('VISUAL_ADMIN_URL', 'http://localhost:8000');
export const PLATFORM_API = env('VISUAL_PLATFORM_API', 'http://api.localhost');
const TENANTS = env('VISUAL_TENANTS', 'coffee,oncall').split(',').filter(Boolean);

export const credentials = {
  platformAdmin: { email: env('VISUAL_ADMIN_EMAIL', 'admin@ulams.app'), password: env('VISUAL_ADMIN_PASSWORD', 'secret') },
  // Seeded demo learner on the platform (DemoCoursesSeeder); `prepare` enrols it.
  platformStudent: {
    email: env('VISUAL_PLATFORM_STUDENT_EMAIL', 'sam.okafor@demo.ulams.app'),
    password: env('VISUAL_PLATFORM_STUDENT_PASSWORD', tenantPassword()),
  },
  tenant: (slug, who = 'student1') => ({ email: `${who}@${slug}.ulams.app`, password: tenantPassword() }),
};

/** Static page that `prepare` creates when a target has none (covers markdown rendering). */
export const VISUAL_PAGE = {
  slug: 'visual-regression',
  title: 'Visual regression page',
  active: true,
  content: [
    '# Heading one',
    '',
    'Paragraph with **bold**, _italic_, `inline code` and a [link](https://example.com).',
    '',
    '## Heading two',
    '',
    '- First item',
    '- Second item with a longer line of text that wraps on narrow screens',
    '',
    '1. Ordered one',
    '2. Ordered two',
    '',
    '> A blockquote.',
    '',
    '| Column A | Column B |',
    '|---|---|',
    '| 1 | 2 |',
    '',
    '```',
    'const code = true;',
    '```',
  ].join('\n'),
};

// ---------------------------------------------------------------- API helpers
export async function api(apiUrl, method, p, { token, body } = {}) {
  const r = await fetch(new URL(p, apiUrl), {
    method,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  const json = await r.json().catch(() => null);
  return { status: r.status, json };
}

export async function apiLogin(apiUrl, { email, password }) {
  const { status, json } = await api(apiUrl, 'POST', '/api/auth/login', { body: { email, password, remember_me: 1 } });
  if (!json?.data?.token) throw new Error(`login ${email} @ ${apiUrl} failed (${status})`);
  return json.data.token;
}

/** Picks the first published course and its first lesson/topic from the public API. */
async function resolveIds(apiUrl, preferTitle) {
  const ids = {};
  const courses = (await api(apiUrl, 'GET', '/api/courses?per_page=50')).json?.data ?? [];
  const course =
    (preferTitle && courses.find((c) => preferTitle.test(c.title))) ||
    courses.find((c) => !/E2E/i.test(c.title)) ||
    courses[0];
  ids.courseId = course?.id;
  const pages = (await api(apiUrl, 'GET', '/api/pages?per_page=50')).json?.data ?? [];
  ids.pageSlug = (pages.find((p) => p.slug === VISUAL_PAGE.slug) ?? pages.find((p) => p.active !== false))?.slug;
  return ids;
}

// ---------------------------------------------------------------- login flows
const frontLogin = (cred) => async (page, target) => {
  await page.goto(new URL('/#/login', target.baseUrl).toString());
  await page.locator('input[name="email"]').fill(cred.email);
  await page.locator('input[name="password"]').fill(cred.password);
  await page.locator('form button[type="submit"]').first().click();
  await page.waitForFunction(() => {
    try { return !!JSON.parse(localStorage.getItem('user_token') || 'null')?.token; } catch { return false; }
  }, null, { timeout: 20000 });
  await page.waitForLoadState('networkidle').catch(() => {});
};

const adminLogin = (cred) => async (page, target) => {
  await page.goto(new URL('/user/login', target.baseUrl).toString());
  await page.locator('input#email').fill(cred.email);
  await page.locator('input#password').fill(cred.password);
  // The ProForm submitter is a plain antd button (no type="submit"); the form submits on Enter.
  await page.locator('input#password').press('Enter');
  await page.waitForFunction(() => !!localStorage.getItem('TOKEN'), null, { timeout: 20000 });
  await page.waitForLoadState('networkidle').catch(() => {});
};

// ---------------------------------------------------------------- front pages
const G = ['guest'];
const S = ['student'];
const GS = ['guest', 'student'];

function frontPages() {
  const need = (k) => (t) => {
    if (t.ids[k] == null) throw new Error(`no ${k} resolved from ${t.apiUrl}`);
    return t.ids[k];
  };
  return [
    { name: 'home', path: '/#/', roles: GS },
    { name: 'courses', path: '/#/courses', roles: GS },
    { name: 'course-detail', path: (t) => `/#/courses/${need('courseId')(t)}`, roles: GS },
    { name: 'course-player', path: (t) => `/#/course/${need('courseId')(t)}`, roles: S, extraWaitMs: 800 },
    { name: 'login', path: '/#/login', roles: G },
    { name: 'register', path: '/#/register', roles: G },
    { name: 'my-profile', path: '/#/user/my-profile', roles: S },
    { name: 'my-certificates', path: '/#/user/my-certificates', roles: S },
    { name: 'my-orders', path: '/#/user/my-orders', roles: S },
    { name: 'my-data', path: '/#/user/my-data', roles: S },
    { name: 'cart', path: '/#/cart', roles: S },
    { name: 'webinars', path: '/#/webinars', roles: GS },
    // /events and /tutors routes are commented out in front/src/components/Routes; these
    // URLs therefore render the static-page catch-all. Kept so that re-enabling shows up.
    { name: 'events', path: '/#/events', roles: G },
    { name: 'tutors', path: '/#/tutors', roles: G },
    { name: 'consultations', path: '/#/consultations', roles: GS },
    { name: 'static-page', path: (t) => `/#/${need('pageSlug')(t)}`, roles: G },
    { name: '404', path: '/#/404', roles: G },
  ];
}

function frontTarget(name, baseUrl, apiUrl, studentCred, preferTitle) {
  return {
    name,
    baseUrl,
    apiUrl,
    preferTitle,
    studentCred,
    roles: { guest: {}, student: { login: frontLogin(studentCred) } },
    pages: frontPages(),
    // Hide the dev "Warning" banner state; set defaults the app would otherwise randomise.
    init: { localStorage: { hideWarning: 'true' } },
  };
}

// ---------------------------------------------------------------- admin pages
function adminPages() {
  const need = (k) => (t) => {
    if (t.ids[k] == null) throw new Error(`no ${k} resolved from ${t.apiUrl}`);
    return t.ids[k];
  };
  const A = ['admin'];
  return [
    { name: 'login', path: '/user/login', roles: ['guest'] },
    { name: 'welcome', path: '/welcome', roles: A },
    { name: 'courses-list', path: '/courses/list', roles: A },
    { name: 'course-edit-attributes', path: (t) => `/courses/list/${need('courseId')(t)}/attributes`, roles: A },
    { name: 'course-edit-program', path: (t) => `/courses/list/${need('courseId')(t)}/program`, roles: A, extraWaitMs: 800 },
    { name: 'course-edit-text-topic', path: (t) => `/courses/list/${need('courseId')(t)}/program/?topic=${need('textTopicId')(t)}`, roles: A, extraWaitMs: 800 },
    { name: 'course-edit-gift-quiz', path: (t) => `/courses/list/${need('courseId')(t)}/program/?topic=${need('giftTopicId')(t)}`, roles: A, extraWaitMs: 800 },
    { name: 'users-list', path: '/users/list', roles: A },
    { name: 'settings', path: '/configuration/settings', roles: A },
    { name: 'h5p-list', path: '/courses/h5ps', roles: A },
    { name: 'pages-list', path: '/other/pages', roles: A },
    { name: 'page-editor', path: (t) => `/other/pages/${need('pageId')(t)}`, roles: A, extraWaitMs: 800 },
    { name: 'page-new', path: '/other/pages/new', roles: A, extraWaitMs: 800 },
  ];
}

/** Admin ids: course with a GIFT quiz topic and a rich-text topic, first static page. */
async function resolveAdminIds(apiUrl, cred) {
  const token = await apiLogin(apiUrl, cred);
  const ids = {};
  const courses = (await api(apiUrl, 'GET', '/api/admin/courses?per_page=50', { token })).json?.data ?? [];
  const sorted = [...courses].sort((a, b) => Number(/E2E/i.test(a.title)) - Number(/E2E/i.test(b.title)));
  for (const c of sorted) {
    const prog = (await api(apiUrl, 'GET', `/api/admin/courses/${c.id}/program`, { token })).json?.data;
    const topics = (prog?.lessons ?? []).flatMap((l) => [...(l.topics ?? []), ...(l.lessons ?? []).flatMap((s) => s.topics ?? [])]);
    const gift = topics.find((tp) => /GiftQuiz/i.test(tp.topicable_type ?? ''));
    const text = topics.find((tp) => /RichText/i.test(tp.topicable_type ?? ''));
    ids.courseId ??= c.id;
    if (gift && text) {
      Object.assign(ids, { courseId: c.id, giftTopicId: gift.id, textTopicId: text.id });
      break;
    }
    ids.giftTopicId ??= gift && c.id === ids.courseId ? gift.id : undefined;
    ids.textTopicId ??= text && c.id === ids.courseId ? text.id : undefined;
  }
  const pages = (await api(apiUrl, 'GET', '/api/admin/pages?per_page=50', { token })).json?.data ?? [];
  ids.pageId = (pages.find((p) => p.slug === VISUAL_PAGE.slug) ?? pages[0])?.id;
  return ids;
}

// ---------------------------------------------------------------- config
export async function buildConfig() {
  const targets = [
    frontTarget('front-platform', FRONT_URL, PLATFORM_API, credentials.platformStudent, /Coffee/i),
    ...TENANTS.map((slug) =>
      frontTarget(`front-${slug}`, `http://${slug}.app.localhost`, `http://${slug}.localhost`, credentials.tenant(slug)),
    ),
    {
      name: 'admin-platform',
      baseUrl: ADMIN_URL,
      apiUrl: PLATFORM_API,
      roles: { guest: {}, admin: { login: adminLogin(credentials.platformAdmin) } },
      pages: adminPages(),
      // Notification counter in the header changes whenever anything is logged.
      mask: ['.ant-layout-header .ant-badge-count', '.ant-pro-global-header .ant-badge-count'],
      resolve: () => resolveAdminIds(PLATFORM_API, credentials.platformAdmin),
    },
  ];
  for (const t of targets) {
    try {
      t.ids = t.resolve ? await t.resolve() : await resolveIds(t.apiUrl, t.preferTitle);
    } catch (e) {
      console.warn(`[visual] could not resolve ids for ${t.name}: ${e.message}`);
      t.ids = {};
    }
  }
  return {
    targets,
    // Masked regions are painted magenta in every run, so they never count as diff.
    mask: [],
    hide: [],
  };
}
