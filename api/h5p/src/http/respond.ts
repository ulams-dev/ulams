import type { NextFunction, Request, RequestHandler, Response } from 'express';
import { AggregateH5pError, H5pError } from '@lumieducation/h5p-server';

import { H5PUser, hasAnyPermission, isAuthenticated } from '../auth/users';
import type { Logger } from '../logger';

/** Laravel-style success envelope. */
export function ok(res: Response, data: unknown, message = '', status = 200): void {
    res.status(status).json({ success: true, data, message });
}

export class HttpError extends Error {
    constructor(
        public readonly status: number,
        message: string,
        public readonly details?: unknown
    ) {
        super(message);
    }
}

/** Wraps an async handler so rejections reach the error handler (also fine in Express 5). */
export function asyncHandler(fn: (req: Request, res: Response, next: NextFunction) => Promise<unknown>): RequestHandler {
    return (req, res, next) => {
        Promise.resolve(fn(req, res, next)).catch(next);
    };
}

export function userOf(req: Request): H5PUser {
    return (req as Request & { user: H5PUser }).user;
}

/**
 * Route guard: 401 for anonymous callers, 403 if none of the permissions is
 * held. The system user passes every guard.
 */
export function requireAny(...permissions: string[]): RequestHandler {
    return (req, res, next) => {
        const user = userOf(req);
        if (!isAuthenticated(user)) {
            res.status(401).json({ success: false, message: 'Unauthenticated.' });
            return;
        }
        if (permissions.length > 0 && !hasAnyPermission(user, ...permissions)) {
            res.status(403).json({ success: false, message: 'Forbidden.' });
            return;
        }
        next();
    };
}

/**
 * Route guard for server-to-server maintenance calls: only the system user
 * (a valid X-Internal-Token of the request's tenant) passes.
 */
export function requireSystem(): RequestHandler {
    return (req, res, next) => {
        const user = userOf(req);
        if (!isAuthenticated(user)) {
            res.status(401).json({ success: false, message: 'Unauthenticated.' });
            return;
        }
        if (user.isSystem !== true) {
            res.status(403).json({ success: false, message: 'Forbidden.' });
            return;
        }
        next();
    };
}

/**
 * JSON error handler for the REST routes: `{success:false, message}` with
 * the H5pError status, translated through req.t when available.
 */
export function jsonErrorHandler(logger: Logger) {
    return (err: any, req: Request, res: Response, _next: NextFunction): void => {
        if (res.headersSent) {
            res.end();
            return;
        }
        const t = (req as any).t as ((key: string, opts?: any) => string) | undefined;
        if (err instanceof H5pError) {
            const message = t ? t(err.errorId, err.replacements) : err.errorId;
            const body: Record<string, unknown> = { success: false, message };
            if (err instanceof AggregateH5pError) {
                body.errors = err.getErrors().map((e) => ({
                    code: e.errorId,
                    message: t ? t(e.errorId, e.replacements) : e.errorId
                }));
            }
            if (err.httpStatusCode >= 500) {
                logger.error({ err }, 'H5P error');
            }
            res.status(err.httpStatusCode || 500).json(body);
            return;
        }
        if (err instanceof HttpError) {
            res.status(err.status).json({ success: false, message: err.message, ...(err.details ? { errors: err.details } : {}) });
            return;
        }
        // express-fileupload / body-parser errors carry a status
        const status = Number(err?.status ?? err?.statusCode);
        if (status >= 400 && status < 500) {
            res.status(status).json({ success: false, message: err.message ?? 'Bad request' });
            return;
        }
        logger.error({ err }, 'Unhandled error');
        res.status(500).json({ success: false, message: 'Internal server error' });
    };
}
