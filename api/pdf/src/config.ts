import path from 'node:path';
import { fileURLToPath } from 'node:url';

export interface Config {
    port: number;
    /** Shared secret Laravel sends as X-Internal-Token. */
    internalToken: string;
    /** Directory holding the bundled TTF fonts (see fonts/README.md). */
    fontsDir: string;
    /** express.json body limit, e.g. "5mb" (templates may embed base64 images). */
    maxBodySize: string;
    /** Maximum number of input records (one rendered document set each) per request. */
    maxInputs: number;
    /** Maximum number of pages in a template. */
    maxPages: number;
    /** Maximum number of fields on one template page. */
    maxFieldsPerPage: number;
    /** A render that takes longer is answered with 504. */
    renderTimeoutMs: number;
    /** Render worker threads (renders running at the same time). */
    maxConcurrency: number;
    /** Renders waiting for a worker; requests above it get 503. */
    maxQueue: number;
    logLevel: string;
}

const here = path.dirname(fileURLToPath(import.meta.url));

const int = (value: string | undefined, fallback: number): number => {
    const parsed = Number.parseInt(value ?? '', 10);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
};

export const loadConfig = (env: NodeJS.ProcessEnv = process.env): Config => ({
    port: int(env.PORT, 3000),
    internalToken: env.PDF_INTERNAL_TOKEN ?? '',
    // dist/config.js and src/config.ts both sit one level below the package root
    fontsDir: env.PDF_FONTS_DIR ?? path.resolve(here, '..', 'fonts'),
    maxBodySize: env.PDF_MAX_BODY_SIZE ?? '5mb',
    maxInputs: int(env.PDF_MAX_INPUTS, 100),
    maxPages: int(env.PDF_MAX_PAGES, 20),
    maxFieldsPerPage: int(env.PDF_MAX_FIELDS_PER_PAGE, 200),
    renderTimeoutMs: int(env.PDF_RENDER_TIMEOUT_MS, 20000),
    maxConcurrency: int(env.PDF_MAX_CONCURRENCY, 2),
    maxQueue: int(env.PDF_MAX_QUEUE, 16),
    logLevel: env.LOG_LEVEL ?? 'info'
});
