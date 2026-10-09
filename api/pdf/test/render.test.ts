import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { pino } from 'pino';
import request from 'supertest';
import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import { getDocument, OPS } from 'pdfjs-dist/legacy/build/pdf.mjs';
import { createApp } from '../src/app.js';
import { loadConfig, type Config } from '../src/config.js';
import { loadFonts, type FontStore } from '../src/fonts.js';
import { RenderPool, type PoolOptions } from '../src/pool.js';

const here = path.dirname(fileURLToPath(import.meta.url));
const TOKEN = 'test-token';
const certificate = (name = 'default') =>
    JSON.parse(readFileSync(path.resolve(here, `../../packages/templates-pdf/resources/pdfme/certificate-${name}.json`), 'utf8'));

const inputs = {
    '@VarUserName': 'Małgorzata Żółkiewska',
    '@VarCourseTitle': 'Bezpieczeństwo pracy — szkolenie okresowe',
    '@VarToday': '08.10.2026',
    '@VarCertificateId': '7d1f1c1e-0000-4000-8000-000000000001',
    '@VarCertificateVerifyUrl': 'http://app.localhost/certificates/verify/7d1f1c1e-0000-4000-8000-000000000001',
    '@VarAppName': 'Ulams'
};

let fonts: FontStore;
let pool: RenderPool;
const pools: RenderPool[] = [];
const logger = pino({ level: 'silent' });
const makePool = (options: Partial<PoolOptions> = {}) => {
    const created = new RenderPool({ size: 2, maxQueue: 8, timeoutMs: 20000, fontsDir: loadConfig({}).fontsDir, logger, ...options });
    pools.push(created);
    return created;
};
const app = (overrides: Partial<Config> = {}, renderPool?: RenderPool) =>
    createApp({ config: { ...loadConfig({}), internalToken: TOKEN, ...overrides }, fonts, logger, pool: renderPool ?? pool });

const binary = (res: any, callback: (err: Error | null, body: any) => void) => {
    const chunks: Buffer[] = [];
    res.on('data', (chunk: Buffer) => chunks.push(chunk));
    res.on('end', () => callback(null, Buffer.concat(chunks)));
};

const renderPdf = (body: unknown, overrides: Partial<Config> = {}, renderPool?: RenderPool) =>
    request(app(overrides, renderPool)).post('/render').set('X-Internal-Token', TOKEN).send(body as object).buffer(true).parse(binary);

/**
 * Text of the first page and whether it has a QR code. pdfme draws barcodes as
 * vector paths: a QR code is one path made of hundreds of module rectangles,
 * while frames and lines have a handful of segments.
 */
const inspect = async (pdf: Buffer) => {
    const doc = await getDocument({ data: new Uint8Array(pdf), useSystemFonts: false }).promise;
    const page = await doc.getPage(1);
    const content = await page.getTextContent();
    const text = content.items.map((item) => ('str' in item ? item.str : '')).join(' ');
    const ops = await page.getOperatorList();
    const pathSizes = ops.fnArray
        .map((fn, i) => (fn === OPS.constructPath ? Object.keys((ops.argsArray[i] as unknown[][])[1]?.[0] ?? {}).length : 0))
        .filter((size) => size > 0);
    const qrCodes = pathSizes.filter((size) => size > 500).length;
    return { pages: doc.numPages, text, qrCodes };
};

beforeAll(() => {
    fonts = loadFonts(loadConfig({}).fontsDir);
    pool = makePool();
});

afterAll(async () => {
    await Promise.all(pools.map((p) => p.close()));
});

describe('health and auth', () => {
    it('answers /health without a token', async () => {
        const res = await request(app()).get('/health');
        expect(res.status).toBe(200);
        expect(res.body.status).toBe('ok');
    });

    it('rejects /render without or with a wrong token', async () => {
        const body = { template: certificate(), inputs: [inputs] };
        expect((await request(app()).post('/render').send(body)).status).toBe(401);
        const wrong = await request(app()).post('/render').set('X-Internal-Token', 'nope').send(body);
        expect(wrong.status).toBe(401);
        expect(wrong.body.error).toBe('unauthorized');
    });

    it('rejects every call when the service has no token configured', async () => {
        const res = await request(app({ internalToken: '' })).post('/render').set('X-Internal-Token', '').send({});
        expect(res.status).toBe(401);
    });

    it('serves the font manifest and files to authorised callers only', async () => {
        expect((await request(app()).get('/fonts')).status).toBe(401);
        const manifest = await request(app()).get('/fonts').set('X-Internal-Token', TOKEN);
        expect(manifest.status).toBe(200);
        expect(manifest.body.data).toContainEqual({ name: 'NotoSans-Regular', file: 'NotoSans-Regular.ttf', fallback: true });
        const file = await request(app()).get('/fonts/NotoSans-Bold.ttf').set('X-Internal-Token', TOKEN).buffer(true).parse(binary);
        expect(file.status).toBe(200);
        expect(file.headers['content-type']).toContain('font/ttf');
        expect((file.body as Buffer).length).toBeGreaterThan(10000);
        expect((await request(app()).get('/fonts/..%2Fpackage.json').set('X-Internal-Token', TOKEN)).status).toBe(404);
    });
});

