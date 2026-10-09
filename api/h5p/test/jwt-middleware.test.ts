import express from 'express';
import request from 'supertest';
import { beforeAll, describe, expect, it, vi } from 'vitest';

import { authMiddleware } from '../src/auth/middleware';
import { JwtVerifier } from '../src/auth/jwt';
import { HttpGet, ProfileCache, ProfileClient, tokenCacheKey } from '../src/auth/profile';
import { H5PUser } from '../src/auth/users';
import { makeKeyPair, KeyPair } from './helpers';

const PROFILE = {
    success: true,
    message: 'My profile',
    data: {
        id: 1,
        name: 'Root Admin',
        first_name: 'Root',
        last_name: 'Admin',
        email: 'admin@ulams.app',
        roles: ['admin'],
        permissions: ['h5p_list', 'h5p_create', 'access dashboard']
    }
};

class MemoryCache implements ProfileCache {
    store = new Map<string, { value: string; ttl: number }>();
    async get(key: string) {
        return this.store.get(key)?.value;
    }
    async set(key: string, value: string, ttl: number) {
        this.store.set(key, { value, ttl });
    }
}

function buildApp(opts: { verifier?: JwtVerifier; httpGet: HttpGet; cache?: MemoryCache; internalToken?: string }) {
    const profiles = new ProfileClient({
        baseUrl: 'http://caddy',
        hostHeader: 'api.localhost',
        profilePath: '/api/profile/me',
        cacheTtlSec: 60,
        cache: opts.cache,
        httpGet: opts.httpGet
    });
    const app = express();
    app.use(
        authMiddleware({
            forRequest: () => ({ verifier: opts.verifier, profiles, internalToken: opts.internalToken })
        })
    );
    app.get('/whoami', (req, res) => {
        const user = (req as any).user as H5PUser;
        res.json({ user, hasToken: Boolean(user.token), serialized: JSON.stringify(user) });
    });
    return app;
}

