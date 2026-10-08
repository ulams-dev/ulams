import crypto from 'node:crypto';
import { DeleteObjectsCommand, S3 } from '@aws-sdk/client-s3';
import { H5pError, utils } from '@lumieducation/h5p-server';
import { AppConfig } from '../config';

// Ported from @lumieducation/h5p-mongos3/src/S3Utils.ts (GPL-3.0-or-later).

const DEFAULT_INVALID_CHARACTERS = /[^A-Za-z0-9\-._!()@/]/g;

export function createS3Client(cfg: AppConfig['s3']): S3 {
    return new S3({
        endpoint: cfg.endpoint,
        region: cfg.region,
        forcePathStyle: cfg.forcePathStyle,
        credentials: {
            accessKeyId: cfg.accessKeyId,
            secretAccessKey: cfg.secretAccessKey
        },
        // MinIO and other S3-compatible stores do not all support the
        // flexible checksums newer SDK versions send by default.
        requestChecksumCalculation: 'WHEN_REQUIRED',
        responseChecksumValidation: 'WHEN_REQUIRED'
    });
}

/**
 * Throws if the filename cannot be used as (part of) an S3 key.
 */
export function validateFilename(filename: string, invalidCharactersRegExp?: RegExp): void {
    if (/\.\.\//.test(filename) || /(^|\/)\.\.($|\/)/.test(filename)) {
        throw new H5pError('illegal-filename', { filename }, 400);
    }
    if (filename.startsWith('/')) {
        throw new H5pError('illegal-filename', { filename }, 400);
    }
    // A fresh RegExp without the global flag: .test() on a /g regexp is stateful.
    const re = new RegExp((invalidCharactersRegExp ?? DEFAULT_INVALID_CHARACTERS).source);
    if (re.test(filename)) {
        throw new H5pError('illegal-filename', { filename }, 400);
    }
}

export function sanitizeFilename(filename: string, maxFileLength: number, invalidCharactersRegExp?: RegExp): string {
    return utils.generalizedSanitizeFilename(
        filename,
        invalidCharactersRegExp ?? DEFAULT_INVALID_CHARACTERS,
        maxFileLength
    );
}

/** True if the error returned by the AWS SDK means "object/key does not exist". */
export function isNotFound(error: any): boolean {
    return (
        error?.name === 'NoSuchKey' ||
        error?.name === 'NotFound' ||
        error?.Code === 'NoSuchKey' ||
        error?.$metadata?.httpStatusCode === 404
    );
}

/**
 * Deletes objects in batches of 1000. Adds a Content-MD5 header because some
 * S3-compatible stores still require it for DeleteObjects.
 */
export async function deleteObjects(keys: string[], bucket: string, s3: S3): Promise<void> {
    const pending = [...keys];
    const errors: Error[] = [];
    while (pending.length > 0) {
        const batch = pending.splice(0, 1000);
        const deleteParams = {
            Bucket: bucket,
            Delete: { Objects: batch.map((Key) => ({ Key })), Quiet: true }
        };
        const command = new DeleteObjectsCommand(deleteParams);
        command.middlewareStack.add(
            (next) => async (args) => {
                const request = args.request as { headers: Record<string, string>; body?: unknown };
                if (typeof request.body === 'string' && !request.headers['content-md5']) {
                    request.headers['content-md5'] = crypto.createHash('md5').update(request.body).digest('base64');
                }
                return next(args);
            },
            { step: 'build', name: 'addContentMd5' }
        );
        try {
            const response = await s3.send(command);
            if (response.Errors && response.Errors.length > 0) {
                errors.push(new Error(response.Errors.map((e) => `${e.Key}: ${e.Message}`).join(', ')));
            }
        } catch (error) {
            errors.push(error as Error);
        }
    }
    if (errors.length > 0) {
        throw new Error(`Errors while deleting files in S3 storage: ${errors.map((e) => e.message).join(', ')}`);
    }
}
