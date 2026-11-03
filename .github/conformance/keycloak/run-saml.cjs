// SAML 2.0 sign-in against a real Keycloak (identity-keycloak.yml, after the OpenID Connect run):
//
//   ADMIN_TOKEN=... node run-saml.cjs
//
// 1. An administrator adds Keycloak as a SAML identity provider through the admin API, importing its metadata URL.
// 2. Keycloak itself reads ulams' SP metadata (its client-description converter) and gets a client made from it,
//    which makes Keycloak verify the signature of our AuthnRequests with the certificate from the metadata.
// 3. Learners sign in in a real browser: ulams -> Keycloak login form -> signed assertion posted to the ACS -> the
//    front end's callback URL with a one-time code (no front runs: the script reads the URL the browser is sent to).
// 4. The code is exchanged for a token that works; accounts and audit rows are as expected; a tampered response and a
//    replayed one are refused; single logout started here ends the session at Keycloak and comes back signed.
//
// Prints a JSON summary; exits non-zero on the first failed step. Needs `playwright` (CommonJS) resolvable.
const { chromium } = require('playwright');
const crypto = require('node:crypto');
const http = require('node:http');

const ULAMS = process.env.ULAMS_URL || 'http://ulams.test:18000';
const FRONT = process.env.FRONT_URL || 'http://front.test:18001';
const KEYCLOAK = process.env.KEYCLOAK_URL || 'http://keycloak.test:18180';
const KC_ADMIN = { username: process.env.KC_ADMIN_USER || 'admin', password: process.env.KC_ADMIN_PASSWORD || 'admin-conformance' };
const adminToken = process.env.ADMIN_TOKEN;
const SLUG = 'keycloak-saml';

const b64url = (buf) => buf.toString('base64url');

function fail(step, detail) {
    console.error(JSON.stringify({ ok: false, step, detail }));
    process.exit(1);
}

/** An HTTP call to any of the test hosts; every *.test name resolves to this machine whatever /etc/hosts says. */
function call(method, url, { token, json, form, text, headers = {} } = {}) {
    return new Promise((resolve, reject) => {
        const target = new URL(url);
        const payload = json !== undefined ? JSON.stringify(json) : form ? new URLSearchParams(form).toString() : text !== undefined ? text : null;
        const type = json !== undefined ? 'application/json' : form ? 'application/x-www-form-urlencoded' : text !== undefined ? 'text/plain' : null;
        const request = http.request(
            {
                method,
                host: target.hostname,
                port: target.port,
                path: target.pathname + target.search,
                lookup: (host, options, callback) => (options && options.all ? callback(null, [{ address: '127.0.0.1', family: 4 }]) : callback(null, '127.0.0.1', 4)),
                headers: {
                    Accept: 'application/json',
                    ...(payload !== null ? { 'Content-Type': type, 'Content-Length': Buffer.byteLength(payload) } : {}),
                    ...(token ? { Authorization: `Bearer ${token}` } : {}),
                    ...headers
                }
            },
            (res) => {
                const chunks = [];
                res.on('data', (chunk) => chunks.push(chunk));
                res.on('end', () => {
                    const body = Buffer.concat(chunks).toString('utf8');
                    let parsed;
                    try {
                        parsed = JSON.parse(body);
                    } catch {
                        parsed = { raw: body.slice(0, 2000) };
                    }
                    resolve({ status: res.statusCode, json: parsed, text: body, headers: res.headers });
                });
            }
        );
        request.on('error', reject);
        request.setTimeout(30000, () => request.destroy(new Error(`timeout: ${method} ${url}`)));
        if (payload !== null) request.write(payload);
        request.end();
    });
}

const api = (method, path, token, json) => call(method, ULAMS + path, { token, json });

/** An administrator token of Keycloak's master realm. */
async function keycloakAdminToken() {
    const response = await call('POST', `${KEYCLOAK}/realms/master/protocol/openid-connect/token`, {
        form: { grant_type: 'password', client_id: 'admin-cli', username: KC_ADMIN.username, password: KC_ADMIN.password }
    });
    if (response.status !== 200 || !response.json.access_token) fail('keycloak admin token', response.json);
    return response.json.access_token;
}

