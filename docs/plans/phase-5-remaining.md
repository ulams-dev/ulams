# Phase 5 plan: the remaining UX, reference frontend, offline, search and admin work

Status: **draft, waiting for approval**. No code is changed by this plan.

The learner UI plan (`docs/plans/phase-5-learner-ui.md`, M-UI1 to M-UI8) is merged. This plan covers the Phase 5
items that are still open after it, except "Single frontend for LMS **and** Sylius commerce", which the
commerce plan covers. Facts come from reading `main` at `4bde7dd1f` (2026-10-21). Paths are relative to the
repository root.

---

## 1. Goal

> A tenant gets a reference frontend that speaks its language and looks like its brand, works as an
> installable app, and keeps lessons readable offline, with progress that syncs back under written,
> tested rules. A learner can search every course they can open and get an answer that cites the
> lessons. An admin starts with a guided, non-empty academy (a sample course and templates), works
> on many rows at once, keeps their filters, and can see where any AI output came from in one click.
> The product measures time to first course, time to first enrolment and admin task times without
> sending anything outside the tenant. Every new screen meets WCAG 2.2 AA.

## 2. Scope and TODO items covered

| # | TODO item (quoted from `docs/ROADMAP-TODO.md`, Phase 5) | Coverage | Milestone |
|---|---|---|---|
| T1 | "Audit Wellms frontends; evolve or build new reference app (justify; SSR/SEO) (partial: new Astro SSR app `front/web`, ADR 0008 Proposed; landing, course, lesson player, quiz, finish for the three demo tenants; plan and numbers in `docs/plans/phase-5-reference-frontend.md`; uncommitted)" | Full (close-out: Course JSON-LD, audit record, stale notes) | P5R-2 |
| T2 | "Themeable from builder presets; per-tenant theme (partial: `--ulams-*` theme files per demo preset in `front/ui/src/styles/themes`, chosen from `theme.theme`; accent from `theme.accent` applied server-side with AA contrast; builder presets not connected yet)" | Full | P5R-2 |
| T3 | "PWA offline mode with tested sync/conflict rules" | Full | P5R-4, P5R-5 |
| T4 | "TypeScript SDK from OpenAPI; widget docs with live examples (partial: `@ulams/sdk` in `front/sdk`, fetch-only; request paths typed from the generated spec, response types hand-written because the spec has no response schemas; ...; widget docs with live examples for the tutor and certificate badge widgets not confirmed)" | Full | P5R-3 |
| T5 | "Semantic search with cited AI answers" | Full | P5R-6, P5R-7, P5R-8 |
| T6 | "**WCAG 2.2 AA** + European Accessibility Act; axe in CI (partial: ...; open: the manual screen reader pass (VoiceOver and NVDA) is documented, not done, and the full-stack struggling-learner journey `front/web/tests/e2e/personalisation.spec.ts` is written but has not been run)" | Full except the human screen reader pass (owner input, section 12) | every milestone, P5R-13 |
| T7 | "Admin UX: templates, guided empty states, sample course, bulk operations, saved filters" | Full | P5R-9, P5R-10 |
| T8 | "AI transparency everywhere (diffs, citations, reasons one click away) (partial: learner surfaces done, #315, #319, #314, #320; fragment citations show a label, not a link, by design, O8)" | Full (author and admin surfaces) | P5R-11 |
| T9 | "Metrics: time to first course, time to first enrolment, admin task times" | Full | P5R-12 |
| T10 | "(new) Reference frontend for the demos: UI catalogue `@ulams/ui` (JSON-schema registry, renderer with fallbacks, landing pages as A2UI-shaped documents), BFF with httpOnly session, demo auto-login, SWR cache of public API data (partial: implemented and tested, uncommitted; ...)" | Full (close-out: the code is on `main`; verify and tick) | P5R-2 |
| T11 | "(new) Platform product landing on the platform host (`app.localhost`) with the live demos; account area; webinars/events/consultations pages (partial: implemented and tested, uncommitted; ...)" | Full (close-out, as T10) | P5R-2 |
| T12 | "(new) Translate the existing lesson player and account strings to Polish and Chinese (the new learner strings are in EN/PL/ZH with the learner locale of ADR 0106, #315; the older player, quiz and account strings are English only)" | Full (native review stays an owner action) | P5R-1 |
| T13 | "(new) Tenant default-language setting for the account pages (ADR 0106 leaves the account pages in English until it exists; a per-learner switch stays a later item, O6)" | Full | P5R-1 |

Not in this plan: "Single frontend for LMS **and** Sylius commerce" (commerce plan); "Learner UX: 'continue',
'what's next', progress and time estimates, short lessons" (only the estimates remain; they belong to the
learner UX follow-up); the studio Insights page (Phase 4.1 item); one-click standard reports and BI (Phase 6.5);
"(new) Native review of the Polish and Chinese learner strings" (owner action).

`(new)` items this plan adds to the TODO on approval: "(new) Offline progress sync endpoint with idempotent
client events (ADR 0150)", "(new) Optional embeddings provider and pgvector index for hybrid search (ADR 0151)",
"(new) Tenant AI activity log screen (model, tokens, cost per call)", "(new) Course JSON-LD on course pages".

## 3. What exists today

### 3.1 Reference frontend and theming

- `front/web` (Astro 7 SSR, `@astrojs/node`): learner routes (`courses/[id]`, `learn/[courseId]/[topicId]`,
  `finish`, `account/{index,training,skills,team,personalisation}`, `certificates/verify/[id]`, `accessibility`),
  the platform landing with `pl/` and `zh/` routes, `comparison`, `blog`, `course-generation`, `events/[kind]/[id]`,
  `faq`, the studio (`studio/s/[id]/{workspace,sources,updates,audit,landing,...}`), the learner BFF
  (`src/pages/bff/[...path].ts`, allow-list in `src/lib/bff.ts`), `llms.txt` and Markdown course pages. All of
  this is tracked in git, so the "uncommitted" notes of T1, T10 and T11 are stale.
- Theme: `theme.theme` (one of six presets) and `theme.accent` from public settings; `front/web/src/lib/theme.ts`
  picks the preset, `src/lib/accent.ts` renders the accent with 5:1 contrast. Presets are CSS in
  `front/ui/src/styles/themes/*.css` and mirrored in `front/ui/src/theme/presets.ts` (drift test).
