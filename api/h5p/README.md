# api-h5p

H5P service for the Ulams LMS. It replaces the PHP H5P package
(`ulams/headless-h5p`) with a Node service built on Lumi's
[h5p-nodejs-library](https://github.com/Lumieducation/H5P-Nodejs-library)
(`@lumieducation/h5p-server` 10.0.4, `@lumieducation/h5p-express` 10.0.5).

It serves the H5P player and editor, the H5P AJAX endpoints, library
administration and a small REST API. The LMS frontends reach it same-origin
at `http://<tenant>.localhost/h5p/*` (platform: `http://api.localhost/h5p/*`)
through Caddy. It is multi-tenant: see [Multi-tenancy](#multi-tenancy).

## Architecture

```
browser / admin / front ──► Caddy (<tenant>.localhost, api.localhost)
                              ├─ /h5p/*  ─► h5p:8080  (this service, Express 5)
                              └─ else    ─► Laravel php-fpm

h5p service
  ├─ auth: Passport RS256 JWT (Bearer or ?_token=) → GET {Laravel}/api/profile/me
  │        (roles + permissions, cached in Redis)   | X-Internal-Token → system user
  ├─ TenantResolver ─► Tenant { pg pool, S3 client, JWT key, H5PEditor, H5PPlayer }
  │        (host → Laravel env file .env.<host>; platform → .env)
  ├─ Postgres  schema "h5p" in each tenant DB: contents, content_user_data, finished_data, schema_migrations
  ├─ S3/MinIO  tenant bucket (ulams-<slug>): h5p/content/{id}/…, h5p/temp/…
  ├─ Redis     library cache, content-type cache, profile cache, locks
  └─ volume    /data/libraries  (installed H5P libraries, shared)
```

| Source | Role |
| --- | --- |
| `src/index.ts` | HTTP server, background jobs (Hub cache refresh, temp file sweep), shutdown |
| `src/app.ts` | Express app: CORS, logging, uploads, tenant resolution, auth, routers |
| `src/runtime.ts` | Redis, i18n, shared library storage, tenant resolver |
| `src/tenancy/*` | `TenantResolver` interface, `EnvFileTenantResolver` (Laravel env files), `SingleTenantResolver` (env), `buildTenant`, `.env` parser |
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
| `TENANCY_MODE` | `env-files` if `ENV_DIR` is set, else `single` | `single`: one tenant from the variables above; `env-files`: see [Multi-tenancy](#multi-tenancy) |
| `ENV_DIR` | – | directory with Laravel's `.env` and `.env.<host>` files (compose: `/laravel`, a read-only mount of `api/`) |
| `KEYS_DIR` | `$ENV_DIR/storage` | Laravel storage dir with `oauth-public.key` and `<host_with_underscores>/oauth-public.key` |
| `PLATFORM_HOSTS` | `api.localhost` | comma list of hosts served by the platform tenant (`.env`) |
| `TENANT_FRONT_ORIGIN_PATTERNS` | `http://{slug}.app.localhost,https://{slug}.app.localhost,http://{slug}.app.localhost:4321,http://{slug}.admin.localhost,https://{slug}.admin.localhost,http://{slug}.admin.localhost:8000` | per-tenant CORS / frame-ancestors origins added to `CORS_ORIGINS` (exact origins; ports matter) |
| `TENANT_RELOAD_CHECK_MS` | `2000` | how often env/key files and host lookups are re-checked |
| `TENANT_DB_POOL_MAX` | `5` | Postgres pool size per tenant (env-files mode) |
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
| `POST /h5p/contents/orphans/delete` | internal token only | deletes the S3 files of content ids that have no row (left by an interrupted import or delete) in the request's tenant (its bucket and prefix) → `{contentIds, files}`. Used by the demo reset (`api/packages/demo`) |
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

| Method & path | Who | Description |
| --- | --- | --- |
| `GET /h5p/embed/play/:id` | anyone | player page for iframes; `?language=`, `?contextId=`, `?readOnlyState=yes`, `?hideActions=1` |
| `GET /h5p/embed/edit/:id` | anyone (its API calls need `h5p_create` / edit rights) | editor page; `:id` may be `new`; `?language=` |
| `GET /h5p/embed/assets/{player,editor}.js` | anyone | the pages' scripts (Lumi web components + glue, bundled by `scripts/build-embed.mjs` from `embed-client/`) |

## Embedding (iframe + postMessage)

The LMS frontends (admin, front) never load H5P or Lumi code. They frame the
embed pages and talk to them with `window.postMessage`:

```
admin/front (MIT)                                  H5P service page (GPL), in the iframe
 <iframe src="{API}/h5p/embed/play/12?language=pl">
                                  <── ulams-h5p:ready {mode, contentId}   (repeated until answered)
 ulams-h5p:style {css?, urls?} ──>                                       (optional, before the token)
 ulams-h5p:token {token|null}  ──>                                       page fetches /h5p/contents/12/play
                                  <── ulams-h5p:loaded {contentId, title, library}
                                  <── ulams-h5p:resize {height}
                                  <── ulams-h5p:xapi {statement, contentId}
 ulams-h5p:token {newToken}    ──>                                       after every token refresh
 ulams-h5p:save                ──>                                       editor only
                                  <── ulams-h5p:saved {contentId, metadata}  (editor)
                                  <── ulams-h5p:error {message, code?}       (load | save | validation)
```

| Message | Direction | Payload |
| --- | --- | --- |
| `ulams-h5p:ready` | page → parent | `{mode: 'play' or 'edit', contentId}`; no secrets; posted to each allowed origin every 500 ms (max 20×) until the parent answers |
| `ulams-h5p:token` | parent → page | `{token: string or null}`; the first one starts loading (null = anonymous play), later ones refresh the token. A player that gets no token within 5 s plays anonymously |
| `ulams-h5p:style` | parent → page | `{css?: string, urls?: string[]}`; applied to the page and, for the iframe embed type, inside the content iframe. URLs must be on the page origin or an allowed origin |
| `ulams-h5p:loaded` | page → parent | `{contentId, title?, library?}` |
| `ulams-h5p:resize` | page → parent | `{height}`: document height in px |
| `ulams-h5p:xapi` | page → parent | `{statement, contentId}`: every xAPI statement of the content and its sub-content |
| `ulams-h5p:save` | parent → editor | `{}` |
| `ulams-h5p:saved` | editor → parent | `{contentId, metadata}`; new content gets its id here |
| `ulams-h5p:error` | page → parent | `{message, code?: 'load', 'save' or 'validation'}` |

Security rules:

- **Origins.** The page accepts messages only from `window.parent` and an
  origin in `CORS_ORIGINS` (`*` disables the check). After the first accepted
  message it talks only to that origin and posts to it by name, never to `*`.
  The parent accepts messages only from the iframe's `contentWindow` with the
  API origin and posts only to that origin.
- **Framing.** Embed pages send `Content-Security-Policy: frame-ancestors 'self'
  <CORS_ORIGINS>` and `Referrer-Policy: no-referrer`.
- **Tokens** travel only in postMessage and in the `Authorization` header of
  the page's same-origin API calls (plus H5P core's `?_token=`, see above).
  They are never part of an embed URL and the page never logs them.

The page code lives in `embed-client/` (`protocol.ts` has the message types).
The frontends keep their own MIT copies of the types in
`front/src/lib/sdk/types/h5p.ts` and `admin/src/components/H5P/utils.ts`.

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
  the model expires, state saves arrive anonymous and fail with 403. The LMS
  frontends therefore send every refreshed token to the embed page
  (`ulams-h5p:token`, see "Embedding"); the player page re-fetches
  `GET /h5p/contents/:id/play` with it and swaps the AJAX URLs in place (the
  running content is not reloaded), the editor page swaps the token inside its
  model's `ajaxPath`. `/play` is cheap (one row read plus cached library
  metadata) and is sent with `Cache-Control: no-store`.
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

- **Routing**: Caddy sends `<host>.localhost/h5p/*` (tenants and
  `api.localhost`) to `h5p:8080` (`Caddyfile.snippet`, placed before the
  php_fastcgi handling, with
  `request_body max_size 300MB` for that path). Frontends call
  `/h5p/contents/:id/play` with their normal Bearer token.
- **Server-to-server**: Laravel calls the service with
  `X-Internal-Token: $H5P_INTERNAL_TOKEN` (system user), e.g. to delete content
  or run imports, plus `X-Forwarded-Host: <tenant host>` to select the tenant.
- **Reading content**: Laravel may read `h5p.contents` (in its own tenant database) directly, **read-only**
  (for search indexing, or to check that a lesson's `contentId` exists, its
  title and main library). It must not write to the `h5p` schema; migrations
  belong to this service. Ids are `bigint` (strings in the API).
- **Results**: `h5p.finished_data` has per-user scores (`user_id` = LMS user
  id as text) that Laravel can read for progress reports.

## Multi-tenancy

The Laravel API is multi-tenant through gecche/laravel-multidomain: tenant
`<slug>` is served at `<slug>.localhost` with its own env file
`api/.env.<slug>.localhost` and Passport keys in
`api/storage/<slug>_localhost/`. The platform is `api.localhost` with `api/.env`
and `api/storage/oauth-public.key`. With `TENANCY_MODE=env-files` the service
follows the same files (`src/tenancy/EnvFileTenantResolver.ts`):

| Request host (X-Forwarded-Host, else Host) | Env file | Passport key | Tenant id |
| --- | --- | --- | --- |
| in `PLATFORM_HOSTS` (`api.localhost`) | `$ENV_DIR/.env` | `$KEYS_DIR/oauth-public.key` | `default` |
| `coffee.localhost` | `$ENV_DIR/.env.coffee.localhost` | `$KEYS_DIR/coffee_localhost/oauth-public.key` | `coffee_localhost` |
| `x.coffee.localhost` (no own file) | leftmost labels are stripped while a tenant file exists further down → `.env.coffee.localhost` | same | `coffee_localhost` |
| anything else (`evil.localhost`, `h5p:8080`, subdomains of the platform host) | – | – | **404** `{"success":false,"message":"Unknown tenant."}` |

Unlike gecche there is no final fallback to `.env`: an unknown host never
reaches the platform's data.

From the tenant's env file the service takes:

- **Postgres**: `DB_HOST`, `DB_PORT`, `DB_DATABASE` (required), `DB_USERNAME`,
  `DB_PASSWORD`. The service's tables live in schema `h5p` (`DB_SCHEMA`) of
  that database; migrations run on the tenant's first request. Pool size
  `TENANT_DB_POOL_MAX`. `DATABASE_URL` of the service is never used.
- **S3**: `AWS_BUCKET` (required), `AWS_ENDPOINT`, `AWS_DEFAULT_REGION`,
  `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_USE_PATH_STYLE_ENDPOINT`
  (the service's `S3_*` values fill in what is missing, except the bucket).
  Objects go under `S3_PREFIX` (`h5p/`) inside the tenant bucket.
- **Auth**: the Passport public key file above (or `PASSPORT_PUBLIC_KEY` in the
  env file); profile calls go to `LARAVEL_API_URL` (`http://caddy`) with
  `Host: <tenant host>` (platform: the host of `APP_URL`);
  `H5P_INTERNAL_TOKEN` from the file, else the service's own.
- **Origins**: `CORS_ORIGINS` + `TENANT_FRONT_ORIGIN_PATTERNS` with `{slug}`
  = `TENANT_SLUG` (else the first host label) + the origins of `FRONTEND_URL`
  and `ADMIN_URL`, ports included.
  They drive CORS and the embed pages' `frame-ancestors` / postMessage
  allow-list, so `coffee.app.localhost` can frame `coffee.localhost/h5p/embed/*`
  but not `oncall.localhost/h5p/embed/*`.

Tenants are built lazily and cached. Every `TENANT_RELOAD_CHECK_MS` the
resolver re-checks the files (mtime + size, which works on Docker bind
mounts where `fs.watch` does not): a new `.env.<host>` works without a
restart; a change that alters the tenant's settings rebuilds the tenant (the
old pools close after 60 s); an invalid change is logged and the previous
configuration kept; a deleted env file evicts the tenant. A failed first
build (e.g. database down) is retried on the next request.

Shared by all tenants: the library volume (installing or deleting a library
affects every tenant), the library cache, the Redis lock provider and i18n.
Per-tenant Redis keys are namespaced `h5p:t:<tenantId>:…` (content type
cache, Hub uuid, profile cache).

`/h5p/health` answers for the request's tenant
(`{ok, tenant, db, redis, s3}`), 404 for unknown hosts and a liveness answer
(`{ok, tenant: null, redis}`) for loopback hosts, which the image's
`HEALTHCHECK` uses.

**Server-to-server calls** (Laravel → `http://h5p:8080`) must name the
tenant: send `X-Forwarded-Host: <tenant host>` (e.g. the host of `APP_URL`)
together with `X-Internal-Token`. Without it the host is `h5p` and the call
gets 404.

Caddy must forward the original host (`header_up X-Forwarded-Host {host}`);
the `http://*.localhost` site of `api/docker/conf/Caddyfile` sends `/h5p/*` of
every tenant host to the service. In compose, `api/` is mounted read-only at
`/laravel` (`ENV_DIR=/laravel`, `KEYS_DIR=/laravel/storage`).

`TENANCY_MODE=single` keeps the old behaviour: one tenant (`default`) from the
`DB_*` / `S3_*` / `JWT_*` variables, served for every host. A different
registry (a table, a Laravel endpoint) only needs another `TenantResolver`
that maps `requestHost()` to `TenantSettings` and calls `buildTenant()`.

## Development

`api-h5p` is a Yarn workspace of the monorepo (root `yarn.lock`, no
package-lock). From the repository root:

```
corepack yarn install
corepack yarn workspace api-h5p download:core   # H5P core + editor into api/h5p/h5p
corepack yarn workspace api-h5p dev             # tsx watch; needs postgres/redis/minio reachable
corepack yarn turbo run build typecheck lint test --filter=api-h5p
```

`build` runs `tsc` and then `scripts/build-embed.mjs` (esbuild bundles
`embed-client/*.ts` with `@lumieducation/h5p-webcomponents` into
`dist/embed/`). `test` runs the unit suites only. The integration suites
(`test/http.test.ts`, `test/pg-storage.test.ts`) need the real Postgres, Redis
and MinIO, so run `test:integration` in a container on the `ulams` network.
Each run uses a throwaway schema `h5p_test_<random>` and S3 prefix
`h5p-test-<random>` and removes both.

Docker (build context = repository root; `api/h5p/Dockerfile.dockerignore` is
an allow-list):

```
docker build -f api/h5p/Dockerfile -t ulams/h5p:dev .
# compose (from api/): build: { context: .., dockerfile: api/h5p/Dockerfile }
```

## License

This service builds on Lumi's h5p-nodejs-library, licensed
**GPL-3.0-or-later**. `PgContentStorage`, `PgContentUserDataStorage` and
`S3TemporaryFileStorage` are ports of Lumi's `h5p-mongos3` classes. The H5P
core and editor client files (h5p-php-library, h5p-editor-php-library) are
**GPL-3.0**, as was the PHP H5P package this service replaces. The service is
therefore distributed as GPL-3.0-or-later (`package.json` `license`).

### Licensing boundary

- api-h5p is a **separate program** under GPL-3.0-or-later. Everything GPL
  (`@lumieducation/*`, the H5P core/editor JS, the bundled embed scripts) is
  installed, bundled and served only by this service.
- The MIT/proprietary parts (admin, front, the Laravel API) talk to it only
  over HTTP and `postMessage` (iframes, see "Embedding"); they do not link,
  import or bundle any of its code. admin and front enforce this with an
  ESLint `no-restricted-imports` rule on `@lumieducation/*` (front also runs
  `yarn lint:gpl`, which covers `src/lib`). Do not import this workspace or
  its dependencies from other workspaces.
- The service ships its own source: this directory (including
  `embed-client/` and the build scripts) is the complete corresponding source
  of the image and of the scripts it serves; the bundles carry source maps.
