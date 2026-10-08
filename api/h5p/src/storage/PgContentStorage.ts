import { Readable, Stream } from 'node:stream';
import { Pool } from 'pg';
import { Upload } from '@aws-sdk/lib-storage';
import { ObjectCannedACL, S3 } from '@aws-sdk/client-s3';
import {
    ContentId,
    H5pError,
    IContentMetadata,
    IContentStorage,
    IFileStats,
    ILibraryName,
    IUser,
    Logger
} from '@lumieducation/h5p-server';

import { qi } from '../db/pool';
import { deleteObjects, isNotFound, sanitizeFilename, validateFilename } from './s3';

const log = new Logger('PgContentStorage');

/** Row shape returned by {@link PgContentStorage.findRow}. */
export interface ContentRow {
    id: string;
    user_id: string | null;
    title: string;
    main_library: string;
    library_version: string;
    metadata: IContentMetadata;
    parameters: any;
    created_at: Date;
    updated_at: Date;
}

export interface PgContentStorageOptions {
    /** Postgres schema holding the `contents` table. */
    schema: string;
    s3Bucket: string;
    /** Key prefix, e.g. "h5p" -> objects at "h5p/content/{id}/{file}". */
    s3Prefix: string;
    s3Acl?: ObjectCannedACL;
    /** Maximum S3 key length (S3: 1024; some MinIO setups: 255). */
    maxKeyLength?: number;
    invalidCharactersRegexp?: RegExp;
}

/**
 * Returns the "major.minor" version of the main library of a content object,
 * looked up in its preloaded dependencies.
 */
export function mainLibraryVersion(metadata: IContentMetadata): string {
    const main = metadata?.preloadedDependencies?.find((d) => d.machineName === metadata.mainLibrary);
    return main ? `${main.majorVersion}.${main.minorVersion}` : '';
}

/**
 * Content storage that keeps metadata and parameters in Postgres
 * (`<schema>.contents`) and all content files in S3 under
 * `${prefix}/content/{contentId}/{filename}`.
 *
 * Port of @lumieducation/h5p-mongos3 MongoS3ContentStorage (GPL-3.0-or-later)
 * with MongoDB replaced by Postgres. Content ids are bigserial values exposed
 * as decimal strings.
 */
export default class PgContentStorage implements IContentStorage {
    private readonly table: string;
    private readonly keyPrefix: string;
    private readonly maxKeyLength: number;

    constructor(
        private readonly pool: Pool,
        private readonly s3: S3,
        private readonly options: PgContentStorageOptions
    ) {
        this.table = `${qi(options.schema)}.contents`;
        this.keyPrefix = options.s3Prefix ? `${options.s3Prefix}/content/` : 'content/';
        // Leave room for the prefix, the content id (up to 19 digits), the
        // unique suffix Lumi appends to duplicate filenames (8) and separators.
        this.maxKeyLength = (options.maxKeyLength ?? 1024) - this.keyPrefix.length - 19 - 8 - 2;
    }

    /** Valid content ids are positive bigint values in decimal notation. */
    public static isValidId(contentId: ContentId | undefined | null): contentId is string {
        return typeof contentId === 'string' && /^[1-9][0-9]{0,18}$/.test(contentId);
    }

    private requireId(contentId: ContentId): string {
        if (!PgContentStorage.isValidId(contentId)) {
            throw new H5pError('mongo-s3-content-storage:content-not-found', {}, 404);
        }
        return contentId;
    }

    /** S3 key of a content file. */
    public getS3Key(contentId: ContentId, filename: string): string {
        const key = `${this.keyPrefix}${contentId}/${filename}`;
        if (key.length > (this.options.maxKeyLength ?? 1024)) {
            log.error(`The S3 key for "${filename}" in content ${contentId} is ${key.length} characters long.`);
            throw new H5pError('mongo-s3-content-storage:filename-too-long', { filename }, 400);
        }
        return key;
    }

