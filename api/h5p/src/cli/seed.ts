import { createWriteStream } from 'node:fs';
import { readdir, rm, stat, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { Readable } from 'node:stream';
import { pipeline } from 'node:stream/promises';
import { randomUUID } from 'node:crypto';

import { loadConfig } from '../config';
import { logger } from '../logger';
import { createRuntime } from '../runtime';
import { systemUser } from '../auth/users';
import { importPackage } from '../h5p/importPackage';
import { SAMPLE_PACKAGES } from './samples';

const HELP = `Usage: npm run seed -- [options] [file.h5p | dir | https://url.h5p ...]

Installs libraries from the H5P Hub and imports .h5p packages as the internal
system user. Prints a JSON report to stdout (logs go to stderr).

Options:
  --hub <A,B,...>     install these content types from the H5P Hub first
                      (e.g. H5P.MultiChoice,H5P.DragQuestion)
  --samples [k1,k2]   import the curated sample packages (all, or the given
                      keys: ${SAMPLE_PACKAGES.map((s) => s.key).join(', ')})
  --list-samples      print the curated sample list and exit
  --tenant <id>       tenant id (default: "default")
  --out <file>        also write the JSON report to this file
  -h, --help          show this help

Positional arguments: .h5p files, directories (all *.h5p inside, not
recursive) or http(s) URLs.

Output: {"mapping": {"<source>": "<contentId>"}, "results": [...], "hub": [...]}
`;

interface Args {
    hub: string[];
    samples?: string[];
    listSamples: boolean;
    tenant: string;
    out?: string;
    sources: string[];
}

function parseArgs(argv: string[]): Args {
    const args: Args = { hub: [], listSamples: false, tenant: 'default', sources: [] };
    for (let i = 0; i < argv.length; i += 1) {
        const a = argv[i];
        const next = (): string => {
            const v = argv[i + 1];
            if (v === undefined) {
                throw new Error(`${a} needs a value`);
            }
            i += 1;
            return v;
        };
        if (a === '-h' || a === '--help') {
            console.log(HELP);
            process.exit(0);
        } else if (a === '--hub') {
            args.hub.push(...next().split(',').map((s) => s.trim()).filter(Boolean));
        } else if (a === '--samples') {
            const v = argv[i + 1];
            if (v !== undefined && !v.startsWith('-') && !v.includes('/') && !v.endsWith('.h5p')) {
                args.samples = v.split(',').map((s) => s.trim()).filter(Boolean);
                i += 1;
            } else {
                args.samples = SAMPLE_PACKAGES.map((s) => s.key);
            }
        } else if (a === '--list-samples') {
            args.listSamples = true;
        } else if (a === '--tenant') {
            args.tenant = next();
        } else if (a === '--out') {
            args.out = next();
        } else if (a.startsWith('-')) {
            throw new Error(`Unknown option ${a}`);
        } else {
            args.sources.push(a);
        }
    }
    return args;
}

async function expandSources(sources: string[]): Promise<string[]> {
    const out: string[] = [];
    for (const s of sources) {
        if (/^https?:\/\//i.test(s)) {
            out.push(s);
            continue;
        }
        const st = await stat(s);
        if (st.isDirectory()) {
            const entries = (await readdir(s)).filter((f) => f.toLowerCase().endsWith('.h5p')).sort();
            out.push(...entries.map((f) => path.join(s, f)));
        } else {
            out.push(s);
        }
    }
    return out;
}

async function downloadOnce(url: string, dir: string): Promise<string> {
    const response = await fetch(url, { redirect: 'follow', signal: AbortSignal.timeout(5 * 60 * 1000) });
    if (!response.ok || !response.body) {
        const error = new Error(`Download failed: HTTP ${response.status}`) as Error & { retry?: boolean };
        error.retry = response.status >= 500 || response.status === 429;
        throw error;
    }
    const type = response.headers.get('content-type') ?? '';
    if (type.includes('text/html')) {
        throw new Error(`Download returned HTML (${type}), not a .h5p package`);
    }
    const file = path.join(dir, `seed-${randomUUID()}.h5p`);
    await pipeline(Readable.fromWeb(response.body as any), createWriteStream(file));
    return file;
}

/** Downloads with up to 3 attempts on 5xx / 429 / network errors. */
async function download(url: string, dir: string): Promise<string> {
    let lastError: unknown;
    for (let attempt = 1; attempt <= 3; attempt += 1) {
        try {
            return await downloadOnce(url, dir);
        } catch (error: any) {
            lastError = error;
            const retryable = error?.retry === true || error?.name === 'TypeError' || error?.name === 'TimeoutError';
            if (!retryable || attempt === 3) {
                break;
            }
            logger.warn({ url, attempt, err: error.message }, 'Download failed, retrying');
            await new Promise((r) => setTimeout(r, attempt * 3000));
        }
    }
    throw lastError;
}

async function main(): Promise<void> {
    const args = parseArgs(process.argv.slice(2));
    if (args.listSamples) {
        console.log(JSON.stringify(SAMPLE_PACKAGES, null, 2));
        return;
    }

    const config = loadConfig();
    const runtime = await createRuntime(config, logger);
    const user = systemUser();
    const report: {
        hub: { machineName: string; ok: boolean; installed?: string[]; error?: string }[];
        results: Record<string, unknown>[];
        mapping: Record<string, string>;
    } = { hub: [], results: [], mapping: {} };

    try {
        const tenant = await runtime.tenants.get(args.tenant);
        const editor = tenant.h5p.editor;

        if (args.hub.length > 0) {
            await editor.contentTypeCache.updateIfNecessary();
            for (const machineName of args.hub) {
                try {
                    const installed = await editor.installLibraryFromHub(machineName, user);
                    report.hub.push({
                        machineName,
                        ok: true,
                        installed: installed
                            .filter((l) => l.type !== 'none' && l.newVersion)
                            .map((l) => `${l.newVersion!.machineName}-${l.newVersion!.majorVersion}.${l.newVersion!.minorVersion}.${l.newVersion!.patchVersion}`)
                    });
                    logger.info({ machineName }, 'Installed from H5P Hub');
                } catch (error) {
                    report.hub.push({ machineName, ok: false, error: (error as Error).message });
                    logger.error({ machineName, err: (error as Error).message }, 'Hub install failed');
                }
            }
        }

        const sampleSources = (args.samples ?? []).map((key) => {
            const sample = SAMPLE_PACKAGES.find((s) => s.key === key);
            if (!sample) {
                throw new Error(`Unknown sample "${key}"`);
            }
            return { source: sample.url, sample };
        });
        const explicit = (await expandSources(args.sources)).map((source) => ({ source, sample: undefined }));

        for (const { source, sample } of [...sampleSources, ...explicit]) {
            let localPath = source;
            let downloaded = false;
            try {
                if (/^https?:\/\//i.test(source)) {
                    localPath = await download(source, config.paths.temp);
                    downloaded = true;
                }
                const result = await importPackage(editor, localPath, user);
                report.mapping[sample?.key ?? source] = result.contentId;
                report.results.push({
                    source,
                    key: sample?.key,
                    ok: true,
                    contentId: result.contentId,
                    title: result.metadata.title,
                    mainLibrary: result.metadata.mainLibrary,
                    installedLibraries: result.installedLibraries.length
                });
                logger.info({ source, contentId: result.contentId }, 'Imported package');
            } catch (error) {
                report.results.push({ source, key: sample?.key, ok: false, error: (error as Error).message });
                logger.error({ source, err: (error as Error).message }, 'Import failed');
            } finally {
                if (downloaded) {
                    await rm(localPath, { force: true });
                }
            }
        }
    } finally {
        await runtime.close();
    }

    const json = JSON.stringify(report, null, 2);
    if (args.out) {
        await writeFile(args.out, json);
    }
    console.log(json);
    const failed = report.results.some((r) => !r.ok) || report.hub.some((h) => !h.ok);
    process.exitCode = failed ? 2 : 0;
}

main().catch((err) => {
    console.error(err instanceof Error ? err.message : err);
    process.exit(1);
});
