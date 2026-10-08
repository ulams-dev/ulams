import { timingSafeEqual } from 'node:crypto';
import express, { type NextFunction, type Request, type Response } from 'express';
import { pinoHttp } from 'pino-http';
import type { Logger } from 'pino';
import type { Config } from './config.js';
import { fontManifest, type FontStore } from './fonts.js';
import type { RenderPool } from './pool.js';
import { prepare, RenderError } from './render.js';

export interface AppDeps {
    config: Config;
    fonts: FontStore;
    logger: Logger;
    pool: RenderPool;
}

const tokenMatches = (expected: string, given: unknown): boolean => {
    if (!expected || typeof given !== 'string') {
        return false;
    }
    const a = Buffer.from(expected);
    const b = Buffer.from(given);
    return a.length === b.length && timingSafeEqual(a, b);
};

const sendError = (res: Response, status: number, code: string, message: string, details?: unknown) => {
    res.status(status).json({ error: code, message, ...(details === undefined ? {} : { details }) });
};

export const createApp = ({ config, fonts, logger, pool }: AppDeps) => {
    const app = express();
    app.disable('x-powered-by');
    app.use(pinoHttp({ logger, autoLogging: { ignore: (req) => req.url === '/health' } }));

    app.get('/health', (_req, res) => {
        res.json({ status: 'ok', active: pool.active, fonts: fonts.files.size });
    });

    // Everything below is server-to-server only (Laravel): X-Internal-Token.
    app.use((req: Request, res: Response, next: NextFunction) => {
        if (!tokenMatches(config.internalToken, req.header('x-internal-token'))) {
            sendError(res, 401, 'unauthorized', 'missing or invalid X-Internal-Token');
            return;
        }
        next();
    });

    app.get('/fonts', (_req, res) => {
        res.json({ data: fontManifest() });
    });

    app.get('/fonts/:file', (req, res) => {
        const bytes = fonts.files.get(req.params.file);
        if (!bytes) {
            sendError(res, 404, 'not_found', 'unknown font file');
            return;
        }
        res.type('font/ttf').set('Cache-Control', 'public, max-age=604800, immutable').send(bytes);
    });

    app.post('/render', express.json({ limit: config.maxBodySize }), async (req, res) => {
        const started = Date.now();
        try {
            const body = (req.body ?? {}) as { template?: unknown; inputs?: unknown };
            const job = prepare({ template: body.template, inputs: body.inputs }, fonts, config);
            const pdf = await pool.render(job.template, job.inputs);
            req.log.info({ ms: Date.now() - started, bytes: pdf.byteLength, warnings: job.warnings }, 'rendered');
            if (job.warnings.length > 0) {
                res.set('X-Render-Warnings', String(job.warnings.length));
            }
            res.type('application/pdf').send(Buffer.from(pdf.buffer, pdf.byteOffset, pdf.byteLength));
        } catch (error) {
            if (error instanceof RenderError) {
                if (error.status === 503) {
                    res.set('Retry-After', '2');
                }
                req.log.warn({ code: error.code, details: error.details }, error.message);
                sendError(res, error.status, error.code, error.message, error.details);
                return;
            }
            req.log.error({ err: error }, 'render crashed');
            sendError(res, 500, 'internal_error', 'unexpected renderer error');
        }
    });

    app.use((_req: Request, res: Response) => sendError(res, 404, 'not_found', 'not found'));

    // body-parser errors (413 too large, 400 malformed JSON) and the rest
    app.use((error: any, _req: Request, res: Response, _next: NextFunction) => {
        const status = typeof error?.status === 'number' ? error.status : 500;
        const code = status === 413 ? 'payload_too_large' : status === 400 ? 'invalid_json' : 'internal_error';
        sendError(res, status, code, status === 500 ? 'unexpected error' : String(error.message));
    });

    return app;
};
