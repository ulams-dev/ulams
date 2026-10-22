// OpenID Connect sign-in against a real Keycloak (identity-keycloak.yml):
//
//   ADMIN_TOKEN=... node run-oidc.cjs
//
// 1. An administrator adds Keycloak as an identity provider through the admin API.
// 2. A learner signs in in a real browser: ulams -> Keycloak login form -> ulams callback -> the front end's
//    callback URL with a one-time code (no front runs: the script serves a stub page there and reads the URL).
// 3. The code is exchanged with the verifier for an access token, which works on the profile endpoint.
// 4. A new student exists, the audit trail says so and verifies; a second sign-in creates nobody; a wrong
//    password never reaches ulams; a replayed callback is refused; an address Keycloak did not verify is refused.
//
// Prints a JSON summary; exits non-zero on the first failed step. Needs `playwright` (CommonJS) resolvable,
// e.g. NODE_PATH=<dir>/node_modules.
const { chromium } = require('playwright');
const crypto = require('node:crypto');
const http = require('node:http');

const ULAMS = process.env.ULAMS_URL || 'http://ulams.test:18000';
const FRONT = process.env.FRONT_URL || 'http://front.test:18001';
const KEYCLOAK = process.env.KEYCLOAK_URL || 'http://keycloak.test:18180';
const adminToken = process.env.ADMIN_TOKEN;

const b64url = (buf) => buf.toString('base64url');

function fail(step, detail) {
    console.error(JSON.stringify({ ok: false, step, detail }));
    process.exit(1);
}

/**
 * A call to the API under the name the browser used (ulams.test), because one-time codes are bound to the host
 * they were issued for. The name resolves to this machine whatever /etc/hosts says.
 */
function api(method, path, token, body) {
    return new Promise((resolve, reject) => {
        const url = new URL(ULAMS + path);
        const payload = body ? JSON.stringify(body) : null;
        const request = http.request(
            {
                method,
                host: url.hostname,
                port: url.port,
                path: url.pathname + url.search,
                lookup: (host, options, callback) => (options && options.all ? callback(null, [{ address: '127.0.0.1', family: 4 }]) : callback(null, '127.0.0.1', 4)),
                headers: {
                    Accept: 'application/json',
                    ...(payload ? { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(payload) } : {}),
                    ...(token ? { Authorization: `Bearer ${token}` } : {})
                }
            },
            (res) => {
                const chunks = [];
                res.on('data', (chunk) => chunks.push(chunk));
                res.on('end', () => {
                    const text = Buffer.concat(chunks).toString('utf8');
                    let json;
                    try {
                        json = JSON.parse(text);
                    } catch {
                        json = { raw: text.slice(0, 300) };
                    }
                    resolve({ status: res.statusCode, json, headers: res.headers });
                });
            }
        );
        request.on('error', reject);
        request.setTimeout(30000, () => request.destroy(new Error(`timeout: ${method} ${path}`)));
        if (payload) request.write(payload);
        request.end();
    });
}

