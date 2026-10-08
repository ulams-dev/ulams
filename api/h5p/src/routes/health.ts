import { Router } from 'express';
import type { RedisClientType } from 'redis';

import { requestHost, Tenant, TenantResolver } from '../tenancy/types';

/** Hosts the container health check uses: no tenant, liveness only. */
const LIVENESS_HOSTS = new Set(['127.0.0.1', 'localhost', '[::1]', '::1']);

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
 * GET /h5p/health -> {ok, tenant, db, redis, s3} for the request's tenant; 503
 * if any dependency is down. Unknown host -> 404, except loopback hosts
 * (container health check), which get a liveness answer {ok, tenant: null, redis}.
 */
export function healthRouter(tenants: TenantResolver, redis: RedisClientType<any, any, any>): Router {
    const router = Router();
    router.get('/', async (req, res) => {
        res.setHeader('Cache-Control', 'no-store');
        let tenant: Tenant | undefined;
        let failed = false;
        try {
            tenant = await tenants.resolve(req);
        } catch {
            failed = true;
        }
        if (!tenant && !failed) {
            if (LIVENESS_HOSTS.has(requestHost(req.headers) ?? '')) {
                const redisOk = await probe(() => redis.ping());
                res.status(redisOk ? 200 : 503).json({ ok: redisOk, tenant: null, redis: redisOk });
                return;
            }
            res.status(404).json({ success: false, ok: false, message: 'Unknown tenant.' });
            return;
        }
        const [db, redisOk, s3] = await Promise.all([
            tenant ? probe(() => tenant.pool.query('SELECT 1')) : Promise.resolve(false),
            probe(() => redis.ping()),
            tenant ? probe(() => tenant.s3.headBucket({ Bucket: tenant.settings.s3.bucket })) : Promise.resolve(false)
        ]);
        const ok = db && redisOk && s3;
        res.status(ok ? 200 : 503).json({ ok, tenant: tenant?.id ?? null, db, redis: redisOk, s3 });
    });
    return router;
}
