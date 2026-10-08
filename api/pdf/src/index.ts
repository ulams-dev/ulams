import { pino } from 'pino';
import { createApp } from './app.js';
import { loadConfig } from './config.js';
import { loadFonts } from './fonts.js';
import { RenderPool } from './pool.js';

const config = loadConfig();
const logger = pino({ level: config.logLevel });

if (!config.internalToken) {
    logger.fatal('PDF_INTERNAL_TOKEN is not set; refusing to start (every render call would be rejected)');
    process.exit(1);
}

const fonts = loadFonts(config.fontsDir);
const pool = new RenderPool({
    size: config.maxConcurrency,
    maxQueue: config.maxQueue,
    timeoutMs: config.renderTimeoutMs,
    fontsDir: config.fontsDir,
    logger
});
const app = createApp({ config, fonts, logger, pool });

const server = app.listen(config.port, () => {
    logger.info({ port: config.port, fonts: fonts.files.size }, 'pdf renderer listening');
});

const shutdown = () => server.close(() => void pool.close().then(() => process.exit(0)));
process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);
