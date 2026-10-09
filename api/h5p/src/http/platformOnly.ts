import type { NextFunction, Request, Response } from 'express';

/** Id of the platform tenant (EnvFileTenantResolver.PLATFORM_TENANT_ID, SingleTenantResolver). */
const PLATFORM_TENANT_ID = 'default';

/**
 * H5P libraries are installed once for every tenant (one shared library volume), so writes to them
 * (upload, install, update, delete, content-type cache refresh) are allowed on the platform host
 * only. Reads stay available to every tenant. Mount after tenant resolution.
 */
export function platformOnlyWrites(req: Request, res: Response, next: NextFunction): void {
    if (req.method === 'GET' || req.method === 'HEAD' || req.method === 'OPTIONS') {
        next();
        return;
    }
    const tenant = (req as Request & { tenant?: { id: string } }).tenant;
    if (tenant && tenant.id !== PLATFORM_TENANT_ID) {
        res.status(403).json({
            success: false,
            message: 'H5P libraries are shared by all academies; install and update them on the platform.'
        });
        return;
    }
    next();
}
