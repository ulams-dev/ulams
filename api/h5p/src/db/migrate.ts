import { createHash } from 'node:crypto';
import { Pool } from 'pg';
import { qi } from './pool';

/**
 * Schema migrations for the service's own Postgres schema (default `h5p`).
 *
 * Laravel runs `migrate:fresh` on the `public` schema, so everything here
 * lives in a separate schema and is created by the service on startup. Each
 * migration runs exactly once (tracked in `<schema>.schema_migrations`), all
 * pending migrations run in a single transaction, and a transaction-scoped
 * advisory lock serialises concurrent replicas.
 *
 * Never edit a migration that has shipped; append a new one.
 */
export interface Migration {
    version: string;
    description: string;
    up: (schema: string) => string;
}

export const migrations: Migration[] = [
    {
        version: '0001',
        description: 'contents, content_user_data, finished_data',
        up: (s) => `
CREATE TABLE IF NOT EXISTS ${s}.contents (
    id              bigserial PRIMARY KEY,
    user_id         text NULL,
    title           text NOT NULL DEFAULT '',
    main_library    text NOT NULL,
    library_version text NOT NULL DEFAULT '',
    metadata        jsonb NOT NULL,
    parameters      jsonb NOT NULL,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS contents_main_library_idx ON ${s}.contents (main_library);
CREATE INDEX IF NOT EXISTS contents_user_id_idx ON ${s}.contents (user_id);
CREATE INDEX IF NOT EXISTS contents_created_at_idx ON ${s}.contents (created_at DESC);

CREATE TABLE IF NOT EXISTS ${s}.content_user_data (
    id              bigserial PRIMARY KEY,
    content_id      bigint NOT NULL REFERENCES ${s}.contents (id) ON DELETE CASCADE,
    user_id         text NOT NULL,
    data_type       text NOT NULL,
    sub_content_id  text NOT NULL,
    -- '' means "no context" (Postgres 12 has no NULLS NOT DISTINCT)
    context_id      text NOT NULL DEFAULT '',
    user_state      text NOT NULL,
    preload         boolean NOT NULL DEFAULT false,
    invalidate      boolean NOT NULL DEFAULT false,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT content_user_data_unique
        UNIQUE (content_id, user_id, data_type, sub_content_id, context_id)
);
CREATE INDEX IF NOT EXISTS content_user_data_user_idx ON ${s}.content_user_data (user_id);
CREATE INDEX IF NOT EXISTS content_user_data_content_invalidate_idx ON ${s}.content_user_data (content_id) WHERE invalidate;

CREATE TABLE IF NOT EXISTS ${s}.finished_data (
    content_id         bigint NOT NULL REFERENCES ${s}.contents (id) ON DELETE CASCADE,
    user_id            text NOT NULL,
    score              double precision NOT NULL DEFAULT 0,
    max_score          double precision NOT NULL DEFAULT 0,
    opened_timestamp   double precision NULL,
    finished_timestamp double precision NULL,
    completion_time    double precision NULL,
    created_at         timestamptz NOT NULL DEFAULT now(),
    updated_at         timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (content_id, user_id)
);
CREATE INDEX IF NOT EXISTS finished_data_user_idx ON ${s}.finished_data (user_id);
`
    }
];

function lockKey(schema: string): string {
    // pg_advisory_xact_lock takes a bigint; derive a stable one from the schema.
    const hex = createHash('sha256').update(`api-h5p:migrate:${schema}`).digest('hex').slice(0, 15);
    return BigInt(`0x${hex}`).toString();
}

/**
 * Creates the schema and applies all pending migrations. Idempotent.
 * @returns the versions that were applied in this run
 */
export async function migrate(pool: Pool, schema: string): Promise<string[]> {
    const s = qi(schema);
    const client = await pool.connect();
    const applied: string[] = [];
    try {
        await client.query('BEGIN');
        await client.query('SELECT pg_advisory_xact_lock($1::bigint)', [lockKey(schema)]);
        await client.query(`CREATE SCHEMA IF NOT EXISTS ${s}`);
        await client.query(
            `CREATE TABLE IF NOT EXISTS ${s}.schema_migrations (
                version     text PRIMARY KEY,
                description text NOT NULL DEFAULT '',
                applied_at  timestamptz NOT NULL DEFAULT now()
            )`
        );
        const { rows } = await client.query<{ version: string }>(`SELECT version FROM ${s}.schema_migrations`);
        const done = new Set(rows.map((r) => r.version));
        for (const m of migrations) {
            if (done.has(m.version)) {
                continue;
            }
            await client.query(m.up(s));
            await client.query(`INSERT INTO ${s}.schema_migrations (version, description) VALUES ($1, $2)`, [
                m.version,
                m.description
            ]);
            applied.push(m.version);
        }
        await client.query('COMMIT');
        return applied;
    } catch (error) {
        await client.query('ROLLBACK').catch(() => undefined);
        throw error;
    } finally {
        client.release();
    }
}
