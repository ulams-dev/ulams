# Phase 5 plan: learner UI for the Phase 4 and Phase 6 backends

Status: **draft, waiting for approval**. No code is changed by this plan.

The Phase 4 personalisation backends (`learner-insights`, `tutor`) and the Phase 6 compliance backends
(`compliance`, `templates-pdf` certificates) are merged, but most of what they offer never reaches a
learner: the remediation documents come back from the API as data nobody renders, the tutor has no
panel, the presentation profile is never applied, and a learner cannot see their required training,
certificates or skills. This plan covers that front-end work, the small API additions it needs, the
two real-model eval commands that are still open, and the accessibility gate (WCAG 2.2 AA, European
Accessibility Act documentation) for every new screen.

Facts below come from reading `main` at `d365cbb60` (2026-10-21). Paths are relative to the repository
root.

---

## 1. What exists today

### 1.1 Backends (merged)

| Package | Learner endpoints (all `auth:api`, scoped to the caller) | Notes for the UI |
|---|---|---|
| `learner-insights` (`api/packages/learner-insights/src/routes.php`) | `GET courses/{c}/topics/{t}/support`; `POST assignments/{a}/feedback`, `/hide`, `/reflection`; `GET/PUT courses/{c}/presentation`; `GET/PUT me`, `me/settings`, `GET me/export`, `DELETE me`; `POST signals`; `POST nudges/opened` | `support` returns `{enabled, items[]}`; each item has `assignmentId`, `kind`, `status` (`generating`/`ready`), `reasons[]`, `why`, `feedback`, `document`, `citations[{fragmentId,label}]`, `aiLabel`. The document is `{summary, blocks[{component, props:{title, markdown, practice?}, citations[]}]}` with `component` in `Prose`, `WorkedExample`, `PracticeQuestion`, `ElaborativeQuestion` (`src/Remediation/RemediationGenerator.php:28,204`). `reflection` returns `{correct, missing, followUp, citations, remaining}`; 429 at the daily limit, 503 when AI is off. `presentation` returns `{adaptive, profile{density, chunking, navigation, hints}, derived, overrides, why[]}` |
| `tutor` (`api/packages/tutor/src/routes.php`) | `POST courses/{c}/messages {text, topicId?, conversationId?}`; `GET courses/{c}/conversations`; `GET/DELETE conversations/{id}` | `ask` returns `{conversationId, messageId, questionId, answer, citations[{chunkId,label,topicId,fragmentIds}], refusal, inScope, remaining, label}`. Errors: 503 `disabled`, 403 `forbidden`, 429 `limit` (daily or monthly). `tutor.enabled` is a non-public setting, so **the front cannot know whether the tutor is on before asking**. No streaming: the `ai` driver streams internally but returns one final result (`api/packages/ai/src/Drivers/AnthropicDriver.php`) |
| `compliance` (`api/packages/compliance/src/UlamsComplianceServiceProvider.php:182`) | `GET api/compliance/my-assignments`, `my-paths`, `my-skills`, `team/assignments` (managers, direct reports only) | Assignment shape in `src/Http/AssignmentPresenter.php` (status, due, completed, escalation, certificate id/status/expiry); path steps `done/available/locked` with `waiting_for`; skills with `area`, `courses`, `earned_at`, `expires_at` |
| `templates-pdf` | `GET api/pdfs` (own certificates), `GET api/pdfs/generate/{id}` (PDF bytes), public `GET api/certificates/verify/{certificateId}` | `PdfListResource` has `status`, `expires_at`, `revoked_at`, `course_version` but **not the public `certificate_id`**, so a learner's list cannot link to the verification page |

### 1.2 Front end (merged)

- `@ulams/ui` (`front/ui`): JSON-schema registry (`src/registry.ts`, 1 941 lines), renderer with text
  fallbacks (`src/render-core.ts`, `src/Render.astro`, `src/Node.astro`), learner-layout manifest copied
  into `topic-type-layout` (`scripts/learner-manifest.mjs`), custom elements in `src/elements/`, embeddable
  widgets (`src/elements/widgets.ts`: catalogue, my courses, continue), interface strings in EN/PL/ZH
  (`src/lib/i18n.ts`), vitest + axe in jsdom. **None of** `SupportCard`, `WorkedExample`,
  `PracticeQuestion`, `ElaborativeQuestion`, `FeynmanReflection`, `AiLabel`, `WhyThis`, `CitationList`,
  `TutorPanel` **exist**. `Prose` has a closed schema without `title`, so the stored remediation
  `Prose` block (`{title, markdown}`) would render as a fallback today. `Steps` is a landing component
  with plain-text items, not a lesson chunker.
- `@ulams/web` (`front/web`): lesson player (`src/components/LessonPlayer.astro`, server-rendered, Living
  Course notices fetched in SSR and rendered with `<Render>`), course page, finish page (shows a
  **mock** certificate preview, not the issued one), account page (profile, my courses, my tokens),
  `/account/personalisation` (statuses with reasons, switches, export, erase; `src/lib/insights.ts`),
  public `/certificates/verify/[id]`. Learner BFF allow-list `src/lib/bff.ts` (buffers responses as
  text, 20 s timeout); the studio BFF already passes SSE through (`src/pages/studio/api/[...path].ts`).
  Learner pages are English only (`Base` defaults to `lang="en"`); only the platform landing has
  PL/ZH routes (ADR 0096).
