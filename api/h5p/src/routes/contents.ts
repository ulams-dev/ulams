import { Router, Request } from 'express';
import { Pool } from 'pg';
import * as H5P from '@lumieducation/h5p-server';

import { qi } from '../db/pool';
import { H5PServices } from '../h5p/createH5P';
import { hasPermission, isAuthenticated } from '../auth/users';
import PgContentStorage from '../storage/PgContentStorage';
import { asyncHandler, HttpError, ok, requireAny, requireSystem, userOf } from '../http/respond';
import { importPackage } from '../h5p/importPackage';

export interface ContentListItem {
    id: string;
    title: string;
    mainLibrary: string;
    libraryVersion: string;
    userId: string | null;
    createdAt: string;
    updatedAt: string;
}

interface ContentsRouterOptions {
    pool: Pool;
    schema: string;
    h5p: H5PServices;
}

function language(req: Request): string {
    const q = req.query.language;
    if (typeof q === 'string' && /^[a-zA-Z]{2,3}([-_][a-zA-Z0-9]{2,8})?$/.test(q)) {
        return q;
    }
    return (req as any).language ?? 'en';
}

function queryString(req: Request, name: string): string | undefined {
    const v = req.query[name];
    return typeof v === 'string' && v !== '' ? v : undefined;
}

function truthy(v: string | undefined): boolean {
    return v !== undefined && ['yes', 'true', '1'].includes(v.toLowerCase());
}

function requireValidId(id: string): string {
    if (!PgContentStorage.isValidId(id)) {
        throw new HttpError(404, 'Content not found.');
    }
    return id;
}

function validateSaveBody(body: any): { library: string; params: any; metadata: H5P.IContentMetadata } {
    if (
        !body ||
        typeof body.library !== 'string' ||
        !body.params ||
        body.params.params === undefined ||
        !body.params.metadata ||
        typeof body.params.metadata !== 'object'
    ) {
        throw new HttpError(400, 'Malformed request: expected {library, params: {params, metadata}}.');
    }
    return { library: body.library, params: body.params.params, metadata: body.params.metadata };
}