describe('POST /render', () => {
    it('renders the default certificate with the learner name and a verification QR code', async () => {
        const res = await renderPdf({ template: certificate(), inputs: [inputs] });
        expect(res.status).toBe(200);
        expect(res.headers['content-type']).toBe('application/pdf');
        const pdf = res.body as Buffer;
        expect(pdf.subarray(0, 5).toString()).toBe('%PDF-');

        const { pages, text, qrCodes } = await inspect(pdf);
        expect(pages).toBe(1);
        expect(text).toContain('Małgorzata Żółkiewska');
        expect(text).toContain('Bezpieczeństwo pracy');
        expect(text).toContain('CERTIFICATE OF COMPLETION');
        expect(text).toContain('7d1f1c1e-0000-4000-8000-000000000001');
        expect(qrCodes).toBe(1);
    });

    it.each(['coffee', 'oncall', 'nightsky', 'gravity', 'poland', 'ulam'])('renders the %s themed certificate', async (theme) => {
        const res = await renderPdf({ template: certificate(theme), inputs: [inputs] });
        expect(res.status).toBe(200);
        const { text, qrCodes } = await inspect(res.body as Buffer);
        expect(text).toContain('Małgorzata Żółkiewska');
        expect(qrCodes).toBe(1);
    });

    it('renders one page per input record', async () => {
        const res = await renderPdf({ template: certificate(), inputs: [inputs, { ...inputs, '@VarUserName': 'Jan Nowak' }] });
        expect(res.status).toBe(200);
        expect((await inspect(res.body as Buffer)).pages).toBe(2);
    });

    it('renders with missing variables: empty text, no QR code, and a warning header', async () => {
        const res = await renderPdf({ template: certificate(), inputs: [{ '@VarUserName': 'Jan Nowak' }] });
        expect(res.status).toBe(200);
        expect(res.headers['x-render-warnings']).toBe('1');
        const { text, qrCodes } = await inspect(res.body as Buffer);
        expect(text).toContain('Jan Nowak');
        expect(text).not.toContain('Bezpieczeństwo');
        expect(qrCodes).toBe(0);
    });

    it('defaults to one empty input record', async () => {
        const res = await renderPdf({ template: certificate() });
        expect(res.status).toBe(200);
        expect((await inspect(res.body as Buffer)).text).toContain('CERTIFICATE OF COMPLETION');
    });

    it('reports missing values of required fields with 422', async () => {
        const template = certificate();
        template.schemas[0].find((field: { name: string }) => field.name === '@VarUserName').required = true;
        const res = await request(app()).post('/render').set('X-Internal-Token', TOKEN).send({ template, inputs: [{}] });
        expect(res.status).toBe(422);
        expect(res.body.error).toBe('missing_required_fields');
        expect(res.body.details).toEqual(['@VarUserName']);
    });

    it('falls back to Noto Sans for unknown fonts', async () => {
        const template = certificate();
        template.schemas[0].forEach((field: { fontName?: string }) => {
            if (field.fontName) field.fontName = 'Comic Sans MS';
        });
        const res = await renderPdf({ template, inputs: [inputs] });
        expect(res.status).toBe(200);
        expect((await inspect(res.body as Buffer)).text).toContain('Małgorzata Żółkiewska');
    });

    it('refuses remote base PDFs and images (no outbound requests)', async () => {
        const remoteBase = { ...certificate(), basePdf: 'http://169.254.169.254/latest/meta-data' };
        const res1 = await request(app()).post('/render').set('X-Internal-Token', TOKEN).send({ template: remoteBase, inputs: [inputs] });
        expect(res1.status).toBe(400);
        expect(res1.body.error).toBe('invalid_template');

        const withImage = certificate();
        withImage.schemas[0].push({ name: 'logo', type: 'image', content: '', position: { x: 0, y: 0 }, width: 10, height: 10 });
        const res2 = await request(app()).post('/render').set('X-Internal-Token', TOKEN)
            .send({ template: withImage, inputs: [{ ...inputs, logo: 'http://example.com/logo.png' }] });
        expect(res2.status).toBe(422);
        expect(res2.body.error).toBe('remote_image');
    });

    it('rejects invalid templates and unsupported field types', async () => {
        const bad = await request(app()).post('/render').set('X-Internal-Token', TOKEN).send({ template: { schemas: 'x' } });
        expect(bad.status).toBe(400);

        const template = certificate();
        template.schemas[0].push({ name: 'x', type: 'checkbox', position: { x: 0, y: 0 }, width: 5, height: 5 });
        const res = await request(app()).post('/render').set('X-Internal-Token', TOKEN).send({ template, inputs: [inputs] });
        expect(res.status).toBe(422);
        expect(res.body.error).toBe('unsupported_field_type');
    });

    it('enforces the body size, input count, timeout and concurrency limits', async () => {
        const body = { template: certificate(), inputs: [inputs] };
        const tooLarge = await request(app({ maxBodySize: '1kb' })).post('/render').set('X-Internal-Token', TOKEN).send(body);
        expect(tooLarge.status).toBe(413);

        const tooMany = await request(app({ maxInputs: 1 })).post('/render').set('X-Internal-Token', TOKEN)
            .send({ ...body, inputs: [inputs, inputs] });
        expect(tooMany.status).toBe(413);
        expect(tooMany.body.error).toBe('too_many_inputs');

        // the worker running an over-time render is terminated and replaced
        const slowPool = makePool({ size: 1, timeoutMs: 1 });
        const slow = await request(app({}, slowPool)).post('/render').set('X-Internal-Token', TOKEN).send(body);
        expect(slow.status).toBe(504);
        expect(slow.body.error).toBe('render_timeout');

        const busyPool = makePool({ size: 1, maxQueue: 0 });
        const busyApp = app({}, busyPool);
        const [first, second] = await Promise.all([
            request(busyApp).post('/render').set('X-Internal-Token', TOKEN).send(body),
            request(busyApp).post('/render').set('X-Internal-Token', TOKEN).send(body)
        ]);
        expect([first.status, second.status].sort()).toEqual([200, 503]);
        expect((first.status === 503 ? first : second).headers['retry-after']).toBe('2');
    });
});
