# Plan: feature catalogue on the landing and a friendlier API browser

Status: **approved 2026-10-11**, in implementation.

Decisions: (1) Scalar. (2) New `FeatureIndex` component. (3) H5P list: the content types published on
h5p.org (supplied by the owner, 2026-10-11) instead of only the seeded demos. (4) Translations only for
the landings (PL, ZH); docs and every other page stay English. (5) `ItemList` JSON-LD: yes. (6) Complete
OpenAPI responses for the featured packages now. Simplification from the design below: there is no
separate `features.json`; the English landing document (`platform.json`, `FeatureIndex` node) is the
single source and the drift tests read it.

## Goal

1. A collapsed-by-default "Everything it does" section on the platform landing (EN, PL, ZH) that lists
   what the product really does, grouped, with every item linking to its docs page. Content is in the
   HTML (not loaded on click), so search engines index it; it also works as a sales checklist.
2. A browsable API reference in the docs site (grouped by package, searchable, with request examples
   and try-it-out), instead of only "Swagger UI on a running API host".

## What exists today (explored)

- `api/packages/`: 66 packages, each with a generated docs page in
  `front/docs-site/src/content/docs/reference/packages/<name>.md`.
- Landing = JSON trees (`front/web/src/docs/platform.json`, `i18n/platform.{pl,zh}.json`) of catalogue
  components. A unit test keeps the translations parallel to English. The `Faq` component is an
  accessible no-JS disclosure list (`<details>`), but it is a flat question/answer list.
- API docs: `darkaonline/l5-swagger` annotations, Swagger UI at `/api/documentation` on every API host,
  `storage/api-docs/api-docs.json` (1.1 MB, gitignored, built per container), types in `@ulams/sdk`.
  Many annotations have no response bodies (roadmap 7.3).
- Docs site: Starlight (`front/docs-site`), already has `developers/api-reference.mdx` and
  `reference/api-endpoints.md` (endpoint index, 60 KB).

## Design

### 1. Feature inventory (single source of truth)

`front/web/src/docs/features.json`: groups, each with items `{ id, title, text, packages[], docs }`.
Draft groups (every item is verified against the package code and tests before it goes in; nothing is
claimed from a README alone):

| Group | Draft items (packages) |
|---|---|
| Content and lessons | Topic types: video, audio, image, PDF, rich text (Markdown), oEmbed, H5P, LiaScript, interactive (topic-types, liascript, interactive, h5p) |
| Media pipeline | Video to adaptive HLS with ffmpeg, per-bitrate and scale config, queued (video); on-demand image resize, cached, optimised, batch endpoint (images); uploads and files; YouTube (youtube, files, uploads) |
| Quizzes and assessment | GIFT quizzes: multiple choice (single and multiple), true/false, short answer, matching, numerical, essay, description; attempts, time limit, per-attempt shuffle, teacher grading of essays (topic-type-gift); questionnaires; projects and tasks |
| H5P | Authoring and playing H5P in an isolated service (Lumi); content types are installed from the H5P Hub per platform; the seeded demos use Multiple Choice, Drag and Drop, Drag the Words, Fill in the Blanks, Dialog Cards, Image Hotspots, Course Presentation, Interactive Video, Branching Scenario |
| Standards | SCORM 1.2/2004, cmi5, xAPI LRS, LTI, course import/export (scorm, laravel-scorm, cmi5, lrs, lti, courses-import-export) |
| AI and Living Course | Course Builder, cited generation, Living Course sync, adaptive paths (course-builder, living-course, adapt, ai) |
| Live learning | Webinars, Jitsi, Pencil Spaces, stationary events, consultations, Mattermost |
| Commerce | Cart, payments (Przelewy24), vouchers, invoices, Sylius headless commerce, course access and entitlements |
| Platform and admin | Multi-tenancy, roles and permissions, settings, custom model fields, translations, email/SMS/PDF templates, bulk notifications, CSV users, reports, MailerLite |
| Developer | OpenAPI, scoped API tokens, webhooks, TypeScript SDK, CLI |