function slug(text: string): string {
    return (
        text
            .normalize('NFKD')
            .replace(/[̀-ͯ]/g, '')
            .replace(/[^a-zA-Z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .toLowerCase()
            .slice(0, 60) || 'content'
    );
}

/**
 * REST API for content: /h5p/contents/...
 * Responses use the Laravel envelope {success, data, message}.
 */
export function contentsRouter({ pool, schema, h5p }: ContentsRouterOptions): Router {
    const router = Router();
    const table = `${qi(schema)}.contents`;
    const { editor, player, contentStorage, permissionSystem } = h5p;

    // ---- list -------------------------------------------------------------
    router.get(
        '/',
        requireAny('h5p_list', 'h5p_author_list'),
        asyncHandler(async (req, res) => {
            const user = userOf(req);
            const page = Math.max(1, Number.parseInt(queryString(req, 'page') ?? '1', 10) || 1);
            const perPage = Math.min(100, Math.max(1, Number.parseInt(queryString(req, 'perPage') ?? queryString(req, 'per_page') ?? '15', 10) || 15));
            const where: string[] = [];
            const params: unknown[] = [];
            const q = queryString(req, 'q') ?? queryString(req, 'title');
            if (q) {
                params.push(`%${q.replace(/[\\%_]/g, (m) => `\\${m}`)}%`);
                where.push(`title ILIKE $${params.length}`);
            }
            // Authors without h5p_list only see their own content.
            if (!hasPermission(user, 'h5p_list')) {
                params.push(String(user.id));
                where.push(`user_id = $${params.length}`);
            }
            const whereSql = where.length ? `WHERE ${where.join(' AND ')}` : '';
            const total = Number(
                (await pool.query<{ n: string }>(`SELECT count(*) AS n FROM ${table} ${whereSql}`, params)).rows[0].n
            );
            params.push(perPage, (page - 1) * perPage);
            const { rows } = await pool.query(
                `SELECT id::text AS id, title, main_library, library_version, user_id, created_at, updated_at
                   FROM ${table} ${whereSql}
                  ORDER BY id DESC
                  LIMIT $${params.length - 1} OFFSET $${params.length}`,
                params
            );
            const data: ContentListItem[] = rows.map((r) => ({
                id: r.id,
                title: r.title,
                mainLibrary: r.main_library,
                libraryVersion: r.library_version,
                userId: r.user_id,
                createdAt: new Date(r.created_at).toISOString(),
                updatedAt: new Date(r.updated_at).toISOString()
            }));
            res.status(200).json({
                success: true,
                data,
                meta: {
                    current_page: page,
                    per_page: perPage,
                    total,
                    last_page: Math.max(1, Math.ceil(total / perPage))
                },
                message: ''
            });
        })
    );

    // ---- upload .h5p ---------------------------------------------------------
    router.post(
        '/upload',
        requireAny('h5p_create'),
        asyncHandler(async (req, res) => {
            const files = (req as any).files as Record<string, any> | undefined;
            const file = files?.h5p_file;
            if (!file || Array.isArray(file)) {
                throw new HttpError(422, 'Upload exactly one .h5p file in the multipart field "h5p_file".');
            }
            if (!/\.h5p$/i.test(file.name ?? '')) {
                throw new HttpError(422, 'The uploaded file must have the .h5p extension.');
            }
            if (file.truncated) {
                throw new HttpError(413, 'The uploaded file is too large.');
            }
            const source: Buffer | string = file.tempFilePath ? file.tempFilePath : file.data;
            const result = await importPackage(editor, source, userOf(req));
            ok(
                res,
                result,
                'Content uploaded',
                201
            );
        })
    );

    // ---- maintenance (internal) ------------------------------------------------
    // Deletes the files of contents that have no row any more. The router is
    // bound to the request's tenant (database schema, bucket and prefix), so a
    // tenant can only sweep its own storage. Internal token only.
    router.post(
        '/orphans/delete',
        requireSystem(),
        asyncHandler(async (_req, res) => {
            const result = await contentStorage.deleteOrphanedFiles();
            ok(res, result, 'Orphaned content files deleted');
        })
    );

    // ---- create / update / delete --------------------------------------------
    router.post(
        '/',
        requireAny('h5p_create'),
        asyncHandler(async (req, res) => {
            const { library, params, metadata } = validateSaveBody(req.body);
            const result = await editor.saveOrUpdateContentReturnMetaData(
                undefined as unknown as string,
                params,
                metadata,
                library,
                userOf(req)
            );
            ok(res, { contentId: result.id, metadata: result.metadata }, 'Content created', 201);
        })
    );

    router.patch(
        '/:id',
        requireAny('h5p_update', 'h5p_author_update'),
        asyncHandler(async (req, res) => {
            const id = requireValidId(req.params.id as string);
            const { library, params, metadata } = validateSaveBody(req.body);
            if (!(await contentStorage.contentExists(id))) {
                throw new HttpError(404, 'Content not found.');
            }
            const result = await editor.saveOrUpdateContentReturnMetaData(id, params, metadata, library, userOf(req));
            ok(res, { contentId: result.id, metadata: result.metadata }, 'Content updated');
        })
    );

    router.delete(
        '/:id',
        requireAny('h5p_delete', 'h5p_author_delete'),
        asyncHandler(async (req, res) => {
            const id = requireValidId(req.params.id as string);
            if (!(await contentStorage.contentExists(id))) {
                throw new HttpError(404, 'Content not found.');
            }
            await editor.deleteContent(id, userOf(req));
            ok(res, { contentId: id }, 'Content deleted');
        })
    );

    // ---- single content -------------------------------------------------------
    router.get(
        '/:id',
        asyncHandler(async (req, res) => {
            const id = requireValidId(req.params.id as string);
            const row = await contentStorage.findRow(id);
            if (!row) {
                throw new HttpError(404, 'Content not found.');
            }
            ok(res, {
                id: row.id,
                title: row.title,
                mainLibrary: row.main_library,
                libraryVersion: row.library_version,
                userId: row.user_id,
                createdAt: new Date(row.created_at).toISOString(),
                updatedAt: new Date(row.updated_at).toISOString(),
                metadata: row.metadata
            });
        })
    );

    router.get(
        '/:id/play',
        asyncHandler(async (req, res) => {
            const id = requireValidId(req.params.id as string);
            const user = userOf(req);
            const asUserId = queryString(req, 'asUserId');
            if (asUserId && asUserId !== user.id && !hasPermission(user, 'h5p_read')) {
                throw new HttpError(403, 'Viewing another user\'s state requires h5p_read.');
            }
            const anonymous = !isAuthenticated(user);
            const model = (await player.render(id, user, language(req), {
                contextId: queryString(req, 'contextId'),
                asUserId,
                // Anonymous learners cannot store state: do not even try.
                readOnlyState: anonymous || truthy(queryString(req, 'readOnlyState'))
            })) as H5P.IPlayerModel;
            if (anonymous && model?.integration) {
                model.integration.postUserStatistics = false;
            }
            // The model embeds the caller's token in AJAX URLs: never cache it.
            // Clients re-fetch it after refreshing their token (cheap: one DB
            // read + cached library metadata).
            res.setHeader('Cache-Control', 'no-store, private');
            ok(res, model);
        })
    );

    router.get(
        '/:id/edit',
        asyncHandler(async (req, res) => {
            const raw = req.params.id as string;
            const user = userOf(req);
            if (!isAuthenticated(user)) {
                throw new HttpError(401, 'Unauthenticated.');
            }
            const isNew = raw === 'new' || raw === 'undefined';
            const id = isNew ? undefined : requireValidId(raw);
            if (id && !(await contentStorage.contentExists(id))) {
                throw new HttpError(404, 'Content not found.');
            }
            const allowed = id
                ? await permissionSystem.checkForContent(user, H5P.ContentPermission.Edit, id)
                : await permissionSystem.checkForContent(user, H5P.ContentPermission.Create, undefined);
            if (!allowed) {
                throw new HttpError(403, 'Forbidden.');
            }
            const editorModel = (await editor.render(id as string, language(req), user)) as H5P.IEditorModel;
            if (!id) {
                ok(res, editorModel);
                return;
            }
            const content = await editor.getContent(id, user);
            ok(res, {
                ...editorModel,
                library: content.library,
                metadata: content.params.metadata,
                params: content.params.params
            });
        })
    );

    router.get(
        '/:id/download',
        asyncHandler(async (req, res) => {
            const id = requireValidId(req.params.id as string);
            const user = userOf(req);
            const row = await contentStorage.findRow(id);
            if (!row) {
                throw new HttpError(404, 'Content not found.');
            }
            if (!(await permissionSystem.checkForContent(user, H5P.ContentPermission.Download, id))) {
                throw new HttpError(403, 'Forbidden.');
            }
            res.status(200);
            res.setHeader('Content-Type', 'application/zip');
            res.setHeader('Content-Disposition', `attachment; filename="${slug(row.title)}-${id}.h5p"`);
            await editor.exportContent(id, res, user);
        })
    );

    return router;
}
