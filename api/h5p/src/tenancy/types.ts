import type { IncomingHttpHeaders } from 'node:http';
import type { Pool } from 'pg';
import type { S3 } from '@aws-sdk/client-s3';

import type { AppConfig } from '../config';
import type { JwtVerifier } from '../auth/jwt';
import type { ProfileClient } from '../auth/profile';
import type { H5PServices } from '../h5p/createH5P';

/**
 * Everything that differs per LMS tenant. SingleTenantResolver builds one
 * tenant from environment variables; EnvFileTenantResolver maps the request
 * host to a Laravel (gecche/laravel-multidomain) env file. Each tenant has:
 *  - its own Postgres database (the service's tables live in schema `h5p`
 *    inside that tenant's database),
 *  - its own S3 bucket,
 *  - its own Passport public key and Laravel host (for /api/profile/me).
 * The library volume and Redis are shared by all tenants.
 */
export interface TenantSettings {
    id: string;
    /** Hostnames (lowercase, no port) that select this tenant. */
    hosts: string[];
    /**
     * Origins allowed for CORS and as frame-ancestors of the embed pages
     * (exact origins; '*' allows any).
     */
    corsOrigins: string[];
    db: AppConfig['db'];
    s3: AppConfig['s3'];
    auth: {
        jwtPublicKey?: string;
        jwtAudience?: string;
        jwtIssuer?: string;
        clockToleranceSec: number;
        laravelApiUrl: string;
        laravelApiHost?: string;
        profilePath: string;
        profileCacheTtlSec: number;
        internalToken?: string;
    };
}

/** A fully wired tenant: connections + H5P object graph. */
export interface Tenant {
    id: string;
    settings: TenantSettings;
    pool: Pool;
    s3: S3;
    verifier?: JwtVerifier;
    profiles: ProfileClient;
    h5p: H5PServices;
    /** Releases the tenant's DB pool and S3 client. */
    close(): Promise<void>;
}

export interface TenantRequest {
    headers: IncomingHttpHeaders;
}

export interface TenantResolver {
    /**
     * Tenant for an HTTP request (by X-Forwarded-Host, then Host). Returns
     * undefined for unknown hosts (the app answers 404).
     */
    resolve(req: TenantRequest): Promise<Tenant | undefined>;
    /** Tenant by id (CLI tools, background jobs). Throws if unknown. */
    get(id: string): Promise<Tenant>;
    /** Tenants that are currently initialised (background jobs iterate these). */
    active(): Tenant[];
    close(): Promise<void>;
}

/** Host the client asked for: first X-Forwarded-Host value, else Host; lowercase, no port. */
export function requestHost(headers: IncomingHttpHeaders): string | undefined {
    const forwarded = headers['x-forwarded-host'];
    const raw = (Array.isArray(forwarded) ? forwarded[0] : forwarded)?.split(',')[0]?.trim() || headers.host;
    if (!raw) {
        return undefined;
    }
    return raw.replace(/:\d+$/, '').toLowerCase();
}
