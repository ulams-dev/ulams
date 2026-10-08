import { Readable } from 'node:stream';
import { afterAll, beforeAll, describe, expect, it } from 'vitest';
import { Pool } from 'pg';
import { S3 } from '@aws-sdk/client-s3';
import { H5pError, IContentMetadata, IUser } from '@lumieducation/h5p-server';

import { AppConfig } from '../src/config';
import { createPool, qi } from '../src/db/pool';
import { migrate, migrations } from '../src/db/migrate';
import { createS3Client } from '../src/storage/s3';
import PgContentStorage from '../src/storage/PgContentStorage';
import PgContentUserDataStorage from '../src/storage/PgContentUserDataStorage';
import S3TemporaryFileStorage from '../src/storage/S3TemporaryFileStorage';
import { deletePrefix, dropSchema, testConfig } from './helpers';

const author: IUser = { id: '10', name: 'Author', email: 'a@x', type: 'local' };
const admin: IUser = { id: '1', name: 'Admin', email: 'b@x', type: 'local' };

function metadata(overrides: Partial<IContentMetadata> = {}): IContentMetadata {
    return {
        title: 'Quiz about Poland',
        mainLibrary: 'H5P.MultiChoice',
        language: 'en',
        embedTypes: ['iframe'],
        license: 'U',
        defaultLanguage: 'en',
        preloadedDependencies: [
            { machineName: 'H5P.MultiChoice', majorVersion: 1, minorVersion: 16 },
            { machineName: 'H5P.Question', majorVersion: 1, minorVersion: 5 }
        ],
        ...overrides
    } as IContentMetadata;
}

const streamOf = (text: string): Readable => Readable.from([Buffer.from(text)]);
async function readAll(stream: Readable): Promise<string> {
    const chunks: Buffer[] = [];
    for await (const c of stream) {
        chunks.push(Buffer.from(c));
    }
    return Buffer.concat(chunks).toString('utf8');
}

async function expectH5pError(promise: Promise<unknown>, status: number): Promise<void> {
    await expect(promise).rejects.toBeInstanceOf(H5pError);
    await promise.catch((e: H5pError) => expect(e.httpStatusCode).toBe(status));
}

