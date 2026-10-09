// Runs one Adapt framework build in a throwaway workspace and returns the SCORM zip.
import { spawn } from 'node:child_process';
import { access, cp, mkdir, mkdtemp, readdir, rm, symlink, writeFile } from 'node:fs/promises';
import path from 'node:path';

import { zipDirectory } from './zip.mjs';

export const PARTS = ['course', 'config', 'contentObjects', 'articles', 'blocks', 'components'];
const LIST_PARTS = ['contentObjects', 'articles', 'blocks', 'components'];
const ID_RE = /^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/;
const LANGUAGE_RE = /^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})?$/;

export class BuildError extends Error {
    /** @param {string} message @param {number} status @param {string} [log] */
    constructor(message, status = 422, log = '') {
        super(message);
        this.status = status;
        this.log = log;
    }
}

/**
 * Checks the request body shape (the API already validated the structure, ADR 0013; this is
 * the worker's own guard). Returns the problems; empty when usable.
 */
export function checkRequest(body) {
    const problems = [];
    if (!body || typeof body !== 'object' || Array.isArray(body)) {
        return ['body: must be a JSON object'];
    }
    if (typeof body.id !== 'string' || !ID_RE.test(body.id)) {
        problems.push('id: required, letters, digits, ".", "_" or "-"');
    }
    const source = body.source;
    if (!source || typeof source !== 'object' || Array.isArray(source)) {
        problems.push('source: required object');
        return problems;
    }
    for (const part of ['course', 'config']) {
        if (!source[part] || typeof source[part] !== 'object' || Array.isArray(source[part])) {
            problems.push(`source.${part}: required object`);
        }
    }
    for (const part of LIST_PARTS) {
        if (!Array.isArray(source[part])) {
            problems.push(`source.${part}: required array`);
        }
    }
    const lang = source.config?._defaultLanguage;
    if (lang !== undefined && (typeof lang !== 'string' || !LANGUAGE_RE.test(lang))) {
        problems.push('source.config._defaultLanguage: invalid language code');
    }
    return problems;
}

/**
 * Course files as the framework expects them in src/course: config.json and
 * <language>/{course,contentObjects,articles,blocks,components}.json. Fills the `_type`s the
 * framework requires and always enables SCORM tracking (spoor), whose output we import.
 */
export function courseFiles(source) {
    const language = typeof source.config?._defaultLanguage === 'string' ? source.config._defaultLanguage : 'en';
    const config = {
        ...source.config,
        _defaultLanguage: language,
        _spoor: { ...(source.config?._spoor ?? {}), _isEnabled: true }
    };
    const typed = (items, type) => items.map((item) => ({ _type: type, ...item }));
    return {
        language,
        files: {
            'config.json': config,
            [`${language}/course.json`]: { ...source.course, _type: 'course' },
            [`${language}/contentObjects.json`]: source.contentObjects,
            [`${language}/articles.json`]: typed(source.articles, 'article'),
            [`${language}/blocks.json`]: typed(source.blocks, 'block'),
            [`${language}/components.json`]: typed(source.components, 'component')
        }
    };
}

/**
 * Copies the framework (without node_modules and the example course) into a new directory
 * under workDir, links node_modules and writes the course. Returns the workspace path.
 */
export async function prepareWorkspace(frameworkDir, workDir, source) {
    const dir = await mkdtemp(path.join(workDir, 'adapt-build-'));
    const skip = new Set(['node_modules', '.git', 'build']);
    for (const entry of await readdir(frameworkDir)) {
        if (skip.has(entry)) {
            continue;
        }
        await cp(path.join(frameworkDir, entry), path.join(dir, entry), {
            recursive: true,
            filter: (src) => !src.startsWith(path.join(frameworkDir, 'src', 'course'))
        });
    }
    await symlink(path.join(frameworkDir, 'node_modules'), path.join(dir, 'node_modules'), 'dir');
    const { files, language } = courseFiles(source);
    await mkdir(path.join(dir, 'src', 'course', language), { recursive: true });
    for (const [name, data] of Object.entries(files)) {
        await writeFile(path.join(dir, 'src', 'course', name), JSON.stringify(data, null, 2));
    }
    return { dir, language };
}

/** Runs a command with a timeout; kills the whole process group on timeout. */
export function run(command, args, { cwd, timeoutMs, env }) {
    return new Promise((resolve) => {
        const child = spawn(command, args, { cwd, env, detached: true, stdio: ['ignore', 'pipe', 'pipe'] });
        let log = '';
        const append = (chunk) => {
            log = (log + chunk.toString()).slice(-20000);
        };
        child.stdout.on('data', append);
        child.stderr.on('data', append);
        let timedOut = false;
        const timer = setTimeout(() => {
            timedOut = true;
            try {
                process.kill(-child.pid, 'SIGKILL');
            } catch {
                child.kill('SIGKILL');
            }
        }, timeoutMs);
        child.on('error', (error) => {
            clearTimeout(timer);
            resolve({ code: -1, log: `${log}\n${error.message}`, timedOut });
        });
        child.on('close', (code) => {
            clearTimeout(timer);
            resolve({ code: code ?? -1, log, timedOut });
        });
    });
}

/**
 * The real build: `grunt build` of the framework in a throwaway workspace, then the build
 * directory zipped. Throws BuildError (422 build failed, 504 timeout).
 */
export function createFrameworkBuilder({ frameworkDir, workDir, timeoutMs, maxOutputBytes, logger }) {
    const grunt = path.join(frameworkDir, 'node_modules', 'grunt', 'bin', 'grunt');
    return async function build({ id, source }) {
        const { dir } = await prepareWorkspace(frameworkDir, workDir, source);
        const started = Date.now();
        try {
            const env = {
                PATH: process.env.PATH ?? '/usr/local/bin:/usr/bin:/bin',
                HOME: dir,
                TMPDIR: dir,
                NODE_ENV: 'production'
            };
            const result = await run(process.execPath, [grunt, 'build', '--no-color', `--cachepath=${path.join(dir, '.cache')}`], {
                cwd: dir,
                timeoutMs,
                env
            });
            if (result.timedOut) {
                throw new BuildError(`The build did not finish within ${Math.round(timeoutMs / 1000)} s`, 504, result.log);
            }
            if (result.code !== 0) {
                throw new BuildError('The Adapt build failed', 422, result.log);
            }
            const out = path.join(dir, 'build');
            try {
                await access(path.join(out, 'imsmanifest.xml'));
            } catch {
                throw new BuildError('The build has no imsmanifest.xml (adapt-contrib-spoor missing?)', 422, result.log);
            }
            const zip = await zipDirectory(out, maxOutputBytes);
            logger?.info?.({ id, ms: Date.now() - started, bytes: zip.length }, 'Adapt build finished');
            return zip;
        } finally {
            await rm(dir, { recursive: true, force: true }).catch(() => undefined);
        }
    };
}
