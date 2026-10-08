import { rm } from 'node:fs/promises';
import express, { Express, Request, RequestHandler, Router } from 'express';
import cors from 'cors';
import fileUpload from 'express-fileupload';
import pinoHttp from 'pino-http';
import type { RedisClientType } from 'redis';
import {
    contentTypeCacheExpressRouter,
    h5pAjaxExpressRouter,
    libraryAdministrationExpressRouter
} from '@lumieducation/h5p-express';

import { AppConfig } from './config';
import { Logger, redactUrl } from './logger';
import { I18nSetup } from './h5p/i18n';
import { authMiddleware } from './auth/middleware';
import { contentsRouter } from './routes/contents';
import { healthRouter } from './routes/health';
import { embedRouter } from './routes/embed';
import { jsonErrorHandler, requireAny } from './http/respond';
import { Tenant, TenantResolver } from './tenancy/types';

export interface AppDeps {
    config: AppConfig;
    logger: Logger;
    redis: RedisClientType<any, any, any>;
    i18n: I18nSetup;
    tenants: TenantResolver;
}

export interface RequestWithTenant extends Request {
    tenant: Tenant;
}

/** Deletes express-fileupload temp files once the response is done. */
function cleanupUploads(req: Request): void {
    const files = (req as any).files as Record<string, any> | undefined;
    if (!files) {
        return;
    }
    for (const entry of Object.values(files)) {
        for (const f of Array.isArray(entry) ? entry : [entry]) {
            if (f?.tempFilePath) {
                rm(f.tempFilePath, { force: true }).catch(() => undefined);
            }
        }
    }
}

/**
 * Permission guards for Lumi's library administration router, which does not
 * check permissions itself. Library *files* (/h5p/libraries/:uberName/:file)
 * are served by the AJAX router mounted before this one and stay public.
 */
function libraryAdminGuards(): Router {
    const guard = Router();
    guard.get('/', requireAny('h5p_library_list'));
    guard.get('/:ubername', requireAny('h5p_library_list', 'h5p_library_read'));
    guard.post('/', requireAny('h5p_library_upload', 'h5p_library_install'));
    guard.patch('/:ubername', requireAny('h5p_library_update'));
    guard.delete('/:ubername', requireAny('h5p_library_delete'));
    return guard;
}

function contentTypeCacheGuards(): Router {
    const guard = Router();
    guard.get('/update', requireAny('h5p_library_list', 'h5p_library_install', 'h5p_library_update'));
    guard.post('/update', requireAny('h5p_library_install', 'h5p_library_update'));
    return guard;
}

/** Routers bound to one tenant's editor/player/pool (built once per tenant). */
interface TenantRouters {
    contents: RequestHandler;
    ajax: RequestHandler;
    libraries: RequestHandler;
    contentTypeCache: RequestHandler;
}

function buildTenantRouters(config: AppConfig, tenant: Tenant): TenantRouters {
    const { h5p } = tenant;
    return {
        contents: contentsRouter({ pool: tenant.pool, schema: tenant.settings.db.schema, h5p }),
        ajax: h5pAjaxExpressRouter(h5p.editor, config.paths.core, config.paths.editor, undefined, 'auto') as any,
        libraries: libraryAdministrationExpressRouter(h5p.editor) as any,
        contentTypeCache: contentTypeCacheExpressRouter(h5p.editor.contentTypeCache) as any
    };
}

