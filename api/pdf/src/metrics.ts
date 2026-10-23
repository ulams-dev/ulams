import type { NextFunction, Request, Response } from 'express';
import { Gauge, Histogram, Registry, collectDefaultMetrics } from 'prom-client';

/**
 * Prometheus metrics of the renderer: default Node metrics (event-loop lag, heap, GC), request
 * durations by route and status, and the render pool. The endpoint is internal (the service is not
 * published and needs no X-Internal-Token for it); the gateway must never route it publicly.
 */
export const createMetrics = (pool: { active: number }) => {
    const registry = new Registry();
    collectDefaultMetrics({ register: registry });
    const duration = new Histogram({
        name: 'ulams_pdf_http_request_duration_seconds',
        help: 'HTTP request duration of the PDF service.',
        labelNames: ['method', 'route', 'status'],
        buckets: [0.01, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10, 30],
        registers: [registry]
    });
    new Gauge({
        name: 'ulams_pdf_active_renders',
        help: 'Renders running in the worker pool now.',
        registers: [registry],
        collect() {
            this.set(pool.active);
        }
    });

    const middleware = (req: Request, res: Response, next: NextFunction) => {
        const end = duration.startTimer();
        res.on('finish', () => {
            end({ method: req.method, route: req.route?.path ?? 'unmatched', status: String(res.statusCode) });
        });
        next();
    };
    const handler = async (_req: Request, res: Response) => {
        res.set('Content-Type', registry.contentType).set('Cache-Control', 'no-store').send(await registry.metrics());
    };

    return { registry, middleware, handler };
};
