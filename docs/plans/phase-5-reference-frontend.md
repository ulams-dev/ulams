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

## Batch 3 (2026-10-11): platform landing redesign

Status: approved 2026-10-11; implemented and verified.

### Goal

Make the platform landing at `app.localhost` explain ULAMS quickly and guide visitors to one clear
next step. Reduce cognitive overload by shortening the first-screen message, lowering the number of
competing calls to action and giving secondary product details a calmer hierarchy. Apply the redesign
to the English, Polish and Simplified Chinese versions.

### Scope

- Redesign the platform landing document and its layout using the existing `@ulams/ui` catalogue and
  platform theme. Keep the tenant academy pages, account, events and learner flows out of scope.
- Rework the hero around one concise product promise, a short supporting sentence, one primary CTA
  to try the demos and one quieter secondary route to self-hosting. Replace the current orbit of ten
  capabilities with a clear, static product visual that shows a real ULAMS workflow.
- Reorder and trim the sections so visitors encounter the product proof and live demos before the
  detailed comparison and implementation information. Preserve accurate feature status labels and
  the current demo, documentation, self-host and comparison destinations.
- Keep EN/PL/ZH copy and page structure aligned; preserve each locale's canonical and alternate URLs.
- Follow the updated visual direction requested by the product owner: expressive creative-tech,
  midnight navy and near-black hero surfaces, violet/lilac light, a citrus CTA, bold sans typography
  and warm off-white content sections. Use the references for palette, contrast and visual rhythm,
  not as assets to copy. Keep the layout readable and accessible across locales.

### UX principles to apply

- **Hick–Hyman law:** reduce simultaneous choices in the hero and top navigation; keep the rest of
  the useful routes available below the fold.
- **Von Restorff effect:** give the demo action one clear visual emphasis and keep secondary actions
  visually quiet.
- **Gestalt proximity and similarity:** group each concise benefit with its supporting product
  evidence and keep unrelated product claims in separate sections.
- Use familiar navigation and CTA patterns, readable line lengths, keyboard support, visible focus,
  reduced-motion support, and touch targets suitable for phone use.

These are design checks, not numerical laws: the result should be validated with page scans and a
short review at desktop and phone widths.

### Product visual and motion

Use a real interface or workflow assembled from existing UI components and demo data; do not invent
customer logos, adoption metrics, or shipped capabilities. Prefer a quiet still or a user-controlled
sequence over multiple auto-playing animated stories. The owner suggested a generated video explainer
as an alternative, but video generation is not available in the current toolset. Do not make this
batch depend on a generated video or add a video asset; leave a future video slot possible without
blocking the static product visual.

### Out of scope

- Building a shared design system or extracting a broad set of reusable page components; that is a
  later step after the page direction is approved.
- Redesigning tenant sites or other routes, changing product features, adding sales claims, adding
  motion libraries, or generating new video/image assets.
- Reworking account, checkout, events booking or the old React frontend.

### Acceptance criteria

- The landing has one dominant message and one visually dominant primary action above the fold at
  desktop and 360 px phone widths.
- The product visual explains one concrete workflow without requiring motion; any optional motion
  respects `prefers-reduced-motion` and does not autoplay sound.
- EN, PL and ZH have matching section order, working in-page links, accurate availability labels,
  and language-correct navigation and CTAs.
- Existing live demos, self-hosting, documentation and comparison content remain reachable.
- No horizontal overflow; keyboard navigation, focus states, contrast and page landmarks remain
  accessible. The platform landing passes the existing axe page scan at desktop and phone widths.
- Update the Phase 5 TODO notes and the docs site in the implementation batch after the redesign is
  approved and verified.

### Implementation and verification

Explore the existing `Hero`, platform page document, catalogue schemas and localized page parity
tests before editing. Prefer configuration changes in the three platform page documents and small
targeted edits to existing components; add a new component only if the existing catalogue cannot
present the product proof accessibly. Verify typecheck/build, the locale parity and page document
tests, Playwright smoke and axe scans at desktop and phone widths, and inspect the rendered page
visually. Do not restructure unrelated dirty or untracked work as part of this batch.

