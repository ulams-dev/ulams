import assert from 'node:assert/strict';
import { lstat, mkdir, mkdtemp, readdir, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { after, before, describe, it } from 'node:test';
import { fileURLToPath } from 'node:url';

import { BuildError, checkRequest, courseFiles, createFrameworkBuilder, prepareWorkspace } from '../src/build.mjs';
import { readZip } from './zip.test.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const fixture = JSON.parse(await readFile(path.join(here, 'fixtures', 'course.json'), 'utf8'));

/**
 * A stand-in framework: Gruntfile, plugins under src/ and a `grunt` that "builds" by copying
 * the course JSON to build/ with an imsmanifest.xml. Its behaviour is chosen by the course
 * title: "fail" exits 1, "slow" never finishes, "no-manifest" skips the manifest.
 */
async function fakeFramework(root) {
    const fw = path.join(root, 'framework');
    await mkdir(path.join(fw, 'src', 'core', 'js'), { recursive: true });
    await mkdir(path.join(fw, 'src', 'components', 'adapt-contrib-text'), { recursive: true });
    await mkdir(path.join(fw, 'src', 'course', 'en'), { recursive: true });
    await mkdir(path.join(fw, 'grunt'), { recursive: true });
    await mkdir(path.join(fw, 'node_modules', 'grunt', 'bin'), { recursive: true });
    await writeFile(path.join(fw, 'Gruntfile.js'), 'module.exports = () => {};\n');
    await writeFile(path.join(fw, 'adapt.json'), '{}\n');
    await writeFile(path.join(fw, 'src', 'core', 'js', 'adapt.js'), '// core\n');
    await writeFile(path.join(fw, 'src', 'components', 'adapt-contrib-text', 'bower.json'), '{"name":"adapt-contrib-text"}\n');
    await writeFile(path.join(fw, 'src', 'course', 'en', 'course.json'), '{"title":"example course"}\n');
    await writeFile(
        path.join(fw, 'node_modules', 'grunt', 'bin', 'grunt'),
        `const fs = require('fs');
const path = require('path');
const course = JSON.parse(fs.readFileSync('src/course/en/course.json', 'utf8'));
if (course.title === 'fail') { console.log('>> Missing _ids: x (block)'); process.exit(1); }
if (course.title === 'slow') { setInterval(() => {}, 1000); return; }
fs.mkdirSync('build/course/en', { recursive: true });
for (const f of fs.readdirSync('src/course/en')) fs.copyFileSync(path.join('src/course/en', f), path.join('build/course/en', f));
fs.copyFileSync('src/course/config.json', 'build/course/config.json');
if (course.title !== 'no-manifest') fs.writeFileSync('build/imsmanifest.xml', '<manifest/>');
fs.writeFileSync('build/env.json', JSON.stringify({ args: process.argv.slice(2), keys: Object.keys(process.env).sort() }));
`
    );
    return fw;
}

describe('checkRequest', () => {
    it('accepts the fixture', () => {
        assert.deepEqual(checkRequest(fixture), []);
    });

    it('rejects bad ids, missing parts and odd languages', () => {
        assert.deepEqual(checkRequest(null), ['body: must be a JSON object']);
        const bad = structuredClone(fixture);
        bad.id = '../etc';
        delete bad.source.blocks;
        bad.source.config._defaultLanguage = '../en';
        const problems = checkRequest(bad);
        assert.equal(problems.length, 3);
        assert.match(problems.join('\n'), /id: required/);
        assert.match(problems.join('\n'), /source.blocks: required array/);
        assert.match(problems.join('\n'), /_defaultLanguage/);
    });
});

describe('courseFiles', () => {
    it('lays the course out per language, fills _types and forces spoor', () => {
        const source = structuredClone(fixture.source);
        source.config._spoor = { _isEnabled: false };
        delete source.blocks[0]._type;
        const { language, files } = courseFiles(source);
        assert.equal(language, 'en');
        assert.deepEqual(Object.keys(files).sort(), [
            'config.json',
            'en/articles.json',
            'en/blocks.json',
            'en/components.json',
            'en/contentObjects.json',
            'en/course.json'
        ]);
        assert.equal(files['config.json']._spoor._isEnabled, true);
        assert.equal(files['en/course.json']._type, 'course');
        assert.equal(files['en/blocks.json'][0]._type, 'block');
    });
});

describe('framework builder (fake framework)', () => {
    let root;
    let fw;
    let work;

    before(async () => {
        root = await mkdtemp(path.join(tmpdir(), 'adapt-builder-test-'));
        fw = await fakeFramework(root);
        work = path.join(root, 'work');
        await mkdir(work);
    });

    after(async () => {
        await rm(root, { recursive: true, force: true });
    });

    it('prepares a workspace without the example course and with linked node_modules', async () => {
        const { dir } = await prepareWorkspace(fw, work, fixture.source);
        assert.ok((await lstat(path.join(dir, 'node_modules'))).isSymbolicLink());
        assert.ok((await lstat(path.join(dir, 'src', 'components'))).isDirectory());
        const course = JSON.parse(await readFile(path.join(dir, 'src', 'course', 'en', 'course.json'), 'utf8'));
        assert.equal(course.title, 'Adapt fixture');
        await rm(dir, { recursive: true, force: true });
    });

    it('builds, zips build/ and removes the workspace', async () => {
        process.env.ADAPT_BUILDER_TOKEN = 'must-not-leak';
        const build = createFrameworkBuilder({ frameworkDir: fw, workDir: work, timeoutMs: 10_000, maxOutputBytes: 1 << 20 });
        const zip = await build(fixture);
        delete process.env.ADAPT_BUILDER_TOKEN;
        const files = readZip(zip);
        assert.equal(files['imsmanifest.xml'].toString(), '<manifest/>');
        assert.equal(JSON.parse(files['course/en/components.json'].toString()).length, 2);
        const env = JSON.parse(files['env.json'].toString());
        assert.equal(env.args[0], 'build');
        // a minimal environment: no secrets of the worker (its token) reach the build
        assert.ok(env.keys.includes('PATH'));
        assert.ok(!env.keys.includes('ADAPT_BUILDER_TOKEN'));
        assert.deepEqual(await readdir(work), []);
    });

    it('reports a failed build with its log', async () => {
        const build = createFrameworkBuilder({ frameworkDir: fw, workDir: work, timeoutMs: 10_000, maxOutputBytes: 1 << 20 });
        const source = structuredClone(fixture);
        source.source.course.title = 'fail';
        await assert.rejects(build(source), (error) => error instanceof BuildError && error.status === 422 && /Missing _ids/.test(error.log));
        assert.deepEqual(await readdir(work), []);
    });

    it('kills a build that runs past the timeout', async () => {
        const build = createFrameworkBuilder({ frameworkDir: fw, workDir: work, timeoutMs: 300, maxOutputBytes: 1 << 20 });
        const source = structuredClone(fixture);
        source.source.course.title = 'slow';
        await assert.rejects(build(source), (error) => error instanceof BuildError && error.status === 504);
        assert.deepEqual(await readdir(work), []);
    });

    it('refuses a build without a SCORM manifest', async () => {
        const build = createFrameworkBuilder({ frameworkDir: fw, workDir: work, timeoutMs: 10_000, maxOutputBytes: 1 << 20 });
        const source = structuredClone(fixture);
        source.source.course.title = 'no-manifest';
        await assert.rejects(build(source), /imsmanifest.xml/);
    });
});
