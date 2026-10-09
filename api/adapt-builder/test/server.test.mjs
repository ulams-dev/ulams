import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { after, before, describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';

import { BuildError } from '../src/build.mjs';
import { BuildQueue, createBuildServer } from '../src/server.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const fixture = JSON.parse(await readFile(path.join(here, 'fixtures', 'course.json'), 'utf8'));
const silent = { info() {}, warn() {}, error() {} };

describe('build server', () => {
    let server;
    let base;
    const calls = [];
    let behaviour = 'ok';

    before(async () => {
        server = createBuildServer({
            token: 'secret-token',
            maxBodyBytes: 64 * 1024,
            logger: silent,
            build: async (job) => {
                calls.push(job);
                if (behaviour === 'fail') {
                    throw new BuildError('The Adapt build failed', 422, '>> Missing _ids: x (block)\n>> Using source at src/');
                }
                if (behaviour === 'crash') {
                    throw new Error('disk full at /opt/secret/path');
                }
                return Buffer.from('PK-zip-bytes');
            }
        });
        await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
        base = `http://127.0.0.1:${server.address().port}`;
    });

    after(() => new Promise((resolve) => server.close(resolve)));

    const post = (body, headers = {}) =>
        fetch(`${base}/build`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Internal-Token': 'secret-token', ...headers },
            body: typeof body === 'string' ? body : JSON.stringify(body)
        });

    it('answers health without a token', async () => {
        const res = await fetch(`${base}/health`);
        assert.equal(res.status, 200);
        assert.deepEqual(await res.json(), { ok: true, busy: false, queued: 0 });
    });

    it('returns the zip for a valid source', async () => {
        behaviour = 'ok';
        const res = await post(fixture);
        assert.equal(res.status, 200);
        assert.equal(res.headers.get('content-type'), 'application/zip');
        assert.equal(Buffer.from(await res.arrayBuffer()).toString(), 'PK-zip-bytes');
        assert.equal(calls.at(-1).id, 'fixture-v1');
        assert.equal(calls.at(-1).source.course.title, 'Adapt fixture');
    });

    it('rejects a missing or wrong token before reading the source', async () => {
        const before = calls.length;
        assert.equal((await post(fixture, { 'X-Internal-Token': '' })).status, 401);
        assert.equal((await post(fixture, { 'X-Internal-Token': 'secret-tokem' })).status, 401);
        assert.equal(calls.length, before);
    });

    it('rejects other content types, bad JSON, bad shapes and big bodies', async () => {
        assert.equal((await post(fixture, { 'Content-Type': 'text/plain' })).status, 415);
        assert.equal((await post('{nope')).status, 400);
        const res = await post({ id: 'x', source: { course: {} } });
        assert.equal(res.status, 400);
        assert.match((await res.json()).error, /source.config: required object/);
        const big = structuredClone(fixture);
        big.source.course.body = 'x'.repeat(70 * 1024);
        assert.equal((await post(big)).status, 413);
    });

    it('maps build failures to their status with the relevant log lines only', async () => {
        behaviour = 'fail';
        const res = await post(fixture);
        assert.equal(res.status, 422);
        const { error } = await res.json();
        assert.match(error, /Missing _ids: x \(block\)/);
        assert.doesNotMatch(error, /Using source at/);
    });

    it('hides unexpected errors', async () => {
        behaviour = 'crash';
        const res = await post(fixture);
        assert.equal(res.status, 500);
        assert.deepEqual(await res.json(), { error: 'Internal error' });
    });

    it('answers 404 and 405 elsewhere', async () => {
        assert.equal((await fetch(`${base}/nope`)).status, 404);
        assert.equal((await fetch(`${base}/build`)).status, 405);
    });

    it('refuses to start without a token', () => {
        assert.throws(() => createBuildServer({ token: '', build: async () => Buffer.alloc(0) }), /ADAPT_BUILDER_TOKEN/);
    });
});

describe('BuildQueue', () => {
    it('runs one build at a time and rejects when the queue is full', async () => {
        const queue = new BuildQueue(1);
        let running = 0;
        let peak = 0;
        const release = [];
        const task = () =>
            new Promise((resolve) => {
                running++;
                peak = Math.max(peak, running);
                release.push(() => {
                    running--;
                    resolve('done');
                });
            });
        const first = queue.run(task);
        const second = queue.run(task);
        await assert.rejects(queue.run(task), (e) => e instanceof BuildError && e.status === 503);
        await new Promise((r) => setImmediate(r));
        release.shift()();
        assert.equal(await first, 'done');
        await new Promise((r) => setImmediate(r));
        release.shift()();
        assert.equal(await second, 'done');
        assert.equal(peak, 1);
    });
});