    public async addContent(
        metadata: IContentMetadata,
        content: any,
        user: IUser,
        contentId?: ContentId
    ): Promise<ContentId> {
        const title = metadata?.title ?? '';
        const mainLibrary = metadata?.mainLibrary ?? '';
        const version = mainLibraryVersion(metadata);
        const userId = user?.id && user.id !== 'anonymous' ? String(user.id) : null;
        try {
            if (!contentId) {
                const { rows } = await this.pool.query<{ id: string }>(
                    `INSERT INTO ${this.table} (user_id, title, main_library, library_version, metadata, parameters)
                     VALUES ($1, $2, $3, $4, $5::jsonb, $6::jsonb)
                     RETURNING id::text AS id`,
                    [userId, title, mainLibrary, version, JSON.stringify(metadata), JSON.stringify(content ?? {})]
                );
                return rows[0].id;
            }
            const id = this.requireId(contentId);
            // Update in place; ownership (user_id) stays with the creator so an
            // admin editing an author's content does not take it over.
            const updated = await this.pool.query(
                `UPDATE ${this.table}
                    SET title = $2, main_library = $3, library_version = $4,
                        metadata = $5::jsonb, parameters = $6::jsonb, updated_at = now()
                  WHERE id = $1::bigint`,
                [id, title, mainLibrary, version, JSON.stringify(metadata), JSON.stringify(content ?? {})]
            );
            if (updated.rowCount === 1) {
                return id;
            }
            // Upsert semantics (as in the Mongo implementation): create the row
            // with the requested id and move the sequence past it.
            await this.pool.query(
                `INSERT INTO ${this.table} (id, user_id, title, main_library, library_version, metadata, parameters)
                 VALUES ($1::bigint, $2, $3, $4, $5, $6::jsonb, $7::jsonb)`,
                [id, userId, title, mainLibrary, version, JSON.stringify(metadata), JSON.stringify(content ?? {})]
            );
            await this.pool.query(
                `SELECT setval(pg_get_serial_sequence('${this.table}', 'id'), GREATEST((SELECT max(id) FROM ${this.table}), 1))`
            );
            return id;
        } catch (error) {
            if (error instanceof H5pError) {
                throw error;
            }
            log.error(`Error when adding or updating content in Postgres: ${(error as Error).message}`);
            throw new H5pError('mongo-s3-content-storage:mongo-add-update-error', {}, 500);
        }
    }

    public async addFile(contentId: ContentId, filename: string, stream: Stream, user?: IUser): Promise<void> {
        validateFilename(filename, this.options.invalidCharactersRegexp);
        const id = this.requireId(contentId);
        try {
            await new Upload({
                client: this.s3,
                params: {
                    ACL: this.options.s3Acl ?? 'private',
                    Body: stream as Readable,
                    Bucket: this.options.s3Bucket,
                    Key: this.getS3Key(id, filename),
                    Metadata: user?.id ? { owner: String(user.id) } : undefined
                }
            }).done();
        } catch (error) {
            if (error instanceof H5pError) {
                throw error;
            }
            log.error(`Error while uploading file "${filename}" to S3: ${(error as Error).message}`);
            throw new H5pError('mongo-s3-content-storage:s3-upload-error', { filename }, 500);
        }
    }

    public async contentExists(contentId: ContentId): Promise<boolean> {
        if (!PgContentStorage.isValidId(contentId)) {
            return false;
        }
        const { rowCount } = await this.pool.query(`SELECT 1 FROM ${this.table} WHERE id = $1::bigint`, [contentId]);
        return rowCount === 1;
    }

    public async deleteContent(contentId: ContentId, user?: IUser): Promise<void> {
        const id = this.requireId(contentId);
        if (!(await this.contentExists(id))) {
            throw new H5pError('mongo-s3-content-storage:content-not-found', {}, 404);
        }
        try {
            const files = await this.listFiles(id, user as IUser);
            await deleteObjects(
                files.map((f) => this.getS3Key(id, f)),
                this.options.s3Bucket,
                this.s3
            );
            // content_user_data and finished_data rows go with it (ON DELETE CASCADE)
            await this.pool.query(`DELETE FROM ${this.table} WHERE id = $1::bigint`, [id]);
        } catch (error) {
            log.error(`Error while deleting content ${id}: ${(error as Error).message}`);
            throw new H5pError('mongo-s3-content-storage:deleting-content-error', {}, 500);
        }
    }

