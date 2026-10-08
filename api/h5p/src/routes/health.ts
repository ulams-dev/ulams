import { Router } from 'express';
import type { RedisClientType } from 'redis';

import { TenantResolver } from '../tenancy/types';

async function probe(fn: () => Promise<unknown>, timeoutMs = 3000): Promise<boolean> {
    let timer: NodeJS.Timeout | undefined;
    try {
        await Promise.race([
            fn(),
            new Promise((_, reject) => {
                timer = setTimeout(() => reject(new Error('timeout')), timeoutMs);
            })
        ]);
        return true;
    } catch {
        return false;
    } finally {
        if (timer) {
            clearTimeout(timer);
        }
    }
}

/**
 * GET /h5p/health -> {ok, db, redis, s3} for the request's tenant; 503 if
 * any dependency is down (or the host maps to no tenant).
 */
export function healthRouter(tenants: TenantResolver, redis: RedisClientType<any, any, any>): Router {
    const router = Router();
    router.get('/', async (req, res) => {
        const tenant = await tenants.resolve(req).catch(() => undefined);
        const [db, redisOk, s3] = await Promise.all([
            tenant ? probe(() => tenant.pool.query('SELECT 1')) : Promise.resolve(false),
            probe(() => redis.ping()),
            tenant ? probe(() => tenant.s3.headBucket({ Bucket: tenant.settings.s3.bucket })) : Promise.resolve(false)
        ]);
        const ok = db && redisOk && s3;
        res.setHeader('Cache-Control', 'no-store');
        res.status(ok ? 200 : 503).json({ ok, db, redis: redisOk, s3 });
    });
    return router;
}
