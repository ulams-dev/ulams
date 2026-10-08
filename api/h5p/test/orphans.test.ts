import { describe, expect, it } from 'vitest';
import express from 'express';
import request from 'supertest';
import type { Pool } from 'pg';
import type { S3 } from '@aws-sdk/client-s3';

import PgContentStorage from '../src/storage/PgContentStorage';
import { contentsRouter } from '../src/routes/contents';
import { ALL_H5P_PERMISSIONS, anonymousUser, H5PUser, systemUser } from '../src/auth/users';

/**
 * In-memory stand-ins for one tenant's Postgres table and S3 bucket, enough for
 * PgContentStorage.deleteOrphanedFiles().
 */
function fakeTenant(bucket: string, prefix: string, rowIds: string[], keys: string[]) {
    const objects = new Map<string, Set<string>>([[bucket, new Set(keys)]]);
    const queries: unknown[][] = [];
    const pool = {
        query: async (_sql: string, params: unknown[]) => {
            queries.push(params);
            const wanted = (params[0] as string[]).map(String);
            return { rows: rowIds.filter((id) => wanted.includes(id)).map((id) => ({ id })), rowCount: 0 };
        }
    } as unknown as Pool;
    const s3 = {
        listObjectsV2: async (input: { Bucket: string; Prefix: string; Delimiter?: string }) => {
            const all = [...(objects.get(input.Bucket) ?? [])].filter((k) => k.startsWith(input.Prefix));
            if (input.Delimiter) {
                const prefixes = new Set(
                    all.map((k) => input.Prefix + k.substring(input.Prefix.length).split('/')[0] + '/')
                );
                return { CommonPrefixes: [...prefixes].map((Prefix) => ({ Prefix })), IsTruncated: false };
            }
            return { Contents: all.map((Key) => ({ Key })), IsTruncated: false };
        },
        send: async (command: { input: { Bucket: string; Delete: { Objects: { Key: string }[] } } }) => {
            for (const { Key } of command.input.Delete.Objects) {
                objects.get(command.input.Bucket)?.delete(Key);
            }
            return {};
        }
    } as unknown as S3;
    const storage = new PgContentStorage(pool, s3, { schema: 'h5p', s3Bucket: bucket, s3Prefix: prefix });
    return { storage, objects, queries, keys: () => [...(objects.get(bucket) ?? [])].sort() };
}

describe('PgContentStorage.deleteOrphanedFiles', () => {
    it('deletes the files of contents without a row and keeps the others', async () => {
        const t = fakeTenant('ulams-coffee', 'h5p', ['1'], [
            'h5p/content/1/content.json',
            'h5p/content/1/images/a.png',
            'h5p/content/7/content.json',
            'h5p/content/7/images/b.png',
            'h5p/content/tmp/x',
            'h5p/temporary-storage/abc'
        ]);

        expect(await t.storage.deleteOrphanedFiles()).toEqual({ contentIds: ['7'], files: 2 });
        expect(t.keys()).toEqual([
            'h5p/content/1/content.json',
            'h5p/content/1/images/a.png',
            'h5p/content/tmp/x',
            'h5p/temporary-storage/abc'
        ]);
    });

    it('only looks at its own bucket and prefix (tenant)', async () => {
        const coffee = fakeTenant('ulams-coffee', 'h5p', [], ['h5p/content/3/content.json']);
        // a second tenant sharing nothing: its orphan must survive a sweep of the first
        coffee.objects.set('ulams-oncall', new Set(['h5p/content/3/content.json']));

        expect(await coffee.storage.deleteOrphanedFiles()).toEqual({ contentIds: ['3'], files: 1 });
        expect([...(coffee.objects.get('ulams-oncall') ?? [])]).toEqual(['h5p/content/3/content.json']);
    });

    it('does nothing without stored files', async () => {
        const t = fakeTenant('ulams-coffee', 'h5p', ['1'], []);
        expect(await t.storage.deleteOrphanedFiles()).toEqual({ contentIds: [], files: 0 });
        expect(t.queries).toEqual([]);
    });
});

describe('POST /contents/orphans/delete', () => {
    function app(user: H5PUser) {
        let swept = 0;
        const contentStorage = {
            deleteOrphanedFiles: async () => {
                swept++;
                return { contentIds: ['7'], files: 2 };
            }
        };
        const server = express();
        server.use((req, _res, next) => {
            (req as unknown as { user: H5PUser }).user = user;
            next();
        });
        server.use(
            '/h5p/contents',
            contentsRouter({ pool: {} as Pool, schema: 'h5p', h5p: { contentStorage } as never })
        );
        return { server, swept: () => swept };
    }

    it('runs for the system user (internal token)', async () => {
        const a = app(systemUser());
        const res = await request(a.server).post('/h5p/contents/orphans/delete');
        expect(res.status).toBe(200);
        expect(res.body).toMatchObject({ success: true, data: { contentIds: ['7'], files: 2 } });
        expect(a.swept()).toBe(1);
    });

    it('is refused to anonymous callers and to LMS users, even with every H5P permission', async () => {
        const admin: H5PUser = {
            id: '1',
            name: 'Admin',
            email: '',
            type: 'local',
            roles: ['admin'],
            permissions: [...ALL_H5P_PERMISSIONS],
            isAnonymous: false,
            isSystem: false
        };
        const anon = app(anonymousUser());
        const lms = app(admin);

        expect((await request(anon.server).post('/h5p/contents/orphans/delete')).status).toBe(401);
        expect((await request(lms.server).post('/h5p/contents/orphans/delete')).status).toBe(403);
        expect(anon.swept() + lms.swept()).toBe(0);
    });
});