export function createApp(deps: AppDeps): Express {
    const { config, logger, tenants } = deps;
    const base = config.mountPath; // '/h5p'
    const app = express();

    const routerCache = new Map<string, TenantRouters>();
    const routersFor = (req: Request): TenantRouters => {
        const tenant = (req as RequestWithTenant).tenant;
        let routers = routerCache.get(tenant.id);
        if (!routers) {
            routers = buildTenantRouters(config, tenant);
            routerCache.set(tenant.id, routers);
        }
        return routers;
    };
    const dispatch =
        (pick: (r: TenantRouters) => RequestHandler): RequestHandler =>
        (req, res, next) =>
            pick(routersFor(req))(req, res, next);

    app.disable('x-powered-by');
    app.set('trust proxy', true);

    app.use(
        pinoHttp({
            logger,
            serializers: {
                req: (req) => ({ id: req.id, method: req.method, url: redactUrl(req.url) }),
                res: (res) => ({ statusCode: res.statusCode })
            },
            autoLogging: { ignore: (req) => req.url === `${base}/health` },
            customLogLevel: (_req, res, err) => (err || res.statusCode >= 500 ? 'error' : 'info')
        })
    );

    app.use(
        cors({
            origin: (origin, callback) => {
                // Same-origin and server-to-server requests have no Origin.
                if (!origin || config.corsOrigins.includes(origin) || config.corsOrigins.includes('*')) {
                    callback(null, origin ?? true);
                } else {
                    callback(null, false);
                }
            },
            credentials: true,
            exposedHeaders: ['Content-Disposition', 'Content-Length', 'Content-Range', 'Accept-Ranges'],
            allowedHeaders: [
                'Authorization',
                'Content-Type',
                'Accept',
                'Accept-Language',
                'X-Requested-With',
                'X-Internal-Token',
                'Range'
            ],
            maxAge: 600
        })
    );

    // Health is unauthenticated and does not need body parsing.
    app.use(`${base}/health`, healthRouter(tenants, deps.redis));

    // Embed pages (HTML + bundled JS): no tenant, no auth; the token arrives
    // later via postMessage and is used for same-origin API calls.
    app.use(`${base}/embed`, embedRouter({ base, allowedOrigins: config.corsOrigins }));

    // Tenant from X-Forwarded-Host / Host.
    app.use(async (req, res, next) => {
        try {
            const tenant = await tenants.resolve(req);
            if (!tenant) {
                res.status(404).json({ success: false, message: 'Unknown tenant.' });
                return;
            }
            (req as RequestWithTenant).tenant = tenant;
            next();
        } catch (error) {
            next(error);
        }
    });

    app.use(express.json({ limit: '50mb' }));
    app.use(express.urlencoded({ extended: true, limit: '50mb' }));
    app.use(
        fileUpload({
            limits: { fileSize: config.h5p.maxTotalSize },
            useTempFiles: true,
            tempFileDir: config.paths.temp,
            abortOnLimit: true,
            limitHandler: (_req, res) => {
                res.status(413).json({
                    success: false,
                    message: `File too large (limit ${Math.round(config.h5p.maxTotalSize / 1024 / 1024)} MB).`
                });
            }
        })
    );
    app.use((req, res, next) => {
        res.on('finish', () => cleanupUploads(req));
        res.on('close', () => cleanupUploads(req));
        next();
    });

    app.use(deps.i18n.middleware);
    app.use(
        authMiddleware({
            forRequest: (req) => {
                const t = (req as RequestWithTenant).tenant;
                return { verifier: t.verifier, profiles: t.profiles, internalToken: t.settings.auth.internalToken };
            },
            logger
        })
    );

    // REST API (Laravel-style JSON envelope)
    app.use(`${base}/contents`, dispatch((r) => r.contents), jsonErrorHandler(logger));

    // Lumi routers: AJAX endpoints, core/editor/library/content files,
    // contentUserData, finishedData, download.
    app.use(base, dispatch((r) => r.ajax));
    app.use(`${base}/libraries`, libraryAdminGuards(), dispatch((r) => r.libraries), jsonErrorHandler(logger));
    app.use(
        `${base}/content-type-cache`,
        contentTypeCacheGuards(),
        dispatch((r) => r.contentTypeCache),
        jsonErrorHandler(logger)
    );

    app.use(base, (_req, res) => {
        res.status(404).json({ success: false, message: 'Not found.' });
    });
    app.use(jsonErrorHandler(logger));
    return app;
}
