import { readdirSync, readFileSync, statSync } from 'node:fs';
import path from 'node:path';

import { AppConfig } from '../config';
import { Logger } from '../logger';
import { SharedH5P } from '../h5p/createH5P';
import { buildTenant } from './buildTenant';
import { parseDotenv } from './dotenv';
import { requestHost, Tenant, TenantRequest, TenantResolver, TenantSettings } from './types';

/**
 * Multi-tenant resolver backed by the Laravel API's gecche/laravel-multidomain
 * env files (TENANCY_MODE=env-files):
 *
 *   host in PLATFORM_HOSTS          -> ENV_DIR/.env, KEYS_DIR/oauth-public.key   (id "default")
 *   coffee.localhost                -> ENV_DIR/.env.coffee.localhost,
 *                                      KEYS_DIR/coffee_localhost/oauth-public.key (id "coffee_localhost")
 *   x.coffee.localhost (no own file)-> falls back to .env.coffee.localhost (leftmost labels
 *                                      are stripped while a tenant file exists further down)
 *   anything else                   -> undefined (the app answers 404; never the platform)
 *
 * Tenants are built lazily on first use (buildTenant runs the service
 * migrations in schema `h5p` of the tenant database) and cached. The env and
 * key files are re-checked (mtime + size) at most every TENANT_RELOAD_CHECK_MS:
 * new tenant files work without a restart, changed files rebuild the tenant
 * (the old one is closed after a grace period), deleted files evict it.
 */

/** Builds a tenant from settings (injected in tests). */
export type TenantBuilder = (settings: TenantSettings) => Promise<Tenant>;

export interface EnvFileTenantResolverOptions {
    app: AppConfig;
    logger: Pick<Logger, 'info' | 'warn' | 'error'>;
    build: TenantBuilder;
    /** Clock (ms), injectable for tests. */
    now?: () => number;
    /** Delay before a replaced/evicted tenant's connections are closed. */
    closeGraceMs?: number;
}

/** A host's env file. */
export interface TenantSource {
    /** Laravel domain whose env file applies (e.g. coffee.localhost, or the platform host). */
    domain: string;
    platform: boolean;
    envFile: string;
    keyFile: string;
    /** Tenant id: "default" for the platform, else the gecche storage dir name (coffee_localhost). */
    id: string;
}

const HOST_RE = /^(?=.{1,253}$)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*$/;
const SLUG_RE = /^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/;
export const PLATFORM_TENANT_ID = 'default';
const MAX_HOST_CACHE = 1000;

/** gecche storage directory for a domain: dots -> underscores. */
export function storageDirName(domain: string): string {
    return domain.replace(/\./g, '_');
}

function isFile(file: string): boolean {
    try {
        return statSync(file).isFile();
    } catch {
        return false;
    }
}

/** "mtime:size" of a file, or "missing". */
function stamp(file: string): string {
    try {
        const st = statSync(file);
        return `${st.mtimeMs}:${st.size}`;
    } catch {
        return 'missing';
    }
}

/**
 * Maps a request host to its env file. Platform hosts match exactly. Other
 * hosts try `.env.<host>`, then strip the leftmost label while at least two
 * labels remain (like gecche), returning the first file that exists. Unlike
 * gecche there is no final fallback to `.env`: an unknown host is no tenant.
 */
export function findTenantSource(
    host: string | undefined,
    opts: { envDir: string; keysDir: string; platformHosts: string[] },
    exists: (file: string) => boolean = isFile
): TenantSource | undefined {
    if (!host || !HOST_RE.test(host)) {
        return undefined;
    }
    if (opts.platformHosts.includes(host)) {
        const envFile = path.join(opts.envDir, '.env');
        if (!exists(envFile)) {
            return undefined;
        }
        return {
            domain: host,
            platform: true,
            envFile,
            keyFile: path.join(opts.keysDir, 'oauth-public.key'),
            id: PLATFORM_TENANT_ID
        };
    }
    const labels = host.split('.');
    for (let i = 0; labels.length - i >= 2; i++) {
        const domain = labels.slice(i).join('.');
        if (opts.platformHosts.includes(domain)) {
            // Subdomains of the platform host are not tenants.
            return undefined;
        }
        const envFile = path.join(opts.envDir, `.env.${domain}`);
        if (exists(envFile)) {
            const dir = storageDirName(domain);
            return {
                domain,
                platform: false,
                envFile,
                keyFile: path.join(opts.keysDir, dir, 'oauth-public.key'),
                id: dir
            };
        }
    }
    return undefined;
}

/** Origin (scheme://host[:port]) of a URL, or undefined. */
function originOf(url: string | undefined): string | undefined {
    if (!url) {
        return undefined;
    }
    try {
        const u = new URL(url);
        return u.protocol === 'http:' || u.protocol === 'https:' ? u.origin : undefined;
    } catch {
        return undefined;
    }
}

