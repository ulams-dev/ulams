import http from 'node:http';
import { AddressInfo } from 'node:net';
import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import request from 'supertest';
import pino from 'pino';
import { createClient, RedisClientType } from 'redis';
import type { Express } from 'express';

import { AppConfig } from '../src/config';
import { createApp } from '../src/app';
import { createSharedH5P } from '../src/h5p/createH5P';
import { createI18n } from '../src/h5p/i18n';
import { buildTenant } from '../src/tenancy/buildTenant';
import { settingsFromEnv, SingleTenantResolver } from '../src/tenancy/SingleTenantResolver';
import { Tenant } from '../src/tenancy/types';
import { ALL_H5P_PERMISSIONS } from '../src/auth/users';
import { deletePrefix, dropSchema, KeyPair, makeKeyPair, testConfig } from './helpers';

const PERMISSIONS: Record<string, string[]> = {
    '1': [...ALL_H5P_PERMISSIONS], // admin
    '10': ['h5p_author_list', 'h5p_create', 'h5p_author_update'], // author
    '30': [] // learner
};

/** Fake Laravel /api/profile/me; only answers for Host: api.localhost. */
function startFakeLaravel(): Promise<http.Server> {
    const server = http.createServer((req, res) => {
        if (req.headers.host !== 'api.localhost' || req.url !== '/api/profile/me') {
            res.writeHead(404).end();
            return;
        }
        const token = (req.headers.authorization ?? '').replace(/^Bearer /, '');
        const sub = JSON.parse(Buffer.from(token.split('.')[1] ?? '', 'base64url').toString() || '{}').sub;
        if (!(sub in PERMISSIONS)) {
            res.writeHead(401, { 'Content-Type': 'application/json' }).end('{"message":"Unauthenticated."}');
            return;
        }
        res.writeHead(200, { 'Content-Type': 'application/json' }).end(
            JSON.stringify({
                success: true,
                data: { id: Number(sub), name: `User ${sub}`, email: `${sub}@x`, roles: [], permissions: PERMISSIONS[sub] }
            })
        );
    });
    return new Promise((resolve) => server.listen(0, '127.0.0.1', () => resolve(server)));
}

