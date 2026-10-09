# Phase 5 (pulled forward): reference frontend for the demos

Status: implemented on `phase-0/foundation`, not committed. Decision record:
[ADR 0008](../decisions/0008-reference-frontend.md) (Proposed).

## Goal

Replace the old EscolaLMS React front for the three demo academies with a fast, server-rendered
reference frontend that sells the product from the first second, and lay the base for Phase 5
(reference frontend, SDK, web components) and Phase 2.7 (a UI catalogue agents compose).

## Scope and TODO items covered

- Phase 5: "Audit Wellms frontends; evolve or build new reference app" (decision: new app, ADR 0008),
  "Themeable from builder presets; per-tenant theme", "TypeScript SDK from OpenAPI" (partial),
  "Web components" (partial: quiz, H5P, video, progress), "WCAG 2.2 AA" (partial),
  "Learner UX: continue, progress" (partial).
- Phase 2.7: UI component catalogue with JSON schemas, fallbacks and a renderer (partial: no
  AG-UI transport, no playground yet).

Out of scope: cart/checkout (Sylius, later), account area, webinars and consultations pages,
PWA/offline, project file upload, cmi5 launch. The old front (`front/src`) stays for those.

## Design

Three workspaces under `front/`, keeping the three top-level folders (admin, api, front):

| Workspace | Package | Content |
|---|---|---|
| `front/web` | `@ulams/web` | Astro 5 SSR app (Node adapter), pages, layouts, server libraries, landing documents |
| `front/sdk` | `@ulams/sdk` | fetch-only API client, OpenAPI path types, demo session, tenant and topic helpers |
| `front/ui` | `@ulams/ui` | catalogue registry (JSON Schemas), renderer, Astro components, web components, theme tokens |

- **Tenant** from the `Host` header with the existing host rules; server-side calls go straight to the
  tenant API (`http://<slug>.localhost` through Caddy).
- **Cache**: public API data in memory per tenant, stale-while-revalidate (45 s fresh, 30 min stale),
  warmed at server start; the program per session token for 60 s, progress for 5 s and invalidated on
  writes.
- **Auth**: httpOnly session cookie; on `/learn/*` the middleware logs in as the demo student
  (`POST /api/demo/login`, else password login with `DEMO_STUDENT_EMAIL`/`DEMO_STUDENT_PASSWORD` from
  the server env). One shared token per demo tenant, re-login once on 401 (hourly reset).
- **BFF**: islands call `/bff/api/…`; allow-list of learner endpoints, Origin check on writes.
- **H5P**: framed through a same-origin `/h5p/*` proxy (see "API issues" for why).
- **Pages as documents**: landings are `front/web/src/docs/<tenant>.json` (catalogue components +
  `$data` bindings); the course page and the lesson player build their documents in
  `src/lib/page-docs.ts`. One `<Render>` validates and renders all of them.
- **Motion**: native cross-document View Transitions, hover prefetch + Chromium prerender, scroll
  reveal (IntersectionObserver, `translate` so hover transforms keep working), hover micro-animations,
  skeletons in islands, a branded navigation bar that appears only after 150 ms, everything off with
  `prefers-reduced-motion`.

## What is done

- Landing for coffee, oncall and nightsky from the Stitch designs, with API data only: course,
  lessons and topics, tutors, testimonials and FAQ from `course.fields.landing`, events, products and
  prices. Invented numbers from the Stitch screens and the old landings (ratings, alumni counts,
  MTTR, "12,480 kids", fake slots) are gone; the On-Call drill log is labelled as an illustration.
- Course page: header with facts and tutors, program in the tenant's style, description, enrol card;
  progress and "continue" for a returning learner.
- Lesson player: program tree with status, topic content for every seeded type (rich text with
  tables and MathML math, video with HLS-on-play and chapters, audio, image, PDF, YouTube embed, H5P,
  SCORM, cmi5 card, project brief, GIFT quiz with all eight question types), downloads, prev/next,
  j/k keys, progress ping and completion, preview mode without access.
- Finish page: completion ring, certificate preview with the learner's name, open topics.
- Login page (password and "continue as demo student"), branded 404/500.
- Demo badge "Demo – resets hourly" with the tenant admin link and the other demos.
- Tests: SDK 21, UI 33 (schema, registry, renderer, markdown sanitising, theme contrast), web 25
  (view model, documents against the catalogue with real API fixtures, cache, tenant, BFF rules);
  Playwright smoke 30 (3 tenants × landing, course, lessons, quiz, BFF; desktop and 360 px phone,
  no horizontal scroll, landmarks, httpOnly session).

