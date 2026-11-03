# Plan: image service as a deployment choice, with Cloudflare Image Transformations on the hosted demos and the landing

Status: draft, waiting for owner approval. ADR 0193 is Proposed with this plan. Nothing here is implemented.
TODO item: Phase 8 "(new) Image service as a deployment choice" in `docs/ROADMAP-TODO.md`.

Owner decisions (2026-10-22):

1. "Replace the PHP image resize with Cloudflare Image Transformations." Images > Transformations is enabled on
   the `ulams.app` zone and verified (`https://<host>/cdn-cgi/image/width=640,format=auto,quality=78/<source>`
   returns resized files).
2. Scope correction, same day: **do not remove** `api/packages/images` or `/api/images/img`. Self-hosters and
   other clients keep it. Stop using PHP resizing on the hosted demo academies and the `ulams.app` landing, and
   make the image service a deployment choice.

Facts below come from `main` at `50fcd03be` (2026-10-22) and from the open PR #404. Paths are relative to the
repo root.

---

## 1. Goal

- Every client that shows images builds its image URLs through **one URL builder with one set of presets**. The
  builder's mode is a deployment setting: `cloudflare`, `astro` (sharp, `/_image`), `noop` (the original file) or
  `php` (the existing `/api/images/img`, kept as an optional legacy API).
- The hosted demo academies (MyDevil staging, `*-staging.ulams.app`) and the `ulams.app` landing (Cloudflare
  Pages) run in `cloudflare` mode. No request from them reaches `/api/images/img` or an `imgcache/` URL.
- Each mode can be switched to `noop` (originals) without a code change. In `cloudflare` mode a failed
  transformation also falls back to the original by itself (`onerror=redirect`).
- Nothing changes for a self-hoster who changes no setting.

## 2. TODO items covered

- Phase 8, new: "Image service as a deployment choice (`cloudflare | astro | noop | php`), one URL builder and
  presets, the hosted demos and the landing on Cloudflare Image Transformations, the PHP images API kept as an
  optional legacy API". Milestones IMG-1 to IMG-7 below.
- Touches, but does not tick: "Shared-hosting variant" (Phase 8; MyDevil build steps change) and the
  `sharp` dependency hold (0.1c; `cloudflare` mode does not need sharp on the hosts that cannot run it).

## 3. What exists

### 3.1 The PHP images package (kept)

`api/packages/images` (provider `Ulams\Images\UlamsImagesServiceProvider`, registered in `api/config/app.php`):

- `GET /api/images/img?path=&w=&h=&size=&format=` renders the image and answers 302 to the cached file.
  `POST /api/images/img` renders a batch (at most 20 paths, 4096 px per side; `ImagesRenderRequest`) and answers
  JSON. Both use the `images.render` rate limiter (POST is always limited). Routes: `src/routes.php`.
- `ImagesService::render()` reads the source with `Storage::get()`, resizes with `intervention/image` ^2 on GD
  (`api/config/image.php`, `driver => gd`), writes `imgcache/<sha1(path + json(params))>.<ext>` to the default
  disk, runs `spatie/image-optimizer` (through `spatie/laravel-image-optimizer` ^1.7.1) only on a local disk, and
  records a row in `image_caches` (`path`, `hash_path`; migration `2022_04_21_123405_create_image_caches_table`).
  Errors become an SVG with the message.
- Limits in `src/config.php` (`images.public.*`: max 1000×1000, min 10, a `thumbnail` size 400×300) and
  administrable rate-limit settings (`SettingsServiceProvider`).
- `CustomFilesystemManager` replaces the app's `filesystem` service: every `put`/`delete` fires
  `FileStored`/`FileDeleted`, and a listener deletes cached renders of that directory (`LIKE` query on
  `image_caches`).
- `images:cache-clear` deletes `imgcache/`.
- Tests: `tests/Api/ImagesTest.php`, `tests/Api/SettingsTest.php`, `tests/Feature/*`,
  `tests/Services/ImagesServiceS3Test.php`; three of them are in `exclude-from-classmap` of `api/composer.json`.
