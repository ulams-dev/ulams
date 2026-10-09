// LTI Client Side postMessage Storage, tool side, in a real browser (nightly-conformance.yml).
//
//   ULAMS_COURSE_ID=1 node client-side-storage.cjs
//
// A fake platform (this script) embeds the ulams tool login in an iframe and implements the platform
// half of the spec (lti.capabilities, lti.put_data, lti.get_data) in the parent page or in a sibling
// frame. The platform is registered in ulams beforehand (run-client-side-storage.sh); its signing key
// is served at /jwks and ulams fetches it (LTI_ALLOW_INSECURE_URLS). Scenarios:
//
//   parent    storage in the parent window (lti_storage_target=_parent): login puts the nonce, the
//             launch reads it back, posts it to /api/lti/tool/launch/verify, lands on the front
//   frame     storage in a named sibling frame (lti_storage_target=lti-storage)
//   none      no lti_storage_target: the old flow, no verify step
//   silent    the platform announces storage but never answers: falls back to the server-side state
//   tampered  get_data returns a value this login never stored: the launch is refused
//
// Prints a JSON summary; exits non-zero on the first failed scenario. Needs `playwright` (CommonJS).
const crypto = require('crypto');
const http = require('http');
const { chromium } = require('playwright');

const ULAMS_PUBLIC = process.env.ULAMS_PUBLIC_URL || 'http://ulams.test:18000';
const FRONT_HOST = process.env.FRONT_HOST || 'front.test';
const PLATFORM_HOST = process.env.PLATFORM_HOST || 'platform.test';
const PLATFORM_PORT = Number(process.env.PLATFORM_PORT || 18090);
const ISSUER = `http://${PLATFORM_HOST}:${PLATFORM_PORT}`;
const CLIENT_ID = process.env.PLATFORM_CLIENT_ID || 'storage-platform';
const DEPLOYMENT_ID = process.env.PLATFORM_DEPLOYMENT_ID || 'dep-storage';
const courseId = String(process.env.ULAMS_COURSE_ID || '');

function fail(step, detail) {
    console.error(JSON.stringify({ ok: false, step, detail }));
    process.exit(1);
}

const { publicKey, privateKey } = crypto.generateKeyPairSync('rsa', { modulusLength: 2048 });
const jwk = { ...publicKey.export({ format: 'jwk' }), kid: 'storage-platform-key', alg: 'RS256', use: 'sig' };
const b64 = (value) => Buffer.from(typeof value === 'string' ? value : JSON.stringify(value)).toString('base64url');

function idToken(nonce) {
    const now = Math.floor(Date.now() / 1000);
    const head = b64({ alg: 'RS256', typ: 'JWT', kid: jwk.kid });
    const body = b64({
        iss: ISSUER,
        aud: CLIENT_ID,
        sub: 'storage-learner',
        iat: now,
        exp: now + 300,
        nonce,
        given_name: 'Sto',
        family_name: 'Rage',
        'https://purl.imsglobal.org/spec/lti/claim/deployment_id': DEPLOYMENT_ID,
        'https://purl.imsglobal.org/spec/lti/claim/message_type': 'LtiResourceLinkRequest',
        'https://purl.imsglobal.org/spec/lti/claim/version': '1.3.0',
        'https://purl.imsglobal.org/spec/lti/claim/roles': ['http://purl.imsglobal.org/vocab/lis/v2/membership#Learner'],
        'https://purl.imsglobal.org/spec/lti/claim/resource_link': { id: 'storage-link-1' },
        'https://purl.imsglobal.org/spec/lti/claim/target_link_uri': `${ULAMS_PUBLIC}/api/lti/tool/launch`,
        'https://purl.imsglobal.org/spec/lti/claim/custom': { course_id: courseId }
    });
    const signature = crypto.sign('RSA-SHA256', Buffer.from(`${head}.${body}`), privateKey).toString('base64url');
    return `${head}.${body}.${signature}`;
}