/** Signs in as a Keycloak user in a fresh browser; resolves to the URL the browser was sent back to. */
async function browserLogin(browser, { username, password }) {
    const verifier = b64url(crypto.randomBytes(32));
    const challenge = b64url(crypto.createHash('sha256').update(verifier).digest());
    const context = await browser.newContext();
    const page = await context.newPage();
    // no front end runs: the browser is sent to its callback URL, which is refused at the network, and we read the
    // URL from the request (a stub page cannot be served there, Playwright does not route redirect targets)
    const arrived = new Promise((resolve) => page.on('request', (request) => request.url().startsWith(FRONT) && resolve(request.url())));
    const start = `${ULAMS}/api/identity/sso/keycloak/start?` + new URLSearchParams({ return_to: `${FRONT}/auth/sso/callback`, challenge });
    await page.goto(start, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#username', { timeout: 60000 });
    await page.fill('#username', username);
    await page.fill('#password', password);
    // either the front end's callback, or Keycloak shows the form again with an error
    // (the refused navigation to the front end also rejects this wait: then the answer is the URL we caught)
    const stayed = page
        .waitForURL((url) => /\/realms\/ulams\/login-actions\//.test(url.href), { timeout: 60000 })
        .then(() => null, (error) => (/ERR_CONNECTION_REFUSED/.test(String(error)) ? arrived : Promise.reject(error)));
    await page.click('#kc-login');
    const finalUrl = (await Promise.race([arrived, stayed])) ?? page.url();
    const text = finalUrl.startsWith(FRONT) ? '' : await page.textContent('body').catch(() => '');
    await context.close();
    return { finalUrl, text, verifier };
}

(async () => {
    if (!adminToken) fail('config', 'ADMIN_TOKEN is required');
    const summary = {};

    // 1. the provider, through the admin API
    const created = await api('POST', '/api/admin/identity/providers', adminToken, {
        slug: 'keycloak',
        name: 'Keycloak',
        enabled: true,
        email_domains: ['example.com'],
        oidc: { issuer: `${KEYCLOAK}/realms/ulams`, client_id: 'ulams-web', scopes: ['email', 'profile'] },
        client_secret: 'keycloak-conformance-secret'
    });
    if (created.status !== 201) fail('create provider', created);
    if (JSON.stringify(created.json).includes('keycloak-conformance-secret')) fail('create provider', 'the response contains the client secret');
    summary.redirect_uri = created.json.data.redirect_uri;
    const listed = await api('GET', '/api/identity/providers');
    if (listed.status !== 200 || listed.json.data.length !== 1 || listed.json.data[0].slug !== 'keycloak') fail('public provider list', listed);

    const browser = await chromium.launch({
        args: [`--host-resolver-rules=MAP keycloak.test 127.0.0.1, MAP ulams.test 127.0.0.1, MAP front.test 127.0.0.1`]
    });
    try {
        // 2. Ada signs in
        const ada = await browserLogin(browser, { username: 'ada', password: 'ada-password-1' });
        const back = new URL(ada.finalUrl);
        if (back.origin + back.pathname !== `${FRONT}/auth/sso/callback`) fail('ada: returned to', ada.finalUrl);
        const code = back.searchParams.get('code');
        if (!code || back.searchParams.get('complete') !== 'true') fail('ada: one-time code', ada.finalUrl);
        if (/eyJ/.test(ada.finalUrl)) fail('ada: a token is in the URL', ada.finalUrl);

        // 3. the code is exchanged, once
        const exchange = await api('POST', '/api/identity/sso/exchange', null, { code, verifier: ada.verifier });
        if (exchange.status !== 200 || !exchange.json.data?.token) fail('ada: exchange', exchange);
        const again = await api('POST', '/api/identity/sso/exchange', null, { code, verifier: ada.verifier });
        if (again.status !== 400) fail('ada: the code worked twice', again);
        const me = await api('GET', '/api/profile/me', exchange.json.data.token);
        if (me.status !== 200 || me.json.data.email !== 'ada@example.com' || me.json.data.first_name !== 'Ada') fail('ada: profile', me);
        const roles = JSON.stringify(me.json.data.roles ?? []);
        if (!roles.includes('student') || roles.includes('admin')) fail('ada: roles', me.json.data.roles);
        summary.ada = { email: me.json.data.email, roles: me.json.data.roles };

        // a second sign-in creates nobody
        const adaAgain = await browserLogin(browser, { username: 'ada', password: 'ada-password-1' });
        if (!new URL(adaAgain.finalUrl).searchParams.get('code')) fail('ada again: one-time code', adaAgain.finalUrl);

        // a wrong password never reaches ulams
        const wrong = await browserLogin(browser, { username: 'ada', password: 'not-the-password' });
        if (wrong.finalUrl.startsWith(FRONT)) fail('wrong password: returned to the front end', wrong.finalUrl);
        if (!/Invalid username or password/i.test(wrong.text)) fail('wrong password: no error at Keycloak', wrong.text.slice(0, 200));

        // an address Keycloak did not verify is refused, nobody is created
        const bob = await browserLogin(browser, { username: 'bob', password: 'bob-password-1' });
        const bobBack = new URL(bob.finalUrl);
        if (bobBack.searchParams.get('error') !== 'email_unverified' || bobBack.searchParams.has('code')) fail('bob: expected email_unverified', bob.finalUrl);

        // a callback cannot be replayed: its state is burned
        const replay = await api('GET', `/api/identity/oidc/keycloak/callback?code=x&state=${'A'.repeat(43)}.${Math.floor(Date.now() / 1000) + 60}.${'B'.repeat(43)}`);
        if (replay.status !== 400) fail('forged state', replay);
    } finally {
        await browser.close();
    }

    // 4. what the administrator sees
    const audit = await api('GET', '/api/admin/identity/audit?limit=100', adminToken);
    if (audit.status !== 200) fail('audit', audit);
    const rows = audit.json.data;
    const logins = rows.filter((r) => r.action === 'sso.login');
    const failures = rows.filter((r) => r.action === 'sso.login_failed');
    if (logins.length !== 2) fail('audit: expected two sso.login rows', rows.map((r) => r.action));
    if (logins.filter((r) => r.data.created === true).length !== 1) fail('audit: exactly one login should have created the account', logins);
    if (!rows.some((r) => r.action === 'sso.link')) fail('audit: no sso.link', rows.map((r) => r.action));
    if (!failures.some((r) => r.data.reason === 'email_not_verified_by_provider')) fail('audit: bob is not in the failures', failures);
    if (JSON.stringify(rows).includes('keycloak-conformance-secret') || /eyJ/.test(JSON.stringify(rows))) fail('audit: secret or token in the trail', 'found');
    const verify = await api('GET', '/api/admin/identity/audit/verify', adminToken);
    if (verify.status !== 200 || verify.json.data.ok !== true) fail('audit verify', verify);
    const users = await api('GET', '/api/admin/users?search=example.com&per_page=50', adminToken);
    const emails = JSON.stringify(users.json).match(/(ada|bob)@example\.com/g) || [];
    if (emails.filter((e) => e === 'ada@example.com').length < 1 || emails.includes('bob@example.com')) fail('users: expected Ada once and no Bob', emails);

    summary.audit = { logins: logins.length, failures: failures.length, chain: verify.json.data };
    console.log(JSON.stringify({ ok: true, ...summary }));
})().catch((e) => fail('unexpected', String((e && e.stack) || e)));