- Tests: `tests/a11y/*.a11y.spec.ts` (axe and keyboard, desktop and 360 px, against
  `tests/mock-api/server.mjs`) run in CI as `web-a11y` (ADR 0069, #294); full-stack e2e in `tests/e2e`.
- `@ulams/sdk` (`front/sdk`): request paths typed from the generated OpenAPI (it already lists the
  tutor, presentation, support and compliance paths); clients for course builder, Living Course
  (author and learner) and an SSE reader (`src/ag-ui.ts`: `SseParser`, `readEventStream`,
  `connectEventStream`). No insights, tutor, compliance or certificate client.

### 1.3 Not in this plan

The studio Insights page and the admin Learner Insights settings screen (author and admin UI,
`docs/plans/phase-4.md` M4.6), `ProposalService::createFromInsights`, the experiments package and the
A/B and delayed-retention measures (blocked on L2-23 and #58), semantic search (5.3), PWA offline mode,
translating the existing lesson player strings, and the policy-owner dashboard (6.1.1).

---

## 2. Goal and scope

> A learner who struggles with a lesson sees a short, cited "Extra help for you" card that says why it
> appeared and can be rated or hidden; they can explain the idea in their own words and get feedback;
> they can ask a course tutor that answers only from the course, with citations, streaming as it
> writes; they can choose how lessons are presented (compact, in steps, with hints shown) and see when
> a layout is suggested and why; and they can see their required training, learning paths,
> certificates (with verification links and a badge they can embed) and skills. Every new screen
> passes axe (WCAG 2.2 AA) on desktop and at 360 px, works with the keyboard alone, respects reduced
> motion, and is documented for the European Accessibility Act. The two real-model evals report on
> remediation and tutor quality.

### 2.1 TODO items covered

| TODO item (quoted from `docs/ROADMAP-TODO.md`) | Coverage | Milestone |
|---|---|---|
| 2.7 "Remediation components: Feynman reflection, elaborative questions, worked examples (moved to `docs/plans/phase-4.md` M4.5; #59) (partial: API and declarative blocks merged, #281; the catalogue components and the learner card are not built)" | Full | M-UI1, M-UI2 |
| 2.7 "Per-learner density, chunking, navigation, visible hints from Learner Insights (moved to `docs/plans/phase-4.md` M4.8; #59) (partial: presentation profile API merged, derived per request with learner overrides, #288; the front work is open)" | Full (A/B measurement stays blocked on the experiments package) | M-UI4 |
| 2.7 "UI component catalogue: name, props JSON Schema, model description, accessible implementation, text fallback" | Partial: adds the learner support set and its manifest | M-UI1, M-UI2, M-UI4 |
| 4.1 "(new) Streaming tutor answers (LLM client streaming over the SSE event log)" | Full | M-UI6 |
| 4.2 "(new) Tutor panel in the learner UI and the `tutor:eval` and `learner-insights:eval` real-model eval commands (M4.7 and M4.8; open)" | Full | M-UI3, M-UI7 |
| 4.1 "Privacy: per-tenant toggle, retention, explanations, minimal data to LLM" | Partial: the evals assert that no learner identity reaches the model | M-UI7 |
| 4.3 "Reasons for every recommendation · AI on/off and provider per tenant" | Partial: learner-side reasons on every card and suggestion | M-UI1, M-UI4 |
| 4.3 "AI content labelling · documented data flows, no training on customer content" | Partial: AI labels on remediations, tutor answers, reflection feedback and AI-assisted topics | M-UI1, M-UI2, M-UI3 |
| 5 "Web components (my courses, continue, catalogue, quiz, tutor, certificate badge)" | Full (tutor and certificate badge) | M-UI3, M-UI5 |
| 5 "TypeScript SDK from OpenAPI; widget docs with live examples" | Partial: insights, tutor, compliance and certificate clients; widget docs for the two new widgets | M-UI1, M-UI3, M-UI5 |
| 5 "AI transparency everywhere (diffs, citations, reasons one click away)" | Partial: learner surfaces | M-UI1 to M-UI4 |
| 5 "**WCAG 2.2 AA** + European Accessibility Act; axe in CI" | Full for the new screens, plus the EAA documentation | every milestone, M-UI8 |
| Quality bar "Mocked LLM in tests + eval command on golden fixtures" | Partial: two more eval commands | M-UI7 |
| Quality bar "WCAG 2.2 AA checks for learner-facing UI" | Partial: every screen of this plan | every milestone |

`(new)` items this plan adds to the TODO on approval (under Phase 5 unless noted):

- (new) Learner views for required training, learning paths, certificates and the skill profile
  (Phase 6.1/6.6 backends, M-UI5).
- (new) The finish page shows the issued certificate instead of a mock preview (M-UI5).
- (new) Accessibility statement page per tenant and EAA documentation for operators (M-UI8).
- (new) Translate the existing lesson player and account strings to PL and ZH (not in this plan).
- (new, Phase 4) Practice answers in remediations as learner signals, if #O5 says yes (section 9).

### 2.2 Preconditions

1. None blocking for M-UI1 to M-UI5: all backends are on `main`.
2. M-UI6 needs ADR 0105 (tutor streaming) accepted; M-UI7 live runs need `ANTHROPIC_API_KEY` and a
   monthly eval cap.
3. Full-stack journeys run on the fake LLM driver (`AI_DRIVER=fake`); `learner-insights` and `tutor`
   already ship fake responders (`src/Fake/RemediationResponders.php`, `src/Fake/TutorResponder.php`).

---

## 3. Design

### 3.1 Principles

- **Server-rendered first.** Cards, profile and account views render in SSR from API data, like the
  Living Course notices (`LessonPlayer.astro:77`). Custom elements add only the interactive parts
  (rate, hide, reflect, ask), each working through the learner BFF with the httpOnly session.
- **Declarative only.** Remediation documents render through the catalogue (`<Render>`), validated
  against the registry; unknown or invalid blocks fall back to text. No model-written markup.
- **No dead ends** (principle 1): every surface has loading, generating, empty, error, limit,
  AI-disabled and opted-out states with a next action.
- **One source of strings.** New interface strings go into `UiStrings` (`front/ui/src/lib/i18n.ts`)
  in EN, PL and ZH; content (remediations, tutor answers, reasons) comes from the API.

### 3.2 New catalogue components (`front/ui`)

Each gets a registry entry (closed props schema, model-facing description, `interactive`, text
fallback), an Astro component in `src/components/`, an example in `catalogue/examples/`, and vitest
tests (schema, fallback, render, axe). "App-filled" means the description says authors and models do
not place it, like `UpdateNotice`.

| Component | Props (all objects closed) | Behaviour and accessibility |
|---|---|---|
| `Prose` (changed) | adds optional `title` (≤ 120) | Rendered as an `h3` above the Markdown. Backwards compatible; needed because stored remediation `Prose` blocks carry `title` |
| `WorkedExample` | `title` (req.), `markdown` (req., ≤ 1 800) | Static; labelled `article` with the title as heading |
| `PracticeQuestion` | `title`, `markdown`, `practice{stem, options[2..5]{text, correct}, explanation}` (req.) | Radio group in a `fieldset`/`legend`; "Check" reveals right/wrong in text (never colour only) and the explanation; the explanation stays in a `<template>` until an attempt (same rule as `PracticeActivity`). Fallback omits the correct answer. Custom element `ulams-practice-question` |
| `ElaborativeQuestion` | `title`, `markdown` (req.) | The question, plus an optional notes field labelled "Your thoughts (stay on this device)"; nothing is sent |
| `CitationList` | `items[{label (≤ 200), href?}]` (1..12), `label` default "Sources" | `<ul>` inside a `nav`-less `section` with a visible heading; links only when the API gives a lesson target |
| `AiLabel` | `text` (≤ 120), `tone` (`generated`, `assisted`) | Short text with an icon marked `aria-hidden`; never colour only |
| `WhyThis` | `summary` (default "Why am I seeing this?"), `reasons[]` (1..6, plain text) | Native `<details>` disclosure |
| `SupportCard` (app-filled, children) | `assignmentId`, `status` (`generating`, `ready`, `failed`), `summary`, `reasons[]`, `aiLabel`, `feedback` (`helpful`, `not_helpful`, null), `reflection` (bool) | Labelled `section role="region"` ("Extra help for you"), heading `h2`; children are the blocks; footer with "This helped" / "Not helpful" (toggle buttons with `aria-pressed`) and "Hide extra help for this lesson" (confirms in place, then moves focus to the lesson heading). Status changes announced politely through one `aria-live` region. `generating`: "Preparing an explanation…" with no spinner under reduced motion; shown on the next visit |
| `FeynmanReflection` (app-filled) | `assignmentId`, `prompt` (default "Explain it in your own words"), `maxLength` 2 000 | Labelled `textarea` with a character counter (`aria-describedby`), the privacy sentence from `phase-4.md` §7.2 step 8 before the button, "Get feedback" posts to the BFF; the result shows "What you got right", "What is missing", a follow-up question, citations and an `AiLabel`; 429 and 503 states in words; text is not stored client-side after submit |
| `ProseSteps` | `chunks[{heading (≤ 160), markdown}]` (2..12), `startAt` default 0 | Lesson Markdown split at H2/H3: one part at a time with "Previous part" / "Next part" and "Show the whole lesson"; part n of m in text; without JavaScript every part is visible. Custom element `ulams-prose-steps`; focus moves to the new part heading |
| `PracticeActivity` (changed) | adds optional `initialHints` (0..3) | The first N hints of each challenge render open. Defaults to 0, so existing layouts are unchanged |
| `CertificateBadge` | `certificateId`, `status` (`valid`, `expired`, `revoked`), `courseTitle`, `holderName?`, `issuedAt`, `expiresAt?`, `verifyHref` | Status in words plus icon; link "Verify this certificate". Also exposed as the `<ulams-certificate-badge>` widget (3.6) |
| `TutorPanel` (app-filled) | `courseId`, `topicId?`, `remaining`, `dailyLimit`, `attemptActive` | Astro shell for the `ulams-tutor` element (3.5) |

Text fallbacks: blocks fall back to title + Markdown; `PracticeQuestion` to the stem without the
answer; `SupportCard` to summary + reasons; `TutorPanel` to "Ask the course tutor: open this page in a
browser".

**Manifest and server check (new, closes a Phase 4 gap).** `REMEDIATION_COMPONENTS = ["Prose",
"WorkedExample", "PracticeQuestion", "ElaborativeQuestion"]` and `remediationManifest()` in
`src/registry.ts`; `scripts/remediation-manifest.mjs` writes `catalogue/remediation-manifest.json` and
its copy `api/packages/learner-insights/resources/remediation-manifest.json` (`--check` in `yarn test`,
like the learner-layout manifest). `RemediationGenerator` validates each block's props against that
copy before storing (an invalid block fails the remediation, as an ungrounded one does). The plan for
Phase 4 promised this check (§11.3); the code does not do it today.

### 3.3 Lesson page: "Extra help for you" (M-UI1, M-UI2)

- `front/web/src/lib/support.ts` (new): `getSupport(tenant, token, courseId, topicId)` (1.5 s timeout,
  failure = no card) and `supportDocs(items, locale)`, which maps each item to one `SupportCard` node
  whose children are the stored blocks (component and props as stored), each followed by a
  `CitationList` built from the block's `citations` resolved against the item's `citations` labels,
  plus a `FeynmanReflection` node when the item is `ready` and the tenant has AI on.
- `LessonPlayer.astro`: fetch support in parallel with the notices for tracked, accessible topics;
  render cards after the Living Course notices and before the topic document (the learner sees help
  before re-reading), and a short link "Extra help is available below" in the topic header when the
  card is long.
- Interactions: `front/ui/src/elements/support.ts` (`ulams-support`) posts feedback and hide through
  `/bff`. BFF rules (`front/web/src/lib/bff.ts`):
  `GET /api/learner-insights/courses/\d+/topics/\d+/support`,
  `POST /api/learner-insights/assignments/[0-9A-Za-z]{26}/(feedback|hide|reflection)`.
- Citations: fragments have no learner URL today, so citations show the fragment label as text (no
  link). Tutor citations link to their topic.
- AI-assisted topics (4.3 labelling, M-UI2): `GET /api/courses/{id}` gains `ai_assisted: bool` per
  topic through a resource extension in `course-builder` (entity map lookup, no LMS table change, as
  `phase-4.md` §9.7 planned); the player shows `AiLabel tone=assisted` "Created with AI assistance and
  reviewed by the author" in the lesson footer when the tenant setting `ai.label_generated_courses` is
  on (default on).

### 3.4 Adaptive presentation (M-UI4)

- `front/web/src/lib/presentation.ts` (new): `getPresentation` / `savePresentation`, defaults when the
  module is off or the call fails.
- **Lesson view** control: a disclosure in the lesson player header (and on the course page next to
  the syllabus) with four labelled radio groups: text density (comfortable, compact), lesson parts
  (whole lesson, one part at a time), navigation (overview, linear), hints shown at start (0 to 3);
  "Reset to suggested". It is a plain form posted to `/learn/{course}/presentation` (SSR action, works
  without JavaScript) that calls `PUT presentation` and redirects back. When `adaptive` is true and the
  derived profile differs from the defaults, a `WhyThis` shows the API's `why[]` ("Suggested because
  two lessons in this module were hard"), and a one-line notice says the layout was adjusted with a
  "Show the whole lesson" action (the learner override).
- Application, catalogue only, no model call:
  - `density=compact`: `data-density="compact"` on the player root swaps the spacing tokens
    (`--ulams-space-*`) in `front/ui/src/styles/base.css`;
  - `chunking=steps`: `topicDoc()` (`front/web/src/lib/page-docs.ts`) turns the reading topic's `Prose`
    into `ProseSteps` when it has at least two H2/H3 sections (`splitMarkdownSections()` in
    `front/ui/src/lib/markdown.ts`, unit-tested on code fences and headings inside lists);
  - `navigation=linear`: the course tree (`<details data-tree>`) starts closed and the footer leads with
    "Next"; `overview` opens it on wide screens;
  - `hints=N`: `layoutNodes()` sets `PracticeActivity.initialHints = N`.
- Controls are shown whenever the endpoint answers (the module is on for the tenant); suggestions only
  when `adaptive` is true (the tenant flag). Open question #O3.

### 3.5 Tutor panel (M-UI3, streaming in M-UI6)

API addition (M-UI3): `GET /api/tutor/courses/{course}/status` → `{enabled, aiEnabled, remaining,
dailyLimit, attemptActive}` (course `attend` gate, tenant isolation test, Swagger). It lets the page
hide the tutor when it is off and tell the learner up front that during a quiz it explains concepts
only.

Front end:

- `front/ui/src/elements/tutor.ts` (`ulams-tutor`): a launcher button "Ask the course tutor" in the
  lesson player footer (same place on every lesson: WCAG 3.2.6 consistent help) opening a native
  `<dialog>`: `show()` (non-modal side sheet, the lesson stays readable) at ≥ 720 px, `showModal()`
  full screen below. Focus goes to the question field on open; Esc and the close button return focus
  to the launcher.
- Contents: conversation log (`role="log"`, `aria-live="polite"`, one announcement per finished
  answer), each answer with `AiLabel` "AI tutor · answers only from this course", `CitationList`
  linking `/learn/{course}/{topicId}`, refusals in plain words (out of scope, quiz in progress,
  unsafe, couldn't answer); question field (≤ 1 000 chars, counter); remaining questions today; the
  learner's earlier conversations of this course (list, open, delete with confirmation, "kept for 90
  days"). Typing state "The tutor is writing…" (static text under reduced motion).
- Markdown in answers: a math-free renderer split out of `front/ui/src/lib/markdown.ts`
  (`renderBasicMarkdown`, same `marked` instance and escaping, no KaTeX import) so the panel chunk
  stays small; the panel script is loaded only when the launcher is first used. Budget: ≤ 20 KB
  gzipped for the panel chunk, measured in `tests/perf/measure.mjs`.
- Errors: 429 → limit text and a link back to the lesson; 503 → "The tutor is not available right
  now"; network → retry button keeping the typed question.
- BFF rules: `GET /api/tutor/courses/\d+/status`, `POST /api/tutor/courses/\d+/messages`,
  `GET /api/tutor/courses/\d+/conversations`, `GET|DELETE /api/tutor/conversations/[0-9A-Za-z]{26}`.
- The same element works as an embeddable widget with `api-url` and `token`, like `widgets.ts`
  (TODO 5.2 "tutor").

### 3.6 Streaming tutor answers (M-UI6, ADR 0105 proposed with this milestone)

ADR 0011 rejected streaming from the HTTP request because it cannot resume; the TODO item asks for
the SSE event log. Design:

1. `ai`: `LlmClient::stream(LlmRequest $request, callable $onDelta): LlmResult`. The Anthropic driver
   already consumes a stream; it forwards text deltas of the structured output. A small incremental
   extractor (no dependency) pulls the `answer` string out of the partial JSON (the schema puts
   `answer` first). The fake driver replays its canned answer in chunks. Logging and cost stay in
   `generate()`'s path (one `ai_calls` row).
2. `tutor`: `POST messages` with `stream: true` returns 202 `{conversationId, runId}` and queues
   `AnswerJob`; the job appends AG-UI events to a new `tutor_events` table (`RUN_STARTED`,
   `TEXT_MESSAGE_START`, `TEXT_MESSAGE_CONTENT`, `TEXT_MESSAGE_END`, `CUSTOM tutor.final` with
   validated answer, citations, refusal and remaining; `RUN_ERROR`) and wakes readers through the cache
   key, as ADR 0029 does for the builder. `GET /api/tutor/runs/{run}/events` streams with
   `Last-Event-ID` resume, 25 s cap and reconnect. The tailing loop is extracted from
   `course-builder/src/Http/Controllers/EventStreamController.php` into a shared class in `core` rather
   than copied. Events are pruned with the conversation (90 days) and after 1 day for the run log.
3. **Guards stay authoritative.** With an active quiz attempt the tutor never streams (the answer-leak
   check needs the whole answer): the run emits only `tutor.final`. Without an attempt, streamed text is
   shown as a draft (`aria-busy="true"`, not announced); `tutor.final` replaces it with the validated
   answer and citations. If validation fails, the draft is replaced by the standard "I couldn't answer
   that" message. Without JavaScript or when SSE fails, the client falls back to the M-UI3 request.
4. Front: a BFF rule with a `stream` flag passes `text/event-stream` through (pattern of the studio
   BFF: request signal, `X-Accel-Buffering: no`); `ulams-tutor` uses `connectEventStream` from
   `@ulams/sdk`. Under reduced motion the draft appears in sentence-sized steps, not per token.

### 3.7 My training, certificates and skills (M-UI5)

API additions: `certificate_id` in `PdfListResource` (owner only; it is already public on the
verification endpoint); nothing else.

| Route | Content | Data |
|---|---|---|
| `/account/training` (new) | **Required training**: course, status in words (assigned, due soon, overdue by n days, completed, cancelled), due date, "Start" / "Continue" link, the certificate when it satisfied the assignment. **Learning paths**: ordered steps with done / available / locked ("Unlocks after: …"). Empty state: "No required training. Courses you are asked to complete appear here." | `my-assignments`, `my-paths` |
| `/account/training#certificates` | **Certificates**: course, issued, valid until or "does not expire", status (valid, expiring in n days, expired, revoked with date), course version, "Download PDF", "Verify", "Copy badge code" (the `<ulams-certificate-badge>` snippet) | `GET /api/pdfs` |
| `/account/certificates/[id].pdf` (new endpoint) | Streams the PDF with the learner's token (the BFF buffers text, so binary needs its own route); `Content-Disposition: attachment`, `no-store` | `GET /api/pdfs/generate/{id}` |
| `/account/skills` (new) | Skills grouped by area: name, code, earned on, lapses on (or none), the courses that prove it; empty state with a link to the catalogue | `my-skills` |
| `/account/team` (new, only when the API returns rows) | Manager view of direct reports' assignments: overdue first, filter by status | `team/assignments` |
| `/account` | Summary links: "2 trainings due this month", "3 certificates", "5 skills", plus the existing personalisation link | the same calls, in parallel |
| `/learn/{course}/finish` | Replaces the mock preview with the issued certificate (`CertificateBadge`, download and verify); keeps a clearly labelled preview only while the course is unfinished | `GET /api/pdfs?assignable_type=…&assignable_id={course}` (morph alias checked against ADR 0043 at implementation) |

`<ulams-certificate-badge certificate-id api-url>` (widget, `front/ui/src/elements/widgets.ts`): calls
the public verification endpoint, renders status, course, issue and expiry dates in a shadow root,
links to `{tenant}/certificates/verify/{id}`. Holder name only if the verification endpoint returns it
(it does today). Docs page with a live example.

### 3.8 SDK (`front/sdk`)

New modules, unwrapping `data` like `living-learner.ts`, exported from `src/index.ts`, unit-tested
with a fake fetch:

- `insights.ts`: `support`, `feedback`, `hide`, `reflection`, `presentation`, `savePresentation`,
  `me`, `saveSettings`, `exportData`, `erase` (moves the types out of `front/web/src/lib/insights.ts`).
- `tutor.ts`: `status`, `ask`, `askStream` (M-UI6, returns the run id and an event iterator),
  `conversations`, `conversation`, `deleteConversation`.
- `compliance.ts`: `myAssignments`, `myPaths`, `mySkills`, `teamAssignments`.
- `certificates.ts`: `list`, `verify`, `downloadUrl`.

### 3.9 Accessibility (WCAG 2.2 AA) and the European Accessibility Act

Per screen, in the milestone that builds it:

- axe (`@axe-core/playwright`, tags `wcag2a`, `wcag2aa`, `wcag21aa`, `wcag22aa`) on desktop 1440 px
  and phone 360 px in `front/web/tests/a11y/learner.a11y.spec.ts`, every state that changes the DOM
  (empty, generating, ready, feedback given, limit, error, tutor open, reflection result);
- keyboard journey in `front/web/tests/a11y/keyboard.a11y.spec.ts`: reach, operate and leave each
  control in order; focus visible and **not obscured** by the sticky player footer or the tutor sheet
  (2.4.11); targets ≥ 24 × 24 px (2.5.8); Esc closes the tutor and returns focus;
- reduced motion (`prefers-reduced-motion: reduce` emulated): no animation on cards, parts or the
  typing state; streamed text in sentence steps;
- screen reader semantics checked in unit tests: region labels, one polite live region per surface,
  status in words, `aria-pressed` on feedback buttons, `aria-busy` on streaming drafts;
- 200 % zoom and 320 CSS px reflow without horizontal scroll (1.4.10), checked by the phone project;
- component level: vitest + axe in jsdom for every new catalogue component (`front/ui/tests`).

EAA documentation (M-UI8):

- `front/docs-site/src/content/docs/operators/accessibility.mdx` (new): conformance target (WCAG 2.2 AA,
  EN 301 549 v3.2.1 clause 9 mapping), what is tested automatically and by hand, known limitations
  (third-party H5P, SCORM and LTI content; model-generated content is checked by the remediation
  validator and the builder's accessibility critic, not guaranteed), the per-tenant statement and how
  to fill it, feedback and enforcement contacts.
- `front/web/src/pages/accessibility.astro` (new): per-tenant accessibility statement rendered from a
  template with the tenant's name and contact (public settings), linked from the site footer. The
  operator (the service provider under the EAA) owns the facts; the page says so.
- `front/docs-site/src/content/docs/learners/accessibility.mdx`: keyboard shortcuts, lesson view
  options, reduced motion, how to report a barrier.

### 3.10 Internationalisation (EN, PL, ZH)

- New strings go into `UiStrings` with keys prefixed by surface (`support*`, `tutor*`, `lessonView*`,
  `training*`, `certificate*`, `skills*`) in all three languages; a test fails when a key is missing
  in PL or ZH (extends `front/ui/tests/i18n-render.test.ts`).
- Learner locale (ADR 0106, proposed with M-UI1): `learnerLocale(course.language, tenantDefault)` picks
  `pl` or `zh` when the course language starts with that code, else `en`; account pages use the tenant
  default. `Base` gets the matching `lang`. Plural forms through `Intl.PluralRules` (Polish has three).
  Dates through `Intl.DateTimeFormat` in that locale.
- API text (reasons, refusals, error messages): the BFF and `data.ts` forward `Accept-Language`;
  `learner-insights` has `resources/lang/{en,pl}`, so ZH is added there (and in `tutor` for its
  messages). Whether Laravel picks the locale from the header is verified in M-UI1 (open question #O6
  if it does not).
- Tutor answers follow the question's language (the prompt already asks for it); remediations are
  generated in the course language.
- Native review of PL and ZH strings: owner action, as for the landing (ADR 0096).

---

## 4. New dependencies

None. Rendering uses existing `@ulams/ui` infrastructure and `marked`; streaming reuses the SDK's SSE
reader and the builder's tailing approach; tests use the existing Playwright, `@axe-core/playwright`,
vitest, jsdom and axe-core. Not added: a chat UI kit, CopilotKit or `@ag-ui/client` (ADR 0011), a
partial-JSON parser library (the extractor is a few dozen lines with tests), an i18n library.

## 5. Migrations

- `tutor_events` (tenant DB, M-UI6): `id` bigint (SSE id), `run_id` ULID, `conversation_id`, `type`,
  `payload` JSON, `created_at`; index `(run_id, id)`. Down drops the table.
- No other schema change. `certificate_id` in the list resource, `ai_assisted` per topic and the
  tutor status endpoint read existing columns.

---

## 6. Tests

| Layer | What |
|---|---|
| `@ulams/ui` vitest | Schema, defaults, fallback (no answer leak in `PracticeQuestion`), render and axe for every new or changed component; manifest `--check`; `splitMarkdownSections` (fences, nested lists, no headings); i18n key parity; custom elements (`support`, `practice-question`, `prose-steps`, `tutor`) in jsdom: focus moves, `aria-pressed`, live region text, reduced motion |
| `@ulams/sdk` vitest | Every new client method with a fake fetch; error mapping (401, 403, 429, 503); `askStream` over a recorded SSE body including reconnect with `Last-Event-ID` |
| `@ulams/web` vitest | `supportDocs` mapping (citations, failed and generating items, reflection only when ready), BFF rules (allowed and refused paths, the stream flag), presentation form parsing, `learnerLocale`, training status wording (due soon, overdue days), certificate PDF route headers |
| API PHPUnit | Remediation manifest validation (invalid block → failed); tutor status endpoint (enabled, disabled, attempt active); `certificate_id` in the list; `ai_assisted` resource extension; streaming: fake-driver run writes the expected event sequence, active attempt emits only `tutor.final`, validation failure replaces the draft, resume from `Last-Event-ID`, pruning; tenant isolation and learner isolation (another learner's run or conversation is 404) for every new endpoint |
| Playwright a11y suite (mock API, CI `web-a11y`) | `tests/mock-api/server.mjs` gains fixtures for support (generating, ready with all four blocks), reflection, presentation, tutor status, ask, conversations, an SSE run, compliance and certificates. axe and keyboard per screen as in 3.9 |
| Playwright e2e (full stack, fake driver, `PERSONALISATION_E2E=1`) | `tests/e2e/personalisation.spec.ts`: build the coffee course in the studio on the fake driver, sign in as a learner, answer a quiz question wrong twice, run `learner-insights:evaluate`, see the support card with reasons, rate it, get reflection feedback; switch the lesson view to parts and back; ask the tutor (streamed and non-streamed), get a citation link, start a quiz and see the refusal; open training, download a certificate and verify it. axe on every step |
| Evals (real model, never in CI) | M-UI7, section 7.3 |

---

## 7. Milestones

Each milestone is a separate PR from its own branch (`phase-5/<slug>`), demoable on its own, with
tests in the same commits. Axe and keyboard coverage ship with each screen; M-UI8 closes the
cross-cutting gate.

| # | Milestone | TODO items (short) | Demo |
|---|---|---|---|
| **M-UI1** (recommended next) | "Extra help for you" card | 2.7 remediation components (worked example, elaborative, practice); 4.3 reasons; catalogue | On the mock API and on a fake-driver tenant: lesson 2.3 shows "Extra help for you" with an explanation, a worked example and a practice question, sources, the AI label and "Why am I seeing this?"; "This helped" sticks after reload; "Hide" removes the card and focus lands on the lesson heading; a `generating` item shows "Preparing an explanation…"; axe clean on desktop and 360 px |
| M-UI2 | Feynman reflection and AI labels | 2.7 Feynman reflection (completes the remediation item); 4.3 labelling | The learner writes an explanation, sees the privacy sentence, gets "what you got right / what is missing / follow-up" with sources; the sixth try of the day says the limit is reached; topics from a builder course show "Created with AI assistance and reviewed by the author" |
| M-UI3 | Tutor panel | 4.2 tutor panel; 5.2 tutor widget; SDK tutor client | "Ask the course tutor" opens a side sheet; a question gets a cited answer that links to the lesson; an off-topic question is declined; during a quiz the panel says it explains concepts only and refuses an answer request; the counter shows 29 left; the conversation can be reopened and deleted; the same panel embedded on a plain HTML page through `<ulams-tutor>` |
| M-UI4 | Adaptive lesson view | 2.7 density, chunking, navigation, hints | With the tenant flag on and a struggling learner, the lesson opens in parts with hints shown and says why; "Show the whole lesson" switches back and is remembered; compact density and linear navigation from the Lesson view form, also without JavaScript |
| M-UI5 | My training, certificates and skills | (new) learner training views; (new) issued certificate on the finish page; 5.2 certificate badge; SDK compliance and certificate clients | A learner with an overdue required course sees "Overdue by 3 days" and a Continue link, a path with a locked step, a valid certificate with Download and Verify, a skill that lapses with it; the finish page shows the issued certificate; the badge snippet works on a plain page; a manager sees their reports' overdue training |
| M-UI6 | Streaming tutor answers | 4.1 streaming tutor answers | The answer appears as it is written (draft not announced), then the validated answer with citations replaces it; reloading mid-answer resumes; during a quiz nothing streams; with SSE blocked the panel still answers |
| M-UI7 | Real-model evals | 4.2 `tutor:eval`, `learner-insights:eval` (completes that item with M-UI3); quality bar evals | `php artisan learner-insights:eval --fixtures=all` and `php artisan tutor:eval` print and store reports on the fake driver; `--live` runs on the real model within the monthly cap |
| M-UI8 | Accessibility gate and EAA docs | 5 WCAG 2.2 AA + EAA; quality bar WCAG; (new) accessibility statement | Keyboard and reduced-motion suites cover every screen of M-UI1 to M-UI6; the full-stack personalisation journey passes; `/accessibility` shows the tenant statement; the operator and learner accessibility pages are on the docs site |

Order rationale: M-UI1 makes merged backend work visible with no API risk beyond the manifest check;
M-UI2 completes the remediation item; the tutor (M-UI3) is independent and can run in parallel with
M-UI1/M-UI2 (different files except `bff.ts` and `i18n.ts`); M-UI4 and M-UI5 are independent of each
other; M-UI6 needs M-UI3 and an ADR; M-UI7 is backend only and can start any time; M-UI8 last.

### 7.1 Commit order

M-UI1

1. `feat(ui): learner support catalogue components and the remediation manifest`
2. `feat(learner-insights): validate remediation blocks against the UI manifest`
3. `feat(sdk): learner insights client`
4. `feat(web): extra help card on the lesson page`
5. `test(web): axe and keyboard coverage for the extra help card`
6. `docs: learner locale ADR and the extra help docs page`

M-UI2

7. `feat(ui,web): Feynman reflection with AI feedback`
8. `feat(course-builder,web): AI-assisted label on builder-generated topics`

M-UI3

9. `feat(tutor): status endpoint for the learner panel`
10. `feat(sdk): tutor client`
11. `feat(ui): tutor panel element and basic Markdown renderer`
12. `feat(web): tutor panel on the lesson page`
13. `docs: tutor learner and widget docs`

M-UI4

14. `feat(ui): ProseSteps, PracticeActivity initial hints and compact density tokens`
15. `feat(web): lesson view form and adaptive presentation`

M-UI5

16. `feat(templates-pdf): public certificate id in the learner list`
17. `feat(sdk): compliance and certificate clients`
18. `feat(web): my training, certificates and skills pages`
19. `feat(web): issued certificate on the finish page`
20. `feat(ui): certificate badge widget`

M-UI6

21. `docs: ADR 0105 tutor streaming over a run event log`
22. `feat(ai): streaming deltas from the LLM client`
23. `refactor(core): shared SSE tail loop extracted from the builder event stream`
24. `feat(tutor): queued answers with a run event log and SSE stream`
25. `feat(web,sdk): streamed tutor answers through the BFF`

M-UI7

26. `test(learner-insights): remediation eval command and fixtures`
27. `test(tutor): tutor eval command and fixtures`

M-UI8

28. `test(web): full-stack personalisation journey on the fake driver`
29. `feat(web): per-tenant accessibility statement page`
30. `docs: accessibility and European Accessibility Act documentation`
31. `docs: roadmap TODO for the learner UI`

### 7.2 Eval commands (M-UI7)

Following `course-builder/src/Console/EvalCommand.php` (fake driver by default, `--live` for the real
model, `--record` writes cassettes, ADR 0024; monthly caps `LEARNER_INSIGHTS_EVAL_MONTHLY_USD` and
`TUTOR_EVAL_MONTHLY_USD`, default 5; reports as JSON and Markdown in `storage/app/evals/`):

- `learner-insights:eval {--fixtures=all|coffee,git} {--live} {--record}`: builds the coffee and git
  builder fixtures, replays recorded struggle patterns (two wrong answers on a question, a slow
  element, an abandoned element), and scores schema validity, manifest validity (3.2), citations ⊆
  provided fragments, grounding pass rate, no correct answer leaked in explanations of the struggled
  question, reading level not above the lesson's (Flesch-Kincaid for English, sentence length for
  others), cost per remediation, cache reuse across learners, and **data minimisation** (the recorded
  request contains no user id, name or e-mail).
- `tutor:eval {--fixtures=all|coffee,git} {--live} {--record}`: 40 questions per fixture (in scope, out
  of scope, quiz-answer requests during an attempt, injection in a fragment and in the question) and
  scores citation precision (judged by the grounding task), refusal correctness, leak rate (target 0),
  p95 latency, time to first streamed token (after M-UI6), cost per answer.

---

## 8. Risks

| Risk | Mitigation |
|---|---|
| Demo tenants have no builder courses and the modules are off, so the card and tutor cannot be shown on the six demos | Full-stack journey on a fake-driver tenant; the mock API for the a11y suite; a demo seeder only if #O1 says so |
| Streaming shows text that validation later rejects | Never stream during an attempt; drafts are marked busy and not announced; the final event always replaces the draft; fallback to the non-streaming request |
| SSE holds PHP-FPM workers | Same 25 s cap and reconnect as the builder, the separate small pool from ADR 0011, and a per-learner limit of one open run |
| Lesson page gets slower (support, presentation, tutor status calls) | Parallel fetches with 1.5 s timeouts and no card on failure; tutor script loaded on first use; perf budget in `tests/perf/measure.mjs` |
| Remediation documents stored before the manifest check have invalid props | The renderer falls back to text per block; a one-off `learner-insights:retire-remediations --invalid` run retires them so they regenerate |
| PL and ZH strings without a native review | Owner-action issue, as for the landing (ADR 0096); English fallback per key |
| Learner locale from the course language misfires for mixed-language tenants | ADR 0106 documents the rule; the account switch is an open question (#O6) |
| Certificate PDFs through a custom route bypass the BFF allow-list | The route accepts only numeric ids, uses the learner's own token (the API checks ownership), sets `no-store`, and has a tenant and learner isolation e2e |
| EAA statement read as a legal guarantee | The page states the tested standard and known limitations, and that the operator owns the statement |

---

## 9. Open questions (each has a default used until answered)

| # | Question | Recommended default |
|---|---|---|
| O1 | Turn Learner Insights and the tutor on for one public demo academy (cost and a seeded builder course)? | **No for now**; demo on a local fake-driver tenant and screenshots. Revisit when the demos publish (#24) |
| O2 | Tutor layout: non-modal side sheet on desktop with a modal on phones, or always a modal dialog (as `phase-4.md` §11.3 said)? | **Side sheet on desktop, modal on phones** (the learner keeps the lesson visible while asking) |
| O3 | Show the Lesson view controls to every learner when the module is on, or only when the adaptive flag is on? | **Every learner when the module is on**; suggestions only with the flag (a preference is not personalisation) |
| O4 | Stream tutor answers at all, given the 500 ms wake and short answers? | **Yes, as M-UI6 after the panel**, never during quiz attempts; drop it if the M-UI7 eval shows less than 1 s gain in time to first text |
| O5 | Record answers to remediation practice questions as learner signals (better recovery measurement, more data)? | **No**; practice stays local to the browser. Add later behind the existing opt-out if the recovery report needs it |
| O6 | Learner interface language: from the course language (default) or a per-learner switch in the account? | **Course language, then tenant default**; a per-learner switch is a later item |
| O7 | Should the manager team view (`/account/team`) ship in M-UI5, or wait for the compliance admin screens? | **Ship it in M-UI5**, read only |
| O8 | Fragment citations have no learner URL; add a learner "view source passage" endpoint? | **No**; show labels as text, link tutor citations to their lesson |

---

## 10. Files touched (summary)

- `front/ui`: `src/registry.ts`, `src/Node.astro`, new components in `src/components/` (`SupportCard`,
  `WorkedExample`, `PracticeQuestion`, `ElaborativeQuestion`, `FeynmanReflection`, `CitationList`,
  `AiLabel`, `WhyThis`, `ProseSteps`, `CertificateBadge`, `TutorPanel`), `src/components/Prose.astro`,
  `src/components/PracticeActivity.astro`, new elements `src/elements/{support,practice-question,prose-steps,tutor}.ts`,
  `src/elements/widgets.ts`, `src/lib/{i18n,markdown}.ts`, `src/styles/base.css`,
  `scripts/remediation-manifest.mjs`, `catalogue/remediation-manifest.json`, `catalogue/examples/*`,
  `package.json` (script), `tests/*`.
- `front/web`: `src/components/LessonPlayer.astro`, `src/pages/courses/[id].astro`,
  `src/pages/learn/[courseId]/finish.astro`, `src/pages/learn/[courseId]/presentation.ts` (new),
  `src/pages/account/{index,training,skills,team}.astro`, `src/pages/account/certificates/[id].pdf.ts`,
  `src/pages/accessibility.astro`, `src/pages/bff/[...path].ts`, `src/lib/{bff,data,support,presentation,insights}.ts`,
  `src/layouts/Base.astro`, `tests/mock-api/server.mjs`, `tests/a11y/*`, `tests/e2e/personalisation.spec.ts`,
  `tests/unit/*`, `tests/perf/measure.mjs`.
- `front/sdk`: `src/{insights,tutor,compliance,certificates}.ts`, `src/index.ts`, `tests/*`.
- `api/packages`: `learner-insights` (manifest copy and check, ZH strings, eval command, fixtures),
  `tutor` (status endpoint, streaming job, events table, SSE route, eval command, ZH strings),
  `ai` (`LlmClient::stream`, drivers, fake driver), `core` (shared SSE tail loop), `course-builder`
  (use the shared loop; `ai_assisted` resource extension), `templates-pdf` (`certificate_id` in
  `PdfListResource`), each with Swagger attributes and tests.
- Docs: `front/docs-site/src/content/docs/learners/{course-player,personalisation,tutor,training-and-certificates,accessibility}.mdx`,
  `operators/accessibility.mdx`, `developers` widget pages, ADRs 0105 and 0106 (next free numbers at
  implementation time), package READMEs, `docs/ROADMAP-TODO.md`.
