// HTTP API of the build worker (ADR 0013):
//   GET  /health  -> {ok, busy, queued}
//   POST /build   X-Internal-Token, {"id": "<source>-v<version>", "source": {...}}
//                 -> 200 application/zip | 4xx/5xx {"error": "..."}
import { createServer } from 'node:http';
import { timingSafeEqual } from 'node:crypto';

import { BuildError, checkRequest } from './build.mjs';

function sendJson(res, status, body) {
    const data = Buffer.from(JSON.stringify(body));
    res.writeHead(status, {
        'Content-Type': 'application/json; charset=utf-8',
        'Content-Length': data.length,
        'Cache-Control': 'no-store',
        'X-Content-Type-Options': 'nosniff'
    });
    res.end(data);
}

function tokenMatches(expected, given) {
    if (typeof given !== 'string' || given === '') {
        return false;
    }
    const a = Buffer.from(expected);
    const b = Buffer.from(given);
    return a.length === b.length && timingSafeEqual(a, b);
}

function readBody(req, limit) {
    return new Promise((resolve, reject) => {
        const declared = Number(req.headers['content-length']);
        if (Number.isFinite(declared) && declared > limit) {
            reject(new BuildError(`Body over ${limit} bytes`, 413));
            req.resume();
            return;
        }
        const chunks = [];
        let size = 0;
        req.on('data', (chunk) => {
            size += chunk.length;
            if (size > limit) {
                reject(new BuildError(`Body over ${limit} bytes`, 413));
                req.destroy();
                return;
            }
            chunks.push(chunk);
        });
        req.on('end', () => resolve(Buffer.concat(chunks)));
        req.on('error', reject);
    });
}

/** One build at a time; at most `maxQueued` waiting, the rest answered 503. */
export class BuildQueue {
    constructor(maxQueued = 4) {
        this.maxQueued = maxQueued;
        this.running = false;
        this.waiting = [];
    }

    get queued() {
        return this.waiting.length;
    }

    run(task) {
        if (this.running && this.waiting.length >= this.maxQueued) {
            return Promise.reject(new BuildError('The build worker is busy, try again later', 503));
        }
        return new Promise((resolve, reject) => {
            this.waiting.push({ task, resolve, reject });
            this.next();
        });
    }

    next() {
        if (this.running || this.waiting.length === 0) {
            return;
        }
        const { task, resolve, reject } = this.waiting.shift();
        this.running = true;
        Promise.resolve()
            .then(task)
            .then(resolve, reject)
            .finally(() => {
                this.running = false;
                this.next();
            });
    }
}

/**
 * @param {{ token: string, build: (job: {id: string, source: object}) => Promise<Buffer>,
 *           maxBodyBytes?: number, maxQueued?: number, logger?: object }} options
 */
export function createBuildServer({ token, build, maxBodyBytes = 8 * 1024 * 1024, maxQueued = 4, logger = console }) {
    if (!token) {
        throw new Error('ADAPT_BUILDER_TOKEN is required');
    }
    const queue = new BuildQueue(maxQueued);

    return createServer(async (req, res) => {
        const url = new URL(req.url ?? '/', 'http://worker');
        try {
            if (req.method === 'GET' && url.pathname === '/health') {
                sendJson(res, 200, { ok: true, busy: queue.running, queued: queue.queued });
                return;
            }
            if (url.pathname !== '/build') {
                sendJson(res, 404, { error: 'Not found' });
                return;
            }
            if (req.method !== 'POST') {
                res.setHeader('Allow', 'POST');
                sendJson(res, 405, { error: 'Method not allowed' });
                return;
            }
            if (!tokenMatches(token, req.headers['x-internal-token'])) {
                sendJson(res, 401, { error: 'Invalid internal token' });
                return;
            }
            if (!String(req.headers['content-type'] ?? '').toLowerCase().startsWith('application/json')) {
                sendJson(res, 415, { error: 'Send application/json' });
                return;
            }
            const raw = await readBody(req, maxBodyBytes);
            let body;
            try {
                body = JSON.parse(raw.toString('utf8'));
            } catch {
                sendJson(res, 400, { error: 'Invalid JSON' });
                return;
            }
            const problems = checkRequest(body);
            if (problems.length > 0) {
                sendJson(res, 400, { error: problems.join('; ') });
                return;
            }
            const zip = await queue.run(() => build({ id: body.id, source: body.source }));
            res.writeHead(200, {
                'Content-Type': 'application/zip',
                'Content-Length': zip.length,
                'Content-Disposition': `attachment; filename="adapt-${body.id}.zip"`,
                'Cache-Control': 'no-store'
            });
            res.end(zip);
        } catch (error) {
            if (error instanceof BuildError) {
                if (error.log) {
                    logger.warn?.({ err: error.message, log: error.log.slice(-4000) }, 'Adapt build rejected');
                }
                // the last lines of the build log tell the author what to fix (no paths outside the workspace)
                const tail = error.log
                    ? error.log
                          .split('\n')
                          .filter((l) => /error|warning|invalid|missing|orphaned|empty|>>/i.test(l))
                          .filter((l) => !/Using source at|Building to|will be included in the build/.test(l))
                          .slice(-10)
                          .join('\n')
                          .replace(/\/[^\s'"]*adapt-build-[^/\s'"]+\//g, '')
                    : '';
                if (!res.headersSent) {
                    sendJson(res, error.status, { error: tail ? `${error.message}: ${tail}` : error.message });
                }
                return;
            }
            logger.error?.({ err: error?.message }, 'Adapt build worker error');
            if (!res.headersSent) {
                sendJson(res, 500, { error: 'Internal error' });
            }
        }
    });
}