    public async deleteFile(contentId: ContentId, filename: string, _user?: IUser): Promise<void> {
        validateFilename(filename, this.options.invalidCharactersRegexp);
        const id = this.requireId(contentId);
        try {
            await this.s3.deleteObject({ Bucket: this.options.s3Bucket, Key: this.getS3Key(id, filename) });
        } catch (error) {
            log.error(`Error while deleting a file from S3: ${(error as Error).message}`);
            throw new H5pError('mongo-s3-content-storage:deleting-file-error', { filename }, 500);
        }
    }

    public async fileExists(contentId: ContentId, filename: string): Promise<boolean> {
        validateFilename(filename, this.options.invalidCharactersRegexp);
        if (!contentId) {
            throw new H5pError('mongo-s3-content-storage:content-not-found', {}, 404);
        }
        if (!PgContentStorage.isValidId(contentId)) {
            return false;
        }
        try {
            await this.s3.headObject({ Bucket: this.options.s3Bucket, Key: this.getS3Key(contentId, filename) });
            return true;
        } catch {
            return false;
        }
    }

    public async getFileStats(contentId: ContentId, filename: string, _user: IUser): Promise<IFileStats> {
        validateFilename(filename, this.options.invalidCharactersRegexp);
        const id = this.requireId(contentId);
        try {
            const head = await this.s3.headObject({
                Bucket: this.options.s3Bucket,
                Key: this.getS3Key(id, filename)
            });
            return { size: head.ContentLength ?? 0, birthtime: head.LastModified ?? new Date(0) };
        } catch {
            throw new H5pError('content-file-missing', { filename, contentId: id }, 404);
        }
    }

    /**
     * Returns a stream of a content file. Ranges are inclusive on both ends,
     * as in HTTP Range headers (that is what Lumi passes in).
     */
    public async getFileStream(
        contentId: ContentId,
        filename: string,
        _user: IUser,
        rangeStart?: number,
        rangeEnd?: number
    ): Promise<Readable> {
        validateFilename(filename, this.options.invalidCharactersRegexp);
        if (!contentId) {
            throw new H5pError('mongo-s3-content-storage:content-not-found', {}, 404);
        }
        const id = this.requireId(contentId);
        try {
            const result = await this.s3.getObject({
                Bucket: this.options.s3Bucket,
                Key: this.getS3Key(id, filename),
                Range:
                    rangeStart !== undefined && rangeEnd !== undefined
                        ? `bytes=${rangeStart}-${rangeEnd}`
                        : rangeStart !== undefined
                          ? `bytes=${rangeStart}-`
                          : undefined
            });
            return result.Body as Readable;
        } catch (error) {
            if (isNotFound(error)) {
                throw new H5pError('content-file-missing', { filename, contentId: id }, 404);
            }
            throw error;
        }
    }

    /** Full row (used by the REST layer and the permission system). */
    public async findRow(contentId: ContentId): Promise<ContentRow | undefined> {
        if (!PgContentStorage.isValidId(contentId)) {
            return undefined;
        }
        const { rows } = await this.pool.query<ContentRow>(
            `SELECT id::text AS id, user_id, title, main_library, library_version, metadata, parameters,
                    created_at, updated_at
               FROM ${this.table} WHERE id = $1::bigint`,
            [contentId]
        );
        return rows[0];
    }

    /** Owner (creator) of a content object; undefined if missing or unowned. */
    public async getOwner(contentId: ContentId): Promise<string | undefined> {
        if (!PgContentStorage.isValidId(contentId)) {
            return undefined;
        }
        const { rows } = await this.pool.query<{ user_id: string | null }>(
            `SELECT user_id FROM ${this.table} WHERE id = $1::bigint`,
            [contentId]
        );
        return rows[0]?.user_id ?? undefined;
    }

