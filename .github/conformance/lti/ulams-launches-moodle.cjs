// ulams -> Moodle LTI 1.3 launch in a real browser (nightly-conformance.yml).
//
//   ULAMS_TOOL_JSON=tool.json MOODLE_COURSE_ID=4 node ulams-launches-moodle.cjs
//
// The learner starts the LTI topic in ulams (POST /api/lti/launches/{topic}, as the front does);
// the browser follows the OIDC login to Moodle (enrol/lti/login.php), back to ulams
// (api/lti/platform/authorize), which posts the signed id_token to Moodle's launch.php. Moodle
// provisions the user and opens the published course. Grades come back later through AGS
// (moodle-setup.php tool-grade, then ulams-setup.php scores).
const { chromium } = require('playwright');

const ULAMS_DIRECT = process.env.ULAMS_DIRECT_URL || 'http://127.0.0.1:18000';
// ULAMS_TOOL_JSON: the output of `ulams-setup.php tool` (topic and learner token)
const tool = process.env.ULAMS_TOOL_JSON ? JSON.parse(require('fs').readFileSync(process.env.ULAMS_TOOL_JSON, 'utf8')) : {};
const topicId = process.env.ULAMS_TOPIC_ID || tool.topic_id;
const token = process.env.LEARNER_TOKEN || tool.learner_token;
const moodleCourse = process.env.MOODLE_COURSE_ID;

function fail(step, detail) {
    console.error(JSON.stringify({ ok: false, step, detail }));
    process.exit(1);
}

(async () => {
    if (!topicId || !token || !moodleCourse) {
        fail('config', 'ULAMS_TOPIC_ID, LEARNER_TOKEN and MOODLE_COURSE_ID are required');
    }
    const res = await fetch(`${ULAMS_DIRECT}/api/lti/launches/${topicId}`, {
        method: 'POST',
        headers: { Accept: 'application/json', Authorization: `Bearer ${token}`, Host: 'ulams.test:18000' }
    });
    const body = await res.json().catch(() => ({}));
    const url = body?.data?.url;
    if (res.status !== 200 || !url) {
        fail('ulams-launch', { status: res.status, body });
    }

    const browser = await chromium.launch({
        args: ['--host-resolver-rules=MAP moodle.test 127.0.0.1, MAP ulams.test 127.0.0.1']
    });
    const page = await browser.newPage();
    const trail = [];
    page.on('framenavigated', (frame) => frame === page.mainFrame() && trail.push(frame.url()));
    await page.goto(url);
    try {
        await page.waitForURL((u) => u.hostname === 'moodle.test' && u.pathname === '/course/view.php', { timeout: 30_000 });
    } catch {
        fail('moodle-course', { url: page.url(), trail, body: (await page.innerText('body').catch(() => '')).slice(0, 1500) });
    }
    if (new URL(page.url()).searchParams.get('id') !== String(moodleCourse)) {
        fail('moodle-course-id', page.url());
    }
    const text = await page.innerText('body');
    await browser.close();
    console.log(JSON.stringify({ ok: true, landed: page.url(), loggedIn: !/You are not logged in/i.test(text) }));
})().catch((error) => fail('unexpected', String(error?.stack || error)));