### Performance (production build, Chromium, local API, 2026-10-08)

| Page | LCP cold | LCP warm | CLS | JS (gzip) | TTFB |
|---|---|---|---|---|---|
| coffee landing | 85 ms | 81 ms | 0 | 2.8 KB | 8 ms |
| coffee course | 83 ms | 57 ms | 0 | 2.8 KB | 13 ms |
| coffee lesson | 101 ms | 80 ms | 0 | 9.8 KB | 18 ms |
| oncall landing | 76 ms | 76 ms | 0.001 | 2.8 KB | 7 ms |
| oncall course | 61 ms | 66 ms | 0.037 | 2.8 KB | 6 ms |
| oncall lesson | 57 ms | 59 ms | 0 | 9.8 KB | 6 ms |
| nightsky landing | 73 ms | 69 ms | 0.028 | 2.8 KB | 6 ms |
| nightsky course | 55 ms | 68 ms | 0.03 | 2.8 KB | 7 ms |
| nightsky lesson | 76 ms | 75 ms | 0 | 12.4 KB | 6 ms |

Budget: LCP < 1.0 s, JS < 30 KB on landing/course pages, CLS < 0.05: met on every page. The
remaining CLS on two pages is the web-font swap of the heading (fonts are preloaded; `optional`
would remove it but shows fallback fonts on a first visit). HLS video adds 118 KB gzip, loaded only
when a video is played in a browser without native HLS. Measure again with
`yarn workspace @ulams/web perf`.

## Deviations

- Native cross-document View Transitions instead of Astro's `<ClientRouter />` (no JS; see ADR 0008).
- Response types in the SDK are hand-written: the l5-swagger spec has no response schemas
  (request paths are checked against the generated `paths`).
- `vitest` 3.2 in the new workspaces instead of 5.0 (5.0 hoisted to the root broke `api/h5p`, which
  needs its own Vite 8).
- `front/.eslintrc.cjs` now ignores `web`, `sdk` and `ui` (otherwise `front`'s `eslint .` lints the
  new workspaces with the React config). No file under `front/src` changed.

## API issues found (worked around in the front)

1. **Demo mode is off** on the running tenants: `POST /api/demo/login` is 404, so the server uses the
   password fallback.
2. **Night Sky demo student has no course access**: `student1@nightsky.ulams.app` (and the seeded
   students) get 403 on `/api/courses/2/program`; only the two free-preview topics open (the player
   shows a "preview mode" note). Fix: `ulams:demo:seed --skip-content --domain=nightsky.localhost` or
   turn on demo mode.
3. **H5P embed** allows framing and posts messages only to `http://coffee.app.localhost` (no port), so
   it cannot run on `:4321`. The front proxies `/h5p/*` and rewrites `allowedOrigins` to its own
   origin. With Caddy serving `*.app.localhost` on port 80 the proxy is still harmless.
4. **SCORM package files 404**: `/storage/scorm/...` is not served for tenants (files are in
   `storage/<tenant>/app/public/scorm/...`). The player shows an explanation card and a link instead of
   a frame with a 404 inside.
5. **Stationary events** have naive datetimes (`2026-11-28 10:00:00`, no zone); shown as wall-clock
   time.
6. The spec documents almost no responses (types above) and nothing for `/api/demo/*`.

## Caddy change to make the new frontend the default for `*.app.localhost`

In `api/docker/conf/Caddyfile`, the `http://*.app.localhost` block: replace
`reverse_proxy host.docker.internal:3000` with `reverse_proxy host.docker.internal:4321` and drop
`root`/`file_server` (the Astro server serves its own assets). The server already listens on
`0.0.0.0` and allows `*.app.localhost` hosts. Not changed yet.

## Open questions

- Keep `font-display: swap` (brand fonts on first paint, CLS up to 0.04) or switch to `optional`
  (CLS 0, fallback fonts on a first visit)?
- Should the demo student be granted access to every published course on reseed regardless of demo
  mode (issue 2)?
- Next slices: cart/checkout through Sylius, account area ("my courses", certificates), webinars and
  events pages, a catalogue playground (2.7 quality bar), axe in CI.

