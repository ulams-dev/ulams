import { ReadStream } from 'node:fs';
import { Readable } from 'node:stream';
import { Upload } from '@aws-sdk/lib-storage';
import { ObjectCannedACL, S3 } from '@aws-sdk/client-s3';
import {
    H5pError,
    IFileStats,
    ITemporaryFile,
    ITemporaryFileStorage,
    IUser,
    Logger
} from '@lumieducation/h5p-server';

import { deleteObjects, isNotFound, sanitizeFilename, validateFilename } from './s3';

const log = new Logger('S3TemporaryFileStorage');

export interface S3TemporaryFileStorageOptions {
    s3Bucket: string;
    /** Key prefix, e.g. "h5p" -> objects at "h5p/temp/{filename}". */
    s3Prefix: string;
    /** Used to compute expiry in listFiles() from LastModified. */
    temporaryFileLifetimeMs: number;
    s3Acl?: ObjectCannedACL;
    maxKeyLength?: number;
    invalidCharactersRegexp?: RegExp;
}

/**
 * Temporary file storage (editor uploads before content is saved) in S3.
 *
 * Port of @lumieducation/h5p-mongos3 S3TemporaryFileStorage
 * (GPL-3.0-or-later) with two differences:
 *  - all keys live under `${prefix}/temp/`, because the bucket is shared with
 *    the LMS;
 *  - expiry is NOT done with a bucket lifecycle rule (Lumi's
 *    setBucketLifecycleConfiguration replaces every rule of the bucket with an
 *    empty-prefix expiration, which would delete all LMS files). Instead
 *    listFiles() reports files with expiresAt = LastModified + lifetime, so
 *    TemporaryFileManager.cleanUp() can delete them. The service runs
 *    cleanUp() periodically.
 */
export default class S3TemporaryFileStorage implements ITemporaryFileStorage {
    private readonly keyPrefix: string;
    private readonly maxKeyLength: number;

    constructor(
        private readonly s3: S3,
        private readonly options: S3TemporaryFileStorageOptions
    ) {
        this.keyPrefix = options.s3Prefix ? `${options.s3Prefix}/temp/` : 'temp/';
        // Room for the prefix and the 8-char unique suffix + separator Lumi adds.
        this.maxKeyLength = (options.maxKeyLength ?? 1024) - this.keyPrefix.length - 10;
    }

    private key(filename: string): string {
        return `${this.keyPrefix}${filename}`;
    }

    public async deleteFile(filename: string, _ownerId: string): Promise<void> {
        validateFilename(filename, this.options.invalidCharactersRegexp);
        if (!filename) {
            throw new H5pError('s3-temporary-storage:file-not-found', {}, 404);
        }
        try {
            await this.s3.deleteObject({ Bucket: this.options.s3Bucket, Key: this.key(filename) });
        } catch (error) {
            log.error(`Error while deleting a temporary file from S3: ${(error as Error).message}`);
            throw new H5pError('s3-temporary-storage:deleting-file-error', { filename }, 500);
        }
    }

    public async fileExists(filename: string, _user: IUser): Promise<boolean> {
        validateFilename(filename, this.options.invalidCharactersRegexp);
        if (!filename) {
            return false;
        }
        try {
            await this.s3.headObject({ Bucket: this.options.s3Bucket, Key: this.key(filename) });
            return true;
        } catch {
            return false;
        }
    }

    public async getFileStats(filename: string, _user: IUser): Promise<IFileStats> {
        validateFilename(filename, this.options.invalidCharactersRegexp);
        try {
            const head = await this.s3.headObject({ Bucket: this.options.s3Bucket, Key: this.key(filename) });
            return { size: head.ContentLength ?? 0, birthtime: head.LastModified ?? new Date(0) };
        } catch {
            throw new H5pError('file-not-found', {}, 404);
        }
    }

    public async getFileStream(
        filename: string,
        _user: IUser,
        rangeStart?: number,
        rangeEnd?: number
    ): Promise<Readable> {
        validateFilename(filename, this.options.invalidCharactersRegexp);
        if (!filename) {
            throw new H5pError('s3-temporary-storage:file-not-found', {}, 404);
        }
        try {
            const result = await this.s3.getObject({
                Bucket: this.options.s3Bucket,
                Key: this.key(filename),
                Range:
                    rangeStart !== undefined && rangeEnd !== undefined
                        ? `bytes=${rangeStart}-${rangeEnd}`
                        : undefined
            });
            return result.Body as Readable;
        } catch (error) {
            if (isNotFound(error)) {
                throw new H5pError('s3-temporary-storage:file-not-found', {}, 404);
            }
            throw error;
        }
    }

    /**
     * Lists temporary files with their computed expiry. Without a user, all
     * files are returned (used by TemporaryFileManager.cleanUp()). With a user
     * we would need a HEAD per object to read the owner, so we return [] like
     * the original S3 implementation.
     */
    public async listFiles(user?: IUser): Promise<ITemporaryFile[]> {
        if (user) {
            return [];
        }
        const files: ITemporaryFile[] = [];
        let token: string | undefined;
        do {
            const ret = await this.s3.listObjectsV2({
                Bucket: this.options.s3Bucket,
                Prefix: this.keyPrefix,
                ContinuationToken: token,
                MaxKeys: 1000
            });
            for (const c of ret.Contents ?? []) {
                if (!c.Key || c.Key.length <= this.keyPrefix.length) {
                    continue;
                }
                const modified = c.LastModified ?? new Date();
                files.push({
                    filename: c.Key.substring(this.keyPrefix.length),
                    ownedByUserId: '',
                    expiresAt: new Date(modified.getTime() + this.options.temporaryFileLifetimeMs)
                });
            }
            token = ret.IsTruncated ? ret.NextContinuationToken : undefined;
        } while (token);
        return files;
    }

    /** Deletes expired files in bulk; returns how many were removed. */
    public async deleteExpired(now = Date.now()): Promise<number> {
        const expired = (await this.listFiles()).filter((f) => f.expiresAt.getTime() < now);
        if (expired.length > 0) {
            await deleteObjects(
                expired.map((f) => this.key(f.filename)),
                this.options.s3Bucket,
                this.s3
            );
        }
        return expired.length;
    }

    public sanitizeFilename(filename: string): string {
        return sanitizeFilename(filename, this.maxKeyLength, this.options.invalidCharactersRegexp);
    }

    public async saveFile(
        filename: string,
        dataStream: ReadStream,
        user: IUser,
        expirationTime: Date
    ): Promise<ITemporaryFile> {
        validateFilename(filename, this.options.invalidCharactersRegexp);
        if (!filename) {
            throw new H5pError('illegal-filename', {}, 400);
        }
        try {
            await new Upload({
                client: this.s3,
                params: {
                    ACL: this.options.s3Acl ?? 'private',
                    Body: dataStream,
                    Bucket: this.options.s3Bucket,
                    Key: this.key(filename),
                    Metadata: { creator: String(user.id), expires: expirationTime.toISOString() }
                }
            }).done();
            return { filename, ownedByUserId: user.id, expiresAt: expirationTime };
        } catch (error) {
            log.error(`Error while uploading temporary file "${filename}" to S3: ${(error as Error).message}`);
            throw new H5pError('s3-temporary-storage:s3-upload-error', { filename }, 500);
        }
    }
}