### Visual refinement (2026-10-11)

After review, the owner asked for a more obvious visual change. The earlier neutral editorial pass
has been replaced with a high-contrast midnight palette, lilac product lighting and citrus actions,
with warm light sections for reading and demos. Inspiration: [AI Agent Website Design](https://dribbble.com/shots/27793484-AI-Agent-Website-Design), [Creative Tech](https://dribbble.com/shots/27791478-UI-UX-Design-Web-Design-for-Creative-Tech), [Kodilion](https://dribbble.com/shots/27790991-Kodilion-Website-Bits) and [SkinRelief](https://dribbble.com/shots/27705762-SkinRelief-Men-s-Personal-Care-Wellness-Landing-Page). No reference artwork is copied. The production build passes; desktop and phone renders were inspected, and both locale pages have zero horizontal overflow.

### Open question

- If an explainer video is desired later, should the owner provide a clip or enable a video-generation
  tool? The current implementation plan proceeds with a static product visual either way.

## Batch 4 (approved 2026-10-12): split the public platform landing

### Goal

Translate the reviewed Stitch campaign and comparison screens into the Astro reference frontend as
two distinct, indexable public pages: **Overview** and **Comparison**. Make their content, HTML
semantics, accessibility relationships and search metadata straightforward to inspect.

### Source screens and content rules

- Stitch project: `9106478890112698177`.
- Overview design: `ULAMS — Campaign Landing (EN, Reviewed)`.
- Comparison design: `ULAMS — Features, Stakeholders & LMS Comparison`.
- Use the screens for layout, hierarchy, and visual direction. The screens also contain unsupported
  figures, compatibility claims, product status, roadmap capabilities and competitor assertions.
  Verify every product statement against the current code, tracker and docs; omit or accurately label
  unsupported claims. Do not carry over invented metrics, certification or security guarantees.
- Keep the current ULAMS brand assets and the existing EN/PL/ZH language switch.

### Scope and routes

- Keep the English Overview at `/`; retain `/pl/` and `/zh/` for its localized versions.
- Add the Comparison page at `/comparison/`, with corresponding `/pl/comparison/` and
  `/zh/comparison/` routes. Keep cross-page navigation and in-page section links distinct.
- Recompose the reviewed campaign content as the Overview: product promise and review workflow,
  demo access, Course Builder status, architecture/governance, CLI/MCP, lineage, status transparency,
  FAQ and next steps. Keep the Overview concise enough to lead with the product and demos.
- Move the comparison matrix, stakeholder views and architecture details from the comparison screen
  onto the Comparison page. Reuse the current comparison data where it is accurate; do not repeat
  unverified claims from the Stitch screen.
- Localize page titles, descriptions, navigation, headings and core page content for all three
  existing locales. Keep section order and the product-status distinctions aligned between locales.
- Use existing Astro SSR routes, `@ulams/ui`, theme tokens, and the current public-origin and
  locale helpers. Add no runtime dependency, database change or external font/image request.

### SEO, semantics and accessibility

- Extend the shared page shell with page-specific title, description, canonical URL, `hreflang`
  alternates including `x-default`, Open Graph and X/Twitter card metadata, and a consistent,
  real share image. Do not add deprecated `meta keywords` or fabricated ratings/offers.
- Add valid JSON-LD for the organization/site and the relevant page type (SoftwareApplication on
  Overview; WebPage/CollectionPage and BreadcrumbList as appropriate). Use only verified facts;
  omit ratings, price offers, review counts, version numbers and video metadata without a real asset.
- Add `robots.txt` and `sitemap.xml` routes for the platform host. List the six canonical
  locale/page URLs with their language alternates; do not include tenant pages or preview routes.
- Use one descriptive `h1` per page, ordered headings, `header`/`nav`/`main`/`section`/`footer`
  landmarks, native links and buttons, and descriptive accessible names. Add ARIA only where native
  HTML does not supply the required relationship or name.
- Give the comparison table a caption, scoped column and row headers, and a keyboard-usable mobile
  presentation. Keep language switch, focus visibility, contrast, reduced-motion behavior and
  narrow viewport reflow accessible.

### Acceptance criteria

- Overview and Comparison are separate server-rendered routes in EN, PL and ZH, linked from the
  shared header and footer.
- Each page has a unique localized title and description, canonical URL, complete alternate links,
  social metadata, applicable JSON-LD, and appears in the platform sitemap.
- `robots.txt` identifies the sitemap; previews and tenant-only routes are not listed as platform
  URLs.
- Rendered HTML uses semantic landmarks, a single page `h1`, valid heading order, useful link
  names, and a properly labeled comparison table; ARIA is not used in place of native semantics.
- Existing demos and documentation remain reachable. Every product-status and comparison statement
  is grounded in repository documentation or labeled beta/roadmap accurately.
- Layout remains readable at desktop and phone widths without horizontal overflow.

### Implementation notes, risks and verification

- First inspect the existing catalogue schemas and rendering output for the reviewed Stitch sections,
  localized document parity checks, `Base.astro`, current `platformPage` data, and docs-site
  navigation. Reuse existing components; add narrowly scoped page components only where necessary.
- No migration or new dependency is expected. Keep tenant routing unchanged and gate sitemap/SEO
  routes with the existing platform-host middleware flag.
- The public origin currently comes from forwarded request headers. Reuse that established helper
  for canonical and sitemap URLs and confirm its output under the local and production proxy shapes.
- Add/update documentation-site guidance for the two page routes, metadata, structured data and
  sitemap behavior; update the Phase 5 tracker only after implementation and verification.
- Verification: typecheck and production build; inspect rendered HTML metadata and JSON-LD for all
  six localized routes; parse sitemap/robots output; keyboard and narrow-screen review; run the
  existing page accessibility scan where available. No new SEO dependency is planned.

### Approval

Approved by the product owner in chat on 2026-10-12.

### Completion (2026-10-12)

- Implemented Overview and Comparison routes in EN/PL/ZH, linked from shared navigation, with
  localized titles, descriptions, canonical/hreflang, Open Graph/X cards and JSON-LD.
- Moved the existing cited comparison matrix to its own page. Added localized workflow, stakeholder,
  architecture and FAQ content; public pages always show actual product status (ADR 0097 Proposed).
- Added platform-only `robots.txt` and six-URL `sitemap.xml`; documented routes, status policy and
  metadata. No runtime dependency or migration was added.
- Verified: web typecheck, 487 unit tests, web lint, production web build, docs coverage/build, and
  40 landing E2E cases across desktop and phone, including axe, metadata, JSON-LD and sitemap checks.


## Public analytics (2026-10-12)

Owner requested installation of the supplied Umami Cloud script on the landing, docs and demos,
with actual CTA events and a verified visit/click followed by browser exclusion. Use the shared
web shell and Starlight head, exact production domain allowlist, scoped CSP script/collector
origins, native event attributes and the documented per-origin opt-out. No dependency or
migration. Keep customer deployments and author previews untracked (ADR 0101 Proposed).


### Analytics verification and publication

- Source: `6822ee8f6`; landing release `736423c7` on the existing Cloudflare Pages project.
- The six production demo fronts use the updated SSR build; previous dist retained remotely
  as `dist-before-umami-20261012`, with old hashed client assets preserved for cached pages.
- Real browser verification on https://ulams.app accepted the initial pageview and a click
  opening https://gravity.ulams.app/learn/1 with HTTP 200 from gateway.umami.is/api/send.
- Excluded the persistent verification browser on the landing, www, documentation and all six
  demo origins using `umami.disabled=1`; reloading the landing sent no further collector requests.
- Web typecheck, lint and production build passed. Documentation build passed (563 pages) and
  its HTML includes the tracker. GitHub Pages only permits main deployments; the live docs
  await merging the source changes. No protected environment policy was changed.
- Umami dashboard totals were not inspected: no dashboard authentication was supplied.