A unit test fails when a package under `api/packages/` is neither referenced by an item nor listed in
an explicit `excluded` list with a reason (for example `core`, `example-plugin`, `przelewy24-php`), so
the catalogue cannot silently drift as packages are added.

### 2. Landing component

New catalogue component `FeatureIndex` (props: `id`, `eyebrow`, `title`, `intro`, `groups`), rendered as
one native `<details>` per group, all collapsed, each item a short line plus a "Docs" link and the
package chips. No JavaScript, keyboard and screen-reader accessible (WCAG 2.2 AA), expand/collapse-all
is not needed. Placed after the workflow showcase and before the comparison table. Reusing `Faq` was
considered and rejected: it has no nesting, no per-item links and no package chips.
The component goes into the UI catalogue manifest (with the docs page `reference/ui-catalogue.md`
regenerated), the platform JSON gets the section in EN, PL and ZH, and the Cloudflare static build
picks it up unchanged.

SEO: headings per group (h3), real text in the DOM, internal links to docs, `ItemList` JSON-LD is
optional (see open questions).

### 3. API browser

Recommendation: **Scalar API Reference** (MIT, one npm package, static, search, grouped sidebar, code
samples in several languages, try-it-out, dark mode) on a docs page `/developers/api-browser/`,
bundled from npm into the docs site (no CDN at runtime).

- Spec source: the docs CI generates `api-docs.json` once (php artisan l5-swagger:generate in the API
  container used by the existing docs workflow) and copies it to `public/openapi.json`; nothing 1.1 MB
  is committed. Locally `make docs-spec` does the same.
- Navigation: tags by package (adds `#[OA\Tag]` where a controller has none), so the sidebar mirrors
  `reference/packages`.
- Try-it-out server defaults to the throwaway demo API (`gravity-staging-api.ulams.app`) with a
  server picker for a local host; scoped tokens are explained next to the browser.
- The existing Swagger UI stays on the API hosts; `api-reference.mdx` links to both.
- Known limit: response bodies are missing for many endpoints (roadmap 7.3). The browser makes that
  visible, so I would complete responses for the endpoints of the packages listed on the landing
  (video, images, GIFT, courses) in the same milestone, and leave the rest to 7.3.

Alternatives: Redoc (read-only, no try-it in the open edition), Stoplight Elements (heavier, React),
Swagger UI embed (what we already have, the UX complaint).

## Scope and sequencing (small commits, tests in the same commit)

1. `features.json` + schema + drift test (EN only). Facts verified from code; list of what I could
   not verify goes to the open questions, not into the file.
2. `FeatureIndex` component, manifest entry, render tests, axe check, landing section in EN.
3. PL and ZH translations (the parallel-structure test covers them).
4. Docs: spec generation in the docs workflow, Scalar page, tags by package, update `api-reference.mdx`.
5. Responses for the endpoints of the featured packages.
6. TODO items added as `(new)`; docs page for the catalogue (how to add a feature).

## New dependencies

- `@scalar/api-reference` (docs-site only): MIT, actively maintained, about 1.5 MB of JS in the docs
  bundle only, no runtime service, fully self-hosted. Nothing is added to the learner front or API.

## Risks

- Marketing claims drifting from the code: mitigated by the drift test and by one verified source per
  item.
- Spec generation in docs CI needs the API container (time, about 2 min). Fallback: committed snapshot
  refreshed by a make target.
- A 1.1 MB spec in the browser: Scalar loads it once from the static file; acceptable for docs.

## Open questions (need your answer)

1. Scalar (recommended) or Redoc?
2. New `FeatureIndex` component (recommended) or reuse `Faq` with groups as questions?
3. H5P wording: "any content type from the H5P Hub, installed per platform" plus the nine seeded
   demos, or only the nine I can show? I will not write "all H5P types" without checking the library
   list on a live service.
4. Translate to PL and ZH in this milestone (recommended, tests require parallel trees) or EN first?
5. Add `ItemList` JSON-LD to the section? It is cheap but search engines mostly ignore it for lists
   like this.
6. Complete OpenAPI responses for the featured packages now (recommended) or leave all to 7.3?