/**
 * CORS / frame-ancestors origins of a tenant: the static CORS_ORIGINS, the
 * TENANT_FRONT_ORIGIN_PATTERNS with {slug} replaced (tenants only) and the
 * origins of FRONTEND_URL and ADMIN_URL from the tenant's env file, each with
 * its port as configured. Only exact origins: no wildcard hosts or ports.
 */
export function deriveCorsOrigins(input: {
    staticOrigins: string[];
    patterns: string[];
    slug?: string;
    frontendUrl?: string;
    adminUrl?: string;
}): string[] {
    const out = new Set(input.staticOrigins);
    if (input.slug && SLUG_RE.test(input.slug)) {
        for (const pattern of input.patterns) {
            const origin = originOf(pattern.replace(/\{slug\}/g, input.slug));
            if (origin) {
                out.add(origin);
            }
        }
    }
    for (const url of [input.frontendUrl, input.adminUrl]) {
        const origin = originOf(url);
        if (origin) {
            out.add(origin);
        }
    }
    return [...out];
}

function bool(value: string | undefined, fallback: boolean): boolean {
    if (value === undefined || value === '') {
        return fallback;
    }
    return ['1', 'true', 'yes', 'on'].includes(value.toLowerCase());
}

function nonEmpty(value: string | undefined): string | undefined {
    return value === undefined || value === '' ? undefined : value;
}

/**
 * TenantSettings from a parsed Laravel env file. Connection details come from
 * the file (DB_*, AWS_*); service-level defaults are used only for things a
 * Laravel env file does not describe (schema, S3 prefix, pool sizes, Laravel
 * base URL) and as a fallback for the S3 endpoint/credentials. The database
 * name and the bucket must be in the file: a tenant never silently falls back
 * to the platform's database or bucket.
 */
export function settingsFromEnvFile(
    app: AppConfig,
    source: TenantSource,
    vars: Record<string, string>,
    publicKey: string | undefined
): TenantSettings {
    const database = nonEmpty(vars.DB_DATABASE);
    const bucket = nonEmpty(vars.AWS_BUCKET);
    if (!database) {
        throw new Error(`${path.basename(source.envFile)} has no DB_DATABASE`);
    }
    if (!bucket) {
        throw new Error(`${path.basename(source.envFile)} has no AWS_BUCKET`);
    }
    const port = Number.parseInt(vars.DB_PORT ?? '', 10);
    const inlineKey = nonEmpty(vars.PASSPORT_PUBLIC_KEY)?.replace(/\\n/g, '\n');
    const slug = source.platform ? undefined : (nonEmpty(vars.TENANT_SLUG) ?? source.domain.split('.')[0]);
    let apiHost = source.domain;
    if (source.platform) {
        try {
            apiHost = new URL(vars.APP_URL ?? '').hostname || source.domain;
        } catch {
            apiHost = source.domain;
        }
    }

    return {
        id: source.id,
        hosts: [source.domain],
        corsOrigins: deriveCorsOrigins({
            staticOrigins: app.corsOrigins,
            patterns: app.tenancy.frontOriginPatterns,
            slug,
            frontendUrl: vars.FRONTEND_URL,
            adminUrl: vars.ADMIN_URL
        }),
        db: {
            connectionString: undefined,
            host: nonEmpty(vars.DB_HOST) ?? app.db.host,
            port: Number.isNaN(port) ? app.db.port : port,
            database,
            user: nonEmpty(vars.DB_USERNAME) ?? app.db.user,
            password: vars.DB_PASSWORD ?? '',
            schema: app.db.schema,
            poolMax: app.tenancy.dbPoolMax,
            ssl: app.db.ssl
        },
        s3: {
            endpoint: nonEmpty(vars.AWS_ENDPOINT) ?? app.s3.endpoint,
            region: nonEmpty(vars.AWS_DEFAULT_REGION) ?? app.s3.region,
            accessKeyId: nonEmpty(vars.AWS_ACCESS_KEY_ID) ?? app.s3.accessKeyId,
            secretAccessKey: nonEmpty(vars.AWS_SECRET_ACCESS_KEY) ?? app.s3.secretAccessKey,
            bucket,
            prefix: app.s3.prefix,
            forcePathStyle: bool(vars.AWS_USE_PATH_STYLE_ENDPOINT, app.s3.forcePathStyle),
            maxKeyLength: app.s3.maxKeyLength
        },
        auth: {
            jwtPublicKey: inlineKey ?? publicKey,
            jwtAudience: app.auth.jwtAudience,
            jwtIssuer: app.auth.jwtIssuer,
            clockToleranceSec: app.auth.clockToleranceSec,
            laravelApiUrl: app.auth.laravelApiUrl,
            laravelApiHost: apiHost,
            profilePath: app.auth.profilePath,
            profileCacheTtlSec: app.auth.profileCacheTtlSec,
            internalToken: nonEmpty(vars.H5P_INTERNAL_TOKEN) ?? app.auth.internalToken
        }
    };
}

