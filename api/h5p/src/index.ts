import { createServer } from 'node:http';

import { loadConfig } from './config';
import { logger } from './logger';
import { createRuntime } from './runtime';
import { createApp } from './app';

async function main(): Promise<void> {
    const config = loadConfig();
    const runtime = await createRuntime(config, logger);
    const app = createApp({ ...runtime, logger });
    const tenants = runtime.tenants.active();

    const server = createServer(app);
    // Large .h5p uploads over slow links: allow long request bodies.
    server.requestTimeout = 10 * 60 * 1000;
    server.headersTimeout = 65 * 1000;
    server.keepAliveTimeout = 61 * 1000;

    server.listen(config.port, () => {
        logger.info(
            {
                port: config.port,
                baseUrl: config.h5pBaseUrl,
                tenants: tenants.map((t) => ({ id: t.id, schema: t.settings.db.schema, bucket: t.settings.s3.bucket }))
            },
            'api-h5p listening'
        );
    });

    // Refresh the H5P Hub content type cache in the background; the editor
    // also refreshes it on demand.
    if (config.h5p.enableHub) {
        for (const tenant of tenants) {
            tenant.h5p.editor.contentTypeCache
                .updateIfNecessary()
                .then(() => logger.info({ tenant: tenant.id }, 'H5P Hub content type cache is up to date'))
                .catch((err) =>
                    logger.warn({ tenant: tenant.id, err: err.message }, 'Could not update the H5P Hub content type cache')
                );
        }
    }

    // Expired temporary files (editor uploads never saved). Guarded by a Redis
    // lock (NX + EX) so only one replica sweeps at a time.
    const sweep = async (): Promise<void> => {
        const lockKey = `${config.redis.keyPrefix}lock:temp-sweep`;
        const got = await runtime.redis.set(lockKey, String(process.pid), { NX: true, EX: 300 });
        if (got !== 'OK') {
            return;
        }
        try {
            for (const tenant of runtime.tenants.active()) {
                const removed = await tenant.h5p.temporaryStorage.deleteExpired();
                if (removed > 0) {
                    logger.info({ tenant: tenant.id, removed }, 'Deleted expired temporary files');
                }
            }
        } finally {
            await runtime.redis.del(lockKey).catch(() => undefined);
        }
    };
    const sweepTimer = setInterval(() => {
        sweep().catch((err) => logger.warn({ err: err.message }, 'Temporary file sweep failed'));
    }, 30 * 60 * 1000);
    sweepTimer.unref();

    let shuttingDown = false;
    const shutdown = (signal: string): void => {
        if (shuttingDown) {
            return;
        }
        shuttingDown = true;
        logger.info({ signal }, 'Shutting down');
        clearInterval(sweepTimer);
        server.close(() => {
            runtime
                .close()
                .catch(() => undefined)
                .finally(() => process.exit(0));
        });
        setTimeout(() => process.exit(1), 15_000).unref();
    };
    process.on('SIGTERM', () => shutdown('SIGTERM'));
    process.on('SIGINT', () => shutdown('SIGINT'));
}

main().catch((err) => {
    logger.fatal({ err }, 'Failed to start api-h5p');
    process.exit(1);
});