## Batch 2 (2026-10-09): platform landing, tenant look, account, accessibility, fonts, events

Status: implemented, not committed.

1. **Platform product landing** on `app.localhost` and `localhost` (`ULAMS_PLATFORM_HOSTS`): the tenant
   resolver already rejected these hosts (`app` is a reserved slug, plain `localhost` matches no rule);
   the middleware now marks them as platform requests instead of falling back to a tenant.
   `src/docs/platform.json` in a new neutral `platform` theme: product hero with an update-proposal
   diff card (labelled as an illustration), six advantages (Living Course and commerce marked
   "Coming"), the Living Course flow ("On the roadmap"), three live demo cards with "Open as learner"
   (tenant `/learn/:course`, auto-login) and "Open as admin" (tenant admin, auto-login in demo mode)
   plus "Demos reset every hour", standards chips (LTI 1.3 and webhooks "coming"), a self-host terminal
   band. No metrics, no logos. New catalogue components: `Steps`, `Showcase`, `Chips`; new options:
   Hero `product` + `diff`, FeatureList `grid` + item `status`, CtaBand `terminal` + `code`,
   SiteHeader `logo`, Events kind `consultation`.
   Stitch: the generation call for this screen timed out and no screen appeared in the project after
   polling, so the page follows the same written brief (premium, neutral, one violet accent).
2. **Tenant theme and accent from API settings**, rendered server-side as `--ulams-*` variables
   (`src/lib/accent.ts`): text use of the accent is adjusted to 5:1 on the background (AA holds on
   cards), large type to 3:1, on-accent text black or white. Unit-tested for the three tenants and
   extreme accents.
3. **Account** `/account`: profile (name, e-mail, roles), my courses with progress bars and
   continue/start links, logout. Profile and progress are cached per session and warmed at the demo
   login, so the page renders in about 50 ms after the first visit (the API takes 0.7–1.7 s).
   Headers show "My learning" instead of "Sign in" when there is a session.
4. **Accessibility**: `@axe-core/playwright` scans 29 page types (platform, 3 landings, 3 course pages,
   every topic type, quiz question screen, finish, account, events, login, 404) on desktop and a 360 px
   phone with the WCAG 2.0/2.1/2.2 A and AA rules. Fixed: lesson-tree labels under the API accent
   (contrast), icon-only prev/next links on phones (names), scrollable regions without keyboard access
   (status strip now wraps; terminal, formulas and code blocks focusable), plus an own CSRF origin check
   (Astro's `checkOrigin` compared against the listen address and refused same-site form posts in
   production, which also broke `/login` and `/logout`).
5. **Fonts**: kept `font-display: swap`; replaced Astro's generated fallbacks (wrong `size-adjust`) by
   measured fallback faces in `front/ui/src/styles/fallbacks.css`. CLS is now 0 on every measured page.
6. **Events**: `/events` lists webinars, in-person events and consultations; detail pages show date,
   time, place or duration, sanitised description and agenda (`@ulams/ui/html`), tutors and open
   consultation slots. Booking is out of scope (shop). Landing event cards link to the details.

### Performance after batch 2 (production build, Chromium, local API)

| Page | LCP cold | LCP warm | CLS | JS (gzip) |
|---|---|---|---|---|
| platform landing | 98 ms | 88 ms | 0 | 2.8 KB |
| coffee / oncall / nightsky landing | 108 / 118 / 96 ms | 88 / 90 / 122 ms | 0 | 2.8 KB |
| coffee / oncall / nightsky course | 78 / 111 / 77 ms | 81 / 78 / 80 ms | 0 | 2.8 KB |
| lessons | 88–126 ms | 74–94 ms | 0 | 10–12.7 KB |
| account | 59 ms | 55 ms | 0 | 2.8 KB |
| events | 62 ms | 70 ms | 0 | 2.8 KB |

Tests: SDK 21, UI 37, web 36 unit tests; Playwright 96 (smoke + axe, desktop and phone).

### New API issues found

- `GET /api/consultations` returns the author's full user record (e-mail, phone, address fields,
  directory access list) to anonymous visitors. The front shows only names.
- The demo data was reseeded during the work (student names changed, progress reset); nothing in
  the front depends on it, but long e2e runs can hit the quiz attempt limit (3 per hour).
