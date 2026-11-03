-- H5P service schema of one tenant database, with a few rows, plus the PostgreSQL features the platform
-- relies on (tsvector + GIN, a table without a primary key, sequences that have been used). The API
-- migrations and api/h5p/src/db/migrate.ts create most of the h5p schema; the statements for it are
-- no-ops when it exists. Applied to every database of the seeded PostgreSQL 12 cluster by the
-- pg-upgrade job: psql -v tenant=<slug>.
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

CREATE TABLE IF NOT EXISTS h5p.content_user_data (
    id              bigserial PRIMARY KEY,
    content_id      bigint NOT NULL REFERENCES h5p.contents (id) ON DELETE CASCADE,
    user_id         text NOT NULL,
    data_type       text NOT NULL,
    sub_content_id  text NOT NULL,
    context_id      text NOT NULL DEFAULT '',
    user_state      text NOT NULL,
    CONSTRAINT content_user_data_unique UNIQUE (content_id, user_id, data_type, sub_content_id, context_id)
);

INSERT INTO h5p.contents (user_id, title, main_library, library_version, metadata, parameters)
SELECT 'user-' || (n % 5), :'tenant' || ' content ' || n, 'H5P.Accordion', '1.0',
       jsonb_build_object('title', 'Item ' || n), jsonb_build_object('panels', jsonb_build_array(n, n * 2))
FROM generate_series(1, 40) AS n;

INSERT INTO h5p.content_user_data (content_id, user_id, data_type, sub_content_id, user_state)
SELECT c.id, 'user-' || (c.id % 5), 'state', '0', '{"progress":' || c.id || '}'
FROM h5p.contents c;

-- tsvector with a GIN index, as in the tutor package
CREATE TABLE public.fixture_documents (
    id    bigserial PRIMARY KEY,
    body  text NOT NULL,
    tsv   tsvector GENERATED ALWAYS AS (to_tsvector('english', body)) STORED
);
CREATE INDEX fixture_documents_tsv_idx ON public.fixture_documents USING gin (tsv);
INSERT INTO public.fixture_documents (body)
SELECT :'tenant' || ' lesson ' || n || ' about databases and upgrades' FROM generate_series(1, 25) AS n;

-- no primary key: needs REPLICA IDENTITY FULL on the logical-replication path
CREATE TABLE public.fixture_events (
    happened_at timestamptz NOT NULL DEFAULT now(),
    name        text NOT NULL
);
INSERT INTO public.fixture_events (name) SELECT 'event ' || n FROM generate_series(1, 30) AS n;
