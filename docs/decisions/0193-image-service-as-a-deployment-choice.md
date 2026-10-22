# 0193. The image service is a deployment choice behind one URL builder; the hosted demos and the landing use Cloudflare Image Transformations, and the PHP images API stays as an optional legacy API

- Status: Proposed
- Date: 2026-10-22
- Plan: `docs/plans/images-cloudflare.md`; TODO Phase 8 "(new) Image service as a deployment choice"

## Context and problem statement

Images are resized in three different ways today:

- `api/packages/images`: `GET/POST /api/images/img` resizes with `intervention/image` on GD, caches the result
  in `imgcache/` with an `image_caches` row, and runs `spatie/image-optimizer` on a local disk. Admin
  (`ResponsiveImage`, `resizedImage`, the WYSIWYG editor) and the legacy learner app (`front/src`) call it, and
  course import plus the topic-types markdown helper write its URLs into stored content.
- `front/web` and `@ulams/ui`: Astro `<Image>` through `/_image` with sharp. Sharp has no FreeBSD binary, so the
  MyDevil staging fronts and the Cloudflare Pages landing are built with `ULAMS_IMAGE_SERVICE=noop` and ship
  originals.
- Raw `<img>` with the original file elsewhere (blog, rich text, logos).

The owner enabled Cloudflare Images > Transformations on the `ulams.app` zone and asked (2026-10-22) to replace
the PHP resize with it, then corrected the scope the same day: keep the PHP package and its endpoint for
self-hosters and other clients, stop using PHP resizing on the hosted demos and the landing, and make the image
service a deployment choice.

## Decision drivers

- The hosted demos and the landing should serve resized, modern-format images without sharp or PHP CPU.
- Self-hosters must see no change unless they opt in, and must not need Cloudflare.
- One place for widths, quality and formats, so the number of unique transformations stays bounded (free plan:
  5,000 per month, new ones fail with 9422 beyond that, no charge).
- Width and height stay declared (no layout shift); private files never reach a shared edge cache.
- A switch back to originals without a code change.

## Considered options

- A. Remove `api/packages/images` and use Cloudflare everywhere (the first instruction).
- B. A shared URL builder in `@ulams/sdk` with presets and four modes (`cloudflare`, `astro`, `noop`, `php`),
  chosen per deployment; the PHP package kept unchanged as an optional legacy API.
- C. Keep each client's own approach and only switch the MyDevil and landing builds to Cloudflare inside
  `Img.astro` (PR #404 as it stands).
- D. A self-hosted resize service (imgproxy or thumbor) as the default for everyone.

## Decision

**B.**

- `@ulams/sdk/images` builds every image URL from a source (URL, or `{ path, url }` from the API), a width and a
  preset. Modes: `cloudflare` → `/cdn-cgi/image/width=…,quality=…,format=auto,fit=scale-down,onerror=redirect/<source>`
  on the page's own host; `astro` → Astro's `/_image`; `noop` → the original, plain `src`; `php` → today's
  `/api/images/img?path=…&w=…`. Presets (`content`, `richText`, `card`, `avatar`, `logo`) live in one JSON file.
- Pass-through to the original in every mode for `data:`, `blob:`, SVG, GIF, origins that are not allow-listed
  public storage or the own host, and signed URLs.
- Defaults equal today's behaviour: `astro` in the Astro fronts, `php` in admin and the legacy app. The hosted
  demos and the landing set `cloudflare`. `noop` is the kill switch.
- On demo deployments the API writes plain storage URLs into imported content (`IMAGES_CONTENT_URLS=original`;
  default `api`), and the admin editor stores the uploaded file's URL unless the mode is `php`.
- `api/packages/images` is not removed or flagged deprecated: its routes, table, config, optimiser binaries,
  OpenAPI entries, CLI coverage rules and route fixtures stay. Its docs call it an optional legacy API.
- The Cloudflare zone keeps the default source allow-list (same zone only); an optional WAF rule restricts
  `/cdn-cgi/image/*` to option strings the builder emits.

Option A was rejected by the owner: self-hosters without Cloudflare would lose server-side resizing in admin
and third-party clients would break. Option C leaves admin calling PHP on the demos and spreads widths and
quality across clients, so the transformation budget cannot be reasoned about. Option D adds a service to
every deployment for a gain that `astro` and `cloudflare` already cover; it stays a possible fifth mode.

## Consequences

- Good: the demos and the landing get AVIF/WebP at preset widths with no server CPU; the PHP endpoint gets no
  traffic from them.
- Good: one module and one presets file define every image URL; tests pin the output per mode.
- Good: switching to originals is a setting (front per request, admin runtime config, landing rebuild), and
  `onerror=redirect` falls back to the original for same-zone sources by itself.
- Bad: four modes to test and document; admin and the legacy app gain a runtime setting.
- Bad: the hosted demos depend on Cloudflare's free quota and on every host being proxied; a Worker-served
  source can fail with 9524, so each host is smoke-tested before switching.
- Bad: two resize stacks (PHP and CDN) stay in the codebase for as long as the legacy API is kept.
- Open (plan section 14): admin default (`php`), the deprecation flag (no), the transform host (own host), the
  WAF rule (yes), the storage cache lifetime (1 hour), and the Paid plan (no).