// the platform half of the spec: answers lti.capabilities, lti.put_data and lti.get_data from `source`
const STORAGE_HANDLER = `
    var store = {};
    window.__messages = [];
    window.addEventListener('message', function (event) {
        var data = event.data;
        if (!data || !data.subject || String(data.subject).indexOf('lti.') !== 0) { return; }
        window.__messages.push({ subject: data.subject, key: data.key, origin: event.origin });
        var reply = { message_id: data.message_id };
        if (data.subject === 'lti.capabilities') {
            reply.subject = 'lti.capabilities.response';
            reply.supported_messages = [{ subject: 'org.imsglobal.lti.put_data' }, { subject: 'org.imsglobal.lti.get_data' }];
        } else if (data.subject === 'lti.put_data') {
            store[data.key] = data.value;
            reply.subject = 'lti.put_data.response'; reply.key = data.key; reply.value = data.value;
        } else if (data.subject === 'lti.get_data') {
            reply.subject = 'lti.get_data.response'; reply.key = data.key;
            reply.value = TAMPER ? 'nonce-of-another-login' : store[data.key];
        } else { return; }
        event.source.postMessage(reply, event.origin);
    });
`;

function platformPage(mode) {
    const toolLogin = new URL(`${ULAMS_PUBLIC}/api/lti/tool/login`);
    toolLogin.search = new URLSearchParams({
        iss: ISSUER,
        login_hint: 'storage-learner',
        target_link_uri: `${ULAMS_PUBLIC}/api/lti/tool/launch`,
        client_id: CLIENT_ID,
        lti_deployment_id: DEPLOYMENT_ID,
        lti_message_hint: 'storage',
        ...(mode === 'none' ? {} : { lti_storage_target: mode === 'frame' ? 'lti-storage' : '_parent' })
    }).toString();
    const handler = `var TAMPER = ${mode === 'tampered'};${STORAGE_HANDLER}`;
    return `<!doctype html><title>fake platform (${mode})</title>
${mode === 'frame' ? '<iframe name="lti-storage" src="/storage-frame?mode=' + mode + '" style="display:none"></iframe>' : ''}
<iframe name="tool" src="${toolLogin}" style="width:800px;height:400px"></iframe>
${mode === 'parent' || mode === 'tampered' ? `<script>${handler}</script>` : ''}`;
}

const server = http.createServer((req, res) => {
    const url = new URL(req.url, ISSUER);
    const send = (body, type = 'text/html') => {
        res.writeHead(200, { 'Content-Type': type, 'Cache-Control': 'no-store' });
        res.end(body);
    };
    if (url.pathname === '/jwks') {
        return send(JSON.stringify({ keys: [jwk] }), 'application/json');
    }
    if (url.pathname === '/platform') {
        return send(platformPage(url.searchParams.get('mode')));
    }
    if (url.pathname === '/storage-frame') {
        return send(`<!doctype html><script>var TAMPER = false;${STORAGE_HANDLER}</script>`);
    }
    if (url.pathname === '/authorize') {
        // OIDC authentication request of the tool; answer with the signed id_token, posted to the tool
        const redirect = url.searchParams.get('redirect_uri');
        if (url.searchParams.get('client_id') !== CLIENT_ID || !redirect) {
            res.writeHead(400);
            return res.end('bad request');
        }
        return send(`<!doctype html><form id="f" method="post" action="${redirect}">
<input type="hidden" name="id_token" value="${idToken(url.searchParams.get('nonce'))}">
<input type="hidden" name="state" value="${url.searchParams.get('state')}"></form><script>f.submit()</script>`);
    }
    res.writeHead(404);
    res.end();
});