describe('HTTP smoke test (integration)', () => {
    let cfg: AppConfig;
    let app: Express;
    let tenant: Tenant;
    let redis: RedisClientType<any, any, any>;
    let laravel: http.Server;
    let keys: KeyPair;
    const tokens: Record<string, string> = {};

    beforeAll(async () => {
        cfg = testConfig();
        keys = await makeKeyPair();
        laravel = await startFakeLaravel();
        const port = (laravel.address() as AddressInfo).port;

        redis = createClient({ url: cfg.redis.url }) as RedisClientType<any, any, any>;
        await redis.connect();
        const i18n = await createI18n(['en']);
        const shared = createSharedH5P(cfg, redis, i18n.translate);
        const settings = settingsFromEnv(cfg);
        settings.auth = {
            ...settings.auth,
            jwtPublicKey: keys.publicPem,
            jwtAudience: undefined,
            laravelApiUrl: `http://127.0.0.1:${port}`,
            laravelApiHost: 'api.localhost',
            internalToken: 'internal-secret'
        };
        const logger = pino({ level: 'silent' }) as any;
        tenant = await buildTenant(cfg, shared, settings, logger);
        app = createApp({ config: cfg, logger, redis, i18n, tenants: SingleTenantResolver.of(tenant) });

        for (const sub of Object.keys(PERMISSIONS)) {
            tokens[sub] = await keys.sign({}, { sub });
        }
    });

    afterAll(async () => {
        await deletePrefix(tenant.s3, cfg.s3.bucket, cfg.s3.prefix).catch(() => undefined);
        await dropSchema(tenant.pool, cfg.db.schema);
        await tenant.close();
        for await (const key of redis.scanIterator({ MATCH: `${cfg.redis.keyPrefix}*` })) {
            await redis.del(key as string);
        }
        await redis.quit();
        await new Promise((r) => laravel.close(r));
    });

    const bearer = (sub: string) => ({ Authorization: `Bearer ${tokens[sub]}` });

    it('GET /h5p/health reports all dependencies', async () => {
        const res = await request(app).get('/h5p/health');
        expect(res.status).toBe(200);
        expect(res.body).toEqual({ ok: true, db: true, redis: true, s3: true });
    });

    it('GET /h5p/contents: anonymous 401, learner 403, admin 200', async () => {
        const anon = await request(app).get('/h5p/contents');
        expect(anon.status).toBe(401);
        expect(anon.body.success).toBe(false);

        const learner = await request(app).get('/h5p/contents').set(bearer('30'));
        expect(learner.status).toBe(403);
        expect(learner.body).toEqual({ success: false, message: 'Forbidden.' });

        const admin = await request(app).get('/h5p/contents').set(bearer('1'));
        expect(admin.status).toBe(200);
        expect(admin.body).toMatchObject({ success: true, data: expect.any(Array), meta: { current_page: 1 } });
    });

    it('accepts the token as ?_token= and the internal token header', async () => {
        expect((await request(app).get(`/h5p/contents?_token=${tokens['1']}`)).status).toBe(200);
        expect((await request(app).get('/h5p/contents').set('X-Internal-Token', 'internal-secret')).status).toBe(200);
        expect((await request(app).get('/h5p/contents').set('X-Internal-Token', 'wrong')).status).toBe(401);
    });

    it('an expired token is anonymous', async () => {
        const expired = await keys.sign({}, { sub: '1', expiresInSec: -300 });
        expect((await request(app).get('/h5p/contents').set('Authorization', `Bearer ${expired}`)).status).toBe(401);
    });

    it('lists with ?q= and pagination; authors only see their own content', async () => {
        const { contentStorage } = tenant.h5p;
        const meta = (title: string) =>
            ({
                title,
                mainLibrary: 'H5P.MultiChoice',
                language: 'en',
                embedTypes: ['iframe'],
                license: 'U',
                defaultLanguage: 'en',
                preloadedDependencies: [{ machineName: 'H5P.MultiChoice', majorVersion: 1, minorVersion: 16 }]
            }) as any;
        const mine = await contentStorage.addContent(meta('Author quiz 100%'), {}, { id: '10', name: '', email: '', type: 'local' });
        await contentStorage.addContent(meta('Admin quiz'), {}, { id: '1', name: '', email: '', type: 'local' });

        const all = await request(app).get('/h5p/contents?perPage=1&page=1').set(bearer('1'));
        expect(all.body.data).toHaveLength(1);
        expect(all.body.meta).toMatchObject({ per_page: 1, total: 2, last_page: 2 });

        const filtered = await request(app).get('/h5p/contents?q=100%25').set(bearer('1'));
        expect(filtered.body.data.map((c: any) => c.id)).toEqual([mine]);
        expect(filtered.body.data[0]).toMatchObject({
            id: mine,
            title: 'Author quiz 100%',
            mainLibrary: 'H5P.MultiChoice',
            libraryVersion: '1.16',
            userId: '10'
        });

        const author = await request(app).get('/h5p/contents').set(bearer('10'));
        expect(author.status).toBe(200);
        expect(author.body.data.map((c: any) => c.id)).toEqual([mine]);
    });

    it('POST /h5p/contents: anonymous 401, learner 403, malformed 400', async () => {
        const body = { library: 'H5P.MultiChoice 1.16', params: { params: {}, metadata: { title: 'x' } } };
        expect((await request(app).post('/h5p/contents').send(body)).status).toBe(401);
        expect((await request(app).post('/h5p/contents').set(bearer('30')).send(body)).status).toBe(403);
        const bad = await request(app).post('/h5p/contents').set(bearer('1')).send({ library: 'x' });
        expect(bad.status).toBe(400);
        expect(bad.body.success).toBe(false);
    });

    it('POST /h5p/contents/upload requires a file', async () => {
        expect((await request(app).post('/h5p/contents/upload')).status).toBe(401);
        const res = await request(app).post('/h5p/contents/upload').set(bearer('1'));
        expect(res.status).toBe(422);
    });

    it('unknown content and routes return JSON 404s', async () => {
        const play = await request(app).get('/h5p/contents/987654321/play');
        expect(play.status).toBe(404);
        expect(play.body.success).toBe(false);
        expect((await request(app).get('/h5p/contents/abc/play')).status).toBe(404);
        expect((await request(app).get('/h5p/nope')).status).toBe(404);
    });

    it('guards library administration', async () => {
        expect((await request(app).get('/h5p/libraries')).status).toBe(401);
        expect((await request(app).get('/h5p/libraries').set(bearer('30'))).status).toBe(403);
        expect((await request(app).get('/h5p/libraries').set(bearer('1'))).status).toBe(200);
        expect((await request(app).post('/h5p/content-type-cache/update').set(bearer('10'))).status).toBe(403);
    });

    it('answers CORS preflights for configured origins with credentials', async () => {
        const res = await request(app)
            .options('/h5p/contents')
            .set('Origin', 'http://localhost:3000')
            .set('Access-Control-Request-Method', 'POST')
            .set('Access-Control-Request-Headers', 'authorization,content-type');
        expect(res.status).toBe(204);
        expect(res.headers['access-control-allow-origin']).toBe('http://localhost:3000');
        expect(res.headers['access-control-allow-credentials']).toBe('true');

        const get = await request(app).get('/h5p/health').set('Origin', 'http://localhost:3000');
        expect(get.headers['access-control-expose-headers']).toContain('Content-Disposition');

        const evil = await request(app).get('/h5p/health').set('Origin', 'http://evil.example');
        expect(evil.headers['access-control-allow-origin']).toBeUndefined();
    });
});