    public async getMetadata(contentId: ContentId, _user?: IUser): Promise<IContentMetadata> {
        const id = this.requireId(contentId);
        const { rows } = await this.pool.query<{ metadata: IContentMetadata }>(
            `SELECT metadata FROM ${this.table} WHERE id = $1::bigint`,
            [id]
        );
        if (!rows[0]) {
            throw new H5pError('mongo-s3-content-storage:content-not-found', {}, 404);
        }
        return rows[0].metadata;
    }

    public async getParameters(contentId: ContentId, _user?: IUser): Promise<any> {
        const id = this.requireId(contentId);
        const { rows } = await this.pool.query<{ parameters: any }>(
            `SELECT parameters FROM ${this.table} WHERE id = $1::bigint`,
            [id]
        );
        if (!rows[0]) {
            throw new H5pError('mongo-s3-content-storage:content-not-found', {}, 404);
        }
        return rows[0].parameters;
    }

    /**
     * Counts how often a library version is used.
     *
     * asMainLibrary: content whose main library is this machine name and whose
     * preloaded dependencies include this exact major.minor version.
     * asDependency: content with a different main library that lists the
     * library in its preloaded, dynamic or editor dependencies.
     *
     * Versions are compared as text because h5p.json files in the wild use both
     * numbers and strings for majorVersion/minorVersion.
     */
    public async getUsage(library: ILibraryName): Promise<{ asDependency: number; asMainLibrary: number }> {
        const depMatch = (field: string): string => `EXISTS (
            SELECT 1 FROM jsonb_array_elements(
                CASE WHEN jsonb_typeof(metadata->'${field}') = 'array' THEN metadata->'${field}' ELSE '[]'::jsonb END
            ) d
            WHERE d->>'machineName' = $1 AND d->>'majorVersion' = $2 AND d->>'minorVersion' = $3)`;
        const { rows } = await this.pool.query<{ as_main: string; as_dep: string }>(
            `SELECT
                count(*) FILTER (WHERE main_library = $1 AND ${depMatch('preloadedDependencies')}) AS as_main,
                count(*) FILTER (WHERE main_library <> $1 AND (
                    ${depMatch('preloadedDependencies')} OR
                    ${depMatch('dynamicDependencies')} OR
                    ${depMatch('editorDependencies')})) AS as_dep
               FROM ${this.table}`,
            [library.machineName, String(library.majorVersion), String(library.minorVersion)]
        );
        return {
            asMainLibrary: Number(rows[0]?.as_main ?? 0),
            asDependency: Number(rows[0]?.as_dep ?? 0)
        };
    }

    public async listContent(_user?: IUser): Promise<ContentId[]> {
        try {
            const { rows } = await this.pool.query<{ id: string }>(
                `SELECT id::text AS id FROM ${this.table} ORDER BY id`
            );
            return rows.map((r) => r.id);
        } catch (error) {
            log.error(`Error while listing content ids: ${(error as Error).message}`);
            throw new H5pError('mongo-s3-content-storage:listing-content-error', {}, 500);
        }
    }

    /**
     * Lists the files (relative paths, e.g. "images/a.png") stored for a
     * content object. Returns [] if there are none or S3 cannot be listed.
     */
    public async listFiles(contentId: ContentId, _user: IUser): Promise<string[]> {
        const id = this.requireId(contentId);
        const prefix = this.getS3Key(id, '');
        const files: string[] = [];
        let token: string | undefined;
        try {
            do {
                const ret = await this.s3.listObjectsV2({
                    Bucket: this.options.s3Bucket,
                    Prefix: prefix,
                    ContinuationToken: token,
                    MaxKeys: 1000
                });
                for (const c of ret.Contents ?? []) {
                    if (c.Key && c.Key.length > prefix.length) {
                        files.push(c.Key.substring(prefix.length));
                    }
                }
                token = ret.IsTruncated ? ret.NextContinuationToken : undefined;
            } while (token);
        } catch (error) {
            log.debug(`Could not list files of content ${id} in S3: ${(error as Error).message}`);
            return [];
        }
        return files;
    }

    public sanitizeFilename(filename: string): string {
        return sanitizeFilename(filename, this.maxKeyLength, this.options.invalidCharactersRegexp);
    }
}
