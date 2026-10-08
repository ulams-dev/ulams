import { existsSync } from 'node:fs';
import path from 'node:path';
import { Request, Response, Router } from 'express';

/**
 * Embed pages: self-contained HTML documents hosting the Lumi player/editor
 * web components and the H5P core, all served by this (GPL) service. The LMS
 * frontends only frame them and talk to them over postMessage (see README,
 * "Embedding"), so no GPL code is bundled into admin/front.
 *
 *   GET /h5p/embed/play/:id   ?language=&contextId=&readOnlyState=yes&hideActions=1
 *   GET /h5p/embed/edit/:id   (:id may be "new") ?language=
 *   GET /h5p/embed/assets/{player,editor}.js(.map)
 *
 * The pages carry no token: the parent sends it with postMessage after the
 * `ulams-h5p:ready` handshake, so tokens never appear in URLs or logs.
 */

export interface EmbedRouterOptions {
    /** service mount path, e.g. "/h5p" */
    base: string;
    /** origins allowed to frame the pages and to talk to them (CORS_ORIGINS) */
    allowedOrigins: string[];
    /** directory with the bundled player.js / editor.js (dist/embed) */
    assetsDir?: string;
}

const ASSETS = new Set(['player.js', 'editor.js', 'player.js.map', 'editor.js.map', 'player.js.LEGAL.txt', 'editor.js.LEGAL.txt']);
const LANGUAGE = /^[a-zA-Z]{2,3}([-_][a-zA-Z0-9]{2,8})?$/;
const CONTEXT_ID = /^[A-Za-z0-9._:-]{1,128}$/;
const CONTENT_ID = /^[1-9][0-9]{0,18}$/;

export function defaultEmbedAssetsDir(): string {
    // dist/routes -> dist/embed (production); src/routes -> dist/embed (tsx dev)
    const candidates = [path.resolve(__dirname, '../embed'), path.resolve(__dirname, '../../dist/embed')];
    return candidates.find((dir) => existsSync(path.join(dir, 'player.js'))) ?? candidates[0];
}

function queryString(req: Request, name: string): string | undefined {
    const v = req.query[name];
    return typeof v === 'string' && v !== '' ? v : undefined;
}

function truthy(v: string | undefined): boolean {
    return v !== undefined && ['yes', 'true', '1'].includes(v.toLowerCase());
}

/** JSON safe to place inside <script type="application/json">. */
export function scriptJson(value: unknown): string {
    return JSON.stringify(value)
        .replace(/</g, '\\u003c')
        .replace(/>/g, '\\u003e')
        .replace(/&/g, '\\u0026')
        .replace(/\u2028/g, '\\u2028')
        .replace(/\u2029/g, '\\u2029');
}

function escapeAttr(value: string): string {
    return value.replace(/[&<>"']/g, (c) => `&#${c.charCodeAt(0)};`);
}

/** CSP frame-ancestors from the allow-list ('*' keeps framing open). */
export function frameAncestors(origins: string[]): string {
    if (origins.includes('*')) {
        return '*';
    }
    const valid = origins.filter((o) => /^https?:\/\/[^\s/;,]+$/.test(o));
    return ["'self'", ...valid].join(' ');
}

function page(opts: EmbedRouterOptions, script: 'player' | 'editor', config: Record<string, unknown>): string {
    const lang = escapeAttr(String(config.language ?? 'en'));
    return `<!doctype html>
<html lang="${lang}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="referrer" content="no-referrer">
<title>H5P</title>
<style>
html, body { margin: 0; padding: 0; background: transparent; }
body { overflow-x: hidden; overflow-y: ${script === 'player' ? 'hidden' : 'auto'}; }
.ulams-h5p-error { font-family: sans-serif; color: #a8071a; padding: 8px; }
</style>
<script id="ulams-h5p-config" type="application/json">${scriptJson(config)}</script>
<script src="${escapeAttr(`${opts.base}/embed/assets/${script}.js`)}" defer></script>
</head>
<body><div id="root"></div></body>
</html>`;
}

function sendPage(res: Response, opts: EmbedRouterOptions, html: string): void {
    res.status(200);
    res.setHeader('Content-Type', 'text/html; charset=utf-8');
    res.setHeader('Cache-Control', 'no-store');
    res.setHeader('Content-Security-Policy', `frame-ancestors ${frameAncestors(opts.allowedOrigins)}`);
    res.setHeader('X-Content-Type-Options', 'nosniff');
    res.setHeader('Referrer-Policy', 'no-referrer');
    res.send(html);
}

export function embedRouter(opts: EmbedRouterOptions): Router {
    const router = Router();
    const assetsDir = opts.assetsDir ?? defaultEmbedAssetsDir();
    const language = (req: Request) => {
        const l = queryString(req, 'language');
        return l && LANGUAGE.test(l) ? l : 'en';
    };

    router.get('/play/:id', (req, res) => {
        const id = req.params.id as string;
        if (!CONTENT_ID.test(id)) {
            res.status(404).json({ success: false, message: 'Content not found.' });
            return;
        }
        const contextId = queryString(req, 'contextId');
        sendPage(
            res,
            opts,
            page(opts, 'player', {
                mode: 'play',
                contentId: id,
                base: opts.base,
                allowedOrigins: opts.allowedOrigins,
                language: language(req),
                contextId: contextId && CONTEXT_ID.test(contextId) ? contextId : undefined,
                readOnlyState: truthy(queryString(req, 'readOnlyState')),
                hideActions: truthy(queryString(req, 'hideActions'))
            })
        );
    });

    router.get('/edit/:id', (req, res) => {
        const id = req.params.id as string;
        if (id !== 'new' && !CONTENT_ID.test(id)) {
            res.status(404).json({ success: false, message: 'Content not found.' });
            return;
        }
        sendPage(
            res,
            opts,
            page(opts, 'editor', {
                mode: 'edit',
                contentId: id,
                base: opts.base,
                allowedOrigins: opts.allowedOrigins,
                language: language(req)
            })
        );
    });

    router.get('/assets/:file', (req, res) => {
        const file = req.params.file as string;
        if (!ASSETS.has(file)) {
            res.status(404).json({ success: false, message: 'Not found.' });
            return;
        }
        res.setHeader('X-Content-Type-Options', 'nosniff');
        res.sendFile(path.join(assetsDir, file), { maxAge: '1h' }, (err) => {
            if (err && !res.headersSent) {
                res.status(404).json({ success: false, message: 'Not found.' });
            }
        });
    });

    return router;
}
