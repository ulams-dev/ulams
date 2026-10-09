// Entry point: configuration from the environment, see README.md.
import { tmpdir } from 'node:os';

import { createFrameworkBuilder } from './build.mjs';
import { createBuildServer } from './server.mjs';

const int = (name, fallback) => {
    const value = Number.parseInt(process.env[name] ?? '', 10);
    return Number.isFinite(value) && value > 0 ? value : fallback;
};

const log = (level) => (fields, message) =>
    console[level === 'error' ? 'error' : 'log'](JSON.stringify({ level, time: new Date().toISOString(), msg: message, ...fields }));
const logger = { info: log('info'), warn: log('warn'), error: log('error') };

const build = createFrameworkBuilder({
    frameworkDir: process.env.ADAPT_FRAMEWORK_DIR ?? '/opt/adapt/framework',
    workDir: process.env.ADAPT_WORK_DIR ?? tmpdir(),
    timeoutMs: int('ADAPT_BUILD_TIMEOUT_MS', 240_000),
    maxOutputBytes: int('ADAPT_MAX_OUTPUT_MB', 512) * 1024 * 1024,
    logger
});

const server = createBuildServer({
    token: process.env.ADAPT_BUILDER_TOKEN ?? '',
    build,
    maxBodyBytes: int('ADAPT_MAX_SOURCE_KB', 8192) * 1024,
    maxQueued: int('ADAPT_MAX_QUEUED', 4),
    logger
});

// builds can take minutes: keep the request open
server.requestTimeout = 0;
server.headersTimeout = 30_000;

const port = int('PORT', 8080);
server.listen(port, () => logger.info({ port }, 'adapt-builder listening'));

const shutdown = () => server.close(() => process.exit(0));
process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);
