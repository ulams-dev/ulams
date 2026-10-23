import { randomUUID } from 'node:crypto';
import type { NextFunction, Request, RequestHandler, Response } from 'express';
import { Histogram, Registry, collectDefaultMetrics } from 'prom-client';

/**
 * Prometheus metrics of the H5P service: default Node metrics (event-loop lag, heap, GC) and request
 * durations by route and status. `GET /metrics` is for the internal network (Caddy routes only
 * /h5p/* to this service, so the path is not public) and needs no tenant or token.
 */
export function createMetrics() {
    const registry = new Registry();
    collectDefaultMetrics({ register: registry });
    const duration = new Histogram({
        name: 'ulams_h5p_http_request_duration_seconds',
        help: 'HTTP request duration of the H5P service.',
        labelNames: ['method', 'route', 'status'],
        buckets: [0.01, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10, 30, 120],
        registers: [registry]
    });

    const middleware: RequestHandler = (req: Request, res: Response, next: NextFunction) => {
        const end = duration.startTimer();
        res.on('finish', () => {
            // `route.path` is the pattern of the last router; unmatched requests share one label
            end({ method: req.method, route: req.route?.path ?? 'unmatched', status: String(res.statusCode) });
        });
        next();
    };
    const handler: RequestHandler = async (_req, res) => {
        res.set('Content-Type', registry.contentType).set('Cache-Control', 'no-store').send(await registry.metrics());
    };

    return { registry, middleware, handler };
}

/** Takes the id the API sent (`X-Request-Id`) so logs of both services share it, else makes one. */
export function requestId(req: Request, res: Response): string {
    const given = req.headers['x-request-id'];
    const id = typeof given === 'string' && /^[0-9A-Za-z-]{8,64}$/.test(given) ? given : randomUUID();
    res.setHeader('X-Request-Id', id);
    return id;
}
