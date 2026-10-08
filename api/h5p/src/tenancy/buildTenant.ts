import { AppConfig } from '../config';
import { Logger } from '../logger';
import { createPool } from '../db/pool';
import { migrate } from '../db/migrate';
import { createS3Client } from '../storage/s3';
import { createH5P, SharedH5P } from '../h5p/createH5P';
import { JwtVerifier } from '../auth/jwt';
import { ProfileClient } from '../auth/profile';
import { Tenant, TenantSettings } from './types';

/**
 * Opens the tenant's DB pool (running migrations in its `h5p` schema), S3
 * client, JWT verifier and H5P editor/player. Used by every resolver, so a
 * multi-tenant resolver only has to produce TenantSettings and cache the
 * result.
 */
export async function buildTenant(
    app: AppConfig,
    shared: SharedH5P,
    settings: TenantSettings,
    logger: Logger
): Promise<Tenant> {
    const pool = createPool(settings.db);
    pool.on('error', (err) => logger.error({ err, tenant: settings.id }, 'Postgres pool error'));
    try {
        const applied = await migrate(pool, settings.db.schema);
        if (applied.length > 0) {
            logger.info({ tenant: settings.id, schema: settings.db.schema, applied }, 'Applied database migrations');
        }
    } catch (error) {
        await pool.end().catch(() => undefined);
        throw error;
    }

    const s3 = createS3Client(settings.s3);
    const h5p = await createH5P(app, shared, {
        id: settings.id,
        pool,
        s3,
        schema: settings.db.schema,
        s3Bucket: settings.s3.bucket,
        s3Prefix: settings.s3.prefix,
        s3MaxKeyLength: settings.s3.maxKeyLength
    });

    const verifier = settings.auth.jwtPublicKey
        ? new JwtVerifier({
              publicKeyPem: settings.auth.jwtPublicKey,
              audience: settings.auth.jwtAudience,
              issuer: settings.auth.jwtIssuer,
              clockToleranceSec: settings.auth.clockToleranceSec
          })
        : undefined;
    if (!verifier) {
        logger.warn({ tenant: settings.id }, 'No JWT public key configured: every caller of this tenant is anonymous');
    }

    const prefix = `${app.redis.keyPrefix}t:${settings.id}:`;
    const redis = shared.redis;
    const profiles = new ProfileClient({
        baseUrl: settings.auth.laravelApiUrl,
        hostHeader: settings.auth.laravelApiHost,
        profilePath: settings.auth.profilePath,
        cacheTtlSec: settings.auth.profileCacheTtlSec,
        cache: {
            get: (key) => redis.get(`${prefix}${key}`),
            set: async (key, value, ttl) => {
                await redis.set(`${prefix}${key}`, value, { EX: ttl });
            }
        }
    });

    return {
        id: settings.id,
        settings,
        pool,
        s3,
        verifier,
        profiles,
        h5p,
        close: async () => {
            await pool.end().catch(() => undefined);
            s3.destroy();
        }
    };
}