async function scenario(browser, mode) {
    const page = await browser.newPage();
    const seen = { verify: [], storageMessages: [] };
    let landing = null;
    // the front is not running: the redirect to its landing page carries the one-time code
    page.on('request', (request) => {
        if (new URL(request.url()).hostname === FRONT_HOST) {
            landing = request.url();
        }
    });
    page.on('request', (request) => {
        if (request.url().endsWith('/api/lti/tool/launch/verify')) {
            seen.verify.push(request.postData() || '');
        }
    });
    await page.goto(`${ISSUER}/platform?mode=${mode}`);
    // silent: the tool waits for answers that never come (about 4 s per step)
    const deadline = Date.now() + (mode === 'silent' ? 30000 : 15000);
    let tool;
    while (Date.now() < deadline) {
        tool = page.frame({ name: 'tool' });
        const text = await tool.evaluate(() => document.body?.innerText || '').catch(() => '');
        if (landing !== null || /does not belong to this browser|expired|could not be opened/i.test(text)) {
            break;
        }
        await page.waitForTimeout(200);
    }
    const text = await page.frame({ name: 'tool' }).evaluate(() => document.body?.innerText || '').catch(() => '');
    const frames = [page, ...page.frames().map((f) => f)];
    for (const frame of page.frames()) {
        const messages = await frame.evaluate(() => window.__messages || []).catch(() => []);
        seen.storageMessages.push(...messages);
    }
    await page.close();
    return { landing, text: text.trim().slice(0, 300), seen, frames: frames.length };
}

(async () => {
    if (!courseId) {
        fail('config', 'ULAMS_COURSE_ID is required');
    }
    await new Promise((resolve) => server.listen(PLATFORM_PORT, '0.0.0.0', resolve));
    const browser = await chromium.launch({
        executablePath: process.env.CHROMIUM_PATH || undefined,
        args: [`--host-resolver-rules=MAP ${PLATFORM_HOST} 127.0.0.1, MAP ulams.test 127.0.0.1, MAP ${FRONT_HOST} 127.0.0.1`]
    });
    const summary = {};
    try {
        const subjects = (result) => result.seen.storageMessages.map((m) => m.subject);
        const landedWithCode = (result) => result.landing !== null && new URL(result.landing).searchParams.get('code');

        for (const mode of ['parent', 'frame']) {
            const result = (summary[mode] = await scenario(browser, mode));
            if (!landedWithCode(result)) {
                fail(mode, result);
            }
            for (const subject of ['lti.capabilities', 'lti.put_data', 'lti.get_data']) {
                if (!subjects(result).includes(subject)) {
                    fail(mode, { missing: subject, result });
                }
            }
            if (result.seen.verify.length !== 1) {
                fail(mode, { expected: 'one verify post', result });
            }
            const keys = result.seen.storageMessages.filter((m) => m.key).map((m) => m.key);
            if (keys.length < 2 || !keys.every((key) => key.startsWith('lti1p3_state-')) || new Set(keys).size !== 1) {
                fail(mode, { expected: 'put and get on one lti1p3_state-* key', keys });
            }
            if (!result.seen.storageMessages.every((m) => m.origin === ULAMS_PUBLIC)) {
                fail(mode, { expected: 'messages from the tool origin', result });
            }
        }

        const none = (summary.none = await scenario(browser, 'none'));
        if (!landedWithCode(none) || none.seen.verify.length !== 0 || none.seen.storageMessages.length !== 0) {
            fail('none', none);
        }

        const silent = (summary.silent = await scenario(browser, 'silent'));
        if (!landedWithCode(silent) || silent.seen.verify.length !== 1 || !/stored=(&|$)/.test(silent.seen.verify[0])) {
            fail('silent', silent);
        }

        const tampered = (summary.tampered = await scenario(browser, 'tampered'));
        if (landedWithCode(tampered) || !/does not belong to this browser/.test(tampered.text)) {
            fail('tampered', tampered);
        }
    } finally {
        await browser.close();
        server.close();
    }
    console.log(JSON.stringify({ ok: true, scenarios: Object.keys(summary) }));
    process.exit(0);
})().catch((error) => fail('unexpected', String(error?.stack || error)));
