import { readFileSync } from 'node:fs';
import path from 'node:path';

/**
 * All runtime configuration comes from environment variables. See README.md
 * for the full table.
 */
export interface AppConfig {
    port: number;
    logLevel: string;
    /** Absolute public origin the service is reachable at (same origin as the Laravel API). */
    publicUrl: string;
    /**
     * Base URL H5P puts into player/editor models. Relative ('/h5p') by
     * default; set H5P_ABSOLUTE_URLS=true to emit `${publicUrl}/h5p/...`.
     */
    h5pBaseUrl: string;
    /** Path prefix the routers are mounted at. Always '/h5p'. */
    mountPath: string;
    corsOrigins: string[];

    db: {
        connectionString?: string;
        host: string;
        port: number;
        database: string;
        user: string;
        password: string;
        schema: string;
        poolMax: number;
        ssl: boolean;
    };
    redis: {
        url: string;
        keyPrefix: string;
    };
    s3: {
        endpoint?: string;
        region: string;
        accessKeyId: string;
        secretAccessKey: string;
        bucket: string;
        prefix: string;
        forcePathStyle: boolean;
        maxKeyLength: number;
    };
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
    paths: {
        libraries: string;
        core: string;
        editor: string;
        temp: string;
    };
    h5p: {
        maxFileSize: number;
        maxTotalSize: number;
        enableHub: boolean;
        contentUserStateSaveIntervalMs: number;
        temporaryFileLifetimeMs: number;
    };
}

function env(name: string, fallback?: string): string | undefined {
    const value = process.env[name];
    if (value === undefined || value === '') {
        return fallback;
    }
    return value;
}

function required(name: string, fallback?: string): string {
    const value = env(name, fallback);
    if (value === undefined) {
        throw new Error(`Missing required environment variable ${name}`);
    }
    return value;
}

function int(name: string, fallback: number): number {
    const raw = env(name);
    if (raw === undefined) {
        return fallback;
    }
    const n = Number.parseInt(raw, 10);
    if (Number.isNaN(n)) {
        throw new Error(`Environment variable ${name} must be an integer`);
    }
    return n;
}

function bool(name: string, fallback: boolean): boolean {
    const raw = env(name);
    if (raw === undefined) {
        return fallback;
    }
    return ['1', 'true', 'yes', 'on'].includes(raw.toLowerCase());
}

const MB = 1024 * 1024;

/**
 * Reads the Passport public key either from JWT_PUBLIC_KEY (PEM content; "\n"
 * escapes are accepted) or from the file at JWT_PUBLIC_KEY_PATH.
 */
export function loadPublicKey(): string | undefined {
    const inline = env('JWT_PUBLIC_KEY');
    if (inline) {
        return inline.replace(/\\n/g, '\n');
    }
    const keyPath = env('JWT_PUBLIC_KEY_PATH', '/keys/oauth-public.key');
    try {
        return readFileSync(keyPath as string, 'utf8');
    } catch {
        return undefined;
    }
}

export function loadConfig(): AppConfig {
    const publicUrl = (env('PUBLIC_URL', 'http://api.localhost') as string).replace(/\/+$/, '');
    const mountPath = '/h5p';
    const h5pRoot = env('H5P_ROOT', path.resolve('h5p')) as string;
    const schema = env('DB_SCHEMA', 'h5p') as string;
    if (!/^[a-z_][a-z0-9_]{0,62}$/.test(schema)) {
        throw new Error('DB_SCHEMA must be a lowercase SQL identifier');
    }
    const redisPassword = env('REDIS_PASSWORD');
    const redisUrl =
        env('REDIS_URL') ??
        `redis://${redisPassword ? `:${encodeURIComponent(redisPassword)}@` : ''}${env('REDIS_HOST', 'redis')}:${int('REDIS_PORT', 6379)}/${int('REDIS_DB', 0)}`;

    return {
        port: int('PORT', 8080),
        logLevel: env('LOG_LEVEL', 'info') as string,
        publicUrl,
        mountPath,
        h5pBaseUrl: bool('H5P_ABSOLUTE_URLS', false) ? `${publicUrl}${mountPath}` : mountPath,
        corsOrigins: (env('CORS_ORIGINS', 'http://localhost:3000,http://localhost:8000,http://api.localhost') as string)
            .split(',')
            .map((o) => o.trim())
            .filter(Boolean),
        db: {
            connectionString: env('DATABASE_URL'),
            host: env('DB_HOST', 'postgres') as string,
            port: int('DB_PORT', 5432),
            database: env('DB_DATABASE', 'default') as string,
            user: env('DB_USERNAME', 'default') as string,
            password: env('DB_PASSWORD', 'secret') as string,
            schema,
            poolMax: int('DB_POOL_MAX', 10),
            ssl: bool('DB_SSL', false)
        },
        redis: {
            url: redisUrl,
            keyPrefix: env('REDIS_KEY_PREFIX', 'h5p:') as string
        },
        s3: {
            endpoint: env('S3_ENDPOINT', 'http://minio:9000'),
            region: env('S3_REGION', 'us-east-1') as string,
            accessKeyId: required('S3_KEY', env('AWS_ACCESS_KEY_ID', 'admin')),
            secretAccessKey: required('S3_SECRET', env('AWS_SECRET_ACCESS_KEY', 'minio_secretpassword')),
            bucket: required('S3_BUCKET', 'wellms'),
            prefix: (env('S3_PREFIX', 'h5p') as string).replace(/^\/+|\/+$/g, ''),
            forcePathStyle: bool('S3_FORCE_PATH_STYLE', true),
            maxKeyLength: int('S3_MAX_KEY_LENGTH', 1024)
        },
        auth: {
            jwtPublicKey: loadPublicKey(),
            jwtAudience: env('JWT_AUDIENCE'),
            jwtIssuer: env('JWT_ISSUER'),
            clockToleranceSec: int('JWT_CLOCK_TOLERANCE', 30),
            laravelApiUrl: (env('LARAVEL_API_URL', 'http://caddy') as string).replace(/\/+$/, ''),
            laravelApiHost: env('LARAVEL_API_HOST', 'api.localhost'),
            profilePath: env('LARAVEL_PROFILE_PATH', '/api/profile/me') as string,
            profileCacheTtlSec: int('PROFILE_CACHE_TTL', 60),
            internalToken: env('H5P_INTERNAL_TOKEN')
        },
        paths: {
            libraries: env('H5P_LIBRARIES_PATH', path.join(h5pRoot, 'libraries')) as string,
            core: env('H5P_CORE_PATH', path.join(h5pRoot, 'core')) as string,
            editor: env('H5P_EDITOR_PATH', path.join(h5pRoot, 'editor')) as string,
            temp: env('H5P_TMP_PATH', path.join(h5pRoot, 'tmp')) as string
        },
        h5p: {
            maxFileSize: int('H5P_MAX_FILE_SIZE_MB', 64) * MB,
            maxTotalSize: int('H5P_MAX_TOTAL_SIZE_MB', 256) * MB,
            enableHub: bool('H5P_HUB_ENABLED', true),
            contentUserStateSaveIntervalMs: int('H5P_STATE_SAVE_INTERVAL_MS', 5000),
            temporaryFileLifetimeMs: int('H5P_TEMP_FILE_LIFETIME_MIN', 120) * 60 * 1000
        }
    };
}
