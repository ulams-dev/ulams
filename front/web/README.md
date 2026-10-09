# @ulams/web: reference frontend

Server-rendered learner frontend for ulams tenants: landing, course page, lesson player, quiz and
finish page. Astro 5 (SSR, Node adapter), zero client JS by default, small web components where a
page needs interaction. Decision record: [ADR 0008](../../docs/decisions/0008-reference-frontend.md).

## Run it

Requirements: Node 22 (`.nvmrc`), the API stack running (`yarn dev:api`, Caddy on port 80, so
`http://coffee.localhost` answers).

```bash
cp front/web/.env.example front/web/.env   # then set DEMO_STUDENT_PASSWORD (TENANT_DEMO_PASSWORD in api/.env)
yarn install
yarn dev:web                               # astro dev on :4321
```

Open the platform product page or a tenant by host name (`*.localhost` resolves to 127.0.0.1, no
Caddy change needed):

- http://app.localhost:4321/ (or http://localhost:4321/): the ulams product landing with the demos
- http://coffee.app.localhost:4321/
- http://oncall.app.localhost:4321/
- http://nightsky.app.localhost:4321/

Production build and server:

```bash
yarn workspace @ulams/web build
yarn workspace @ulams/web start            # node dist/server/entry.mjs on :4321, reads .env
```

| Command | What it does |
|---|---|
| `yarn workspace @ulams/web test` | unit tests (vitest): view model, documents vs catalogue, cache, tenant, BFF rules |
| `yarn workspace @ulams/web test:e2e` | Playwright against the server on :4321: smoke tests of every tenant and the platform page, plus an axe WCAG 2.2 AA scan of every page type, desktop and 360 px phone (`WEB_BASE_PORT` for another port) |
| `yarn workspace @ulams/web perf` | LCP, CLS, JS and transfer per page from a running production build |
| `yarn workspace @ulams/web typecheck` | `astro check` + `tsc` |
| `yarn workspace @ulams/web lint` | eslint |

### Environment

See [.env.example](.env.example). Everything is read at runtime on the server; nothing is inlined into
the client build.

| Variable | Default | Meaning |
|---|---|---|
| `ULAMS_TENANT_HOSTS` | `{slug}.app.localhost=>http://{slug}.localhost` | host rules, same syntax as the old front |
| `ULAMS_ADMIN_URL` | `http://{slug}.admin.localhost` | tenant admin (demo badge link) |
| `ULAMS_PLATFORM_HOSTS` | `app.localhost,localhost,127.0.0.1` | hosts (port ignored) that serve the platform product landing |
| `ULAMS_DEMO_TENANTS` | `coffee,oncall,nightsky` | demo academies shown on the platform landing |
| `ULAMS_DEFAULT_TENANT` | `coffee` | tenant for other hosts without a rule (e.g. a LAN IP); empty shows a picker |
| `ULAMS_CACHE_TTL` | `45` | seconds public API data is fresh; it is served stale for 30 min while refreshing |
| `ULAMS_WARM_TENANTS` | `coffee,oncall,nightsky` | tenants fetched when the server starts |
| `DEMO_STUDENT_EMAIL` | `student1@{slug}.ulams.app` | fallback demo account when the tenant has no demo mode |
| `DEMO_STUDENT_PASSWORD` | (empty) | its password; keep it in `.env` (git-ignored) |

## Architecture

```
browser ── HTML (SSR) ──────────────── Astro server (front/web) ── fetch ──▶ tenant API (http://<slug>.localhost)
   │                                     │  middleware: Host → tenant, session cookie, auto demo login
   │                                     │  in-memory SWR cache of public data (per tenant)
   ├─ /bff/api/… (allow-listed) ─────────┤  adds the httpOnly session token, forwards
   └─ /h5p/… (iframe) ───────────────────┘  same-origin proxy to the tenant's H5P service
```

- `src/middleware.ts`: tenant from the `Host` header; on `/learn/*` and `/bff/*` it makes sure there is
  a session (cookie, else a demo login: `POST /api/demo/login`, falling back to the password account).
- `src/lib/data.ts`: API access with the stale-while-revalidate cache (`src/lib/cache.ts`).
- `src/lib/view-model.ts`: turns API responses into the **data model** documents bind to. It only
  uses API data; no invented numbers.
- `src/docs/<theme>.json`: the landing page of each tenant as a catalogue document.
- `src/lib/page-docs.ts`: documents for the course page and each topic type in the lesson player.
- `src/pages`: `/` (tenant landing, or the platform landing on a platform host), `/courses/:id`,
  `/learn/:course` (resume), `/learn/:course/:topic`, `/learn/:course/finish`, `/account` (profile,
  my courses with progress, logout), `/events` and `/events/:kind/:id` (webinars, in-person events,
  consultations), `/login`, `/bff/*`, `/h5p/*`, `/healthz`.
- Platform mode: on a platform host (`ULAMS_PLATFORM_HOSTS`) there is no tenant; `/` renders
  `src/docs/platform.json` with the demo cards built in `src/lib/platform.ts` from each demo tenant's
  API (learner link → `/learn/:course` on the tenant front, auto-login; admin link → the tenant admin,
  which logs in by itself in demo mode).
