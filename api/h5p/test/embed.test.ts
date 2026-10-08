import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import express from 'express';
import request from 'supertest';
import { describe, expect, it } from 'vitest';

import { embedRouter, frameAncestors, scriptJson } from '../src/routes/embed';

const assetsDir = mkdtempSync(path.join(tmpdir(), 'h5p-embed-'));
writeFileSync(path.join(assetsDir, 'player.js'), 'console.log("player")');
writeFileSync(path.join(assetsDir, 'editor.js'), 'console.log("editor")');

const app = express();
app.use(
    '/h5p/embed',
    embedRouter({ base: '/h5p', allowedOrigins: ['http://localhost:3000', 'http://localhost:8000'], assetsDir })
);

function configOf(html: string): any {
    const m = /<script id="ulams-h5p-config" type="application\/json">(.*?)<\/script>/s.exec(html);
    return JSON.parse(m![1]);
}

describe('embed pages', () => {
    it('serves the player page with config and framing policy', async () => {
        const res = await request(app).get('/h5p/embed/play/12?language=pl&hideActions=1&readOnlyState=yes&contextId=c-1');
        expect(res.status).toBe(200);
        expect(res.headers['content-type']).toMatch(/text\/html/);
        expect(res.headers['cache-control']).toBe('no-store');
        expect(res.headers['content-security-policy']).toBe(
            "frame-ancestors 'self' http://localhost:3000 http://localhost:8000"
        );
        expect(res.text).toContain('src="/h5p/embed/assets/player.js"');
        expect(configOf(res.text)).toEqual({
            mode: 'play',
            contentId: '12',
            base: '/h5p',
            allowedOrigins: ['http://localhost:3000', 'http://localhost:8000'],
            language: 'pl',
            contextId: 'c-1',
            readOnlyState: true,
            hideActions: true
        });
    });

    it('serves the editor page for new and existing content', async () => {
        const res = await request(app).get('/h5p/embed/edit/new?language=<script>');
        expect(res.status).toBe(200);
        const cfg = configOf(res.text);
        expect(cfg).toMatchObject({ mode: 'edit', contentId: 'new', language: 'en' });
        expect(res.text).toContain('src="/h5p/embed/assets/editor.js"');
        expect((await request(app).get('/h5p/embed/edit/7')).status).toBe(200);
    });

    it('rejects invalid ids and unknown assets', async () => {
        expect((await request(app).get('/h5p/embed/play/abc')).status).toBe(404);
        expect((await request(app).get('/h5p/embed/play/new')).status).toBe(404);
        expect((await request(app).get('/h5p/embed/edit/..%2Fx')).status).toBe(404);
        expect((await request(app).get('/h5p/embed/assets/secret.js')).status).toBe(404);
    });

    it('serves the bundled assets', async () => {
        const res = await request(app).get('/h5p/embed/assets/player.js');
        expect(res.status).toBe(200);
        expect(res.text).toContain('player');
    });

    it('drops a bogus contextId', async () => {
        const res = await request(app).get('/h5p/embed/play/3?contextId=%3Cx%3E');
        expect(configOf(res.text).contextId).toBeUndefined();
    });
});

describe('embed helpers', () => {
    it('escapes JSON for script elements', () => {
        expect(scriptJson({ a: '</script><b>&' })).not.toMatch(/[<>&]/);
    });

    it('builds frame-ancestors', () => {
        expect(frameAncestors(['*'])).toBe('*');
        expect(frameAncestors(['http://a.test', 'bad origin;'])).toBe("'self' http://a.test");
    });
});