- Swagger: `src/Http/Controllers/Swagger/ImagesControllerSwagger.php`; `front/cli/spec/openapi.json`,
  `front/sdk/src/generated/openapi.ts` and `sdk-php/lib/Api/ImagesApi.php` are generated from it.
- Coverage rules: `front/cli/spec/exclusions.yaml` (`"* /api/images/img"`, browser-only),
  `front/cli/spec/untyped-responses.yaml` (`GET` is `redirect`), `front/cli/coverage/matrix.{json,md}`,
  `api/tests/fixtures/public-routes.php` (`api/images/img`, kind `open`), `api/packages/auth/resources/token-scopes.php`
  (`api/images/*` in scope `courses`).
- Docker: `api/docker/php/install.sh` installs `jpegoptim`, `optipng`, `pngquant`, `gifsicle` for the optimiser
  and builds `gd`; `api/docker/php/README.md` and `NOTICE` list them (GPL tools, source offer).
- Other packages that write `/api/images/img` URLs **into stored content**:
  - `courses-import-export` `ExportImportService::updateRichTextTopicableValue()` rewrites imported rich-text
    images to `url('api/images/img') . '?path=…&w=1000'`.
  - `topic-types` `Helpers/Markdown.php` (`convertAssetPaths`, `convertFilePathByHttp`) writes
    `url('api/images/img?path=') . $destination` and parses such URLs back (`getImagesPathsWithoutImageApi`).
  - `admin` `WysiwygMarkdown` inserts `resizedImage(path, 1000)` (a `/api/images/img` URL on the API host) into
    the markdown it saves.

Upload-time optimisation does not exist: the optimiser runs only on cached renders, and the
`spatie/laravel-image-optimizer` middleware is not registered anywhere. GD stays in the PHP image regardless
(`phpoffice/phpspreadsheet` requires `ext-gd`; tests use `imagecreatetruecolor`).

### 3.2 Consumers

| Client | Where | Today | Hosted on the demos? |
|---|---|---|---|
| Reference front | `front/ui/src/components/Img.astro`, used by 9 `@ulams/ui` components (`Hero`, `HeritageStory`, `FrameSlider`, `Letter`, `Showcase`, `Figure`, `CourseHeader`, `People`, `Events`) and 2 `front/web` pages (`account`, `events/[kind]/[id]`) | Astro `<Image>` through `/_image` (sharp), widths 360–1600, WebP, quality 78; `ULAMS_IMAGE_SERVICE=noop` (build time, `front/web/astro.config.mjs`) swaps in Astro's noop service; `front/web/src/lib/image-cache.ts` caches `/_image` in memory | yes (MyDevil, built with `noop`) |
| Reference front, raw `<img>` | `BlogPost`, `BlogIndex`, `HeroShowcase` (poster), `SiteHeader` (tenant logo), `markdown-basic.ts` (rich-text images), `elements/showcase.ts`, `InteractiveLesson` | original file | yes |
| PR #404 (open) | `Img.astro` | adds `noop` → plain `src` (no fake srcset) and `cloudflare` → `/cdn-cgi/image/width=W,format=auto,quality=78/<src>` srcset; reads `process.env.ULAMS_IMAGE_SERVICE` first, so the mode is a runtime setting on the SSR server | — |
| Landing | `deploy/mydevil/cloudflare/landing-static.mjs` exports the SSR build to Cloudflare Pages; comment says "build with `ULAMS_IMAGE_SERVICE=noop`" | originals (76 images under `front/web/public`) | yes (`ulams.app`) |
| Admin | `admin/src/components/ResponsiveImage` (course cards in `Dashboard/YourCourses` at 240 px, avatars in `Users/myProfile` and `Users/User/form` at 600 px) and `admin/src/services/ulams/files.ts` `resizedImage()` (used by `WysiwygMarkdown`) | `/api/images/img?path=…&w=…` on `REACT_APP_API_URL` | yes (`*-staging-admin.ulams.app`) |
| Legacy learner app | `front/src`: `TutorsSection`, `Book/BookTermModal/SelectedTermContent`, `Events/Event/EventTutor`, `pages/courses/course/Components/CourseAuthor` build `/api/images/img?path=<avatar>`; `lib/sdk/react/components/Image` guesses `imgcache/<sha1>.<ext>` on `VITE_APP_PUBLIC_IMG_URL` and falls back to the API | PHP | no (the demos run `front/web`) |
| Docs | `creators/create-a-course.mdx` (media row), `developers/api-packages.mdx` (images section), `developers/performance.mdx` (Images), `operators/install-mydevil.mdx` (sharp/noop), `developers/reference-frontend.mdx` (landing export), `developers/container-images.mdx` (optimiser binaries) | describe PHP and noop | — |