interface Entry {
    source: TenantSource;
    /** Fingerprint of the env + key files the tenant was built from. */
    stamp: string;
    /** JSON of the settings (a rebuild happens only if they changed). */
    fingerprint: string;
    tenant?: Tenant;
    pending?: Promise<Tenant>;
    checkedAt: number;
    reloading?: boolean;
}

export class EnvFileTenantResolver implements TenantResolver {
    private readonly entries = new Map<string, Entry>();
    private readonly hostCache = new Map<string, { source?: TenantSource; checkedAt: number }>();
    private readonly closing = new Set<NodeJS.Timeout>();
    private readonly envDir: string;
    private readonly keysDir: string;
    private readonly now: () => number;
    private readonly closeGraceMs: number;

    constructor(private readonly options: EnvFileTenantResolverOptions) {
        const { tenancy } = options.app;
        if (!tenancy.envDir) {
            throw new Error('EnvFileTenantResolver needs ENV_DIR');
        }
        this.envDir = tenancy.envDir;
        this.keysDir = tenancy.keysDir ?? path.join(tenancy.envDir, 'storage');
        this.now = options.now ?? Date.now;
        this.closeGraceMs = options.closeGraceMs ?? 60_000;
    }

    static create(app: AppConfig, shared: SharedH5P, logger: Logger): EnvFileTenantResolver {
        return new EnvFileTenantResolver({
            app,
            logger,
            build: (settings) => buildTenant(app, shared, settings, logger)
        });
    }

    private get reloadCheckMs(): number {
        return this.options.app.tenancy.reloadCheckMs;
    }

    /** Env file for a host, cached for TENANT_RELOAD_CHECK_MS (negative results too). */
    public sourceFor(host: string | undefined): TenantSource | undefined {
        if (!host) {
            return undefined;
        }
        const t = this.now();
        const cached = this.hostCache.get(host);
        if (cached && t - cached.checkedAt < this.reloadCheckMs) {
            return cached.source;
        }
        const source = findTenantSource(host, {
            envDir: this.envDir,
            keysDir: this.keysDir,
            platformHosts: this.options.app.tenancy.platformHosts
        });
        if (cached?.source && cached.source.id !== source?.id && !isFile(cached.source.envFile)) {
            this.evict(cached.source.id);
        }
        if (this.hostCache.size >= MAX_HOST_CACHE) {
            this.hostCache.clear();
        }
        this.hostCache.set(host, { source, checkedAt: t });
        return source;
    }

    private evict(id: string): void {
        const entry = this.entries.get(id);
        if (!entry) {
            return;
        }
        this.entries.delete(id);
        this.hostCache.clear();
        this.retire(entry.tenant);
        this.options.logger.info({ tenant: id }, 'Tenant env file removed; tenant evicted');
    }

    async resolve(req: TenantRequest): Promise<Tenant | undefined> {
        const source = this.sourceFor(requestHost(req.headers));
        if (!source) {
            return undefined;
        }
        return this.tenantFor(source);
    }

    async get(id: string): Promise<Tenant> {
        const source = this.sourceById(id);
        if (!source) {
            throw new Error(`Unknown tenant ${id}`);
        }
        const tenant = await this.tenantFor(source);
        if (!tenant) {
            throw new Error(`Unknown tenant ${id}`);
        }
        return tenant;
    }

    active(): Tenant[] {
        return [...this.entries.values()].map((e) => e.tenant).filter((t): t is Tenant => t !== undefined);
    }

    async close(): Promise<void> {
        for (const timer of this.closing) {
            clearTimeout(timer);
        }
        this.closing.clear();
        const tenants = this.active();
        this.entries.clear();
        await Promise.all(tenants.map((t) => t.close().catch(() => undefined)));
    }

    /** Tenant by id ("default", "coffee_localhost") or domain ("coffee.localhost"). */
    private sourceById(id: string): TenantSource | undefined {
        const known = [...this.entries.values()].find((e) => e.source.id === id || e.source.domain === id);
        if (known) {
            return known.source;
        }
        if (id === PLATFORM_TENANT_ID) {
            return this.sourceFor(this.options.app.tenancy.platformHosts[0]);
        }
        if (id.includes('.')) {
            return this.sourceFor(id.toLowerCase());
        }
        let files: string[] = [];
        try {
            files = readdirSync(this.envDir);
        } catch {
            return undefined;
        }
        const file = files.find((f) => f.startsWith('.env.') && storageDirName(f.slice(5)) === id);
        return file ? this.sourceFor(file.slice(5)) : undefined;
    }