describe('auth middleware (JWT + profile + internal token)', () => {
    let keys: KeyPair;
    let otherKeys: KeyPair;
    let verifier: JwtVerifier;
    const okProfile: HttpGet = async () => ({ status: 200, body: JSON.stringify(PROFILE) });

    beforeAll(async () => {
        keys = await makeKeyPair();
        otherKeys = await makeKeyPair();
        verifier = new JwtVerifier({ publicKeyPem: keys.publicPem, clockToleranceSec: 5 });
    });

    it('keeps the token out of the user (and so out of the model URLs) behind a session proxy', async () => {
        const token = await keys.sign({}, { sub: '1' });
        const app = buildApp({ verifier, httpGet: okProfile });

        const direct = await request(app).get('/whoami').set('Authorization', `Bearer ${token}`);
        expect(direct.body.hasToken).toBe(true);

        const proxied = await request(app).get('/whoami').set('Authorization', `Bearer ${token}`).set('X-Ulams-Session-Proxy', '1');
        expect(proxied.status).toBe(200);
        expect(proxied.body.user.id).toBe('1');
        expect(proxied.body.user.isAnonymous).toBe(false);
        expect(proxied.body.hasToken).toBe(false);
    });

    it('accepts a valid Bearer token and loads permissions from the profile', async () => {
        const httpGet = vi.fn(okProfile);
        const app = buildApp({ verifier, httpGet });
        const token = await keys.sign({}, { sub: '1' });
        const res = await request(app).get('/whoami').set('Authorization', `Bearer ${token}`);
        expect(res.status).toBe(200);
        expect(res.body.user).toMatchObject({
            id: '1',
            name: 'Root Admin',
            email: 'admin@ulams.app',
            type: 'local',
            roles: ['admin'],
            permissions: ['h5p_list', 'h5p_create', 'access dashboard'],
            isAnonymous: false,
            isSystem: false
        });
        expect(res.body.hasToken).toBe(true);
        // the token is kept on the user for URL generation but never serialised
        expect(res.body.serialized).not.toContain(token);
        // the profile call goes to Caddy with the API Host header
        const [url, headers] = httpGet.mock.calls[0];
        expect(url).toBe('http://caddy/api/profile/me');
        expect(headers).toMatchObject({ Host: 'api.localhost', Authorization: `Bearer ${token}` });
    });

    it('accepts the token from ?_token= (H5P core AJAX calls)', async () => {
        const app = buildApp({ verifier, httpGet: okProfile });
        const token = await keys.sign({}, { sub: '1' });
        const res = await request(app).get(`/whoami?_token=${token}`);
        expect(res.body.user.id).toBe('1');
        expect(res.body.user.isAnonymous).toBe(false);
    });

    it('treats an expired token as anonymous', async () => {
        const httpGet = vi.fn(okProfile);
        const app = buildApp({ verifier, httpGet });
        const token = await keys.sign({}, { expiresInSec: -120 });
        const res = await request(app).get('/whoami').set('Authorization', `Bearer ${token}`);
        expect(res.body.user).toMatchObject({ id: 'anonymous', name: 'Anonymous', email: '', permissions: [] });
        expect(httpGet).not.toHaveBeenCalled();
    });

    it('tolerates small clock skew', async () => {
        const app = buildApp({ verifier, httpGet: okProfile });
        const token = await keys.sign({}, { expiresInSec: -2 }); // tolerance is 5 s
        const res = await request(app).get('/whoami').set('Authorization', `Bearer ${token}`);
        expect(res.body.user.id).toBe('1');
    });

    it('treats a token signed with another key as anonymous', async () => {
        const app = buildApp({ verifier, httpGet: okProfile });
        const token = await otherKeys.sign({});
        const res = await request(app).get('/whoami').set('Authorization', `Bearer ${token}`);
        expect(res.body.user.id).toBe('anonymous');
    });

    it('treats garbage and missing tokens as anonymous', async () => {
        const app = buildApp({ verifier, httpGet: okProfile });
        expect((await request(app).get('/whoami').set('Authorization', 'Bearer not.a.jwt')).body.user.id).toBe(
            'anonymous'
        );
        expect((await request(app).get('/whoami')).body.user).toMatchObject({ id: 'anonymous', isAnonymous: true });
    });

    it('rejects HS256 tokens (algorithm confusion)', async () => {
        const app = buildApp({ verifier, httpGet: okProfile });
        const header = Buffer.from(JSON.stringify({ alg: 'HS256', typ: 'JWT' })).toString('base64url');
        const body = Buffer.from(JSON.stringify({ sub: '1', exp: Date.now() / 1000 + 60 })).toString('base64url');
        const res = await request(app).get('/whoami').set('Authorization', `Bearer ${header}.${body}.abc`);
        expect(res.body.user.id).toBe('anonymous');
    });

    it('treats a token Laravel rejects (revoked) as anonymous', async () => {
        const app = buildApp({ verifier, httpGet: async () => ({ status: 401, body: '{"message":"Unauthenticated."}' }) });
        const token = await keys.sign({});
        const res = await request(app).get('/whoami').set('Authorization', `Bearer ${token}`);
        expect(res.body.user.id).toBe('anonymous');
    });

    it('keeps the identity but no permissions when Laravel is unreachable', async () => {
        const app = buildApp({
            verifier,
            httpGet: async () => {
                throw new Error('ECONNREFUSED');
            }
        });
        const token = await keys.sign({}, { sub: '42' });
        const res = await request(app).get('/whoami').set('Authorization', `Bearer ${token}`);
        expect(res.body.user).toMatchObject({ id: '42', isAnonymous: false, permissions: [] });
    });

    it('caches the profile by sha256(token), TTL capped by token expiry', async () => {
        const cache = new MemoryCache();
        const httpGet = vi.fn(okProfile);
        const app = buildApp({ verifier, httpGet, cache });
        const token = await keys.sign({}, { expiresInSec: 20 });
        await request(app).get('/whoami').set('Authorization', `Bearer ${token}`);
        await request(app).get('/whoami').set('Authorization', `Bearer ${token}`);
        expect(httpGet).toHaveBeenCalledTimes(1);
        const entry = cache.store.get(tokenCacheKey(token));
        expect(entry).toBeDefined();
        expect(entry!.ttl).toBeLessThanOrEqual(20);
        expect(entry!.ttl).toBeGreaterThan(10);
        expect([...cache.store.keys()][0]).not.toContain(token);
    });

    it('maps X-Internal-Token to the system user', async () => {
        const app = buildApp({ verifier, httpGet: okProfile, internalToken: 's3cret' });
        const res = await request(app).get('/whoami').set('X-Internal-Token', 's3cret');
        expect(res.body.user).toMatchObject({ id: 'system', isSystem: true });
        expect(res.body.user.permissions).toContain('h5p_delete');
    });

    it('rejects a wrong internal token with 401', async () => {
        const app = buildApp({ verifier, httpGet: okProfile, internalToken: 's3cret' });
        const res = await request(app).get('/whoami').set('X-Internal-Token', 'nope');
        expect(res.status).toBe(401);
        expect(res.body).toEqual({ success: false, message: 'Invalid internal token' });
    });

    it('rejects internal tokens when none is configured', async () => {
        const app = buildApp({ verifier, httpGet: okProfile });
        const res = await request(app).get('/whoami').set('X-Internal-Token', 'anything');
        expect(res.status).toBe(401);
    });

    it('ignores the permissions of a profile that belongs to another user', async () => {
        const app = buildApp({ verifier, httpGet: okProfile });
        const token = await keys.sign({}, { sub: '7' }); // profile says id 1
        const res = await request(app).get('/whoami').set('Authorization', `Bearer ${token}`);
        expect(res.body.user).toMatchObject({ id: '7', permissions: [] });
    });
});
