import { describe, expect, it, vi } from 'vitest';
import type { NextFunction, Request, Response } from 'express';

import { platformOnlyWrites } from '../src/http/platformOnly';

function run(method: string, tenantId?: string) {
    const req = { method, tenant: tenantId ? { id: tenantId } : undefined } as unknown as Request;
    const json = vi.fn();
    const res = { status: vi.fn(() => ({ json })) } as unknown as Response;
    const next = vi.fn() as unknown as NextFunction;
    platformOnlyWrites(req, res, next);
    return { res, next, json };
}

describe('platformOnlyWrites (shared H5P libraries)', () => {
    it('lets every tenant read libraries', () => {
        expect(run('GET', 'coffee').next).toHaveBeenCalled();
    });

    it('lets the platform write', () => {
        for (const method of ['POST', 'PATCH', 'DELETE']) {
            expect(run(method, 'default').next).toHaveBeenCalled();
        }
    });

    it('refuses writes from a tenant', () => {
        for (const method of ['POST', 'PATCH', 'DELETE']) {
            const { res, next } = run(method, 'coffee');
            expect(next).not.toHaveBeenCalled();
            expect(res.status).toHaveBeenCalledWith(403);
        }
    });
});