- Tenant look: the theme preset comes from `theme.theme` in the API settings, the accent from
  `theme.accent`; `src/lib/accent.ts` turns the accent into `--ulams-*` variables on the server,
  adjusted to keep AA contrast.

Packages:

- [`@ulams/sdk`](../sdk) (`front/sdk`): `createClient({ baseUrl, token })` with `auth`, `settings`,
  `courses`, `progress`, `quiz`, `events`, `products`; `demoStudentSession()`; tenant and topic helpers.
  Plain `fetch`, works on Node and in the browser (where `baseUrl` is `/bff`).
- [`@ulams/ui`](../ui) (`front/ui`): the catalogue (`src/registry.ts`), the renderer
  (`src/Render.astro`, `src/render-core.ts`), components (`src/components`), web components
  (`src/elements`) and the theme tokens (`src/styles`, `--ulams-*` as in ADR 0004).

Topic types in the player: rich text (Markdown, tables, math as MathML), video (HLS loaded on play,
chapters, transcript), audio, image, PDF, embed (YouTube/Vimeo), H5P (framed, xAPI to progress),
SCORM (the API's player page, or an explanation when the package files are not served), cmi5 and
project (launch/brief cards), GIFT quiz (all eight question types through the quiz-attempts API).
Progress: ping while visible, complete on the button, at the end of reading, at the end of a video, on
a completing H5P statement or a passed quiz.

## How an agent composes a page

1. Read the catalogue: `front/ui/src/registry.ts`, or `yarn workspace @ulams/ui catalogue` for JSON.
   Each component has a description, a JSON Schema of its props (explicit enums and defaults) and says
   whether it takes children.
2. Write a document. The root is `Page` (theme, title, description); its children are `SiteHeader`,
   `Main` (all sections) and `SiteFooter`:

   ```json
   {
     "component": "Page",
     "props": { "theme": "coffee", "title": "The Coffee Atlas" },
     "children": [
       { "component": "SiteHeader", "props": { "brand": "The Coffee Atlas", "cta": { "label": "Start", "href": { "$data": "/course/previewHref" } } } },
       { "component": "Main", "children": [
         { "component": "Hero", "props": { "variant": "editorial", "title": { "$data": "/course/landing/headline", "$default": "Learn coffee the slow way." } } },
         { "component": "Syllabus", "props": { "variant": "folio", "lessons": { "$data": "/course/lessons" } } }
       ] },
       { "component": "SiteFooter", "props": { "brand": "The Coffee Atlas" } }
     ]
   }
   ```

3. Bind facts to the data model with `{"$data": "/json/pointer", "$default": …}` instead of writing
   them: course title, lessons, prices (`/plans`), events (`/events`), testimonials
   (`/course/testimonials`). Pointers are listed in `SiteModel` / `CourseModel` in
   `src/lib/view-model.ts`. Do not invent numbers, ratings or quotes.
4. Validate before saving: `validateDocument(doc, data)` from `@ulams/ui/render-core` returns every
   problem with its JSON path. At render time invalid nodes become plain text, so a bad node never
   breaks the page, but it should not ship.
5. Render: `<Render doc={doc} data={model} />` inside the `Base` layout (see `src/pages/index.astro`).

## Catalogue component notes

(Short notes until the documentation site, built on another branch, covers the catalogue.)

**ComparisonTable** (`front/ui/src/components/ComparisonTable.astro`, schema in `front/ui/src/registry.ts`).
Products in columns, features in rows; no JavaScript. Sticky header and first column, the column with
`highlight: true` is tinted, rows highlight on hover, sections reveal on scroll (off with reduced
motion). Below 1180 px the table scrolls sideways inside a focusable region with edge shadows and a
"scroll sideways" hint. Accessible table markup: `caption`, `th scope="col"` and `th scope="row"`.
Cell values are neutral: Yes, No, Partial, Via plugin, Paid add-on, Not documented, Coming (Coming
only for our own roadmap), or a short phrase for licence and pricing.

The platform landing feeds it from `src/data/comparison.json`: every cell is
`{value, note, source, checkedAt}`, competitor sources are official docs, pricing pages or licences,
and `tests/unit/comparison.test.ts` fails when a cell has no https source or date. To update a fact,
change the cell, its source and `checkedAt`, and the top-level `asOf`; the table shows "As of …" and
a Sources list under it. Plain product names only, no logos.

The table has two groups behind a segmented control built from radio inputs and CSS (no JavaScript;
without `:has()` support both tables simply show): "Open source & creator platforms" and "Enterprise
suites", ulams in both. `comparison.json` has top-level `groups`; a system lists the groups it is in,
a row lists `groups` or applies to all. The component takes `groups` (2 to 4 tables) instead of
`columns` and `rows`. Enterprise-only rows: data residency, SSO, SCIM, authoring tool, content library.

## Performance budget

Production build, measured with `yarn workspace @ulams/web perf` (Chromium, local API): see
[docs/plans/phase-5-reference-frontend.md](../../docs/plans/phase-5-reference-frontend.md) for the
latest numbers. Budget: LCP < 1.0 s, JS < 30 KB gzip on landing and course pages, CLS < 0.05.
