import { AppConfig } from '../config';
import { Logger } from '../logger';
import { SharedH5P } from '../h5p/createH5P';
import { buildTenant } from './buildTenant';
import { Tenant, TenantRequest, TenantResolver, TenantSettings } from './types';

/** TenantSettings for the single tenant described by the environment. */
export function settingsFromEnv(app: AppConfig): TenantSettings {
    return {
        id: 'default',
        hosts: [],
        db: app.db,
        s3: app.s3,
        auth: { ...app.auth }
    };
}

/**
 * Today's deployment: one tenant from environment variables, served for every
 * Host. The multi-tenant resolver will implement the same interface (see
 * README "Multi-tenancy").
 */
export class SingleTenantResolver implements TenantResolver {
    private constructor(private readonly tenant: Tenant) {}

    static async create(app: AppConfig, shared: SharedH5P, logger: Logger): Promise<SingleTenantResolver> {
        return new SingleTenantResolver(await buildTenant(app, shared, settingsFromEnv(app), logger));
    }

    /** For tests: wrap an already built tenant. */
    static of(tenant: Tenant): SingleTenantResolver {
        return new SingleTenantResolver(tenant);
    }

    async resolve(_req: TenantRequest): Promise<Tenant | undefined> {
        return this.tenant;
    }

    async get(id: string): Promise<Tenant> {
        if (id !== this.tenant.id) {
            throw new Error(`Unknown tenant ${id}`);
        }
        return this.tenant;
    }

    active(): Tenant[] {
        return [this.tenant];
    }

    async close(): Promise<void> {
        await this.tenant.close();
    }
}