/**
 * Signs in as a Keycloak user in a browser context; resolves to the URL the browser was sent back to, the verifier
 * and the context (still open when `keep` is set, for single logout). `tamper` rewrites the SAMLResponse on its way
 * to the ACS.
 */
async function browserLogin(browser, { username, password, tamper, keep, slug = SLUG }) {
    const verifier = b64url(crypto.randomBytes(32));
    const challenge = b64url(crypto.createHash('sha256').update(verifier).digest());
    const context = await browser.newContext();
    const page = await context.newPage();
    const posted = [];
    await page.route(`**/api/identity/saml/${slug}/acs`, async (route) => {
        const request = route.request();
        const form = new URLSearchParams(request.postData() || '');
        posted.push({ SAMLResponse: form.get('SAMLResponse'), RelayState: form.get('RelayState') });
        if (tamper) {
            form.set('SAMLResponse', tamper(form.get('SAMLResponse')));
            await route.continue({ postData: form.toString() });
        } else {
            await route.continue();
        }
    });
    const arrived = new Promise((resolve) => page.on('request', (request) => request.url().startsWith(FRONT) && resolve(request.url())));
    const start = `${ULAMS}/api/identity/sso/${slug}/start?` + new URLSearchParams({ return_to: `${FRONT}/auth/sso/callback`, challenge });
    await page.goto(start, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#username', { timeout: 60000 });
    await page.fill('#username', username);
    await page.fill('#password', password);
    // either the front end's callback, or Keycloak shows the form again with an error (its SAML login page keeps the
    // same URL, so the error text is what tells); the refused navigation to the front end rejects this wait: then the
    // answer is the URL we caught
    const stayed = page
        .waitForSelector('text=/Invalid username or password|Account is disabled|Action expired|expired|error/i', { timeout: 45000 })
        .then(() => null, (error) => (/ERR_CONNECTION_REFUSED|Target page, context or browser has been closed|has been closed/.test(String(error)) ? arrived : null));
    await page.click('#kc-login');
    const finalUrl = (await Promise.race([arrived, stayed])) ?? page.url();
    const text = finalUrl.startsWith(FRONT) ? '' : await page.textContent('body').catch(() => '');
    if (!keep) await context.close();
    return { finalUrl, text, verifier, posted, context, page };
}

/**
 * Keycloak reads ulams' SP metadata of a provider (its client-description converter) and gets a client made from it.
 * `attributes` adds to the client's SAML attributes.
 */
async function createKeycloakClient(kc, provider, attributes = {}, overrides = {}) {
    const metadata = await call('GET', `${ULAMS}/api/identity/saml/${provider.slug}/metadata`, { headers: { Accept: 'application/samlmetadata+xml' } });
    if (metadata.status !== 200 || !/EntityDescriptor/.test(metadata.text) || /PRIVATE KEY/.test(metadata.text)) fail('sp metadata', metadata.text.slice(0, 300));
    const converted = await call('POST', `${KEYCLOAK}/admin/realms/ulams/client-description-converter`, { token: kc, text: metadata.text });
    if (converted.status !== 200 || converted.json.protocol !== 'saml') fail('keycloak reads the sp metadata', converted.json);
    const client = converted.json;
    if (client.clientId !== provider.saml.sp.entity_id) fail('sp entity id', { client: client.clientId, expected: provider.saml.sp.entity_id });
    if (client.attributes['saml.client.signature'] !== 'true' || !client.attributes['saml.signing.certificate']) fail('keycloak did not take our signing certificate', client.attributes);
    client.enabled = true;
    client.frontchannelLogout = true;
    Object.assign(client, overrides);
    client.attributes = {
        ...client.attributes,
        'saml.assertion.signature': 'true',
        'saml.server.signature': 'true',
        saml_name_id_format: 'persistent',
        'saml.force.post.binding': 'true',
        'saml.authnstatement': 'true',
        'saml_single_logout_service_url_redirect': provider.saml.sp.slo_url,
        'saml.signature.algorithm': 'RSA_SHA256',
        ...attributes
    };
    const mapper = (name, attribute, attributeName) => ({
        name,
        protocol: 'saml',
        protocolMapper: 'saml-user-property-mapper',
        consentRequired: false,
        config: { 'user.attribute': attribute, 'attribute.name': attributeName, 'attribute.nameformat': 'Basic', 'friendly.name': attributeName }
    });
    client.protocolMappers = [mapper('email', 'email', 'email'), mapper('first name', 'firstName', 'givenName'), mapper('last name', 'lastName', 'sn')];
    // a rerun against the same Keycloak starts clean
    const existing = await call('GET', `${KEYCLOAK}/admin/realms/ulams/clients?clientId=${encodeURIComponent(client.clientId)}`, { token: kc });
    for (const old of Array.isArray(existing.json) ? existing.json : []) {
        await call('DELETE', `${KEYCLOAK}/admin/realms/ulams/clients/${old.id}`, { token: kc });
    }
    const created = await call('POST', `${KEYCLOAK}/admin/realms/ulams/clients`, { token: kc, json: client });
    if (created.status !== 201) fail('create keycloak client', created.json);
    return client;
}

const decodeResponse = (b64) => Buffer.from(b64, 'base64').toString('utf8');

(async () => {
    if (!adminToken) fail('config', 'ADMIN_TOKEN is required');
    const summary = {};

    // 1. the provider, through the admin API, from Keycloak's metadata URL
    const created = await api('POST', '/api/admin/identity/providers', adminToken, {
        protocol: 'saml',
        slug: SLUG,
        name: 'Keycloak SAML',
        enabled: true,
        email_domains: ['example.com'],
        trust_email: true,
        saml: { metadata_url: `${KEYCLOAK}/realms/ulams/protocol/saml/descriptor` }
    });
    if (created.status !== 201) fail('create provider', created);
    const provider = created.json.data;
    if (provider.saml.entity_id !== `${KEYCLOAK}/realms/ulams`) fail('imported entity id', provider.saml);
    if (!provider.saml.sso_url || provider.saml.idp_certificates.length < 1) fail('imported metadata', provider.saml);
    if (/PRIVATE KEY/.test(created.text)) fail('create provider', 'the response contains a private key');
    summary.idp = { entity_id: provider.saml.entity_id, certificates: provider.saml.idp_certificates.length, sso_url: provider.saml.sso_url, slo_url: provider.saml.slo_url };
    const listed = await api('GET', '/api/identity/providers');
    if (listed.status !== 200 || !listed.json.data.some((p) => p.slug === SLUG && p.protocol === 'saml')) fail('public provider list', listed);

    // 2. Keycloak reads our SP metadata and gets a client made from it: it verifies our signed requests with the
    // certificate of the metadata
    const kc = await keycloakAdminToken();
    const client = await createKeycloakClient(kc, provider);
    if (/saml\.encrypt"?:\s*"true"/.test(JSON.stringify(client.attributes))) fail('the metadata invited Keycloak to encrypt', client.attributes);

    const browser = await chromium.launch({
        args: [`--host-resolver-rules=MAP keycloak.test 127.0.0.1, MAP ulams.test 127.0.0.1, MAP front.test 127.0.0.1`]
    });
    let samToken;
    try {
        // 3. Sam signs in: a new student, through a response Keycloak signed for our ACS
        const sam = await browserLogin(browser, { username: 'sam', password: 'sam-password-1', keep: true });
        const back = new URL(sam.finalUrl);
        if (back.origin + back.pathname !== `${FRONT}/auth/sso/callback`) fail('sam: returned to', { url: sam.finalUrl, page: sam.text.replace(/\s+/g, ' ').slice(0, 400) });
        const code = back.searchParams.get('code');
        if (!code || back.searchParams.get('complete') !== 'true') fail('sam: one-time code', sam.finalUrl);
        if (/eyJ/.test(sam.finalUrl)) fail('sam: a token is in the URL', sam.finalUrl);
        const xml = decodeResponse(sam.posted[0].SAMLResponse);
        if (!/<(saml\d?|saml):Assertion[^>]*>/.test(xml) || !/Signature/.test(xml)) fail('sam: the response was not a signed assertion', xml.slice(0, 300));

        const exchange = await api('POST', '/api/identity/sso/exchange', null, { code, verifier: sam.verifier });
        if (exchange.status !== 200 || !exchange.json.data?.token) fail('sam: exchange', exchange);
        samToken = exchange.json.data.token;
        const again = await api('POST', '/api/identity/sso/exchange', null, { code, verifier: sam.verifier });
        if (again.status !== 400) fail('sam: the code worked twice', again);
        const me = await api('GET', '/api/profile/me', samToken);
        if (me.status !== 200 || me.json.data.email !== 'sam@example.com' || me.json.data.first_name !== 'Sam' || me.json.data.last_name !== 'Smith') fail('sam: profile', me);
        const roles = JSON.stringify(me.json.data.roles ?? []);
        if (!roles.includes('student') || roles.includes('admin')) fail('sam: roles', me.json.data.roles);
        summary.sam = { email: me.json.data.email, roles: me.json.data.roles };

        // the same response cannot be posted again: its login state is burned
        const replay = await call('POST', `${ULAMS}/api/identity/saml/${SLUG}/acs`, { form: sam.posted[0] });
        if (replay.status !== 400) fail('replayed response', replay.status);

        // a second sign-in creates nobody
        const samAgain = await browserLogin(browser, { username: 'sam', password: 'sam-password-1' });
        if (!new URL(samAgain.finalUrl).searchParams.get('code')) fail('sam again: one-time code', samAgain.finalUrl);

        // Ada: linked by e-mail (she has an account from the OpenID Connect run) or created
        const ada = await browserLogin(browser, { username: 'ada', password: 'ada-password-1' });
        if (!new URL(ada.finalUrl).searchParams.get('code')) fail('ada: one-time code', ada.finalUrl);

        // a wrong password never reaches ulams
        const wrong = await browserLogin(browser, { username: 'sam', password: 'not-the-password' });
        if (wrong.finalUrl.startsWith(FRONT)) fail('wrong password: returned to the front end', wrong.finalUrl);
        if (!/Invalid username or password/i.test(wrong.text)) fail('wrong password: no error at Keycloak', wrong.text.slice(0, 200));

        // a response changed on its way to the ACS (another identity in the NameID, same signature) is refused
        const tampered = await browserLogin(browser, {
            username: 'sam',
            password: 'sam-password-1',
            tamper: (b64) => Buffer.from(decodeResponse(b64).replace(/(<(?:saml\d?:)?NameID[^>]*>)[^<]+/, '$1G-someone-else'), 'utf8').toString('base64')
        });
        const tamperedBack = new URL(tampered.finalUrl);
        if (tamperedBack.searchParams.get('error') !== 'provider_failed' || tamperedBack.searchParams.has('code')) fail('tampered response: expected provider_failed', tampered.finalUrl);

        // 4. single logout started here: the token dies, the browser goes to Keycloak and comes back signed
        const logout = await call('POST', `${ULAMS}/api/identity/saml/${SLUG}/logout`, { token: samToken, json: { return_to: `${FRONT}/auth/logged-out` } });
        if (logout.status !== 200 || !logout.json.data?.redirect_url) fail('logout', logout);
        const kcUrl = logout.json.data.redirect_url;
        if (!kcUrl.startsWith(`${KEYCLOAK}/realms/ulams/protocol/saml`) || !/Signature=/.test(kcUrl)) fail('logout url', kcUrl);
        const afterLogout = await api('GET', '/api/profile/me', samToken);
        if (afterLogout.status !== 401) fail('the token still works after logout', afterLogout.status);
        const loggedOut = new Promise((resolve) => sam.page.on('request', (request) => request.url().startsWith(`${FRONT}/auth/logged-out`) && resolve(request.url())));
        await sam.page.goto(kcUrl, { waitUntil: 'domcontentloaded' }).catch(() => undefined);
        const landed = await Promise.race([loggedOut, new Promise((resolve) => setTimeout(() => resolve(sam.page.url()), 30000))]);
        if (!String(landed).startsWith(`${FRONT}/auth/logged-out`)) fail('logout: the browser did not come back to the return URL', landed);
        // Keycloak's session is gone: signing in again asks for the password
        const again2 = await sam.context.newPage();
        await again2.goto(`${ULAMS}/api/identity/sso/${SLUG}/start?` + new URLSearchParams({ return_to: `${FRONT}/auth/sso/callback`, challenge: b64url(crypto.randomBytes(32)) }), { waitUntil: 'domcontentloaded' });
        await again2.waitForSelector('#username', { timeout: 30000 }).catch(() => fail('logout: Keycloak still has the session', again2.url()));
        await sam.context.close();
    } finally {
        await browser.close();
    }

    // 5. encrypted assertions: a second provider that requires them, with a Keycloak client that encrypts for the
    // certificate of its metadata (RSA-OAEP-MGF1P with the SHA-1 digest, which is what the SAML library opens, and AES-256-GCM)
    const encSlug = 'keycloak-saml-encrypted';
    const encCreated = await api('POST', '/api/admin/identity/providers', adminToken, {
        protocol: 'saml',
        slug: encSlug,
        name: 'Keycloak SAML (encrypted)',
        enabled: true,
        email_domains: ['example.com'],
        trust_email: true,
        saml: { metadata_url: `${KEYCLOAK}/realms/ulams/protocol/saml/descriptor`, want_encrypted_assertions: true }
    });
    if (encCreated.status !== 201) fail('create encrypted provider', encCreated);
    const encClient = await createKeycloakClient(kc, encCreated.json.data, {
        'saml.encrypt': 'true',
        'saml.encryption.algorithm': 'http://www.w3.org/2009/xmlenc11#aes256-gcm',
        'saml.encryption.keyAlgorithm': 'http://www.w3.org/2001/04/xmlenc#rsa-oaep-mgf1p',
        'saml.encryption.digestMethod': 'http://www.w3.org/2000/09/xmldsig#sha1'
    });
    if (!encClient.attributes['saml.encryption.certificate']) fail('the metadata of an encrypting provider offers no encryption certificate', encClient.attributes);
    const encBrowser = await chromium.launch({
        args: [`--host-resolver-rules=MAP keycloak.test 127.0.0.1, MAP ulams.test 127.0.0.1, MAP front.test 127.0.0.1`]
    });
    try {
        const enc = await browserLogin(encBrowser, { username: 'sam', password: 'sam-password-1', slug: encSlug });
        if (enc.posted.length === 0) fail('encrypted: nothing was posted to the ACS', { url: enc.finalUrl, page: enc.text.replace(/\s+/g, ' ').slice(0, 400) });
        const encXml = decodeResponse(enc.posted[0].SAMLResponse);
        if (!/EncryptedAssertion/.test(encXml)) fail('encrypted: the assertion was not encrypted', encXml.slice(0, 300));
        const encCode = new URL(enc.finalUrl).searchParams.get('code');
        if (!encCode) fail('encrypted: one-time code', { url: enc.finalUrl, page: enc.text.replace(/\s+/g, ' ').slice(0, 400), algorithms: encXml.match(/Algorithm="[^"]+"/g) });
        const encExchange = await api('POST', '/api/identity/sso/exchange', null, { code: encCode, verifier: enc.verifier });
        if (encExchange.status !== 200) fail('encrypted: exchange', encExchange);
        summary.encrypted = { algorithms: [...new Set(encXml.match(/Algorithm="[^"]+"/g))] };
    } finally {
        await encBrowser.close();
    }

    // 6. single logout started by the identity provider: Keycloak ends Sam's session (an administrator signs him out)
    // and sends a signed LogoutRequest to our SLO endpoint server to server, which must end the token
    const bcSlug = 'keycloak-saml-backchannel';
    const bcCreated = await api('POST', '/api/admin/identity/providers', adminToken, {
        protocol: 'saml',
        slug: bcSlug,
        name: 'Keycloak SAML (IdP logout)',
        enabled: true,
        email_domains: ['example.com'],
        trust_email: true,
        saml: { metadata_url: `${KEYCLOAK}/realms/ulams/protocol/saml/descriptor` }
    });
    if (bcCreated.status !== 201) fail('create logout provider', bcCreated);
    await createKeycloakClient(kc, bcCreated.json.data, {
        'saml_single_logout_service_url_post': bcCreated.json.data.saml.sp.slo_url,
        'saml_single_logout_service_url_redirect': ''
    }, { frontchannelLogout: false });
    const bcBrowser = await chromium.launch({
        args: [`--host-resolver-rules=MAP keycloak.test 127.0.0.1, MAP ulams.test 127.0.0.1, MAP front.test 127.0.0.1`]
    });
    try {
        const bc = await browserLogin(bcBrowser, { username: 'sam', password: 'sam-password-1', slug: bcSlug });
        const bcCode = new URL(bc.finalUrl).searchParams.get('code');
        if (!bcCode) fail('idp logout: one-time code', { url: bc.finalUrl, page: bc.text.replace(/\s+/g, ' ').slice(0, 300) });
        const bcExchange = await api('POST', '/api/identity/sso/exchange', null, { code: bcCode, verifier: bc.verifier });
        if (bcExchange.status !== 200) fail('idp logout: exchange', bcExchange);
        const bcToken = bcExchange.json.data.token;
        if ((await api('GET', '/api/profile/me', bcToken)).status !== 200) fail('idp logout: the token does not work', 'before');
        const found = await call('GET', `${KEYCLOAK}/admin/realms/ulams/users?username=sam&exact=true`, { token: kc });
        const kcUser = Array.isArray(found.json) ? found.json[0] : null;
        if (!kcUser) fail('idp logout: keycloak user', found.json);
        const signedOut = await call('POST', `${KEYCLOAK}/admin/realms/ulams/users/${kcUser.id}/logout`, { token: kc });
        if (signedOut.status !== 204) fail('idp logout: keycloak admin logout', signedOut);
        // the LogoutRequest is sent server to server while Keycloak handles the call above; allow a moment for it
        let ended = false;
        for (let i = 0; i < 20 && !ended; i++) {
            ended = (await api('GET', '/api/profile/me', bcToken)).status === 401;
            if (!ended) await new Promise((resolve) => setTimeout(resolve, 500));
        }
        if (!ended) fail('idp logout: the token still works after Keycloak ended the session', 'timeout');
    } finally {
        await bcBrowser.close();
    }

    // the metadata refresh finds nothing new at Keycloak
    const refresh = await api('POST', `/api/admin/identity/providers/${provider.id}/metadata/refresh`, adminToken, {});
    if (refresh.status !== 200 || refresh.json.data.status !== 'unchanged') fail('metadata refresh', refresh);

    // what the administrator sees
    const audit = await api('GET', '/api/admin/identity/audit?limit=200', adminToken);
    if (audit.status !== 200) fail('audit', audit);
    const rows = audit.json.data.filter((r) => r.provider_id === provider.id);
    const logins = rows.filter((r) => r.action === 'sso.login');
    const failures = rows.filter((r) => r.action === 'sso.login_failed');
    if (logins.length !== 3) fail('audit: expected three sso.login rows (sam twice, ada)', rows.map((r) => r.action));
    if (!logins.every((r) => r.data.protocol === 'saml')) fail('audit: protocol of the logins', logins);
    // a first run creates Sam; a rerun on the same database links his existing account by e-mail instead
    if (!logins.some((r) => r.data.created === true) && !rows.some((r) => r.action === 'sso.link')) fail('audit: nobody was created or linked', logins);
    if (!failures.some((r) => r.data.reason === 'signature_invalid')) fail('audit: the tampered response is not in the failures', failures);
    if (!rows.some((r) => r.action === 'saml.logout' && r.data.kind === 'response')) fail('audit: no saml.logout (answer to our request)', rows.map((r) => r.action));
    if (!audit.json.data.some((r) => r.action === 'saml.logout' && r.data.kind === 'request' && r.data.revoked >= 1)) fail('audit: no saml.logout (request from the identity provider)', audit.json.data.map((r) => r.action));
    const trail = JSON.stringify(rows);
    if (/PRIVATE KEY|SAMLResponse|eyJ/.test(trail)) fail('audit: a secret, message or token is in the trail', 'found');
    const verify = await api('GET', '/api/admin/identity/audit/verify', adminToken);
    if (verify.status !== 200 || verify.json.data.ok !== true) fail('audit verify', verify);

    summary.audit = { logins: logins.length, failures: failures.map((r) => r.data.reason), chain: verify.json.data };
    console.log(JSON.stringify({ ok: true, ...summary }));
})().catch((e) => fail('unexpected', String((e && e.stack) || e)));
