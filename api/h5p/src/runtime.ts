import { mkdir } from 'node:fs/promises';
import { createClient, RedisClientType } from 'redis';

import { AppConfig } from './config';
import { Logger } from './logger';
import { createSharedH5P, SharedH5P } from './h5p/createH5P';
import { createI18n, I18nSetup } from './h5p/i18n';
import { SingleTenantResolver } from './tenancy/SingleTenantResolver';
import { EnvFileTenantResolver } from './tenancy/EnvFileTenantResolver';
import { TenantResolver } from './tenancy/types';

/** Process-wide services plus the tenant resolver. */
export interface Runtime {
    config: AppConfig;
    redis: RedisClientType<any, any, any>;
    i18n: I18nSetup;
    shared: SharedH5P;
    tenants: TenantResolver;
    close: () => Promise<void>;
}

export async function createRuntime(
    config: AppConfig,
    logger: Logger,
    makeResolver?: (shared: SharedH5P) => Promise<TenantResolver>
): Promise<Runtime> {
    await Promise.all([config.paths.libraries, config.paths.temp].map((dir) => mkdir(dir, { recursive: true })));

    const redis = createClient({ url: config.redis.url }) as RedisClientType<any, any, any>;
    redis.on('error', (err) => logger.error({ err: err.message }, 'Redis error'));
    await redis.connect();

    const i18n = await createI18n();
    const shared = createSharedH5P(config, redis, i18n.translate);
    const tenants = makeResolver
        ? await makeResolver(shared)
        : config.tenancy.mode === 'env-files'
          ? EnvFileTenantResolver.create(config, shared, logger)
          : await SingleTenantResolver.create(config, shared, logger);

    return {
        config,
        redis,
        i18n,
        shared,
        tenants,
        close: async () => {
            await tenants.close().catch(() => undefined);
            await redis.quit().catch(() => undefined);
        }
    };
}
