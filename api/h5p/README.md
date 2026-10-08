# api-h5p

H5P service for the Ulams LMS. It replaces the PHP H5P package
(`ulams/headless-h5p`) with a Node service built on Lumi's
[h5p-nodejs-library](https://github.com/Lumieducation/H5P-Nodejs-library)
(`@lumieducation/h5p-server` 10.0.4, `@lumieducation/h5p-express` 10.0.5).

It serves the H5P player and editor, the H5P AJAX endpoints, library
administration and a small REST API. The LMS frontends reach it same-origin
at `http://api.localhost/h5p/*` through Caddy.

## Architecture

```
browser / admin / front ──► Caddy (api.localhost)
                              ├─ /h5p/*  ─► h5p:8080  (this service, Express 5)
                              └─ else    ─► Laravel php-fpm

h5p service
  ├─ auth: Passport RS256 JWT (Bearer or ?_token=) → GET {Laravel}/api/profile/me
  │        (roles + permissions, cached in Redis)   | X-Internal-Token → system user
  ├─ TenantResolver ─► Tenant { pg pool, S3 client, JWT key, H5PEditor, H5PPlayer }
  ├─ Postgres  schema "h5p": contents, content_user_data, finished_data, schema_migrations
  ├─ S3/MinIO  bucket "ulams": h5p/content/{id}/…, h5p/temp/…
  ├─ Redis     library cache, content-type cache, profile cache, locks
  └─ volume    /data/libraries  (installed H5P libraries, shared)
```

| Source | Role |
| --- | --- |
| `src/index.ts` | HTTP server, background jobs (Hub cache refresh, temp file sweep), shutdown |
| `src/app.ts` | Express app: CORS, logging, uploads, tenant resolution, auth, routers |
| `src/runtime.ts` | Redis, i18n, shared library storage, tenant resolver |
| `src/tenancy/*` | `TenantResolver` interface, `SingleTenantResolver` (env), `buildTenant` |
| `src/h5p/createH5P.ts` | `H5PConfig`, `UrlGenerator` (`?_token=`), `H5PEditor`, `H5PPlayer` |
| `src/h5p/RedisCache.ts` | cache-manager v4 style cache on Redis (see below) |
| `src/storage/PgContentStorage.ts` | `IContentStorage`: Postgres + S3 (port of `MongoS3ContentStorage`) |
| `src/storage/PgContentUserDataStorage.ts` | `IContentUserDataStorage` (port of `MongoContentUserDataStorage`) |
| `src/storage/S3TemporaryFileStorage.ts` | `ITemporaryFileStorage` on S3 with a key prefix |
| `src/auth/*` | JWT check, profile client, middleware, `LmsPermissionSystem` |
| `src/routes/*` | REST content API, health |
| `src/cli/seed.ts` | Hub installs and .h5p imports for seeding |

Why the cache is custom: the published `@lumieducation/h5p-server@10.0.4`
calls its caches with the cache-manager **v4** API (`set(k, v, {ttl})`,
`wrap`, `reset()`), which cache-manager v5+ and Keyv no longer provide. The
service therefore implements that contract directly on the node-redis client
and does not depend on keyv / @keyv/redis / cache-manager.
`@lumieducation/h5p-redis-lock@10.0.4` needs `redis@^4.7`, so `redis` is pinned
to 4.7.1.

## Environment variables

| Variable | Default | Notes |
| --- | --- | --- |
| `PORT` | `8080` | |
| `LOG_LEVEL` | `info` | pino level |
| `PUBLIC_URL` | `http://api.localhost` | public origin of the service |
| `H5P_ABSOLUTE_URLS` | `false` | `true` makes player/editor models use `${PUBLIC_URL}/h5p/...` instead of `/h5p/...` (needed if a frontend on another origin injects the scripts) |
| `CORS_ORIGINS` | `http://localhost:3000,http://localhost:8000,http://api.localhost` | comma list, `*` allowed; credentials enabled |
| `DATABASE_URL` | – | overrides the `DB_*` connection fields |
| `DB_HOST` / `DB_PORT` | `postgres` / `5432` | |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | `default` / `default` / `secret` | |
| `DB_SCHEMA` | `h5p` | the service's own schema, created and migrated on startup |
| `DB_POOL_MAX` / `DB_SSL` | `10` / `false` | |
| `REDIS_URL` | – | overrides the `REDIS_*` fields |
| `REDIS_HOST` / `REDIS_PORT` / `REDIS_PASSWORD` / `REDIS_DB` | `redis` / `6379` / – / `0` | |
| `REDIS_KEY_PREFIX` | `h5p:` | all keys are namespaced |
| `S3_ENDPOINT` | `http://minio:9000` | empty for AWS |
| `S3_REGION` | `us-east-1` | |
| `S3_KEY` / `S3_SECRET` | `admin` / `minio_secretpassword` | also read from `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` |
| `S3_BUCKET` | `ulams` | |
| `S3_PREFIX` | `h5p` | key prefix inside the bucket |
| `S3_FORCE_PATH_STYLE` | `true` | needed for MinIO |
| `S3_MAX_KEY_LENGTH` | `1024` | lower it (e.g. 255) for MinIO on Windows |
| `JWT_PUBLIC_KEY_PATH` | `/keys/oauth-public.key` | Laravel Passport public key |
| `JWT_PUBLIC_KEY` | – | PEM content instead of a file (`\n` escapes allowed) |
| `JWT_AUDIENCE` / `JWT_ISSUER` | – | optional extra checks (Passport `aud` is the client uuid) |
| `JWT_CLOCK_TOLERANCE` | `30` | seconds |
| `LARAVEL_API_URL` | `http://caddy` | where `/api/profile/me` is called |
| `LARAVEL_API_HOST` | `api.localhost` | `Host` header for that call (Caddy routes on it) |
| `LARAVEL_PROFILE_PATH` | `/api/profile/me` | |
| `PROFILE_CACHE_TTL` | `60` | seconds; never longer than the token lives |
| `H5P_INTERNAL_TOKEN` | – | shared secret for Laravel → H5P calls (`X-Internal-Token`) |
| `H5P_MAX_FILE_SIZE_MB` | `64` | single content file in the editor |
| `H5P_MAX_TOTAL_SIZE_MB` | `256` | .h5p package / upload limit (express-fileupload uses the same value) |
| `H5P_HUB_ENABLED` | `true` | H5P Hub content-type list and installs |
| `H5P_STATE_SAVE_INTERVAL_MS` | `5000` | how often the player saves user state |
| `H5P_TEMP_FILE_LIFETIME_MIN` | `120` | editor uploads not saved into content are deleted after this |
| `H5P_ROOT` | `./h5p` | base for the next three paths in development |
| `H5P_CORE_PATH` / `H5P_EDITOR_PATH` | `$H5P_ROOT/core` / `$H5P_ROOT/editor` | image: `/app/h5p/core`, `/app/h5p/editor` |
| `H5P_LIBRARIES_PATH` | `$H5P_ROOT/libraries` | image: `/data/libraries` (volume) |
| `H5P_TMP_PATH` | `$H5P_ROOT/tmp` | multipart upload temp dir (image: `/tmp/h5p`) |

## Routes

All routes are under `/h5p`. The REST routes answer with the Laravel envelope:
`{"success": true, "data": …, "message": ""}` or `{"success": false, "message": …}`.

| Method & path | Who | Description |
| --- | --- | --- |
| `GET /h5p/health` | anyone | `{ok, db, redis, s3}`; 503 if something is down |
| `GET /h5p/contents` | `h5p_list` (all) or `h5p_author_list` (own only) | `?q=` title filter, `?page=`, `?perPage=` (max 100); `data: [{id, title, mainLibrary, libraryVersion, userId, createdAt, updatedAt}]`, `meta: {current_page, per_page, total, last_page}` |
| `POST /h5p/contents` | `h5p_create` | body `{library: "H5P.MultiChoice 1.16", params: {params, metadata}}` → `{contentId, metadata}` (201) |
| `POST /h5p/contents/upload` | `h5p_create` | multipart field `h5p_file` (.h5p). Installs the package's libraries if the user has `h5p_library_install`/`h5p_library_update` → `{contentId, metadata, installedLibraries}` (201) |
| `GET /h5p/contents/:id` | anyone | row summary + h5p.json metadata |
| `PATCH /h5p/contents/:id` | `h5p_update`, or `h5p_author_update` on own content | same body as POST |
| `DELETE /h5p/contents/:id` | `h5p_delete`, or `h5p_author_delete` on own content | deletes the row, S3 files, user states and results |
| `GET /h5p/contents/:id/play` | anyone | player model (`IPlayerModel`); `?contextId=`, `?asUserId=` (needs `h5p_read`), `?readOnlyState=yes`, `?language=`. `Cache-Control: no-store` |
| `GET /h5p/contents/:id/edit` | `h5p_create` (`:id` = `new`) / edit permission | editor model + `{library, metadata, params}` |
| `GET /h5p/contents/:id/download` | anyone | .h5p package (`Content-Disposition: attachment`) |
| `/h5p/ajax`, `/h5p/content/:id/*`, `/h5p/libraries/:uber/*`, `/h5p/temp-files/*`, `/h5p/params/:id`, `/h5p/contentUserData/*`, `/h5p/finishedData`, `/h5p/core/*`, `/h5p/editor/*`, `/h5p/download/:id` | per Lumi + permission system | Lumi `h5pAjaxExpressRouter` |
| `GET /h5p/libraries`, `GET /h5p/libraries/:uber` | `h5p_library_list` (`h5p_library_read`) | Lumi `libraryAdministrationExpressRouter` |
| `POST /h5p/libraries` | `h5p_library_upload` or `h5p_library_install` | upload a library package (field `file`) |
| `PATCH /h5p/libraries/:uber` | `h5p_library_update` | `{restricted: bool}` |
| `DELETE /h5p/libraries/:uber` | `h5p_library_delete` | |
| `GET/POST /h5p/content-type-cache/update` | library list / install-update | Lumi `contentTypeCacheExpressRouter` (Hub refresh) |

Status codes: 401 when a route needs a logged-in user and the caller is
anonymous (no/invalid/expired token), 403 when the user is logged in but lacks
the permission, 404 for unknown content, 413 for oversized uploads, 422 for an
invalid package.

Lumi's library administration and content-type-cache routers do no
permission checks of their own; the service puts guards in front of them.
Library *files* stay public, because anonymous learners need them.

## Authentication model

1. `X-Internal-Token: $H5P_INTERNAL_TOKEN` → **system user** (every
   permission). A wrong internal token → 401. Use this for Laravel → H5P calls.
2. Passport access token in `Authorization: Bearer …` or in the `?_token=`
   query parameter → verified locally (RS256, Passport public key, `exp`/`nbf`
   with `JWT_CLOCK_TOLERANCE`). The user id is the token `sub`. Name, email,
   roles and permissions come from `GET {LARAVEL_API_URL}/api/profile/me`
   called with the same token, cached in Redis under `sha256(token)` for
   `PROFILE_CACHE_TTL` seconds and never past the token's expiry.
   - Laravel answers 401/403 (e.g. revoked token) → anonymous.
   - Laravel unreachable → the user keeps its id (progress is still saved) but
     gets no permissions.
3. No token or an invalid/expired one → **anonymous** user
   (`{id: 'anonymous', name: 'Anonymous', email: '', permissions: []}`).
   Anonymous learners can play content (preview topics). The player is told not
   to save state or results for them.

**Why `?_token=`.** H5P core makes its own AJAX calls (user state, results,
editor requests) and cannot send an `Authorization` header. The `UrlGenerator`
therefore adds `?_token=<caller's token>` to the AJAX, contentUserData and
finishedData URLs inside the player/editor model. Two consequences:

- **Token expiry.** Passport tokens live about 5 minutes. After the token in
  the model expires, state saves arrive anonymous and fail with 403. **The
  front end re-fetches `GET /h5p/contents/:id/play` whenever it refreshes its
  access token** and re-initialises the player with the new model. The call is
  cheap (one row read plus cached library metadata) and is sent with
  `Cache-Control: no-store`.
- **Logs.** The service redacts `_token` in its own logs, but Caddy's access log
  records full URIs. Add the `log { format filter … }` block from
  `Caddyfile.snippet`, which replaces `_token` and drops `Authorization` /
  `X-Internal-Token`.

### Permissions (`src/auth/PermissionSystem.ts`)

| H5P action | Rule |
| --- | --- |
| Content View / Download / Embed | everyone, including anonymous |
| Content List | `h5p_list`, or `h5p_author_list` (list shows own content only) |
| Content Create | `h5p_create` |
| Content Edit | `h5p_update`, or `h5p_author_update` and owner |
| Content Delete | `h5p_delete`, or `h5p_author_delete` and owner |
| User state / results, own | logged-in users. Anonymous may read its own (always empty) state so the player renders, but never write |
| User state / results, other users | view only, with `h5p_read` (`?asUserId=`) |
| Delete all states of a content (after deleting the content) | content delete permissions |
| CreateRestricted, InstallRecommended, UpdateAndInstallLibraries | `h5p_library_install` or `h5p_library_update` |
| Temporary files (editor uploads) | logged-in users with `h5p_create`, `h5p_update` or `h5p_author_update` |

The owner is `h5p.contents.user_id`: the creator's LMS user id. It does not
change when someone else edits the content.

**Note on `h5p_create`.** When content is saved, Lumi copies its files with an
*Edit* permission check. A role that has `h5p_create` but neither
`h5p_update` nor `h5p_author_update` can create content without files, but
fails on content with images or videos. Give authors `h5p_author_update`
together with `h5p_create`.

## Storage layout

**Postgres** – schema `h5p` (`DB_SCHEMA`) in the LMS database. Laravel's
`migrate:fresh` only touches `public`, so the service owns this schema and
migrates it on startup (`src/db/migrate.ts`: versioned, transactional,
serialised with an advisory lock, recorded in `h5p.schema_migrations`).

| Table | Columns |
| --- | --- |
| `h5p.contents` | `id bigserial` (exposed as string), `user_id text null`, `title`, `main_library` (machine name), `library_version` (`"1.16"`), `metadata jsonb` (h5p.json), `parameters jsonb` (content.json), `created_at`, `updated_at` |
| `h5p.content_user_data` | `content_id → contents ON DELETE CASCADE`, `user_id`, `data_type`, `sub_content_id`, `context_id` (`''` = none), `user_state`, `preload`, `invalidate`; unique per (content, user, type, sub content, context) |
| `h5p.finished_data` | `(content_id, user_id)` PK, `score`, `max_score`, `opened_timestamp`, `finished_timestamp`, `completion_time` |

**S3** (bucket `S3_BUCKET`, prefix `S3_PREFIX`):

- `h5p/content/{contentId}/{path}` – content files (images, video, …)
- `h5p/temp/{filename}` – editor uploads not yet saved. The service deletes
  them after `H5P_TEMP_FILE_LIFETIME_MIN` with a sweep every 30 minutes
  (Redis lock, one replica at a time). Lumi's
  `setBucketLifecycleConfiguration` is **not** used: it replaces every
  lifecycle rule of the bucket with an expire-everything rule, which would
  delete the LMS's files in the shared `ulams` bucket.

`ulams` is public-read, so content files can also be read directly from
`http://storage.localhost/ulams/h5p/content/...` (the PHP package behaved the
same way). The service streams them itself (with HTTP ranges) under
`/h5p/content/{id}/...`.

**Libraries** – `FileLibraryStorage` in `/data/libraries` (Docker volume
`h5p_libraries`, shared by all replicas and tenants), wrapped in
`CachedLibraryStorage` with the Redis cache (`h5p:lib:*`). With several
replicas the volume must be shared (e.g. NFS/EFS). Libraries are also cached
in Redis, so after manual changes on the volume run
`redis-cli --scan --pattern 'h5p:lib:*' | xargs redis-cli del`.

**H5P core/editor client files** are downloaded at image build time from the
commits Lumi pins (`scripts/download-core.sh`, core
`2aeb0b83fa603e331381b3a6b8bf42c3773ba140`, editor
`ab2daa18bd61b19e7f8729e22eec88f3b637a868`) into `/app/h5p/core` and
`/app/h5p/editor`.

## Adding content types

- **Editor**: users with `h5p_library_install` / `h5p_library_update` see the
  H5P Hub in the editor's content-type selector and can install from there.
- **Package upload**: `POST /h5p/contents/upload` (or the editor's upload tab)
  installs every library inside the package when the caller may install
  libraries.
- **Library package**: `POST /h5p/libraries` with field `file`.
- **CLI**: `npm run seed -- --hub H5P.MultiChoice,H5P.DragQuestion`
  (in the container: `docker compose exec h5p node dist/cli/seed.js --hub …`).
- Restrict a content type: `PATCH /h5p/libraries/H5P.Foo-1.2 {"restricted": true}`.
  Restricted types need `h5p_library_install` / `h5p_library_update` to use.

## Seeding

```
node dist/cli/seed.js --help
node dist/cli/seed.js --samples                     # curated examples
node dist/cli/seed.js --samples multiple-choice,drag-and-drop
node dist/cli/seed.js --hub H5P.MultiChoice ./packages/ https://example.org/x.h5p
node dist/cli/seed.js --list-samples
```

The seeder runs as the system user. Logs go to stderr; stdout gets a JSON
report `{"hub": […], "results": […], "mapping": {"<sample key or source>": "<contentId>"}}`.
`--out file.json` also writes the report to a file. The exit code is 2 if any
item failed.

Curated samples (`src/cli/samples.ts`, all checked 2026-10-08). They come from
the export directory the PHP seeder used. Exports that now return 404 come from
the H5P Hub content-type package instead, which bundles demo content:

| Key | Type | Source |
| --- | --- | --- |
| image-hotspots | Image Hotspots | `https://api.h5p.org/v1/content-types/H5P.ImageHotspots` (h5p.org export 404; demo has no background image) |
| drag-and-drop | Drag and Drop | `https://h5p.org/sites/default/files/h5p/exports/drag-and-drop-712.h5p` |
| dialog-cards | Dialog Cards | `…/exports/dialog-cards-620.h5p` |
| flashcards | Flashcards | `…/exports/flashcards-51-111820.h5p` |
| branching-scenario | Branching Scenario | `https://api.h5p.org/v1/content-types/H5P.BranchingScenario` |
| interactive-video | Interactive Video | `https://api.h5p.org/v1/content-types/H5P.InteractiveVideo` (export 404) |
| multiple-choice | Multiple Choice | `…/exports/multiple-choice-713.h5p` |
| course-presentation | Course Presentation | `https://api.h5p.org/v1/content-types/H5P.CoursePresentation` (export 404) |
| true-false | True/False | `…/exports/true-false-question-34806.h5p` |
| fill-in-the-blanks | Fill in the Blanks | `…/exports/fill-in-the-blanks-837.h5p` |
| memory-game | Memory Game | `…/exports/memory-game-5-708.h5p` |

## Laravel integration

- **Routing**: Caddy sends `api.localhost/h5p/*` to `h5p:8080`
  (`Caddyfile.snippet`, placed before the php_fastcgi handling, with
  `request_body max_size 300MB` for that path). Frontends call
  `/h5p/contents/:id/play` with their normal Bearer token.
- **Server-to-server**: Laravel calls the service with
  `X-Internal-Token: $H5P_INTERNAL_TOKEN` (system user), e.g. to delete content
  or run imports.
- **Reading content**: Laravel may read `h5p.contents` directly, **read-only**
  (for search indexing, or to check that a lesson's `contentId` exists, its
  title and main library). It must not write to the `h5p` schema; migrations
  belong to this service. Ids are `bigint` (strings in the API).
- **Results**: `h5p.finished_data` has per-user scores (`user_id` = LMS user
  id as text) that Laravel can read for progress reports.

## Multi-tenancy (designed, not built)

Everything that differs per tenant sits behind `TenantResolver`
(`src/tenancy/types.ts`):

```ts
interface TenantResolver {
  resolve(req): Promise<Tenant | undefined>; // by X-Forwarded-Host, then Host
  get(id): Promise<Tenant>;                   // CLI / jobs
  active(): Tenant[];                         // background jobs
  close(): Promise<void>;
}
```

A `Tenant` holds its own Postgres pool (schema `h5p` inside **that tenant's
database**), S3 client and bucket, Passport public key / JWT verifier, Laravel
profile endpoint and host, internal token, and its own `H5PEditor`/`H5PPlayer`.
The library volume, library cache, Redis lock provider and i18n are shared
(`SharedH5P`). Per-tenant Redis keys are namespaced `h5p:t:<tenantId>:…`.
The Express app resolves the tenant per request, and auth uses the tenant's
key. Lumi's routers are built once per tenant and cached.

Today `SingleTenantResolver` builds one tenant from the environment and serves
it for every host. A `MultiTenantResolver` only has to:

1. map the request host (`requestHost()`) to `TenantSettings` (from a registry
   table, a JSON file or a Laravel endpoint);
2. call `buildTenant(app, shared, settings, logger)` on first use (this runs
   migrations in the tenant's `h5p` schema) and cache the result, ideally with
   an LRU that `close()`s idle tenants' pools;
3. return `undefined` for unknown hosts (the app answers 404).

Then pass it to `createRuntime(config, logger, (shared) => MultiTenantResolver.create(...))`.
Caddy must forward the original host (`header_up X-Forwarded-Host {host}`).

## Development

```
npm ci
npm run download:core           # H5P core + editor into ./h5p
npm run dev                     # tsx watch; needs postgres/redis/minio reachable
npm run lint && npm run build
```

Tests (vitest) include integration tests against the real Postgres, Redis and
MinIO. Run them in a container on the `ulams` network. Each run uses a
throwaway schema `h5p_test_<random>` and S3 prefix `h5p-test-<random>`, and
removes both afterwards:

```
docker run --rm --network ulams -v "$PWD":/app -v api_h5p_test_nm:/app/node_modules \
  -w /app -e REDIS_PASSWORD=ulams node:22-alpine sh -c "npm ci && npm test"
```

(The separate `node_modules` volume keeps Linux binaries apart from a host
install.)

Docker:

```
docker build -t ulams/h5p:dev .
docker compose -f docker-compose.yml -f h5p/compose.h5p.yml up -d h5p
```

## License

This service builds on Lumi's h5p-nodejs-library, licensed
**GPL-3.0-or-later**. `PgContentStorage`, `PgContentUserDataStorage` and
`S3TemporaryFileStorage` are ports of Lumi's `h5p-mongos3` classes. The H5P
core and editor client files (h5p-php-library, h5p-editor-php-library) are
**GPL-3.0**, as was the PHP H5P package this service replaces. The service is
therefore distributed as GPL-3.0-or-later (`package.json` `license`).
