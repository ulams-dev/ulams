import express from 'express';
import request from 'supertest';
import { describe, expect, it } from 'vitest';

import { createMetrics, requestId } from '../src/http/metrics';

const build = () => {
    const metrics = createMetrics();
    const app = express();
    app.use(metrics.middleware);
    app.use((req, res, next) => {
        (req as any).id = requestId(req, res);
        next();
    });
    app.get('/metrics', metrics.handler);
    app.get('/h5p/health', (_req, res) => res.json({ ok: true }));
    return app;
};

describe('metrics and request ids', () => {
    it('serves Prometheus text with event-loop lag and request durations', async () => {
        const app = build();
        await request(app).get('/h5p/health').expect(200);
        await request(app).get('/nothing').expect(404);

        const res = await request(app).get('/metrics').expect(200);

        expect(res.headers['content-type']).toMatch(/^text\/plain; .*version=0\.0\.4/);
        expect(res.text).toMatch(/^# TYPE nodejs_eventloop_lag_seconds gauge$/m);
        expect(res.text).toMatch(/^ulams_h5p_http_request_duration_seconds_count\{method="GET",route="\/h5p\/health",status="200"\} 1$/m);
        expect(res.text).toMatch(/^ulams_h5p_http_request_duration_seconds_count\{method="GET",route="unmatched",status="404"\} 1$/m);
    });

    it('keeps a valid X-Request-Id and replaces a bad one', async () => {
        const app = build();
        const id = '01J9Z3Y8K2M4N6P8Q0R2S4T6V8';

        expect((await request(app).get('/h5p/health').set('X-Request-Id', id)).headers['x-request-id']).toBe(id);
        expect((await request(app).get('/h5p/health').set('X-Request-Id', 'bad id')).headers['x-request-id']).toMatch(/^[0-9a-f-]{36}$/);
    });
});