describe('Postgres + S3 storage (integration)', () => {
    let cfg: AppConfig;
    let pool: Pool;
    let s3: S3;
    let content: PgContentStorage;
    let userData: PgContentUserDataStorage;
    let temp: S3TemporaryFileStorage;

    beforeAll(async () => {
        cfg = testConfig();
        pool = createPool(cfg.db);
        s3 = createS3Client(cfg.s3);
        await migrate(pool, cfg.db.schema);
        content = new PgContentStorage(pool, s3, {
            schema: cfg.db.schema,
            s3Bucket: cfg.s3.bucket,
            s3Prefix: cfg.s3.prefix
        });
        userData = new PgContentUserDataStorage(pool, cfg.db.schema);
        temp = new S3TemporaryFileStorage(s3, {
            s3Bucket: cfg.s3.bucket,
            s3Prefix: cfg.s3.prefix,
            temporaryFileLifetimeMs: 60_000
        });
    });

    afterAll(async () => {
        await deletePrefix(s3, cfg.s3.bucket, cfg.s3.prefix).catch(() => undefined);
        await dropSchema(pool, cfg.db.schema);
        await pool.end();
        s3.destroy();
    });

    describe('migrations', () => {
        it('are idempotent and recorded in schema_migrations', async () => {
            expect(await migrate(pool, cfg.db.schema)).toEqual([]);
            const { rows } = await pool.query(`SELECT version FROM ${qi(cfg.db.schema)}.schema_migrations ORDER BY version`);
            expect(rows.map((r) => r.version)).toEqual(migrations.map((m) => m.version));
        });

        it('serialise concurrent runs (advisory lock)', async () => {
            const other = testConfig();
            const results = await Promise.all([migrate(pool, other.db.schema), migrate(pool, other.db.schema)]);
            expect(results.flat().sort()).toEqual(migrations.map((m) => m.version));
            await dropSchema(pool, other.db.schema);
        });
    });

    describe('PgContentStorage', () => {
        it('adds content and returns a numeric string id', async () => {
            const id = await content.addContent(metadata(), { question: 'Q?' }, author);
            expect(id).toMatch(/^\d+$/);
            expect(await content.contentExists(id)).toBe(true);
            expect(await content.getMetadata(id)).toMatchObject({ title: 'Quiz about Poland', mainLibrary: 'H5P.MultiChoice' });
            expect(await content.getParameters(id)).toEqual({ question: 'Q?' });
            const row = await content.findRow(id);
            expect(row).toMatchObject({
                id,
                user_id: '10',
                title: 'Quiz about Poland',
                main_library: 'H5P.MultiChoice',
                library_version: '1.16'
            });
            expect(await content.getOwner(id)).toBe('10');
        });

        it('updates in place and keeps the original owner', async () => {
            const id = await content.addContent(metadata(), { a: 1 }, author);
            const before = await content.findRow(id);
            const same = await content.addContent(metadata({ title: 'Renamed' }), { a: 2 }, admin, id);
            expect(same).toBe(id);
            const after = await content.findRow(id);
            expect(after).toMatchObject({ title: 'Renamed', user_id: '10', parameters: { a: 2 } });
            expect(after!.updated_at.getTime()).toBeGreaterThanOrEqual(before!.updated_at.getTime());
        });

        it('upserts with an explicit id and moves the sequence past it', async () => {
            const explicit = '900000';
            expect(await content.addContent(metadata(), {}, author, explicit)).toBe(explicit);
            const next = await content.addContent(metadata(), {}, author);
            expect(BigInt(next)).toBeGreaterThan(BigInt(explicit));
        });

        it('stores anonymous content without an owner', async () => {
            const id = await content.addContent(metadata(), {}, { id: 'anonymous', name: '', email: '', type: 'local' });
            expect(await content.getOwner(id)).toBeUndefined();
        });

        it('returns 404 errors for unknown or malformed ids', async () => {
            await expectH5pError(content.getMetadata('987654321'), 404);
            await expectH5pError(content.getParameters('abc'), 404);
            await expectH5pError(content.deleteContent('987654321'), 404);
            expect(await content.contentExists('abc')).toBe(false);
            expect(await content.contentExists('0')).toBe(false);
            expect(await content.findRow('1; DROP TABLE x')).toBeUndefined();
        });

        it('stores files in S3 under <prefix>/content/<id>/ and lists, stats and streams them', async () => {
            const id = await content.addContent(metadata(), {}, author);
            await content.addFile(id, 'images/a.png', streamOf('0123456789'), author);
            await content.addFile(id, 'b.txt', streamOf('hello'), author);
            expect(content.getS3Key(id, 'images/a.png')).toBe(`${cfg.s3.prefix}/content/${id}/images/a.png`);
            expect((await content.listFiles(id, author)).sort()).toEqual(['b.txt', 'images/a.png']);
            expect(await content.fileExists(id, 'images/a.png')).toBe(true);
            expect(await content.fileExists(id, 'missing.png')).toBe(false);
            const stats = await content.getFileStats(id, 'images/a.png', author);
            expect(stats.size).toBe(10);
            expect(stats.birthtime).toBeInstanceOf(Date);
            expect(await readAll(await content.getFileStream(id, 'b.txt', author))).toBe('hello');
            // ranges are inclusive, as in HTTP
            expect(await readAll(await content.getFileStream(id, 'images/a.png', author, 2, 5))).toBe('2345');
            await content.deleteFile(id, 'b.txt');
            expect(await content.listFiles(id, author)).toEqual(['images/a.png']);
        });

        it('maps missing files to 404', async () => {
            const id = await content.addContent(metadata(), {}, author);
            await expectH5pError(content.getFileStats(id, 'nope.png', author), 404);
            await expectH5pError(content.getFileStream(id, 'nope.png', author), 404);
            expect(await content.listFiles(id, author)).toEqual([]);
        });

        it('rejects path traversal and illegal characters', async () => {
            const id = await content.addContent(metadata(), {}, author);
            await expectH5pError(content.addFile(id, '../evil.png', streamOf('x'), author), 400);
            await expectH5pError(content.addFile(id, '/abs.png', streamOf('x'), author), 400);
            await expectH5pError(content.getFileStream(id, 'a b.png', author), 400);
            expect(content.sanitizeFilename('images/ä b?.png')).toMatch(/^images\/[A-Za-z0-9\-._!()@/]+$/);
        });

        it('deleteContent removes S3 files, the row and (cascade) the user data', async () => {
            const id = await content.addContent(metadata(), {}, author);
            await content.addFile(id, 'x.txt', streamOf('x'), author);
            await userData.createOrUpdateContentUserData({
                contentId: id,
                userId: '30',
                dataType: 'state',
                subContentId: '0',
                userState: '{}',
                preload: true,
                invalidate: false
            });
            await userData.createOrUpdateFinishedData({
                contentId: id,
                userId: '30',
                score: 1,
                maxScore: 2,
                openedTimestamp: 1,
                finishedTimestamp: 2,
                completionTime: 1
            });
            await content.deleteContent(id, admin);
            expect(await content.contentExists(id)).toBe(false);
            expect(await content.fileExists(id, 'x.txt')).toBe(false);
            expect(await userData.getContentUserDataByContentIdAndUser(id, '30')).toEqual([]);
            expect(await userData.getFinishedDataByContentId(id)).toEqual([]);
        });

        it('counts library usage (numeric and string versions, all dependency kinds)', async () => {
            const lib = { machineName: 'H5P.UsageProbe', majorVersion: 2, minorVersion: 3 };
            // main library, numeric versions
            await content.addContent(
                metadata({ mainLibrary: 'H5P.UsageProbe', preloadedDependencies: [lib] as any }),
                {},
                author
            );
            // main library, string versions (H5P Hub packages look like this)
            await content.addContent(
                metadata({
                    mainLibrary: 'H5P.UsageProbe',
                    preloadedDependencies: [{ machineName: 'H5P.UsageProbe', majorVersion: '2', minorVersion: '3' }] as any
                }),
                {},
                author
            );
            // used as dependency in preloaded / dynamic / editor dependencies
            await content.addContent(
                metadata({ preloadedDependencies: [{ machineName: 'H5P.MultiChoice', majorVersion: 1, minorVersion: 16 }, lib] as any }),
                {},
                author
            );
            await content.addContent(metadata({ dynamicDependencies: [lib] as any }), {}, author);
            await content.addContent(metadata({ editorDependencies: [lib] as any }), {}, author);
            // other version: not counted
            await content.addContent(
                metadata({ preloadedDependencies: [{ ...lib, minorVersion: 4 }] as any }),
                {},
                author
            );
            expect(await content.getUsage(lib)).toEqual({ asMainLibrary: 2, asDependency: 3 });
            expect(await content.getUsage({ machineName: 'H5P.Nothing', majorVersion: 1, minorVersion: 0 })).toEqual({
                asMainLibrary: 0,
                asDependency: 0
            });
        });

        it('lists all content ids', async () => {
            const id = await content.addContent(metadata(), {}, author);
            const ids = await content.listContent();
            expect(ids).toContain(id);
            expect(ids.every((x) => /^\d+$/.test(x))).toBe(true);
        });
    });

    describe('PgContentUserDataStorage', () => {
        let contentId: string;
        beforeAll(async () => {
            contentId = await content.addContent(metadata(), {}, author);
        });

        const state = (overrides: Record<string, unknown> = {}) => ({
            contentId,
            userId: '30',
            dataType: 'state',
            subContentId: '0',
            userState: '{"answers":[1]}',
            preload: true,
            invalidate: true,
            ...overrides
        });

        it('upserts and reads states, with and without contextId', async () => {
            await userData.createOrUpdateContentUserData(state());
            await userData.createOrUpdateContentUserData(state({ userState: '{"answers":[2]}' }));
            await userData.createOrUpdateContentUserData(state({ contextId: 'course-1', userState: 'ctx' }));
            expect(await userData.getContentUserData(contentId, 'state', '0', '30')).toEqual({
                contentId,
                userId: '30',
                dataType: 'state',
                subContentId: '0',
                contextId: undefined,
                userState: '{"answers":[2]}',
                preload: true,
                invalidate: true
            });
            expect((await userData.getContentUserData(contentId, 'state', '0', '30', 'course-1')).userState).toBe('ctx');
            expect(await userData.getContentUserData(contentId, 'state', '0', '31')).toBeUndefined();
            expect(await userData.getContentUserDataByContentIdAndUser(contentId, '30')).toHaveLength(1);
            expect(await userData.getContentUserDataByContentIdAndUser(contentId, '30', 'course-1')).toHaveLength(1);
            expect((await userData.getContentUserDataByUser({ ...author, id: '30' })).length).toBe(2);
        });

        it('deletes invalidated states, by user and by content', async () => {
            await userData.createOrUpdateContentUserData(state({ userId: '40', invalidate: false }));
            await userData.deleteInvalidatedContentUserData(contentId);
            expect(await userData.getContentUserDataByContentIdAndUser(contentId, '30')).toEqual([]);
            expect(await userData.getContentUserDataByContentIdAndUser(contentId, '40')).toHaveLength(1);
            await userData.createOrUpdateContentUserData(state({ userId: '50' }));
            await userData.deleteAllContentUserDataByUser({ ...author, id: '50' });
            expect(await userData.getContentUserDataByContentIdAndUser(contentId, '50')).toEqual([]);
            await userData.deleteAllContentUserDataByContentId(contentId);
            expect(await userData.getContentUserDataByContentIdAndUser(contentId, '40')).toEqual([]);
        });

        it('upserts and reads finished data', async () => {
            const finished = {
                contentId,
                userId: '30',
                score: 3,
                maxScore: 5,
                openedTimestamp: 1700000000,
                finishedTimestamp: 1700000100,
                completionTime: 100
            };
            await userData.createOrUpdateFinishedData(finished);
            await userData.createOrUpdateFinishedData({ ...finished, score: 5 });
            await userData.createOrUpdateFinishedData({ ...finished, userId: '31', score: 1 });
            const byContent = await userData.getFinishedDataByContentId(contentId);
            expect(byContent).toHaveLength(2);
            expect(byContent[0]).toEqual({ ...finished, score: 5 });
            expect(await userData.getFinishedDataByUser({ ...author, id: '31' })).toHaveLength(1);
            await userData.deleteFinishedDataByUser({ ...author, id: '31' });
            expect(await userData.getFinishedDataByContentId(contentId)).toHaveLength(1);
            await userData.deleteFinishedDataByContentId(contentId);
            expect(await userData.getFinishedDataByContentId(contentId)).toEqual([]);
        });

        it('writes for unknown content fail with 404', async () => {
            await expectH5pError(userData.createOrUpdateContentUserData(state({ contentId: '987654321' })), 404);
            await expectH5pError(userData.createOrUpdateContentUserData(state({ contentId: 'x' })), 404);
            await expectH5pError(
                userData.createOrUpdateFinishedData({
                    contentId: '987654321',
                    userId: '1',
                    score: 0,
                    maxScore: 0,
                    openedTimestamp: 0,
                    finishedTimestamp: 0,
                    completionTime: 0
                }),
                404
            );
        });
    });

    describe('S3TemporaryFileStorage', () => {
        it('saves under <prefix>/temp/, reads, lists and deletes files', async () => {
            const expires = new Date(Date.now() + 60_000);
            const saved = await temp.saveFile('upload-1.png', streamOf('tmpdata') as any, author, expires);
            expect(saved).toEqual({ filename: 'upload-1.png', ownedByUserId: '10', expiresAt: expires });
            expect(await temp.fileExists('upload-1.png', author)).toBe(true);
            expect((await temp.getFileStats('upload-1.png', author)).size).toBe(7);
            expect(await readAll(await temp.getFileStream('upload-1.png', author))).toBe('tmpdata');
            expect(await readAll(await temp.getFileStream('upload-1.png', author, 0, 2))).toBe('tmp');
            const listed = await temp.listFiles();
            expect(listed.map((f) => f.filename)).toContain('upload-1.png');
            expect(await temp.listFiles(author)).toEqual([]);
            await temp.deleteFile('upload-1.png', author.id);
            expect(await temp.fileExists('upload-1.png', author)).toBe(false);
            await expectH5pError(temp.getFileStream('upload-1.png', author), 404);
        });

        it('deleteExpired removes only expired files', async () => {
            await temp.saveFile('old.png', streamOf('o') as any, author, new Date());
            expect(await temp.deleteExpired(Date.now())).toBe(0); // lifetime is 60 s
            expect(await temp.deleteExpired(Date.now() + 120_000)).toBeGreaterThanOrEqual(1);
            expect(await temp.fileExists('old.png', author)).toBe(false);
        });
    });
});