    private readSettings(source: TenantSource): { settings: TenantSettings; stamp: string } {
        const fileStamp = `${stamp(source.envFile)}|${stamp(source.keyFile)}`;
        const vars = parseDotenv(readFileSync(source.envFile, 'utf8'));
        let key: string | undefined;
        try {
            key = readFileSync(source.keyFile, 'utf8');
        } catch {
            key = undefined;
        }
        return { settings: settingsFromEnvFile(this.options.app, source, vars, key), stamp: fileStamp };
    }

    private async tenantFor(source: TenantSource): Promise<Tenant | undefined> {
        const entry = this.entries.get(source.id);
        if (!entry) {
            return this.firstBuild(source);
        }
        if (entry.pending && !entry.tenant) {
            return entry.pending;
        }
        this.maybeReload(entry);
        return this.entries.get(source.id)?.tenant ?? (entry.pending ? entry.pending : undefined);
    }

    private firstBuild(source: TenantSource): Promise<Tenant> {
        const { settings, stamp: fileStamp } = this.readSettings(source);
        const entry: Entry = {
            source,
            stamp: fileStamp,
            fingerprint: JSON.stringify(settings),
            checkedAt: this.now()
        };
        entry.pending = this.options
            .build(settings)
            .then((tenant) => {
                entry.tenant = tenant;
                entry.pending = undefined;
                this.options.logger.info(
                    { tenant: source.id, host: source.domain, db: settings.db.database, bucket: settings.s3.bucket },
                    'Tenant initialised'
                );
                return tenant;
            })
            .catch((error) => {
                // Forget the entry so the next request retries.
                if (this.entries.get(source.id) === entry) {
                    this.entries.delete(source.id);
                }
                this.options.logger.error({ tenant: source.id, err: (error as Error).message }, 'Tenant initialisation failed');
                throw error;
            });
        this.entries.set(source.id, entry);
        return entry.pending;
    }

    /** Re-checks the entry's files; rebuilds or evicts the tenant if they changed. */
    private maybeReload(entry: Entry): void {
        const t = this.now();
        if (entry.reloading || t - entry.checkedAt < this.reloadCheckMs) {
            return;
        }
        entry.checkedAt = t;
        const { source } = entry;
        const fileStamp = `${stamp(source.envFile)}|${stamp(source.keyFile)}`;
        if (fileStamp === entry.stamp) {
            return;
        }
        if (!isFile(source.envFile)) {
            this.evict(source.id);
            return;
        }
        let next: { settings: TenantSettings; stamp: string };
        try {
            next = this.readSettings(source);
        } catch (error) {
            this.options.logger.error(
                { tenant: source.id, err: (error as Error).message },
                'Tenant env file changed but cannot be used; keeping the previous configuration'
            );
            return;
        }
        const fingerprint = JSON.stringify(next.settings);
        if (fingerprint === entry.fingerprint) {
            entry.stamp = next.stamp;
            return;
        }
        entry.reloading = true;
        this.options
            .build(next.settings)
            .then((tenant) => {
                const old = entry.tenant;
                const fresh: Entry = {
                    source,
                    stamp: next.stamp,
                    fingerprint,
                    tenant,
                    checkedAt: this.now()
                };
                if (this.entries.get(source.id) === entry) {
                    this.entries.set(source.id, fresh);
                    this.retire(old);
                    this.options.logger.info({ tenant: source.id }, 'Tenant configuration changed; tenant rebuilt');
                } else {
                    this.retire(tenant);
                }
            })
            .catch((error) => {
                entry.reloading = false;
                this.options.logger.error(
                    { tenant: source.id, err: (error as Error).message },
                    'Tenant rebuild failed; keeping the previous configuration'
                );
            });
    }

    /** Closes a replaced tenant after a grace period (in-flight requests). */
    private retire(tenant: Tenant | undefined): void {
        if (!tenant) {
            return;
        }
        if (this.closeGraceMs <= 0) {
            tenant.close().catch(() => undefined);
            return;
        }
        const timer = setTimeout(() => {
            this.closing.delete(timer);
            tenant.close().catch(() => undefined);
        }, this.closeGraceMs);
        timer.unref();
        this.closing.add(timer);
    }

    /** For tests: wait until no rebuild is running. */
    async settled(): Promise<void> {
        for (let i = 0; i < 100 && [...this.entries.values()].some((e) => e.reloading || e.pending); i++) {
            await new Promise((r) => setTimeout(r, 5));
        }
    }
}
