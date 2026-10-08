import { mkdirSync, mkdtempSync, rmSync, utimesSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import pino from 'pino';
import request from 'supertest';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { AppConfig, DEFAULT_FRONT_ORIGIN_PATTERNS, loadConfig } from '../src/config';
import { createApp } from '../src/app';
import { parseDotenv } from '../src/tenancy/dotenv';
import { frameAncestors } from '../src/routes/embed';
import {
    deriveCorsOrigins,
    EnvFileTenantResolver,
    findTenantSource,
    settingsFromEnvFile
} from '../src/tenancy/EnvFileTenantResolver';
import { Tenant, TenantSettings } from '../src/tenancy/types';

const PLATFORM_ENV = `APP_URL=http://api.localhost
DB_HOST=postgres
DB_DATABASE=default
DB_USERNAME=default
DB_PASSWORD=secret
AWS_BUCKET=ulams
AWS_ENDPOINT="http://minio:9000"
AWS_ACCESS_KEY_ID=admin
AWS_SECRET_ACCESS_KEY=minio_secretpassword
H5P_INTERNAL_TOKEN=platform-token
`;

const tenantEnv = (slug: string, extra = ''): string => `APP_URL=http://${slug}.localhost
APP_NAME="The ${slug}"
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=ulams_${slug}
DB_USERNAME=ulams_${slug}
DB_PASSWORD=pw-${slug}
AWS_BUCKET=ulams-${slug}
AWS_URL=http://storage.localhost/ulams-${slug}
AWS_USE_PATH_STYLE_ENDPOINT=true
TENANT_SLUG=${slug}
FRONTEND_URL=http://${slug}.app.localhost
${extra}`;

function makeConfig(envDir: string, keysDir: string): AppConfig {
    const cfg = loadConfig();
    cfg.corsOrigins = ['http://localhost:3000'];
    cfg.auth.internalToken = 'service-token';
    cfg.tenancy = {
        mode: 'env-files',
        envDir,
        keysDir,
        platformHosts: ['api.localhost'],
        frontOriginPatterns: DEFAULT_FRONT_ORIGIN_PATTERNS.split(','),
        reloadCheckMs: 0,
        dbPoolMax: 5
    };
    return cfg;
}

function fakeTenant(settings: TenantSettings): Tenant {
    return {
        id: settings.id,
        settings,
        pool: {} as any,
        s3: {} as any,
        profiles: {} as any,
        h5p: {} as any,
        close: vi.fn(async () => undefined)
    };
}

const silent = { info: () => undefined, warn: () => undefined, error: () => undefined };

describe('parseDotenv', () => {
    it('parses Laravel env files', () => {
        const vars = parseDotenv(
            [
                '# comment',
                'A=plain',
                'B="quoted value"',
                "C='single ${A}'",
                'D=with inline # comment',
                'E="multi\\nline"',
                'export F=${A}-x',
                'G=',
                'H="${A} and \\"q\\""',
                'not a line',
                'A2=has#hash'
            ].join('\r\n')
        );
        expect(vars).toEqual({
            A: 'plain',
            B: 'quoted value',
            C: 'single ${A}',
            D: 'with inline',
            E: 'multi\nline',
            F: 'plain-x',
            G: '',
            H: 'plain and "q"',
            A2: 'has#hash'
        });
    });

    it('later keys win', () => {
        expect(parseDotenv('X=1\nX=2').X).toBe('2');
    });
});

describe('findTenantSource', () => {
    const files = new Set(['/env/.env', '/env/.env.coffee.localhost', '/env/.env.oncall.localhost']);
    const exists = (f: string) => files.has(f);
    const opts = { envDir: '/env', keysDir: '/env/storage', platformHosts: ['api.localhost'] };

    it('maps the platform host to .env and its key', () => {
        expect(findTenantSource('api.localhost', opts, exists)).toEqual({
            domain: 'api.localhost',
            platform: true,
            envFile: '/env/.env',
            keyFile: '/env/storage/oauth-public.key',
            id: 'default'
        });
    });

    it('maps a tenant host to .env.<host> and storage/<host_underscored>/oauth-public.key', () => {
        expect(findTenantSource('coffee.localhost', opts, exists)).toEqual({
            domain: 'coffee.localhost',
            platform: false,
            envFile: '/env/.env.coffee.localhost',
            keyFile: '/env/storage/coffee_localhost/oauth-public.key',
            id: 'coffee_localhost'
        });
    });

    it('strips leftmost labels only while a tenant file exists further down', () => {
        expect(findTenantSource('a.b.coffee.localhost', opts, exists)?.domain).toBe('coffee.localhost');
        expect(findTenantSource('www.unknown.localhost', opts, exists)).toBeUndefined();
    });

    it('never falls back to the platform', () => {
        expect(findTenantSource('evil.localhost', opts, exists)).toBeUndefined();
        expect(findTenantSource('localhost', opts, exists)).toBeUndefined();
        expect(findTenantSource('x.api.localhost', opts, exists)).toBeUndefined();
        expect(findTenantSource('h5p', opts, exists)).toBeUndefined();
        expect(findTenantSource(undefined, opts, exists)).toBeUndefined();
    });

    it('rejects hosts that could escape the env directory', () => {
        expect(findTenantSource('../.env', opts, () => true)).toBeUndefined();
        expect(findTenantSource('coffee..localhost', opts, () => true)).toBeUndefined();
        expect(findTenantSource('a/b.localhost', opts, () => true)).toBeUndefined();
    });

    it('does not resolve the platform when .env is missing', () => {
        expect(findTenantSource('api.localhost', opts, () => false)).toBeUndefined();
    });
});

describe('deriveCorsOrigins', () => {
    const patterns = DEFAULT_FRONT_ORIGIN_PATTERNS.split(',');

    it('adds the tenant front and admin origins to the static list', () => {
        expect(
            deriveCorsOrigins({ staticOrigins: ['http://localhost:3000'], patterns, slug: 'coffee' })
        ).toEqual([
            'http://localhost:3000',
            'http://coffee.app.localhost',
            'https://coffee.app.localhost',
            'http://coffee.app.localhost:4321',
            'http://coffee.admin.localhost',
            'https://coffee.admin.localhost',
            'http://coffee.admin.localhost:8000'
        ]);
    });

    it('adds FRONTEND_URL and ADMIN_URL with their ports, and nothing broader', () => {
        const origins = deriveCorsOrigins({
            staticOrigins: [],
            patterns: [],
            slug: 'coffee',
            frontendUrl: 'http://coffee.app.localhost:4400/',
            adminUrl: 'https://admin.coffee.example:8443/panel'
        });
        expect(origins).toEqual(['http://coffee.app.localhost:4400', 'https://admin.coffee.example:8443']);
        expect(frameAncestors(origins)).toBe("'self' http://coffee.app.localhost:4400 https://admin.coffee.example:8443");
        // the same host on another port is a different origin
        expect(origins).not.toContain('http://coffee.app.localhost');
    });

    it('keeps ports, adds FRONTEND_URL and ignores bad slugs/patterns', () => {
        expect(
            deriveCorsOrigins({
                staticOrigins: [],
                patterns: ['http://{slug}.app.localhost:3000', 'not a url {slug}'],
                slug: 'night-sky',
                frontendUrl: 'https://learn.example.com/path?x=1'
            })
        ).toEqual(['http://night-sky.app.localhost:3000', 'https://learn.example.com']);
        expect(deriveCorsOrigins({ staticOrigins: ['a'], patterns, slug: 'bad slug*' })).toEqual(['a']);
        expect(deriveCorsOrigins({ staticOrigins: ['a'], patterns })).toEqual(['a']);
    });
});

describe('settingsFromEnvFile', () => {
    const cfg = makeConfig('/env', '/env/storage');
    const source = {
        domain: 'coffee.localhost',
        platform: false,
        envFile: '/env/.env.coffee.localhost',
        keyFile: '/env/storage/coffee_localhost/oauth-public.key',
        id: 'coffee_localhost'
    };

    it('takes DB, bucket, token and host from the tenant file', () => {
        const s = settingsFromEnvFile(cfg, source, parseDotenv(tenantEnv('coffee', 'H5P_INTERNAL_TOKEN=coffee-token')), 'PEM');
        expect(s.id).toBe('coffee_localhost');
        expect(s.db).toMatchObject({
            host: 'postgres',
            port: 5432,
            database: 'ulams_coffee',
            user: 'ulams_coffee',
            password: 'pw-coffee',
            schema: 'h5p',
            poolMax: 5,
            connectionString: undefined
        });
        expect(s.s3).toMatchObject({ bucket: 'ulams-coffee', prefix: 'h5p', forcePathStyle: true });
        expect(s.auth).toMatchObject({
            jwtPublicKey: 'PEM',
            laravelApiHost: 'coffee.localhost',
            internalToken: 'coffee-token'
        });
        expect(s.corsOrigins).toContain('http://coffee.admin.localhost');
        expect(s.corsOrigins).toContain('http://localhost:3000');
    });

    it('falls back to the service internal token and S3 credentials, never to its DB or bucket', () => {
        const s = settingsFromEnvFile(cfg, source, parseDotenv(tenantEnv('coffee')), undefined);
        expect(s.auth.internalToken).toBe('service-token');
        expect(s.auth.jwtPublicKey).toBeUndefined();
        expect(s.s3.accessKeyId).toBe(cfg.s3.accessKeyId);
        expect(() => settingsFromEnvFile(cfg, source, { AWS_BUCKET: 'b' }, undefined)).toThrow(/DB_DATABASE/);
        expect(() => settingsFromEnvFile(cfg, source, { DB_DATABASE: 'd' }, undefined)).toThrow(/AWS_BUCKET/);
    });

    it('uses an inline PASSPORT_PUBLIC_KEY and the platform APP_URL host', () => {
        const platform = { ...source, domain: 'api.localhost', platform: true, id: 'default' };
        const s = settingsFromEnvFile(
            cfg,
            platform,
            { ...parseDotenv(PLATFORM_ENV), PASSPORT_PUBLIC_KEY: 'A\\nB' },
            'FILE'
        );
        expect(s.auth.jwtPublicKey).toBe('A\nB');
        expect(s.auth.laravelApiHost).toBe('api.localhost');
        expect(s.corsOrigins).toEqual(['http://localhost:3000']);
    });
});

describe('EnvFileTenantResolver', () => {
    let dir: string;
    let cfg: AppConfig;
    let built: TenantSettings[];
    let resolver: EnvFileTenantResolver;

    const writeTenant = (slug: string, extra = '') => {
        writeFileSync(path.join(dir, `.env.${slug}.localhost`), tenantEnv(slug, extra));
        mkdirSync(path.join(dir, 'storage', `${slug}_localhost`), { recursive: true });
        writeFileSync(path.join(dir, 'storage', `${slug}_localhost`, 'oauth-public.key'), `KEY-${slug}`);
    };
    const host = (h: string) => ({ headers: { host: h } });

    beforeEach(() => {
        dir = mkdtempSync(path.join(tmpdir(), 'h5p-tenancy-'));
        mkdirSync(path.join(dir, 'storage'));
        writeFileSync(path.join(dir, '.env'), PLATFORM_ENV);
        writeFileSync(path.join(dir, 'storage', 'oauth-public.key'), 'KEY-platform');
        writeTenant('coffee');
        cfg = makeConfig(dir, path.join(dir, 'storage'));
        built = [];
        resolver = new EnvFileTenantResolver({
            app: cfg,
            logger: silent,
            closeGraceMs: 0,
            build: async (settings) => {
                built.push(settings);
                return fakeTenant(settings);
            }
        });
    });

    afterEach(async () => {
        await resolver.close();
        rmSync(dir, { recursive: true, force: true });
    });

    it('resolves the platform and tenants by Host and X-Forwarded-Host', async () => {
        const platform = await resolver.resolve(host('api.localhost:80'));
        expect(platform?.id).toBe('default');
        expect(platform?.settings.db.database).toBe('default');
        expect(platform?.settings.auth.jwtPublicKey).toBe('KEY-platform');
        expect(platform?.settings.auth.internalToken).toBe('platform-token');

        const coffee = await resolver.resolve({ headers: { host: 'h5p:8080', 'x-forwarded-host': 'Coffee.localhost' } });
        expect(coffee?.id).toBe('coffee_localhost');
        expect(coffee?.settings.db.database).toBe('ulams_coffee');
        expect(coffee?.settings.s3.bucket).toBe('ulams-coffee');
        expect(coffee?.settings.auth.jwtPublicKey).toBe('KEY-coffee');
        expect(coffee?.settings.auth.laravelApiHost).toBe('coffee.localhost');
    });

    it('returns undefined for unknown hosts (no platform fallback)', async () => {
        expect(await resolver.resolve(host('evil.localhost'))).toBeUndefined();
        expect(await resolver.resolve(host('h5p:8080'))).toBeUndefined();
        expect(await resolver.resolve({ headers: {} })).toBeUndefined();
    });

    it('builds each tenant once, also under concurrent first requests', async () => {
        const [a, b] = await Promise.all([
            resolver.resolve(host('coffee.localhost')),
            resolver.resolve(host('www.coffee.localhost'))
        ]);
        expect(a).toBe(b);
        expect(await resolver.resolve(host('coffee.localhost'))).toBe(a);
        expect(built).toHaveLength(1);
        expect(resolver.active().map((t) => t.id)).toEqual(['coffee_localhost']);
    });

    it('picks up a new tenant file without a restart', async () => {
        expect(await resolver.resolve(host('oncall.localhost'))).toBeUndefined();
        writeTenant('oncall');
        expect((await resolver.resolve(host('oncall.localhost')))?.settings.db.database).toBe('ulams_oncall');
    });

    it('rebuilds a tenant whose env or key file changed and closes the old one', async () => {
        const first = (await resolver.resolve(host('coffee.localhost')))!;
        const envFile = path.join(dir, '.env.coffee.localhost');

        // Only an irrelevant variable changes: no rebuild.
        writeFileSync(envFile, tenantEnv('coffee', 'APP_DEBUG=true'));
        utimesSync(envFile, new Date(), new Date(Date.now() + 5000));
        expect(await resolver.resolve(host('coffee.localhost'))).toBe(first);
        await resolver.settled();
        expect(built).toHaveLength(1);

        writeFileSync(envFile, tenantEnv('coffee', 'DB_PASSWORD=rotated'));
        utimesSync(envFile, new Date(), new Date(Date.now() + 10000));
        await resolver.resolve(host('coffee.localhost'));
        await resolver.settled();
        const second = (await resolver.resolve(host('coffee.localhost')))!;
        expect(second).not.toBe(first);
        expect(second.settings.db.password).toBe('rotated');
        expect(first.close).toHaveBeenCalled();

        const keyFile = path.join(dir, 'storage', 'coffee_localhost', 'oauth-public.key');
        writeFileSync(keyFile, 'KEY-coffee-rotated');
        utimesSync(keyFile, new Date(), new Date(Date.now() + 15000));
        await resolver.resolve(host('coffee.localhost'));
        await resolver.settled();
        expect((await resolver.resolve(host('coffee.localhost')))!.settings.auth.jwtPublicKey).toBe('KEY-coffee-rotated');
    });

    it('keeps the previous tenant when the changed file is invalid', async () => {
        const first = (await resolver.resolve(host('coffee.localhost')))!;
        const envFile = path.join(dir, '.env.coffee.localhost');
        writeFileSync(envFile, 'DB_DATABASE=\n');
        utimesSync(envFile, new Date(), new Date(Date.now() + 5000));
        expect(await resolver.resolve(host('coffee.localhost'))).toBe(first);
    });

    it('evicts a tenant whose env file was removed', async () => {
        const first = (await resolver.resolve(host('coffee.localhost')))!;
        rmSync(path.join(dir, '.env.coffee.localhost'));
        expect(await resolver.resolve(host('coffee.localhost'))).toBeUndefined();
        expect(first.close).toHaveBeenCalled();
        expect(resolver.active()).toEqual([]);
    });

    it('retries a tenant whose first build failed', async () => {
        let fail = true;
        const flaky = new EnvFileTenantResolver({
            app: cfg,
            logger: silent,
            build: async (settings) => {
                if (fail) {
                    throw new Error('db down');
                }
                return fakeTenant(settings);
            }
        });
        await expect(flaky.resolve(host('coffee.localhost'))).rejects.toThrow('db down');
        fail = false;
        expect((await flaky.resolve(host('coffee.localhost')))?.id).toBe('coffee_localhost');
        await flaky.close();
    });

    it('caches host lookups for TENANT_RELOAD_CHECK_MS', async () => {
        let now = 1000;
        cfg.tenancy.reloadCheckMs = 2000;
        const cached = new EnvFileTenantResolver({ app: cfg, logger: silent, now: () => now, build: async (s) => fakeTenant(s) });
        expect(await cached.resolve(host('oncall.localhost'))).toBeUndefined();
        writeTenant('oncall');
        expect(await cached.resolve(host('oncall.localhost'))).toBeUndefined();
        now += 2000;
        expect((await cached.resolve(host('oncall.localhost')))?.id).toBe('oncall_localhost');
        await cached.close();
    });

    it('gets tenants by id or domain', async () => {
        writeTenant('nightsky');
        expect((await resolver.get('default')).id).toBe('default');
        expect((await resolver.get('nightsky_localhost')).settings.hosts).toEqual(['nightsky.localhost']);
        expect((await resolver.get('coffee.localhost')).id).toBe('coffee_localhost');
        await expect(resolver.get('nope')).rejects.toThrow(/Unknown tenant/);
    });
});

describe('app with EnvFileTenantResolver', () => {
    let dir: string;
    let resolver: EnvFileTenantResolver;
    let app: ReturnType<typeof createApp>;

    beforeEach(() => {
        dir = mkdtempSync(path.join(tmpdir(), 'h5p-tenancy-app-'));
        mkdirSync(path.join(dir, 'storage'));
        writeFileSync(path.join(dir, '.env'), PLATFORM_ENV);
        for (const slug of ['coffee', 'oncall']) {
            writeFileSync(path.join(dir, `.env.${slug}.localhost`), tenantEnv(slug));
        }
        const cfg = makeConfig(dir, path.join(dir, 'storage'));
        resolver = new EnvFileTenantResolver({ app: cfg, logger: silent, build: async (s) => fakeTenant(s) });
        app = createApp({
            config: cfg,
            logger: pino({ level: 'silent' }) as any,
            redis: { ping: async () => 'PONG' } as any,
            i18n: { middleware: (_req: any, _res: any, next: any) => next() } as any,
            tenants: resolver
        });
    });

    afterEach(async () => {
        await resolver.close();
        rmSync(dir, { recursive: true, force: true });
    });

    it('answers 404 JSON for unknown hosts', async () => {
        for (const url of ['/h5p/contents', '/h5p/embed/play/1', '/h5p/ajax?action=content-type-cache']) {
            const res = await request(app).get(url).set('Host', 'evil.localhost');
            expect(res.status).toBe(404);
            expect(res.body).toEqual({ success: false, message: 'Unknown tenant.' });
        }
        const health = await request(app).get('/h5p/health').set('Host', 'evil.localhost');
        expect(health.status).toBe(404);
        expect(health.body.message).toBe('Unknown tenant.');
    });

    it('gives the container health check a liveness answer on loopback', async () => {
        const res = await request(app).get('/h5p/health').set('Host', '127.0.0.1:8080');
        expect(res.status).toBe(200);
        expect(res.body).toEqual({ ok: true, tenant: null, redis: true });
    });

    it('frames the embed pages only from the tenant fronts', async () => {
        const res = await request(app).get('/h5p/embed/play/5').set('Host', 'coffee.localhost');
        expect(res.status).toBe(200);
        const csp = res.headers['content-security-policy'];
        expect(csp).toContain('http://coffee.app.localhost');
        expect(csp).toContain('http://coffee.admin.localhost');
        expect(csp).toContain('http://localhost:3000');
        expect(csp).not.toContain('oncall');
    });

    it('allows CORS only from the tenant fronts and the static origins', async () => {
        const preflight = (h: string, origin: string) =>
            request(app)
                .options('/h5p/contents')
                .set('Host', h)
                .set('Origin', origin)
                .set('Access-Control-Request-Method', 'POST');
        const own = await preflight('coffee.localhost', 'http://coffee.admin.localhost');
        expect(own.status).toBe(204);
        expect(own.headers['access-control-allow-origin']).toBe('http://coffee.admin.localhost');
        const staticOrigin = await preflight('coffee.localhost', 'http://localhost:3000');
        expect(staticOrigin.headers['access-control-allow-origin']).toBe('http://localhost:3000');
        const other = await preflight('coffee.localhost', 'http://oncall.app.localhost');
        expect(other.headers['access-control-allow-origin']).toBeUndefined();
        const platform = await preflight('api.localhost', 'http://coffee.app.localhost');
        expect(platform.headers['access-control-allow-origin']).toBeUndefined();
    });
});
