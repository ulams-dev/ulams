import { createHash } from 'node:crypto';
import http from 'node:http';
import https from 'node:https';

/** Minimal GET used for the profile call (injectable for tests). */
export type HttpGet = (
    url: string,
    headers: Record<string, string>,
    timeoutMs: number
) => Promise<{ status: number; body: string }>;

/**
 * GET via node:http(s). We do not use fetch() here because we must override
 * the Host header (Laravel is only reachable through Caddy, which routes on
 * Host) and fetch implementations treat Host as a forbidden header.
 */
export const nodeHttpGet: HttpGet = (url, headers, timeoutMs) =>
    new Promise((resolve, reject) => {
        const target = new URL(url);
        const lib = target.protocol === 'https:' ? https : http;
        const req = lib.request(
            target,
            { method: 'GET', headers, timeout: timeoutMs },
            (res) => {
                const chunks: Buffer[] = [];
                res.on('data', (c: Buffer) => chunks.push(c));
                res.on('end', () =>
                    resolve({ status: res.statusCode ?? 0, body: Buffer.concat(chunks).toString('utf8') })
                );
                res.on('error', reject);
            }
        );
        req.on('timeout', () => req.destroy(new Error(`Profile request timed out after ${timeoutMs} ms`)));
        req.on('error', reject);
        req.end();
    });

/** What we keep from GET /api/profile/me. */
export interface LmsProfile {
    id: string;
    name: string;
    email: string;
    roles: string[];
    permissions: string[];
}

export class ProfileUnauthorizedError extends Error {}

/** Small key-value cache abstraction so tests can use an in-memory map. */
export interface ProfileCache {
    get(key: string): Promise<string | null | undefined>;
    set(key: string, value: string, ttlSec: number): Promise<void>;
}

export interface ProfileClientOptions {
    /** e.g. http://caddy */
    baseUrl: string;
    /** Host header to send (Caddy routes on it), e.g. api.localhost */
    hostHeader?: string;
    profilePath: string;
    cacheTtlSec: number;
    timeoutMs?: number;
    cache?: ProfileCache;
    httpGet?: HttpGet;
}

export function tokenCacheKey(token: string): string {
    return `auth:profile:${createHash('sha256').update(token).digest('hex')}`;
}

function normaliseRoles(roles: unknown): string[] {
    if (!Array.isArray(roles)) {
        return [];
    }
    return roles
        .map((r) => (typeof r === 'string' ? r : (r?.name ?? r?.role ?? '')))
        .filter((r): r is string => typeof r === 'string' && r !== '');
}

function normalisePermissions(perms: unknown): string[] {
    if (!Array.isArray(perms)) {
        return [];
    }
    return perms
        .map((p) => (typeof p === 'string' ? p : (p?.name ?? '')))
        .filter((p): p is string => typeof p === 'string' && p !== '');
}

/**
 * Fetches the caller's roles and permissions from Laravel with the caller's
 * own token and caches them (keyed by sha256(token)) for at most
 * cacheTtlSec and never beyond the token's expiry.
 */
export class ProfileClient {
    constructor(private readonly options: ProfileClientOptions) {}

    public async get(token: string, tokenExp: number): Promise<LmsProfile> {
        const key = tokenCacheKey(token);
        const cache = this.options.cache;
        if (cache) {
            const hit = await cache.get(key).catch(() => undefined);
            if (hit) {
                return JSON.parse(hit) as LmsProfile;
            }
        }
        const profile = await this.fetchProfile(token);
        const secondsLeft = Math.floor(tokenExp - Date.now() / 1000);
        const ttl = Math.min(this.options.cacheTtlSec, secondsLeft);
        if (cache && ttl > 0) {
            await cache.set(key, JSON.stringify(profile), ttl).catch(() => undefined);
        }
        return profile;
    }

    private async fetchProfile(token: string): Promise<LmsProfile> {
        const httpGet = this.options.httpGet ?? nodeHttpGet;
        const headers: Record<string, string> = {
            Accept: 'application/json',
            Authorization: `Bearer ${token}`
        };
        if (this.options.hostHeader) {
            headers.Host = this.options.hostHeader;
        }
        const response = await httpGet(
            `${this.options.baseUrl}${this.options.profilePath}`,
            headers,
            this.options.timeoutMs ?? 5000
        );
        if (response.status === 401 || response.status === 403) {
            throw new ProfileUnauthorizedError(`Laravel rejected the token (${response.status})`);
        }
        if (response.status < 200 || response.status >= 300) {
            throw new Error(`Profile request failed with HTTP ${response.status}`);
        }
        let body: any;
        try {
            body = JSON.parse(response.body);
        } catch {
            throw new Error('Profile response is not JSON');
        }
        const data = body?.data ?? body;
        if (!data || data.id === undefined) {
            throw new Error('Profile response has no data.id');
        }
        const name =
            data.name ?? ([data.first_name, data.last_name].filter(Boolean).join(' ') || data.email || String(data.id));
        return {
            id: String(data.id),
            name,
            email: data.email ?? '',
            roles: normaliseRoles(data.roles),
            permissions: normalisePermissions(data.permissions)
        };
    }
}
