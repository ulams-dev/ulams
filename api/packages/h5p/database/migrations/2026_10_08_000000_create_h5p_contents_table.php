<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Creates the H5P service's `h5p.contents` table if it is not there yet, so a
 * fresh database (and the package test suites) can resolve H5P topics before
 * the service has started once.
 *
 * The H5P service (api/h5p) owns the `h5p` schema: it applies its own versioned
 * migrations on startup (src/db/migrate.ts). The DDL below is a verbatim copy of
 * the contents part of its migration 0001 and is idempotent, so the service's
 * migration still runs cleanly afterwards. No `h5p.schema_migrations` row is
 * written on purpose; later changes to the table come from the service only.
 *
 * PostgreSQL only: on other connections this migration does nothing and H5P
 * topics are not supported.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE SCHEMA IF NOT EXISTS h5p;

CREATE TABLE IF NOT EXISTS h5p.contents (
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
CREATE INDEX IF NOT EXISTS contents_main_library_idx ON h5p.contents (main_library);
CREATE INDEX IF NOT EXISTS contents_user_id_idx ON h5p.contents (user_id);
CREATE INDEX IF NOT EXISTS contents_created_at_idx ON h5p.contents (created_at DESC);
SQL);
    }

    public function down(): void
    {
        // The H5P service owns the h5p schema and its data: never drop it from Laravel.
    }
};
