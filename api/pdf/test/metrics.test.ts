import { pino } from 'pino';
import request from 'supertest';
import { describe, expect, it } from 'vitest';
import { createApp } from '../src/app.js';
import { loadConfig } from '../src/config.js';
import type { FontStore } from '../src/fonts.js';
import type { RenderPool } from '../src/pool.js';

const app = createApp({
    config: { ...loadConfig(), internalToken: 'secret' },
    fonts: { files: new Map() } as unknown as FontStore,
    logger: pino({ level: 'silent' }),
    pool: { active: 2 } as unknown as RenderPool
});

describe('metrics and request ids', () => {
    it('serves Prometheus text without the internal token', async () => {
        await request(app).get('/health').expect(200);
        await request(app).get('/render').expect(401);

        const res = await request(app).get('/metrics').expect(200);

        expect(res.headers['content-type']).toMatch(/^text\/plain; .*version=0\.0\.4/);
        expect(res.text).toMatch(/^ulams_pdf_active_renders 2$/m);
        expect(res.text).toMatch(/^# TYPE nodejs_eventloop_lag_seconds gauge$/m);
        expect(res.text).toMatch(/^ulams_pdf_http_request_duration_seconds_count\{method="GET",route="\/health",status="200"\} 1$/m);
        expect(res.text).toMatch(/^ulams_pdf_http_request_duration_seconds_count\{method="GET",route="unmatched",status="401"\} 1$/m);
    });

    it('echoes a valid X-Request-Id and replaces a bad one', async () => {
        const id = '01J9Z3Y8K2M4N6P8Q0R2S4T6V8';
        const ok = await request(app).get('/health').set('X-Request-Id', id).expect(200);
        expect(ok.headers['x-request-id']).toBe(id);

        const bad = await request(app).get('/health').set('X-Request-Id', 'x y\t').expect(200);
        expect(bad.headers['x-request-id']).toMatch(/^[0-9a-f-]{36}$/);
    });
});