The demo seeds (`api/database/seeds/Demo/*`) store `Storage::url()` of uploaded assets, so seeded content holds
plain public storage URLs, not `/api/images/img`. Avatars are stored at `avatars/<user id>/<slug of file name>`
(`FileHelper::getFilePath`), course assets at `course/<id>/assets/<name>`. The hourly reset runs
`migrate:fresh` and reseeds, so ids and file names come out the same and the public URLs are stable across
resets. `image_caches` on a demo tenant only fills when something calls the PHP endpoint, and every reset
empties it.

### 3.3 Storage and hosts

- Staging (MyDevil): files are in R2 and served by the `ulams-storage` Worker on `staging-storage.ulams.app`
  (`deploy/mydevil/cloudflare/storage.worker.js`; `cache-control: public, max-age=300`, `access-control-allow-origin: *`).
  Fronts, admins and APIs are `*-staging*.ulams.app`. All in the `ulams.app` zone.
- Landing: Cloudflare Pages on the `ulams.app` custom domain; images are its own `/landing/…` files.
- VPS reference (`deploy/vps-cloudflare`): everything behind Cloudflare (tunnel), R2 with custom domains; the
  operator's own zone.
- Docker/Caddy self-host and local dev: SeaweedFS or MinIO behind Caddy on `*.localhost` or the operator's domain;
  no Cloudflare.
- CSP: `front/web/src/lib/csp.ts` allows `'self'` and `https:` in `img-src`, so a same-host `/cdn-cgi/image/…`
  URL needs no change. The content origin (`content.worker.js`, `Caddyfile`, `InteractiveCsp.php`) has
  `img-src 'self' data: blob:`; interactive packages keep using their own relative files and are not touched.

### 3.4 Cloudflare facts that shape the design (developers.cloudflare.com, checked 2026-10-22)

- Images Free plan: 5,000 **unique** transformations per calendar month (source + option set; repeat requests in
  the month are not counted again). Over the limit, cached transformations keep working, new ones fail with
  error 9422, and nothing is charged. Paid: $0.50 per 1,000 unique transformations.
- `onerror=redirect` sends the browser to the original when the transformation fails (quota, bad file, origin
  error). It works only when the source is in the same zone (subdomains accepted).
- Sources: by default only the zone serving `/cdn-cgi/image/` is accepted. Other origins must be allow-listed
  (wildcard `*.example.com` allowed); "any origin" lets third parties use our quota.
- Error 9524: the transformation fails when the image URL is intercepted by a Worker, and on a `pages.dev` host
  (use the custom domain). Our staging sources come from a Worker-served host; the owner's check passed, but
  IMG-6 re-verifies it per host (section 13).
- Transformations analytics: Images > Transformations > Analytics (sampled), with top configurations and origins.

## 4. Scope

In:

- A dependency-free URL builder and presets in `@ulams/sdk` (IMG-1).
- `front/ui` and `front/web` on the builder, superseding the `Img.astro` part of PR #404 (IMG-2).
- Admin on the builder, with a runtime setting (IMG-3).
- API settings so that imports on demo tenants store original URLs, and the package labelled "legacy, optional"
  (IMG-4).
- The legacy learner app on the builder (IMG-5, lowest priority; not hosted).
- MyDevil, landing and VPS deploy files, the storage Worker cache header, an optional WAF rule (IMG-6).
- Docs (IMG-7, plus the docs each PR touches).

Out:

- Removing or deprecating-for-removal `api/packages/images`, its routes, tests, table, config, optimiser
  binaries, OpenAPI entries or coverage rules. They stay as they are.
- Rewriting stored content on tenants that are not demos. Existing `/api/images/img` URLs in content keep working
  because the endpoint stays.
- Cloudflare Images storage (`imagedelivery.net`) and the Images Worker binding.
- An imgproxy/thumbor profile (section 6.4).

## 5. Milestones

Each is one PR against `main` with its own tests; IDs are for commits and the TODO note.

| ID | PR | Depends on | Demo after merge |
|---|---|---|---|
| IMG-1 | `@ulams/sdk` image URL builder, presets, unit tests | — | none (library) |
| IMG-2 | `Img.astro` and the raw `<img>` sites in `front/ui`/`front/web` on the builder; runtime mode; rich text through the builder | IMG-1 (#404 merged or closed first) | local front with `ULAMS_IMAGE_SERVICE=cloudflare` renders `/cdn-cgi/image` srcsets |
| IMG-6a | MyDevil front and landing builds in `cloudflare` mode, smoke script, staging notes | IMG-2 | landing and the six demo fronts serve transformed images |
| IMG-3 | Admin `ResponsiveImage`, `resizedImage`, `WysiwygMarkdown` on the builder; `REACT_APP_IMAGE_SERVICE` | IMG-1 | staging admin course cards and avatars via `/cdn-cgi/image` |
| IMG-6b | Admin runtime config on MyDevil, storage Worker image cache header, optional WAF rule | IMG-3 | — |
| IMG-4 | `IMAGES_CONTENT_URLS=api|original` for imports, demo tenants on `original`; "legacy" labels; seed checks | — | importing a course on a demo tenant stores plain storage URLs |
| IMG-5 | Legacy learner app on the builder (`VITE_ULAMS_IMAGE_SERVICE`, default `php`) | IMG-1 | — |
| IMG-7 | Operators page "Image service", the other docs pages | all | docs site |

Order: IMG-1 → IMG-2 → IMG-6a (the landing and demo fronts are the visible win) → IMG-3 → IMG-6b → IMG-4 →
IMG-5 → IMG-7.

## 6. Design

### 6.1 Modes

| Mode | What a URL becomes | Needs | Default for |
|---|---|---|---|
| `cloudflare` | `<transform origin>/cdn-cgi/image/<options>/<source URL>` | the page host proxied by a Cloudflare zone with Transformations enabled; the source in that zone or allow-listed | hosted demos, landing (set explicitly) |
| `astro` | Astro `<Image>` → `/_image?href=…&w=…` (sharp) | sharp on the front server | `front/web` and `front/ui` when unset (today's behaviour) |
| `noop` | the original URL, plain `src`, no `srcset` | nothing | the kill switch; hosts without sharp and without Cloudflare |
| `php` | `<api>/api/images/img?path=<path>&w=<w>` | the PHP images package (kept) | admin and the legacy learner app when unset (today's behaviour) |

`astro` exists only in the Astro fronts; `php` only where the client has a storage path and the API URL (admin,
legacy app). Asking for a mode a client does not support falls back to `noop`, with a console warning once.

### 6.2 The URL builder (IMG-1)

New module `front/sdk/src/images.ts` exported as `@ulams/sdk/images` (no runtime dependencies, so admin and the
legacy app can import it from the npm workspace; `admin` and `front` are already workspace members):

```ts
export type ImageService = "cloudflare" | "astro" | "noop" | "php";
export interface ImageConfig {
  service: ImageService;
  transformOrigin?: string;      // "" = the page's own host (relative URL); e.g. "https://ulams.app"
  allowedSources: string[];      // origins whose files may be transformed (public storage, own host)
  apiUrl?: string;               // for php
}
export function imageUrl(src: ImageSource, width: number, preset: PresetName, config: ImageConfig): string;
export function imageSrcSet(src: ImageSource, preset: PresetName, config: ImageConfig, maxWidth?: number): string | undefined;
export function resolveImageService(value: string | undefined, fallback: ImageService): ImageService;
export const IMAGE_PRESETS: Readonly<Record<PresetName, Preset>>;
// ImageSource = absolute or root-relative URL, or { path, url } from the API (path for php, url for the rest)
```

Rules, all unit-tested:

- **Pass-through to the original** (any mode): `data:`, `blob:`, SVG, GIF (keeps animation), URLs not under
  `allowedSources` or the own host, and URLs with a signature or credentials in the query string
  (`X-Amz-Signature`, `Signature=`, `token=`, `Expires=`). A signed or private file must never land in a shared
  edge cache, and a foreign origin would fail the zone's source check.
- **Widths** come only from the preset's list, capped at the declared intrinsic width (no upscaling; Cloudflare
  `fit=scale-down` as a second guard). A fixed list keeps the number of unique transformations bounded (6.5).
- **Cloudflare options** in a fixed order so equal requests produce equal URLs:
  `width=<w>,quality=<q>,format=auto,fit=scale-down,onerror=redirect`. `format=auto` returns AVIF or WebP by the
  `Accept` header. The source keeps its absolute URL (`/cdn-cgi/image/…/https://staging-storage.ulams.app/coffee/…`)
  or, for own files, its root-relative path (`/cdn-cgi/image/…/landing/shot.png`).
- **php** builds exactly today's URL (`/api/images/img?path=<path>&w=<w>`) so admin and the legacy app keep
  working byte for byte when unset.
- `transformOrigin` defaults to relative URLs, so every tenant host serves its own transformations and no CSP or
  CORS change is needed. A single image host is possible later by setting it.

Presets (one JSON file, `front/sdk/src/images/presets.json`, imported by the builder and by tests in each client):

| Preset | Widths (px) | Quality | Used by |
|---|---|---|---|
| `content` | 360, 640, 960, 1280, 1600 | 78 | `Img.astro` default (today's list), figures, hero, frame slider |
| `richText` | 960, 1280 (src only, no srcset) | 78 | images inside lesson and blog markdown (no intrinsic size known) |
| `card` | 240, 480 | 78 | admin course cards, catalogue cards |
| `avatar` | 96, 192, 600 | 80 | people, tutors, profile pictures |
| `logo` | 160, 320 | 85 | tenant logo in the header |

### 6.3 Where the mode is read

| Client | Setting | When read | Notes |
|---|---|---|---|
| `front/web` SSR pages and `@ulams/ui` | `ULAMS_IMAGE_SERVICE` (`process.env` first, then `import.meta.env`, as in #404); `ULAMS_IMAGE_SOURCES` (comma-separated public storage origins; defaults to the storage origins already used for the CSP) | per request (runtime) | `astro.config.mjs` keeps swapping in Astro's noop service when the value is `noop` or `cloudflare`, so `/_image` never needs sharp in those modes |
| Static landing export | same variable at build time | build | `landing-static.mjs` refuses to run unless the build was made with `cloudflare` or `noop` and prints which |
| Admin | `REACT_APP_IMAGE_SERVICE` in `runtime-config.json` (ADR 0056, written by `admin/entrypoint.sh`) or the build env | page load | default `php` |
| Legacy learner app | `VITE_ULAMS_IMAGE_SERVICE` | build | default `php`; `Image` keeps its `imgcache` guess only in `php` mode |

### 6.4 Self-hosters

- **Nothing changes by default**: `front/web` stays on `astro` (sharp is in the front image), admin and the legacy
  app stay on `php`, the PHP package, its optimiser binaries and the `images.render` limiter stay.
- Behind Cloudflare (the `deploy/vps-cloudflare` reference, or any proxied zone): enable Transformations on the
  zone and set `ULAMS_IMAGE_SERVICE=cloudflare` and `REACT_APP_IMAGE_SERVICE=cloudflare`. Documented in IMG-7,
  with the source allow-list step when storage is on another zone.
- Without Cloudflare and without sharp (shared hosting): `noop`, or `php` for admin.
- No imgproxy/thumbor profile now. The builder's mode list is the extension point; a fifth mode is one function
  and a preset mapping. Recommended only if a self-hoster asks.

### 6.5 Free-plan budget and the kill switch

Estimate of unique transformations per month (one per source and width; `format=auto` is one option set):

- Landing: 76 files × at most 5 widths ≈ 380.
- Six to nine demo tenants: seeded course images, figures and avatars, roughly 30–60 images each × 2–5 widths ≈
  1,000–2,000, stable across hourly resets because the URLs are stable (3.2).
- Admin cards and avatars on the demos: a few hundred.
- Visitor uploads on demo tenants: unbounded in principle, wiped hourly.

Expected total 2,000–3,000, inside 5,000. Guards:

1. **Automatic fallback**: `onerror=redirect` on every URL; all our sources are in `ulams.app`, so a 9422 (quota)
   or any transformation error gives the original with the declared width and height (no layout shift).
2. **Bounded option space**: preset widths only; optional WAF custom rule (free plans include custom rules) that
   blocks `/cdn-cgi/image/*` requests whose option string is not one the builder emits, so nobody can burn the
   quota with arbitrary widths (IMG-6b).
3. **Source allow-list stays at the default** (same zone only). Never "any origin".
4. **Kill switch to originals**: set `ULAMS_IMAGE_SERVICE=noop` (front, read per request: restart the Passenger
   app with `tmp/restart.txt`), `REACT_APP_IMAGE_SERVICE=noop` (admin runtime config or rebuild), or rebuild the
   landing with `noop`. No code change. Disabling Transformations on the zone is **not** the kill switch: the
   `/cdn-cgi/image` URLs already in pages would then fail.
5. **Monitoring**: monthly look at Transformations analytics (top configurations and origins); a line in
   `deploy/mydevil/STAGING-NOTES.md` with the count after the first full month.

### 6.6 Accessibility, layout shift and caching

- Every `<img>` the builder serves keeps `width`, `height` (or an aspect-ratio box), `alt`, `loading`,
  `decoding` and `fetchpriority` exactly as `Img.astro` sets them today; `sizes` keeps the caller's value. Tests
  assert the attributes in every mode.
- `noop` renders a plain `src` without `srcset` (as #404 does), so the browser does not download one file five
  times under different descriptors.
- Cloudflare caches transformed images at the edge and serves them with the origin's cache lifetime. The
  storage Worker sends `max-age=300` for every object; IMG-6b raises it to `public, max-age=3600` with ETag
  revalidation for `image/*` only (an hourly demo reset rewrites the same keys, so a day would serve stale
  images after a content change). Landing files keep the Pages defaults.

### 6.7 What demo tenants stop doing (IMG-3, IMG-4)

- Admin `WysiwygMarkdown` stores the uploaded file's public `url` in markdown (not a `/api/images/img` URL)
  whenever the mode is not `php`. Renderers apply the `richText` preset at display time.
- `IMAGES_CONTENT_URLS=api|original` (new key `images.content_urls` in `api/packages/images/src/config.php`,
  default `api`): `ExportImportService` and `topic-types` `Markdown` write `Storage::url($path)` instead of
  `url('api/images/img?path=…')` when it is `original`. Parsing of existing `/api/images/img` URLs stays, so old
  exports still import. The MyDevil API `.env` sets `original` (every tenant there is a demo; the key is
  read once per deployment, not per tenant).
- A seed test asserts that seeded demo content contains no `api/images/img` and no `imgcache/`, and that two
  seeds of one experience produce the same image URLs (the budget in 6.5 depends on it).
- `image_caches` and the PHP endpoint stay on the demo tenants; with no caller they stay empty.

### 6.8 The PHP package as "legacy, optional"

- README of `api/packages/images`, the Swagger description of both operations and the docs pages say: "Optional
  legacy resize API, kept for self-hosted admin and third-party clients; prefer a CDN or the front's image
  service." No `deprecated: true` flag and no removal date (see Q2), so the generated SDK, the CLI coverage
  rules (`exclusions.yaml`, `untyped-responses.yaml`, the matrix) and `public-routes.php` do not change.
- No new endpoint anywhere in this plan, so no tenant isolation test or route fixture changes.

## 7. What is removed

Nothing in code. Removed from the **hosted** deployments only: the build flag `ULAMS_IMAGE_SERVICE=noop` for
MyDevil fronts and the landing (replaced by `cloudflare`), and the admin and front calls to `/api/images/img` on
demo tenants. The docs line "build with `ULAMS_IMAGE_SERVICE=noop`" becomes "`cloudflare` on the hosted demos,
`noop` where neither sharp nor Cloudflare exists".

## 8. Migration

- No database migration. `image_caches` stays.
- No content rewrite. Demo content is reseeded hourly and holds plain storage URLs already; other tenants keep
  their `/api/images/img` URLs, which keep working.
- Config: new optional env keys `ULAMS_IMAGE_SOURCES` (front), `REACT_APP_IMAGE_SERVICE` (admin),
  `VITE_ULAMS_IMAGE_SERVICE` (legacy app), `IMAGES_CONTENT_URLS` (API). Added to the relevant `.env.example`
  files with their defaults.

## 9. Tests

| Milestone | Tests |
|---|---|
| IMG-1 | Vitest in `front/sdk/tests/images.test.ts`: URL per mode and preset; option order is stable; widths capped at intrinsic width and taken from the preset only; pass-through for `data:`, `blob:`, SVG, GIF, foreign origin, signed query strings; `php` output equals today's admin URL byte for byte; unknown mode → `noop` |
| IMG-2 | `front/ui/tests/img-srcset.test.ts` (from #404) extended: `cloudflare`, `astro`, `noop` render with `width`, `height`, `alt`, `loading`, `sizes`; `noop` has no `srcset`; remote storage `src` becomes an absolute-source `/cdn-cgi/image` URL; rich-text images get the `richText` URL; tenant logo uses `logo`. `front/web` test that `csp.ts` still allows the output (same host). Playwright a11y run (`playwright.a11y.config.ts`) unchanged and green |
| IMG-6a | `deploy/mydevil/test/` check that `landing-static.mjs` refuses a build without `cloudflare`/`noop`; a smoke script `deploy/mydevil/bin/check-images.sh <host>` that fetches one `/cdn-cgi/image` URL per front and the landing and expects `200` with `content-type: image/avif` or `image/webp` and `cf-resized` header, and one `onerror=redirect` case |
| IMG-3 | Admin unit tests for `ResponsiveImage` and `resizedImage` per mode; `WysiwygMarkdown` stores the plain URL unless `php`; admin Playwright mock run green |
| IMG-4 | PHPUnit: `ExportImportService` and `Markdown` with `images.content_urls=original` write `Storage::url()`, with `api` write today's URL; import of an old export with `/api/images/img` still works; demo seed test from 6.7. The existing `packages/images` tests stay untouched and green |
| IMG-5 | Vitest for the legacy app's avatar URLs per mode |
| all | full API suite, linters, `node scripts/adr-index.mjs --check`, CLI coverage check unchanged |

## 10. New dependencies

None. The builder is plain TypeScript. Cloudflare Images is a hosted service on the owner's account (free plan);
self-hosters are not required to use it.

## 11. Rollout and rollback

Rollout per section 5. After IMG-6a: run the smoke script against the landing and each demo front, record the
result and the first analytics count in `STAGING-NOTES.md`.

Rollback, fastest first:

1. Flip the mode to `noop` (front restart, admin runtime config, landing rebuild). Originals, no code change.
2. Revert the deploy change of IMG-6a/6b (MyDevil back to the `noop` build).
3. Revert the PR. Every PR is additive and the default behaviour equals today's, so reverts are independent.

## 12. Security and privacy

- Only public files go through Cloudflare; signed and private URLs pass through untouched (6.2).
- Source allow-list stays at "same zone"; the optional WAF rule limits the option space.
- Uploaded images stay untrusted input: Cloudflare decodes them, not our servers, in `cloudflare` mode; in `php`
  mode nothing changes.
- No new endpoint, no tenant data crosses tenants: each tenant host transforms its own storage URLs.

## 13. Risks

| Risk | Mitigation |
|---|---|
| Error 9524 on sources served by the `ulams-storage` Worker or on the admin/front hosts | IMG-6a smoke script per host before switching; fall back to `noop` per host; if needed, transform through an Images binding in the storage Worker (not planned) |
| A staging host is DNS-only (not proxied), so `/cdn-cgi/image` 404s | smoke script per host; keep that host on `noop` |
| Quota exhausted (new sources every reset, visitor uploads, abuse) | stable seed URLs with a test, preset widths, WAF rule, `onerror=redirect`, monthly analytics check |
| Production storage moves to another registrable domain (content origin plans) | allow-list that origin in Sources; `onerror=redirect` no longer applies across zones, so the WAF rule and budget check matter more |
| `format=auto` counted as several transformations | verify in analytics in the first month; switch to `format=webp` in the preset if so |
| PR #404 merges with its own `cfUrl` | IMG-2 replaces it with the builder; the #404 test file is kept and extended |
| Self-hosters surprised by a changed default | defaults equal today's behaviour; the change is opt-in per deployment |

## 14. Open questions (with recommended defaults)

1. **Default mode for admin and the legacy app when unset.** Recommended: `php` (unchanged for self-hosters).
   Alternative `noop` saves PHP CPU but ships full-size avatars in admin.
2. **Mark `/api/images/img` deprecated in OpenAPI?** Recommended: no flag and no removal date; label it "legacy,
   optional" in text. A `deprecated: true` flag ripples into the generated SDKs and implies a removal the owner
   ruled out.
3. **Transform host.** Recommended: each page's own host (relative URLs). Alternative: one image host such as
   `https://ulams.app` for a shared cache across tenants, at the cost of a CSP origin and cross-host requests.
4. **Plain `/cdn-cgi/image/` or a Transform Rule path such as `/img/…`.** Recommended: plain, no extra zone rule.
5. **`format=auto` or `format=webp`.** Recommended: `auto` (AVIF where supported), revisit if analytics show it
   doubles the count.
6. **WAF rule limiting option strings.** Recommended: yes, one custom rule in IMG-6b.
7. **Storage Worker cache lifetime for images.** Recommended: `public, max-age=3600` with ETag.
8. **`IMAGES_CONTENT_URLS` default.** Recommended: `api` (unchanged); demo tenants set `original`.
9. **Rich-text images in `front/web` through the builder.** Recommended: yes, `richText` preset, `src` only.
10. **Upgrade to Images Paid if the free quota runs out.** Recommended: no; stay on free with the fallback and
    review after the first month.
11. **Legacy learner app (IMG-5) at all.** Recommended: yes, last, default `php`; it is not hosted, so it can be
    dropped if the owner prefers.
12. **imgproxy/thumbor profile for self-hosters.** Recommended: not now; add on request.
