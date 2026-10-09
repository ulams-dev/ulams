// Moodle -> ulams LTI 1.3 round trip in a real browser (nightly-conformance.yml).
//
//   MOODLE_CMID=3 ULAMS_COURSE_ID=1 ULAMS_TOPIC_ID=1 node moodle-roundtrip.cjs
//
// 1. The Moodle student opens the LTI activity; Moodle runs the OIDC login with ulams
//    (api/lti/tool/login), posts the signed id_token to api/lti/tool/launch, and ulams redirects
//    to the front landing with a one-time code (no front runs: we only read that URL).
// 2. The code is exchanged for a Passport token (api/lti/tool/exchange), as the front does.
// 3. The learner finishes the course's topic; ulams sends the grade to Moodle through AGS
//    (QUEUE_CONNECTION=sync, so during the request). The caller checks Moodle's gradebook.
//
// Prints a JSON summary; exits non-zero on the first failed step. Needs `playwright` (CommonJS)
// resolvable, e.g. NODE_PATH=<dir>/node_modules.
const { chromium } = require('playwright');

const MOODLE = process.env.MOODLE_URL || 'http://moodle.test:18080';
const ULAMS_DIRECT = process.env.ULAMS_DIRECT_URL || 'http://127.0.0.1:18000';
const FRONT_HOST = process.env.FRONT_HOST || 'front.test';
const cmid = process.env.MOODLE_CMID;
const courseId = Number(process.env.ULAMS_COURSE_ID);
const topicId = Number(process.env.ULAMS_TOPIC_ID);
const username = process.env.MOODLE_STUDENT || 'student';
const password = process.env.MOODLE_STUDENT_PASSWORD || 'Student-1234!';

function fail(step, detail) {
    console.error(JSON.stringify({ ok: false, step, detail }));
    process.exit(1);
}

async function api(method, path, token, body) {
    const res = await fetch(ULAMS_DIRECT + path, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            Host: 'ulams.test:18000',
            ...(token ? { Authorization: `Bearer ${token}` } : {})
        },
        body: body ? JSON.stringify(body) : undefined
    });
    const text = await res.text();
    let json;
    try {
        json = JSON.parse(text);
    } catch {
        json = { raw: text.slice(0, 500) };
    }
    return { status: res.status, json };
}

(async () => {
    if (!cmid || !courseId || !topicId) {
        fail('config', 'MOODLE_CMID, ULAMS_COURSE_ID and ULAMS_TOPIC_ID are required');
    }
    const browser = await chromium.launch({
        args: [`--host-resolver-rules=MAP moodle.test 127.0.0.1, MAP ulams.test 127.0.0.1, MAP ${FRONT_HOST} 127.0.0.1`]
    });
    const page = await browser.newPage();
    const trail = [];
    page.on('framenavigated', (frame) => frame === page.mainFrame() && trail.push(frame.url()));
    page.on('requestfailed', (request) => trail.push(`FAILED ${request.method()} ${request.url()} ${request.failure()?.errorText}`));
    // the front is not running: the redirect to its landing page carries the one-time code
    let landing = null;
    page.on('request', (request) => {
        if (new URL(request.url()).hostname === FRONT_HOST) {
            landing = request.url();
        }
    });

    await page.goto(`${MOODLE}/login/index.php`);
    await page.fill('#username', username);
    await page.fill('#password', password);
    await Promise.all([page.waitForLoadState('load'), page.click('#loginbtn')]);
    if (page.url().includes('/login/')) {
        fail('moodle-login', page.url());
    }

    await page.goto(`${MOODLE}/mod/lti/launch.php?id=${cmid}&triggerview=0`);
    for (let i = 0; i < 300 && landing === null; i++) {
        await page.waitForTimeout(100);
    }
    if (landing === null) {
        fail('launch', { url: page.url(), trail, body: (await page.content()).slice(0, 1500) });
    }
    const params = new URL(landing).searchParams;
    const code = params.get('code');
    if (!code || Number(params.get('course')) !== courseId) {
        fail('landing', landing);
    }
    await browser.close();

    const exchange = await api('POST', '/api/lti/tool/exchange', null, { code });
    const token = exchange.json?.data?.token;
    if (exchange.status !== 200 || !token) {
        fail('exchange', exchange);
    }
    const again = await api('POST', '/api/lti/tool/exchange', null, { code });
    if (again.status === 200) {
        fail('code-reuse', 'a launch code was accepted twice');
    }
    const me = await api('GET', '/api/profile/me', token);
    if (me.status !== 200) {
        fail('profile', me);
    }

    for (const status of [2, 1]) {
        const progress = await api('PATCH', `/api/courses/progress/${courseId}`, token, {
            progress: [{ topic_id: topicId, status }]
        });
        if (progress.status !== 200) {
            fail(`progress-${status}`, progress);
        }
    }

    console.log(JSON.stringify({ ok: true, user: me.json?.data?.email ?? me.json?.data?.id, course: courseId }));
})().catch((error) => fail('unexpected', String(error?.stack || error)));
