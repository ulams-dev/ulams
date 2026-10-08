import { Pool, PoolConfig } from 'pg';
import { AppConfig } from '../config';

export function createPool(db: AppConfig['db']): Pool {
    const base: PoolConfig = db.connectionString
        ? { connectionString: db.connectionString }
        : {
              host: db.host,
              port: db.port,
              database: db.database,
              user: db.user,
              password: db.password
          };
    return new Pool({
        ...base,
        max: db.poolMax,
        ssl: db.ssl ? { rejectUnauthorized: false } : undefined,
        application_name: 'api-h5p',
        idleTimeoutMillis: 30_000,
        connectionTimeoutMillis: 10_000
    });
}

/** Quotes a (validated) schema identifier for use in SQL. */
export function qi(identifier: string): string {
    if (!/^[a-z_][a-z0-9_]{0,62}$/.test(identifier)) {
        throw new Error(`Invalid SQL identifier: ${identifier}`);
    }
    return `"${identifier}"`;
}