- **Builder presets are connected**: the interview offers the presets and `course-builder/src/Apply/SiteTheme.php`
  writes `theme.theme`/`theme.accent` on apply; `Site/NewSite.php` passes them to a new tenant. The T2 note
  "builder presets not connected yet" is stale. Gaps: the preset list is hard-coded in four places
  (`SiteTheme::current()`, `front/web/src/lib/theme.ts`, `src/lib/accent.ts`, `front/ui/src/theme/presets.ts`);
  there is no admin theme screen (only the generic key/value settings in `admin/src/pages/Settings`); no tenant
  logo; the brand importer (`front/ui/src/theme/brand-import.ts`, #297) is reachable only from the CLI flow.
- SEO: only `src/layouts/Base.astro` emits JSON-LD; course pages have no `Course` structured data.
- `public/site.webmanifest` is static (platform name and colours) and there is **no service worker**, no
  IndexedDB use and no offline handling anywhere in `front/`.

### 3.2 Language

- `front/web/src/lib/locale.ts`: `learnerLocale(course.language, tenantDefault)` and `accountLocale(settings)`,
  which reads `global.language` from the public settings. The account pages already call `accountLocale`, but
  nothing creates or validates `global.language`: `ulams:tenant:seed-demo` does not write it, the settings
  package does not validate it and the admin has no field for it.
- Strings: `front/ui/src/lib/{i18n,training-strings,tutor-strings,accessibility-strings}.ts` (EN/PL/ZH with a parity
  test). Still English only: `src/components/LessonPlayer.astro` (header, footer, "Free preview", navigation),
  `learn/[courseId]/finish.astro`, `courses/[id].astro`, `account/index.astro` (profile, my courses, my tokens),
  `login.astro`, and the custom elements `front/ui/src/elements/{quiz,progress,video,h5p}.ts`. `<html lang>` stays
  `en` on learner pages (ADR 0106).

### 3.3 SDK and widgets

- `@ulams/sdk` (`front/sdk`): clients for course builder, Living Course, insights, tutor (with streaming),
  compliance, certificates, topics, H5P, frames and an SSE reader; paths typed from
  `src/generated/openapi.ts` (`openapi-typescript`, dev tool); response types hand-written in `src/types.ts`.
  Since #309 every operation has a typed 2xx response (`ulams spec-quality` gate), but about 400 still return
  the generic `ApiEnvelope`.
- Web components: `<ulams-catalogue>`, `<ulams-my-courses>`, `<ulams-continue>` (`src/elements/widgets.ts`),
  `<ulams-quiz>`, `<ulams-progress>`, `<ulams-video>`, `<ulams-h5p>`, `<ulams-tutor>`, `<ulams-certificate-badge>`.
  Docs: `front/docs-site/src/content/docs/extending/web-components.mdx` has code snippets only, no live examples.

### 3.4 Search and AI answers

- No search endpoint or page exists. The tutor (`api/packages/tutor`, ADR 0060) has the pieces: chunk index
  `tutor_chunks` (blueprint blocks plus cited fragments for builder courses, rich text split at headings for the
  rest; quiz content never indexed), PostgreSQL full-text search (`Retrieval/PostgresRetriever.php`, `tsv` GIN
  index, `ts_rank_cd`, per-language config with `simple` for Polish and Chinese), `LikeRetriever` for SQLite,
  structured answers validated against the provided chunk ids (`TutorService::validate`), the attempt guard and
  leak check (`Services/AttemptGuard.php`, `Support/Leak.php`), daily and monthly limits, `tutor:eval` with
  golden fixtures (`resources/fixtures/{coffee,git,injection}.questions.json`) on the fake driver. The index is
  built lazily (`TutorIndexer::ensureFresh` on ask) or with `tutor:index`.
- LLM layer (`api/packages/ai`): Anthropic, fake and disabled drivers. **No embeddings API** (Anthropic offers
  none), no vector storage.
- PostgreSQL: `api/docker-compose.yml` and every CI workflow run `postgres:12`; production references
  (`deploy/vps-cloudflare/compose.yml`, `front/cli/src/stack/config.ts`) run `postgres:17.11-alpine`. ADR 0040
  (PostgreSQL 17, dump and restore) is Proposed and its L0-02 line is not implemented. No extension is used.
  pgvector 0.8 supports PostgreSQL 13 and later, not 12; the official `postgres` images do not ship it.
- Tenants have separate databases and Passport keys (ADR 0007); package tests prove a foreign token is
  rejected (`compliance/tests/Api/TenantIsolationTest.php` pattern). Course access is `CoursesPolicy::attend`.

### 3.5 Progress API (relevant to offline sync)

- `PATCH api/courses/progress/{course}` sets absolute statuses (`INCOMPLETE=0`, `COMPLETE=1`, `IN_PROGRESS=2`)
  per topic; a later call can downgrade a completed topic, and a call with every topic incomplete starts a new
  attempt (`ValueObjects/CourseProgressCollection::setProgress`).
- `PUT api/courses/progress/{topic}/ping` adds the seconds since the last ping by **server clock**, forgotten
  after a gap (`FORGET_TRACKING_SESSION_AFTER_MINUTES`). Offline time would be lost.
- No client timestamps, no idempotency. Deadlines (`afterDeadline()`) and quiz deadlines (ADR 0082) are
  server-side.

### 3.6 Admin

- `admin/` is Umi Max 4, React 18, antd 5, ProComponents 2.8; jest unit tests and a few Playwright specs in
  `admin/src/e2e`. Screens: courses (list, editor, H5P, SCORM, LiaScript, Interactive, Adapt, categories, access,
  quiz reports), a Course Builder entry card that links to the studio, users, groups, roles, tokens,
  notifications, analytics/reports, settings, templates, e-mail templates.
- **No `rowSelection` anywhere** (no bulk operations), no saved filters, list filters are lost on reload.
  `Dashboard/Tutorial` is a static two-card text. There is no compliance, Learner Insights, Living Course or
  AI usage screen in the admin; Living Course proposals and audit live in the studio (`front/web/src/pages/studio`).
- Tenant provisioning (`tenancy/src/Services/TenantProvisioner.php`) runs `migrate`, keys, permissions, LTI keys
  and `ulams:tenant:seed-demo` (users and settings). **No sample course**. `courses-import-export` can import a
  course package.

### 3.7 Analytics

- `reports` package: course popularity, seconds spent, sales metrics. Domain events `CoursedPublished`,
  `CourseAssigned`, `CourseStarted` exist in `courses/src/Events`. Umami (ADR 0101) is limited to the owned
  public sites; customer deployments carry no tracker. Nothing records time to first course or admin task times.

## 4. Design

### 4.1 Language: tenant default and translated player strings (T12, T13; P5R-1)

- **Setting.** `global.language` becomes a first-class public setting with values `en`, `pl`, `zh`, validated in
  the settings package (`api/packages/settings/src/Http/Requests` rule for the `global.language` key) and written
  by `ulams:tenant:seed-demo --language=` (default `en`; `poland` demo seeds `pl` only for its Polish academy if it
  has one, otherwise `en`). `ulams settings set global.language pl` works through the existing CLI noun.
- **Admin.** A "Default language" select in `admin/src/pages/Settings/global.tsx` (labels in the admin locale),
  with a one-line help text: "Account pages and courses without a language use this language."
- **Strings.** New `front/ui/src/lib/player-strings.ts` and `account-strings.ts` (EN/PL/ZH, same parity test as
  `training-strings.ts`) for `LessonPlayer.astro`, `finish.astro`, `courses/[id].astro`, `account/index.astro`,
  `login.astro`, and the custom elements `quiz.ts`, `progress.ts`, `video.ts`, `h5p.ts` (elements read `lang` from
  the closest ancestor; widgets accept a `lang` attribute). Plurals through `Intl.PluralRules`, dates through
  `Intl.DateTimeFormat`.
- **`<html lang>`.** `Base.astro` takes the page locale: the learner locale on course and lesson pages, the account
  locale on account and login pages (ADR 0106's condition, "until the lesson player's own strings are
  translated", is met by this milestone; ADR 0106 gets an amendment line).
- **No new English.** A vitest check (`front/web/tests/unit/hardcoded-strings.test.ts`) parses the learner page and
  component files and fails on visible text nodes that do not come from a strings table (allow-list for brand
  names and symbols).

### 4.2 Theme close-out and reference frontend audit (T1, T2, T10, T11; P5R-2)

- **One preset manifest.** `front/ui/catalogue/themes.json` (generated from `src/theme/presets.ts` by
  `scripts/theme-manifest.mjs`, `--check` in `yarn test`, like the learner-layout manifest) with a copy in
  `api/packages/settings/resources/themes.json`. `SiteTheme::current()`, `front/web/src/lib/theme.ts` and
  `accent.ts` read it instead of their own lists. A new preset is then one CSS file plus one manifest entry.
- **Admin Theme screen** (`admin/src/pages/Settings/theme.tsx`, a tab of Settings): preset cards with the real
  tokens (as the studio picker shows them), accent colour input with the contrast report from
  `front/ui/src/theme/contrast.ts` (ported to a small shared TS module the admin imports from `@ulams/ui/theme`),
  logo upload (SVG or PNG, max 200 KB, stored through the `files` package as `theme.logo`), and "Import from brand
  colours" that runs `brand-import.ts` and shows the proposal as a before/after diff to approve (the same rule as
  every AI or automated change). Saving writes `theme.theme`, `theme.accent`, `theme.logo` through the settings
  service.
- **Tenant logo** in the learner header (`front/web/src/layouts/Base.astro`) with the company name as `alt`,
  falling back to the name as text.
- **Per-tenant manifest.** `front/web/src/pages/site.webmanifest.ts` replaces the static file on tenant hosts:
  tenant name, `theme_color` from `THEME_COLOR`, tenant logo as icon when set (the platform host keeps today's
  file). Needed by the PWA (4.4).
- **Course JSON-LD.** `courses/[id].astro` emits `Course` with `provider` (tenant), `inLanguage`, `hasCourseInstance`
  (online, self-paced) and `offers` only when the course has a price; a unit test validates the shape.
- **Audit close-out.** ADR 0008 is accepted (Decisions, 2026-10-09). `front/docs-site/src/content/docs/developers/reference-frontend.mdx`
  gains a short "Why a new app" section that records the audit already in `docs/plans/phase-5-reference-frontend.md`
  (legacy React SPA frontends: no SSR, styled-components, tenant theming by rebuild; Astro SSR: zero JS by
  default, measured LCP numbers of batch 2). The TODO notes of T1, T10 and T11 are corrected ("uncommitted" removed)
  and the items ticked once this milestone lands.

### 4.3 SDK types and widget docs with live examples (T4; P5R-3)

- **Generated response types.** `front/sdk/src/types.ts` keeps only the types whose operations still return
  `ApiEnvelope`; every client method whose operation has a precise schema uses
  `paths[...]["responses"]["200"]["content"]["application/json"]` from the generated file. A type test
  (`tests/types.test-d.ts`, `vitest --typecheck`) fails when a client's return type and the spec disagree. The
  `ulams spec-quality` list of generic operations drives which types remain hand-written; replacing those schemas
  is Phase 7.3 work.
- **`yarn workspace @ulams/sdk generate --check`** in CI (regenerate into a temp file, diff), so the SDK cannot
  drift from the API spec.
- **Live examples.** `front/docs-site/src/components/WidgetPlayground.astro`: a framed example per widget with
  attribute controls (text, select, checkbox), the rendered widget and the copyable snippet. The docs site is
  static (GitHub Pages), so examples run against a **fixture fetch shim** (`front/docs-site/src/widgets/fixture-api.ts`)
  built from the same fixtures as `front/web/tests/mock-api/server.mjs` (moved to `front/ui/fixtures/` and shared);
  public widgets (catalogue, certificate badge) also offer "Use the live demo academy" against the coffee demo once
  the demos are public (#24). One page per widget under `extending/widgets/` (catalogue, my courses, continue, quiz,
  progress, video, H5P, tutor, certificate badge), each with attributes, events, CSS custom properties and a theme
  switcher that applies the six presets. axe runs on each example in the docs CI (`front/docs-site/tests/widgets.a11y.spec.ts`).

### 4.4 PWA and offline mode (T3; P5R-4, P5R-5; ADR 0150)

**Service worker scope.** `front/web/public/sw.js` (hand-written, built by `scripts/build-sw.mjs` with the hashed
asset list injected) registered at `/` on tenant hosts only; never on the platform host, the studio
(`/studio/*`), `/cli/*` or `/lti/*` (the worker skips those paths, and they are excluded by `navigateFallbackDenylist`
equivalents in code). Content-origin frames (`{slug}.content.ulams.app`: H5P, SCORM, LiaScript, Interactive) are a
different origin and are not cached.

**Caching strategy.**

| Request | Strategy | Cache |
|---|---|---|
| `/_astro/*`, fonts, icons (hashed) | precache at install, cache-first | `ulams-static-{build}` (old builds deleted on activate) |
| Navigations to public pages (landing, catalogue, course pages) | network-first, 3 s timeout, cached copy, else `/offline` | `ulams-pages` (max 50 entries, 7 days) |
| Lessons the learner saved for offline | network-first when online; cached copy offline | `ulams-course-{tenant}-{user}-{course}` |
| `GET /bff/api/courses/progress/{course}` | network-first, cached copy offline | same course cache |
| Progress writes (`PATCH progress`, `PUT ping`, H5P results) | network; offline → queued in IndexedDB | `ulams-sync` store |
| Tutor, search, studio, auth, quiz attempts, everything else under `/bff` | network-only; offline → the surface says it needs a connection | none |

**What works offline.** Reading saved lessons of the rich text, Markdown, image and PDF types (the SSR HTML and
its same-origin images), the course outline, the learner's last known progress, marking a lesson complete, and
reading time. Not offline: quizzes and final tests (server-scored, server deadline per ADR 0082), the tutor and
search, H5P, SCORM, LiaScript, Interactive and video topics (other origin or too large). These show "This lesson
needs a connection" with the lesson title, so there is no dead end.

**Saving a course.** "Save for offline" on the course page and in the lesson player (a toggle button with
`aria-pressed`, storage estimate from `navigator.storage.estimate()`, a list of what will not be available). It
fetches `GET /learn/{course}/offline.json` (new SSR route: the URLs of the readable topics, their images, the
course version signature) and stores them. A saved course shows "Saved for offline · updated 2 days ago" and is
re-fetched in the background when online and the signature differs. `/account` lists saved courses with "Remove".
Logout sends `Clear-Site-Data: "cache", "storage"` from `src/pages/logout.ts`, so a shared device keeps nothing.

**Progress queue and sync.** `front/sdk/src/offline.ts` (pure, testable): `OfflineQueue` over IndexedDB (a ~60-line
wrapper, no library), event shape `{id: ULID, type: "complete" | "in_progress" | "time", topicId, courseId,
occurredAt, seconds?}`, coalescing of time events per topic, a 500-event cap. Replay on `online`, on page load,
on `visibilitychange`, and through Background Sync where the browser has it. Replay posts to a new endpoint
`POST /api/courses/progress/sync` (BFF rule added) with `{sentAt, events[]}`; the response returns a result per
event (`applied`, `duplicate`, `ignored`, `rejected` with a reason), and the page shows a polite status ("3 lessons
synced", or "1 change was not saved: the course was updated").

**Sync and conflict rules (ADR 0150).** Implemented in `courses` (`Services/ProgressSyncService.php` through
`ProgressService`, never by writing tables directly):

1. **Idempotent.** Every event carries a client ULID; processed ids are kept 30 days in `progress_sync_events`
   (tenant DB). A replay is `duplicate`.
2. **Monotonic status.** Per topic, `COMPLETE > IN_PROGRESS > INCOMPLETE`. A synced event never downgrades the
   server state, from this device or another. Starting a course again (all topics incomplete) is online-only.
3. **Time.** Offline reading time is sent as `seconds` with `occurredAt`; it is capped at 60 minutes per event and
   at three times the topic's estimated duration per day, and added once.
4. **Clocks.** The server computes the skew as `now - sentAt` and shifts `occurredAt` by it; events older than 14
   days or in the future after the shift are `rejected` (`too_old`, `clock`).
5. **Deadlines.** A course deadline is checked at the shifted `occurredAt`: work done before the deadline counts even
   if it syncs after it; work done after is `rejected` (`deadline`).
6. **Content changed.** A topic that was removed or deactivated while offline gives `ignored` (`topic_gone`); a
   topic changed by a Living Course update keeps the progress (Phase 3 rule: progress preserved).
7. **Access.** If access was revoked or expired, events are `rejected` (`no_access`) and the saved course is removed
   from the device.
8. **Signals.** Applied events fire the usual domain events with `occurredAt`, so `learner-insights` signals use the
   real time, not the sync time.

### 4.5 Search with cited AI answers (T5; P5R-6 to P5R-8; ADR 0151)

**Decision summary (ADR 0151).** Full-text search is the default and works everywhere (PostgreSQL 12 to 17, shared
hosting, air-gapped, AI off). The "semantic" part comes first from the model: when AI is on, a light-model query
rewrite adds synonyms and the course language's terms before retrieval, and the cited answer reads the results.
Embeddings are an optional second retriever, merged with full-text results by reciprocal rank fusion, enabled only
when an embeddings provider is configured **and** the `vector` extension exists. Default: off.

**Package.** New composer package `api/packages/search` (`ulams/search`, MIT), depending on `courses`, `tutor` and
`ai`. It reuses `tutor_chunks` as the content index (same chunking, quiz content never indexed) and adds:

- `search_course_docs` (tenant DB): one row per course (title, subtitle, summary, lesson titles, language, `tsv`),
  for "which course is about X" results.
- `IndexCourseJob` (queued) on `CoursedPublished`, course update, builder apply and Living Course apply events, plus
  `search:index {--course=}`; unpublished courses are removed from the index. The tutor keeps `ensureFresh` as a
  safety net.
- **Chinese.** PostgreSQL has no Chinese parser in the official image. The indexer and the query builder split CJK
  runs into overlapping character bigrams before `to_tsvector('simple', ...)` (`Support/CjkBigrams.php`, pure PHP,
  unit-tested); Polish keeps `simple`. This also improves the tutor, which uses the same index (`PostgresRetriever`
  gets the same transform).

**Access and isolation.** `courses/src/Services/AccessibleCourses.php` (new domain service) returns the course ids
the user may `attend` (public published, enrolled, group access, author, admin), mirroring `CoursesPolicy::attend`
with a test that the two agree on a fixture matrix. Every query filters by these ids in SQL; results never include
a course outside the set. Tenants are separate databases, so cross-tenant leakage is impossible by construction and
proven by a foreign-token test on every new endpoint.

**API.**

- `GET /api/search?q=&course_id=&limit=` (auth or anonymous for public courses): `{courses[], passages[{chunkId,
  courseId, topicId, label, snippet (ts_headline), href}]}`; 2 to 200 characters; throttle 30/min per user or IP.
- `POST /api/search/answer {q, conversationId?}` (auth): runs the tutor pipeline across the top passages of
  accessible courses with task `search_answer` (light profile, config only): `{answer, citations[{chunkId, courseId,
  topicId, label}], inScope, refusal, remaining, label}`. Guard rails are the tutor's, unchanged: citations must be
  a subset of the provided chunk ids (validator), an in-scope answer needs a citation, out-of-scope answers say why,
  uploaded content is wrapped as untrusted data in the prompt, **the attempt guard runs for every course with an
  active attempt** of the learner (any overlap with a correct option → refusal), the daily question quota is shared
  with the tutor, the tenant monthly cap applies, and every call is logged in `ai_calls` with model, tokens and cost.
  With AI off or the tutor disabled, the endpoint returns 503 and the page shows results only.
- Streaming reuses the tutor run log (`POST ... {stream: true}` → `runs/{run}/events`, ADR 0105).

**Front.** `front/web/src/pages/search.astro` (server-rendered results, form works without JavaScript; `role="search"`
landmark; results as a list with course and lesson headings; `ts_headline` snippets escaped and marked with `<mark>`),
an "Ask about these results" button that opens the answer panel (reuses `tutor-panel.ts` in a "search" mode: AI label,
`CitationList` linking `/learn/{course}/{topic}`, refusals in words), a search box in the learner header, and a
`<ulams-search>` widget. Strings in EN/PL/ZH. SDK: `front/sdk/src/search.ts`.

**Embeddings, optional (P5R-8).**

- `ai`: `EmbeddingClient` contract with `openai_compatible` (one HTTP call to `/v1/embeddings`, covering Ollama, vLLM,
  LiteLLM and hosted OpenAI-compatible services; Laravel HTTP client, no new dependency), `fake` (deterministic hash
  vectors for tests) and `disabled` drivers; `config/ai.php` `embeddings.{driver, base_url, api_key, model,
  dimensions}`; calls logged in `ai_calls` with tokens and cost (price table in config).
- Storage: migration creates `search_embeddings(chunk_id, model, embedding vector(N))` with an HNSW cosine index
  **only when** `CREATE EXTENSION IF NOT EXISTS vector` succeeds (the migration records the outcome; nothing fails
  without it). A model or dimension change requires `search:reindex --embeddings`.
- PostgreSQL versions: pgvector 0.8 needs PostgreSQL 13+, so embeddings are unavailable on the current
  `postgres:12` dev and CI stack and become available after the PostgreSQL 17 move (ADR 0040, L0-02, Phase 8). The
  production references already run 17.11; operators who want embeddings use the `pgvector/pgvector:0.8.x-pg17`
  image (PostgreSQL licence) or install the extension. CI gets one job on `pgvector/pgvector:pg17` for the hybrid
  path; the rest of CI keeps testing the full-text path.
- Ranking: reciprocal rank fusion (k = 60) of the full-text and vector lists; the answer step is unchanged.

**Evaluation.** `search:eval {--fixtures=all|coffee,git,poland-pl,ulam-zh} {--live} {--record}` on golden fixtures
(question, expected topic ids, expected refusal), fake driver by default: recall@5 and MRR of retrieval (full text,
with query rewrite, hybrid), citation precision, refusal correctness, leak rate during attempts (target 0), cost per
answer, p95 latency. The hybrid path is only recommended to operators if it improves recall@5 by at least 10 points
on the fixtures.

### 4.6 Admin UX (T7; P5R-9, P5R-10)

**Guided empty states (P5R-9).** A shared `admin/src/components/GuidedEmpty` (antd `Empty` with a heading, one
sentence and up to three actions):

| Screen | Empty state actions |
|---|---|
| Courses list | "Build a course with AI" (studio), "Start from a template", "Add the sample course" |
| Users list | "Invite a user", "Import users from CSV" (`csv-users`), "Create the demo learners" (demo tenants only) |
| Course access / enrolments | "Enrol users", "Enrol a group", "Make the course public" |
| Groups, Certificates, Quiz reports | one primary action and a docs link each |

**Getting started checklist** replaces `Dashboard/Tutorial`: create a course, publish it, invite a learner, first
enrolment, set the theme and language; each step ticks from the milestones of 4.8 and links to the screen; it can be
dismissed per admin.

**Templates.** Three course templates as course-as-code packages in `api/packages/courses-import-export/resources/templates/`
(EN, PL, ZH titles): "Structured course" (welcome, three modules, module quizzes, final test, certificate), "Policy
training" (policy summary, scenarios, exam, certificate with expiry), "Product tutorial" (setup, three tasks,
check-your-understanding). Each topic has placeholder text that says what to write. `GET /api/admin/course-templates`
and `POST /api/admin/course-templates/{key}/instantiate` (create through the import service, unpublished draft).
Admin: "Start from a template" modal with previews. CLI: `ulams courses create --template=`.

**Sample course.** A short, owned, CC BY 4.0 course (the first two modules of the coffee demo course, already
fact-checked, `demo-content/`) imported as an **unpublished draft tagged "Sample"** by a new provisioning step
`sample` (`ulams:tenant:seed-sample`, skipped with `--no-sample`, idempotent). The admin sees it in the course list with
a "Sample" tag and a "Delete sample" action.

**Bulk operations (P5R-10).** `rowSelection` on the Users and Courses ProTables with a sticky action bar ("12 selected"):

- Users: enrol in courses, add to group, assign required training (compliance assignments already accept
  `user_ids`), send notification (existing bulk notifications), export selected (CSV).
- Courses: publish, unpublish, set category, add tag, export.
- API: `POST /api/admin/bulk` `{action, ids[], params}` in `core`, which dispatches to the owning package's domain
  service per item (enrolment through `course-access`, status through `courses`), runs synchronously up to 100 items
  and as a queued job above (`GET /api/admin/bulk/{id}` for progress), returns per-item results; failures are listed,
  never silent. Permissions are checked per item. Every bulk run is written to the audit log with the actor.
- "Select all N matching" across pages for filters with up to 5 000 rows.

**Saved filters.** Filters and sort of every ProTable go into the URL query (shareable, survive reload) through a
`useTableQueryState` hook. "Save view" stores the query under a name: `admin_saved_views` (tenant DB: `user_id`,
`screen`, `name`, `query` JSON, `shared` bool), endpoints `GET/POST/DELETE /api/admin/saved-views?screen=`; shared
views visible to every admin with access to the screen. Starts on Users, Courses, Course access, Quiz reports,
Compliance assignments.

### 4.7 AI transparency for authors and admins (T8; P5R-11)

One admin component, `admin/src/components/AiProvenance`: an AI label plus a "Why / Sources / Changes" popover that
links to the place with the full record. Placed on every AI output surface:

| Surface | What one click shows | Source |
|---|---|---|
| Course editor topic list and topic form | "Created with AI assistance and reviewed by …", the citations of the topic's blocks with fragment text, link to the studio element | `ai_assisted` (M-UI2) + new `GET /api/admin/course-builder/courses/{c}/topics/{t}/provenance` (blocks, citations, fragment labels and text, applied version, approver) |
| Course page header | "2 source updates waiting" with a link to the studio proposal diff | living-course `sessions/{s}/proposals` (count by course) |
| User → course tab | Learner Insights status with its reasons, remediations given and why | learner-insights admin API (#283) |
| Competency tags | "Suggested by AI, confirmed by …" | compliance tag records (#286) |
| Price suggestion | the reasons the model gave | course-builder `price` task output |
| AI activity log (new screen, Settings → AI) | every LLM call: date, task, model, tokens, cost, linked entity (session, course, conversation counts only, no learner text) | new `GET /api/admin/ai/calls` in `ai` (permission `settings_manage`, paginated, CSV export) |

A transparency registry test (`admin/src/components/AiProvenance/surfaces.test.ts`) lists the surfaces and checks that
each screen renders `AiProvenance` with a non-empty link target on a fixture; a new AI surface without it fails review.
Fragment citations for learners stay labels (O8 of the learner UI plan); authors get the fragment text.

### 4.8 UX metrics (T9; P5R-12; ADR 0152)

**Definitions.**

- *Time to first course*: tenant provisioned → first course created, and → first course published (two numbers).
- *Time to first enrolment*: tenant provisioned → first learner (not admin, tutor or demo seed user) enrolled or assigned.
- *Admin task times*: from opening the form to the success response, for: create course, publish course, create user,
  enrol users, assign required training, review an update proposal, issue a report export. Hidden-tab time is excluded;
  a task idle for more than 10 minutes is dropped.

**Collection (first party, in the tenant, no personal data).**

- New package `api/packages/ux-metrics` (`ulams/ux-metrics`): table `ux_milestones` (`key`, `reached_at`, `seconds`)
  written once by listeners on `CoursedPublished`, course created, `CourseAssigned` and the compliance assignment event;
  the provisioning time comes from a `global.createdAt` setting written by the provisioner (`seed-demo --created-at=`),
  with the earliest admin `created_at` as fallback for existing tenants.
- `ux_task_timings` (`task`, `duration_ms`, `outcome`, `day`), **no user id, no IP, no entity ids**; `POST
  /api/admin/ux/task-timings` (batch, max 50, sent with `navigator.sendBeacon` from `admin/src/services/uxMetrics.ts`
  `startTask/endTask`), permission: any admin. Retention 180 days (daily prune).
- `GET /api/admin/ux/metrics`: milestones and medians/p75 per task over 30 days; shown on the dashboard next to the
  checklist. The platform API (ADR 0085) gets `GET /api/platform/ux-metrics` aggregating milestones across tenants for
  the operator, and `ulams tenants metrics` in the CLI.
- Setting `ux_metrics.enabled` (default on): data never leaves the tenant database; nothing is sent to the ulams
  project or any third party (principle 8, "no telemetry by default", holds). The operator docs page
  `operators/ux-metrics.mdx` lists every field.

### 4.9 Accessibility and EAA close-out (T6; every milestone, P5R-13)

- Every new learner screen (offline states, save-for-offline control, search page and answer panel, translated pages)
  goes into `front/web/tests/a11y/*.a11y.spec.ts` (axe tags `wcag2a`, `wcag2aa`, `wcag21aa`, `wcag22aa`, desktop and
  360 px, keyboard, reduced motion), in the milestone that builds it.
- New admin screens get an axe check in `admin/src/e2e/a11y.e2e.spec.ts` (`@axe-core/playwright`, already a dependency
  of `front/web`; added to the admin as a dev dependency) for the Theme, bulk bar, saved views, AI activity and empty
  states.
- Widget docs examples run axe (4.3).
- The full-stack journey `front/web/tests/e2e/personalisation.spec.ts` runs in the nightly workflow
  (`.github/workflows/nightly-conformance.yml`, new job on the fake driver) and in P5R-13 once by hand with the report
  attached to the PR.
- `front/docs-site/src/content/docs/operators/accessibility.mdx` gains an EN 301 549 clause-by-clause conformance table
  (pass, partial, not applicable) generated from a YAML file, and a blank accessibility conformance report template
  operators can fill. The manual screen reader pass (VoiceOver on macOS and iOS, NVDA on Windows) follows the documented
  script; it needs a person (section 12).

## 5. New dependencies

| Dependency | Where | Why | Licence, maintenance, size, self-hosting |
|---|---|---|---|
| `@axe-core/playwright` (dev) | `admin` | axe on new admin screens | MPL-2.0, Deque, already used in `front/web`; dev only |
| pgvector extension (optional, operator-installed) | PostgreSQL | vector index for the optional hybrid search | PostgreSQL licence, active; not required; the full-text path needs nothing |

Not added: Workbox or `@vite-pwa/astro` (the worker is a few hundred lines with a fixed route table, and the
dependency would bring a build plugin and a larger runtime for features we do not use; revisit if the worker grows),
`idb`/`idb-keyval` (a small wrapper covers one object store), a search engine (Meilisearch, OpenSearch: an extra service
for self-hosters), an embeddings SDK (one HTTP call), a chart library for metrics (antd statistics and tables suffice),
an i18n library.

## 6. Migrations

All in tenant databases, each with a working `down()`:

- `courses`: `progress_sync_events` (`id` ULID primary, `user_id`, `topic_id`, `result`, `created_at`; index on
  `created_at` for the 30-day prune).
- `search`: `search_course_docs` (`course_id` primary, text columns, `language`, `tsv` + GIN on PostgreSQL); optional
  `search_embeddings` (only when the extension exists).
- `tutor`: none (the CJK bigram change rebuilds `tsv` through `tutor:index`, run by `ulams:upgrade`).
- `core`: `admin_saved_views`; `bulk_runs` (`id` ULID, `action`, `actor_id`, `total`, `done`, `failed`, `results` JSON,
  timestamps).
- `ux-metrics`: `ux_milestones`, `ux_task_timings`.
- Settings rows (not schema): `global.language`, `global.createdAt`, `theme.logo`, `ux_metrics.enabled`.

## 7. Tests

| Layer | What |
|---|---|
| API PHPUnit | Sync rules 1-8, one test each with fixtures (duplicate, downgrade attempt, cap, skew, deadline before/after, topic gone, access revoked, signal time); `AccessibleCourses` agrees with `CoursesPolicy::attend` on a matrix; search never returns an inaccessible or unpublished course; quiz text never matches; CJK bigrams; answer citations subset of passages; attempt guard across courses; shared quota; AI off → 503 and results still work; embeddings migration with and without the extension; bulk per-item permissions and results; saved views owner and shared; metrics milestones once, timings without personal data, prune; settings validation of `global.language`; template instantiate and sample seeding idempotent; **tenant isolation** (foreign token rejected) and **learner isolation** for every new endpoint |
| Evals | `search:eval` on golden fixtures with the fake driver in CI (schema, citation coverage, refusal, leak 0); `--live` only by hand within `SEARCH_EVAL_MONTHLY_USD` (default 5) |
| `@ulams/sdk` vitest | `OfflineQueue` (coalescing, cap, replay order, result handling), search client, generated types (`--typecheck`), `generate --check` |
| `@ulams/ui` vitest | string parity for the new tables, elements reading `lang`, theme manifest `--check` |
| `@ulams/web` vitest | hard-coded string check, `offline.json` route, per-tenant manifest, Course JSON-LD, BFF rules for sync and search |
| Playwright (mock API, CI `web-a11y`) | axe, keyboard, reduced motion for every new screen and state |
| Playwright e2e (full stack, fake driver) | `offline.spec.ts`: save a course, `context.setOffline(true)`, read two lessons, complete one, reload offline, go online, assert server progress and the synced notice; a second context completes another topic to prove the union; logout clears caches. `search.spec.ts`: search across two enrolled courses, an unenrolled course never appears, ask about the results, follow a citation, start a quiz and get the refusal. Polish and Chinese account pages with `lang` set |
| Admin jest + Playwright | GuidedEmpty actions, checklist ticks, template modal, bulk bar with partial failures, saved views round trip, `AiProvenance` registry test, uxMetrics beacons, axe |
| Docs site | widget playground examples render and pass axe |

## 8. Milestones

Each milestone is one PR from `phase-5/<slug>`, demoable on its own, tests in the same commits.

| # | Milestone | TODO items | Demo |
|---|---|---|---|
| **P5R-1** (recommended first) | Tenant language and translated player | T12, T13 | Set "Default language: Polski" in the admin; the account, login and finish pages of the tenant read in Polish with `lang="pl"`; a Polish course's lesson player, quiz and progress strings are Polish; ZH likewise; the hard-coded string check guards the files |
| P5R-2 | Theme close-out and frontend audit | T1, T2, T10, T11 | Admin picks the "poland" preset, a brand accent with its contrast report and a logo; the learner site shows them; brand colours import as a diff to approve; course pages carry Course JSON-LD; TODO notes corrected |
| P5R-3 | SDK types and live widget docs | T4 | Docs site: nine widget pages with live examples, attribute controls and theme switcher, axe clean; `generate --check` fails on a spec change |
| P5R-4 | Installable app shell | T3 (part) | The tenant site installs with its own name and colour; offline navigation shows a branded `/offline` page; assets come from the cache on repeat visits |
| P5R-5 | Offline lessons and progress sync | T3 | Save a course, go offline, read and complete lessons, come back online, see "2 lessons synced"; a stale saved course updates; logout clears it |
| P5R-6 | Course search | T5 (part) | `/search` finds lessons across the learner's courses, in English, Polish and Chinese, without AI; an unenrolled course never appears |
| P5R-7 | Cited answers on search | T5 | "Ask about these results" gives a streamed answer citing lessons in two courses; an off-topic question is declined; during a quiz the answer is refused |
| P5R-8 | Optional hybrid retrieval | T5 (complete), (new) embeddings | On a PostgreSQL 17 + pgvector stack with Ollama embeddings, `search:eval` prints recall for full text, rewrite and hybrid; without the extension nothing changes |
| P5R-9 | Admin onboarding | T7 (part) | A new tenant opens to a checklist and a "Sample" draft course; empty lists offer next steps; "Start from a template" creates a structured draft |
| P5R-10 | Bulk operations and saved views | T7 | Select 30 users, enrol them in two courses, see 28 done and 2 failed with reasons; save "Overdue in Sales" and reopen it from a link |
| P5R-11 | AI transparency for authors and admins | T8, (new) AI activity log | From a builder topic in the admin, one click shows its sources and approver; a course shows waiting updates with the diff link; a learner row shows the insight reasons; Settings → AI lists calls with cost |
| P5R-12 | UX metrics | T9 | The dashboard shows "first course published 18 min after setup" and median "enrol users" time; the operator sees the cross-tenant summary with `ulams tenants metrics` |
| P5R-13 | Accessibility and EAA close-out | T6 | Nightly personalisation journey green; EN 301 549 table and report template on the docs site; screen reader findings (once supplied) fixed |

Order: P5R-1 is small, has no API risk beyond one validation rule and unblocks `<html lang>` for every later screen.
P5R-2 and P5R-3 are independent of each other. P5R-4 needs the per-tenant manifest of P5R-2. P5R-6 to P5R-8 are
independent of the PWA work; P5R-8 needs the PostgreSQL 17 move for CI (it can ship behind its own CI job before).
P5R-9 to P5R-12 are admin-side and can run in parallel with the learner-side milestones; P5R-12's checklist ticks reuse
P5R-9's dashboard. P5R-13 last.

### 8.1 Commit order (first commits of each milestone)

- P5R-1: `feat(settings): validated tenant default language` · `feat(admin): default language field` · `feat(ui,web): Polish and Chinese lesson player and account strings` · `test(web): no hard-coded learner strings` · `docs: ADR 0106 amendment for the page language`
- P5R-2: `feat(ui): theme preset manifest shared with the API` · `feat(admin): theme settings with contrast report, logo and brand import` · `feat(web): tenant logo, per-tenant web manifest and Course JSON-LD` · `docs: reference frontend audit record`
- P5R-3: `feat(sdk): generated response types and spec drift check` · `docs(site): widget playground with fixture API`
- P5R-4: `docs: ADR 0150 offline mode and progress sync rules` · `feat(web): service worker, offline page and install`
- P5R-5: `feat(courses): idempotent progress sync endpoint` · `feat(sdk): offline progress queue` · `feat(web): save courses for offline` · `test(web): offline journey`
- P5R-6: `docs: ADR 0151 search retrieval` · `feat(courses): accessible courses service` · `feat(search): course and passage search with CJK bigrams` · `feat(web,sdk): search page and widget`
- P5R-7: `feat(search): cited answers on the tutor guard rails` · `test(search): search eval on golden fixtures`
- P5R-8: `feat(ai): embeddings client` · `feat(search): optional pgvector hybrid retrieval`
- P5R-9: `feat(admin): guided empty states and getting started checklist` · `feat(courses-import-export): course templates` · `feat(tenancy): sample course on provisioning`
- P5R-10: `feat(core): bulk operations with per-item results` · `feat(admin): bulk action bar` · `feat(core,admin): saved table views`
- P5R-11: `feat(course-builder): topic provenance endpoint` · `feat(ai): AI activity log endpoint` · `feat(admin): AI provenance on every AI surface`
- P5R-12: `docs: ADR 0152 first-party UX metrics` · `feat(ux-metrics): milestones and admin task timings` · `feat(admin): dashboard metrics`
- P5R-13: `test(web): nightly personalisation journey` · `docs: EN 301 549 conformance table and report template` · `docs: roadmap TODO for Phase 5`

## 9. ADRs proposed with this plan

- `docs/decisions/0150-pwa-offline-mode-and-progress-sync-rules.md` (Proposed): worker scope, caching, what is offline,
  idempotent monotonic sync.
- `docs/decisions/0151-search-full-text-first-with-optional-embeddings.md` (Proposed): full text plus query rewrite by
  default, optional pgvector hybrid, CJK bigrams, reuse of the tutor index and guard rails.
- `docs/decisions/0152-first-party-ux-metrics-without-personal-data.md` (Proposed): what is measured, where it is stored,
  no third party.

## 10. Risks

| Risk | Mitigation |
|---|---|
| A cached lesson shows content after access ends, or on a shared device | Saved only on explicit action; `Clear-Site-Data` on logout; `no_access` removes it at next sync; caches keyed by tenant and user |
| A buggy service worker serves stale pages to every learner | Versioned caches, network-first for pages, a kill switch (`/sw.js` that unregisters when `PWA_ENABLED=false`), worker skips studio, auth and BFF writes online |
| Offline time inflates reports | Caps of rule 3; offline seconds flagged in `progress_sync_events` so reports can show them separately |
| Search answer leaks a quiz answer from another course | Attempt guard over all active attempts; quiz content never indexed; leak rate 0 in the eval gate |
| Full-text recall is poor for paraphrases | Query rewrite by the light model; optional hybrid; eval numbers decide the default |
| Chinese bigrams bloat the index | Measured on the Ulam ZH fixture; bigrams only for CJK runs |
| pgvector unavailable on a host | Optional by design; the migration records availability; FTS path always tested |
| Bulk actions do damage at scale | Per-item permission checks, per-item results, audit log, confirmation with the count, queued above 100 |
| Metrics read as surveillance of admins | No user ids in timings, aggregates only, documented fields, a switch |
| Sample course clutters a production academy | Unpublished draft, "Sample" tag, one-click delete, `--no-sample` |
| Translated strings without native review | English fallback per key; owner action for review, as ADR 0096 |

## 11. Open questions (each has the default that will be used)

| # | Question | Recommended default |
|---|---|---|
| Q1 | Which lessons work offline? | **Rich text, Markdown, images and PDFs only**; quizzes, tutor, search, H5P, SCORM, LiaScript, Interactive and video need a connection |
| Q2 | Conflict rule for progress from several devices? | **Monotonic union** (complete wins, never downgraded); restarting a course online-only |
| Q3 | Accept offline completions after a course deadline? | **Count work done before the deadline** by its (skew-corrected) time; reject work done after |
| Q4 | Hand-written service worker or Workbox? | **Hand-written**, no dependency; switch to Workbox only if the route table grows past the current one |
| Q5 | Embeddings in Phase 5? | **Optional and off by default**; full text plus model query rewrite is the default; hybrid only where pgvector and an embeddings provider exist |
| Q6 | Which embeddings provider interface? | **OpenAI-compatible `/v1/embeddings`** (Ollama, vLLM, LiteLLM, hosted), model and dimensions in config only |
| Q7 | Search for anonymous visitors? | **Yes for public published courses, results only**; AI answers need sign-in |
| Q8 | Share the daily question quota between tutor and search answers? | **Yes**, one quota (30 per learner per day) |
| Q9 | Sample course content and state? | **First two modules of the coffee course, unpublished draft tagged "Sample"**, on every new tenant unless `--no-sample` |
| Q10 | Where do saved filters live? | **Server side per admin** with an optional "shared" flag, plus URL query state |
| Q11 | Bulk operation limit? | **Synchronous up to 100 items, queued above, 5 000 per run** |
| Q12 | UX metrics on by default? | **On, stored only in the tenant database**, never sent out; operator switch `ux_metrics.enabled` |
| Q13 | Admin task timings: keep user ids for per-admin coaching? | **No**; anonymous durations only |
| Q14 | Default language values? | **en, pl, zh** (the languages the strings exist in); others fall back to English |
| Q15 | Tenant logo formats? | **SVG or PNG, max 200 KB**, SVG served as an attachment-safe image with the existing CSP rule |
| Q16 | Live widget examples: fixtures or a live tenant? | **Fixtures** (works on the static docs site); live demo toggle for public widgets once the demos are published (#24) |
| Q17 | Should the hybrid path wait for the PostgreSQL 17 move? | **Ship P5R-8 with its own pgvector CI job**; the default stack moves with ADR 0040 / L0-02 |

## 12. Inputs only the owner can give

- The **manual screen reader pass** (VoiceOver macOS and iOS, NVDA Windows) needs a person with those setups, or a
  contracted tester; P5R-13 fixes what it finds. Until then the item stays partial.
- **Native review** of the Polish and Chinese strings added in P5R-1 and P5R-6/7 (owner-action issue, as ADR 0096).
- Acceptance of **ADRs 0150, 0151, 0152** (Proposed with this plan).
- For `search:eval --live`: `ANTHROPIC_API_KEY` and the monthly cap; for the hybrid eval: an embeddings endpoint
  (a local Ollama model is enough).
- Publishing the demos (#24) before the live widget toggle and any public offline demo.

## 13. Files touched (summary)

- `front/web`: `src/layouts/Base.astro`, `src/components/LessonPlayer.astro`, `src/pages/{login,search,logout}.*`,
  `src/pages/{courses/[id],learn/[courseId]/finish,account/index}.astro`, `src/pages/learn/[courseId]/offline.json.ts`,
  `src/pages/site.webmanifest.ts`, `src/pages/offline.astro`, `public/sw.js`, `scripts/build-sw.mjs`, `src/lib/{bff,theme,accent,locale}.ts`,
  `tests/{unit,a11y,e2e}/*`.
- `front/ui`: `src/lib/{player-strings,account-strings,search-strings}.ts`, `src/elements/{quiz,progress,video,h5p,tutor-panel,widgets}.ts`,
  `src/theme/presets.ts`, `scripts/theme-manifest.mjs`, `catalogue/themes.json`, `fixtures/*`, `tests/*`.
- `front/sdk`: `src/{offline,search}.ts`, `src/types.ts`, `src/index.ts`, `tests/*`.
- `front/docs-site`: `src/components/WidgetPlayground.astro`, `src/widgets/fixture-api.ts`, `src/content/docs/extending/widgets/*.mdx`,
  `operators/{accessibility,ux-metrics,search,offline}.mdx`, `developers/reference-frontend.mdx`, `tests/widgets.a11y.spec.ts`.
- `admin`: `src/pages/Settings/{global,theme,ai}.tsx`, `src/components/{GuidedEmpty,AiProvenance,BulkBar}`, `src/hooks/useTableQueryState.ts`,
  `src/services/{uxMetrics,bulk,savedViews,templates}.ts`, `src/components/Dashboard/*`, `src/pages/{Users,Courses}/index.tsx`, `src/e2e/*`.
- `api/packages`: `settings` (language validation, themes.json), `courses` (`ProgressSyncService`, `AccessibleCourses`, sync route),
  `search` (new), `tutor` (CJK bigrams), `ai` (`EmbeddingClient`, calls log endpoint), `course-builder` (provenance endpoint,
  `SiteTheme` manifest), `core` (bulk, saved views), `courses-import-export` (templates), `tenancy` (sample step, `--created-at`,
  `--language`), `ux-metrics` (new); Swagger attributes, READMEs and tests for each.
- CI: `.github/workflows/ci.yml` (SDK drift check, pgvector job), `nightly-conformance.yml` (personalisation journey).
- Docs: ADRs 0150-0152, `docs/decisions/README.md` (regenerated), `docs/ROADMAP-TODO.md` (on approval).
