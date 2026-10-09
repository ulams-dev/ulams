# Master prompt: AI-native headless LMS on Wellms ("ULAMS")

You are working on a headless LMS built on **Wellms (Escola LMS)**: a Laravel REST API composed of
`escolalms/*` composer packages, with separate frontends consuming the API. The goal is to turn it
into an **AI-native, headless LMS** whose differentiator is not "generate a course from a PDF"
(that is already a commodity) but **courses that stay in sync with their sources and adapt to
each learner**, with full transparency about where every piece of content came from.

Work **phase by phase**. For every phase: explore → write a plan → **stop and wait for my
approval** → implement in small, reviewable commits → tests → short summary. Never skip a stop.
List open questions explicitly instead of guessing.

---

## 0. Product principles (use these to resolve trade-offs)

Market research (2026) that should drive decisions:
- L&D buyers rank **UX (70%)**, **pricing (63%)** and **integrations (59%)** far above AI
  capabilities (30%). AI only wins if the platform is easy to use and fits existing systems.
- Among AI features, **personalisation** is the most wanted (65% of buyers).
- **Trust** is becoming the main competitive axis: transparent AI, citations, data privacy,
  human control over AI output.
- Buyers seek **certification/compliance** and **extended enterprise** (customer/partner
  training); daily users value **course management** most.
- Competitors (Coursebox, Mindsmith) already offer doc-to-course, chat editing, AI tutor, SCORM/LTI
  export and payments. Their weak spot: generic output and courses that go stale.

Therefore:
1. **UX over feature count.** Every AI feature must have polished loading, streaming, error and
   empty states. No dead ends.
2. **Human in control.** AI proposes, the author approves. Every AI change is a reviewable diff.
3. **Grounded and cited.** Every generated element links to the source fragment it is based on.
   No uncited claims in generated course content.
4. **Integrate, don't lock in.** Standards first (LTI 1.3, SCORM, cmi5, xAPI). Headless means the
   same course can be delivered anywhere.
5. **Cost-aware.** Log tokens and cost for every LLM call from day one.
6. **Developer-first.** Everything the UI can do, the API, CLI and Git workflow can do too.
   Target niche: developer education / customer education for dev tools (docs and repos turned
   into certification paths that update with every release).
7. **Agent-ready.** AI agents are first-class users: MCP interface, machine-readable content,
   scoped permissions and a separate audit trail for agent actions.
8. **Easy to self-host.** One command to run, minimal dependencies, works without AI and without
   internet access, painless upgrades.
9. **Open core.** Free self-hosted core; paid cloud (AI included), enterprise features (SSO/SCIM,
   audit, compliance, certificates) and support. Keep the core genuinely useful; check the
   licences of all `escolalms/*` packages before deciding what can be paid (report in Phase 0).

---

## Phase 0: Foundation and audit

### 0.1 Explore and report (no code)
- Map the repo: packages in use, versions, how courses → lessons → topics and topic types
  (H5P, SCORM, cmi5, video, etc.) are modelled and created via API.
- Map existing modules relevant to later phases and report what each actually does:
  `headless-h5p`, `scorm`, `cmi5`, `lrs`, `tracker`, `reports`, `payments`,
  `cart`, `vouchers`, `translations`, `settings`, `templates`, `notifications`.
- **Do not use, extend or depend on `escolalms/recommender`.** Personalisation is built as a new,
  independent module (Phase 4). Report only whether `recommender` can be safely disabled or
  removed from the API composition, and what (if anything) depends on it.
- Multitenancy: the API depends on `gecche/laravel-multidomain`. Report how domains/tenants are
  configured today (env per domain? DB per domain? storage?) and whether it can support
  dynamically created subdomains.
- Inventory of learner activity data available today, as input for the new Learner Insights
  module: `tracker` events, LRS/xAPI/cmi5 statements, SCORM CMI data, H5P results, quiz attempts,
  course progress, logins. For each: where it is stored, granularity, retention, and gaps.
- How content updates preserve learner progress today (Wellms advertises this; find the mechanism).
- Test setup, CI, code style, queue (Horizon), storage, existing LLM/AI code if any.
- Licence audit: licence of every `escolalms/*` package and key third-party dependency, and what
  it allows for an open-core model (closed paid modules, SaaS use, redistribution).
- Runtime dependencies inventory (DB, Redis, queues, workers, external services) as input for
  Phase 8 self-hosting simplification.
- Commerce audit (input for 6.4): what `payments`, `cart`, `vouchers` and invoicing do today,
  which flows depend on them (enrolment after purchase, course pricing, Wellms frontends), and
  what replacing them with **Sylius 2.x as a headless commerce service** would require. Confirm
  the current Sylius version, API coverage (catalogue, cart, checkout, promotions, taxes),
  payment gateways relevant to us (Stripe, Przelewy24), its MCP admin tool and B2B options.

### 0.2 Framework upgrade
`escolalms/api` requires **Laravel 9**, which is past end of support. Propose an upgrade path to
a currently supported Laravel and PHP version: order of package upgrades, breaking changes, which
`escolalms/*` packages need forks or patches, risk list. Implement only after approval, with the
full test suite green at each step. All later phases build on the upgraded stack.

---

## Phase 1: Content formats and integrations

Follow the existing H5P/SCORM topic-type pattern; implement each format as its own composer
package. If a shared "interactive content format" abstraction fits without a big refactor,
propose it; otherwise do the minimal version and say so.

### 1.1 LiaScript
- Store course source as versioned Markdown plus assets (text form is required for AI later).
- CRUD API: create from Markdown, upload `.md`/zip, update, delete, fetch source.
- Rendering: compare (a) self-hosted LiaScript build loading our Markdown vs (b) server-side
  export with LiaScript-Exporter to SCORM/xAPI played via our runtime. Pick the option giving
  progress tracking with the least custom code. No runtime dependency on liascript.github.io.

### 1.2 Adapt Learning
- Path A: import **built output** (SCORM zip with `adapt-contrib-spoor`) through the SCORM runtime.
- Path B: import **JSON source** (`course`, `contentObjects`, `articles`, `blocks`,
  `components`, `config`) stored as structured data, schema-validated, built into a playable
  course by an isolated build worker (Node + Adapt framework). Path B goes behind a feature flag;
  flag the infra cost.

### 1.3 LTI 1.3 (missing today, high priority)
- **LTI Platform**: launch external tools (GeoGebra, Edpuzzle, coding sandboxes…) inside lessons,
  with grade passback (AGS) and deep linking.
- **LTI Tool**: expose our courses to other LMSs (Moodle, Canvas…) so the platform works as a
  content and AI engine inside existing systems.
- Security: key rotation, nonce/state validation, per-tenant registrations.

### 1.4 Shared requirements
- Upload hardening: zip-slip, MIME/type checks, size limits, virus-scan hook.
- Third-party packages run arbitrary JS: serve from an isolated origin and/or strict CSP.
- Authorisation via existing policies/permissions; OpenAPI annotations like other packages.
- Fixtures and tests: a minimal LiaScript course, a minimal Adapt course (both paths), an LTI
  launch round-trip.

---

## Phase 2: AI Course Builder (MVP, production-quality within scope)

An author uploads source material, answers a short adaptive interview in a chat UI, and gets a
complete course application on its own tenant subdomain, built from our existing components.
Afterwards any element can be edited by chatting about it.

### 2.1 LLM layer
- Provider abstraction, model configured per task via env. Default for testing: Claude Sonnet;
  a cheaper model (e.g. Haiku) for lightweight steps. No model names hardcoded outside config.
- Tool use / structured outputs validated against JSON Schema for every generation step. On
  validation failure: retry with the errors, then fail gracefully.
- Prompt caching for source material.
- Per-call logging: model, tokens, cost estimate, latency, tenant, course. Show running cost per course.
- Hard limits: source size, tokens per course, concurrent generations per user.
- LLM prompts live in versioned files with a short README on how to iterate on them.

### 2.2 Ingestion (stage 1: text)
- PDF (native document input to Claude plus server-side text extraction), Markdown, DOCX → Markdown.
- Normalise into a **Source Document**: cleaned Markdown, heading tree, stable fragment IDs,
  metadata, original file kept. Fragment IDs are what citations point to.
- Uploaded content is **untrusted**: delimit it in prompts; it must never change instructions
  or tool permissions (prompt-injection tests required).
- Stage 2 (design for, don't build): images and video with captions/transcripts.

### 2.3 Interview
Short, adaptive, structured questions rendered as buttons/chips, each with a default and a
"decide for me" option: audience and level, total duration and lesson length, tone, visual style
(theme presets + accent colour, no free-form CSS), free vs paid (price/currency via the active
`CommerceProvider`, see 6.4; until then the existing `payments` package), assessments (per-lesson quizzes, final test, certificate), language.
Stored as an editable **Course Brief**.

### 2.4 Generation pipeline (queued, resumable, streamed)
1. Outline: modules → lessons with learning objectives, each mapped to source fragments.
2. Lesson content in parallel (with a concurrency limit); choose the content type per lesson:
   rich text, LiaScript, or H5P, using only components from a **component registry** with
   their JSON schemas.
3. Assessments with answer explanations, each question traceable to a fragment.
4. Metadata: title, description, SEO, pricing.
5. Tenant provisioning: subdomain via the multidomain setup, theme, publish, commerce channel/product if paid.

Everything lives in one versioned, schema-validated **Course Blueprint** (stable element IDs,
citations per element). LMS entities are created/updated from it **through domain services**,
never by writing tables directly. Persist after each stage; stream progress to the UI (SSE or
websockets).

### 2.5 Element-level chat editing
- Select any element (course, module, lesson, activity, question, theme) and chat about it.
- The LLM gets the element, parent context, brief and cited source fragments, and returns a
  **structured patch** limited to that subtree. Show a diff, apply on confirmation.
- Every change is a blueprint version: undo/redo and restore. Global edits (e.g. translate the
  whole course) go through the queued pipeline.

### 2.6 Author UX
Upload → interview → live progress → course tree + preview → element chat. A "sources" panel
shows which fragment backs each element. Polished states throughout; partial failure allows a
retry of a single step. Student side reuses the existing frontend, themed per tenant.

### 2.7 Generative UI
The builder chat must not be a wall of text. The assistant responds with **interactive UI
components** chosen and filled in by the model, and generated lessons can use AI-composed layouts.
Generative UI here means **declarative specs rendered by our own components**. Model-written
HTML/JS is allowed only in the sandboxed, author-approved `simulation` component described below.

**Architecture**
- A **UI component catalogue** (separate from, but aligned with, the content component registry):
  each component has a name, a JSON Schema for its props, a description for the model, an
  accessible React (or reference-frontend) implementation and a plain-text fallback.
- The model emits UI through tool calls / structured output (`render_ui` with
  `{component, props}`); the backend validates props against the schema before streaming them
  to the client. Invalid or unknown components fall back to text, never break the chat.
- Streaming with progressive rendering: components appear as soon as their required props are
  complete; skeleton states until then.
- User interactions inside components (click, select, edit, drag) are sent back as structured
  events, so the conversation continues from the user's action, not from retyped text.
- **Protocol: A2UI v0.9 (Apache 2.0) with our own component catalogue, transported over AG-UI
  (MIT)** instead of inventing a private protocol. A2UI defines *what* to render (declarative
  component trees, bidirectional, streamed); AG-UI defines *how* agent and UI talk (events over
  SSE). In the plan, confirm current versions, renderer options (e.g. CopilotKit renderers vs our
  own renderer for the reference frontend), and the cost of running without them for self-hosting.
- Three generative UI patterns exist: controlled (fixed components), declarative (A2UI) and
  open-ended (model-written HTML, MCP Apps). We use **declarative by default**; open-ended only for
  the sandboxed simulation component below.

**Builder components (MVP set)**
- Interview controls: option chips, sliders (duration, price), language picker, "decide for me".
- **Theme picker**: live preview cards of presets with the accent colour applied.
- **Outline editor**: editable, drag-and-drop module/lesson tree with source citations per node.
- **Lesson preview card**: rendered lesson with "edit in chat", "regenerate", "view sources".
- **Variant comparison**: 2–3 AI alternatives side by side (e.g. lesson intro, quiz difficulty);
  the author picks one.
- **Quiz question card**: editable question, answers, explanation, cited fragment.
- **Diff view**: before/after for any patch (Phase 2.5 and Phase 3 update proposals).
- **Generation progress**: per-stage status with retry for a failed step and running cost.
- **Publish summary**: subdomain, price, theme, checklist of warnings (e.g. uncited elements,
  accessibility issues) before going live.

**Generative layouts for learners (behind a feature flag)**
- When generating a lesson, the model may compose its layout from approved learning components
  (callout, step-by-step, comparison table, timeline, flip cards, code block with run button,
  embedded H5P/LiaScript block) as a declarative layout tree stored in the blueprint.
- Same rules: schema-validated, rendered only by our components, themeable, WCAG 2.2 AA,
  editable through element-level chat and the diff view.
- Learner Insights (Phase 4) remediations may use the same components (e.g. an extra worked example).

**Pedagogical guardrails (based on Google Research "learning interactives", 2026)**
Generative UI for learning must be guided: unguided discovery learning is largely ineffective.
- **Learning objectives first**: before generating lessons or interactives, the model proposes
  precise learning objectives per module/lesson; the author edits and **approves** them; all
  generation is conditioned on approved objectives and every element links to the objective it
  serves (next to its source citation).
- **Mandatory scaffolding template** for every practice activity: intro that activates prior
  knowledge → toolbox (formulas, definitions, references from cited sources) → challenge levels
  of increasing difficulty aligned with objectives → tiered hints (nudge → pointer → near-solution)
  → explanatory feedback on *why* an answer works or not → worked solution shown only after an
  attempt.
- Four design pillars checked for every activity: curriculum/objective alignment, agency and
  motivation, guidance and scaffolding, formative feedback.

**Generate-then-refine loop with automated evaluation**
- Generation is iterative: generate → critique → fix, until all criteria pass or a retry budget
  is reached (then flag for the author instead of publishing).
- Critics: pedagogy (covers objectives, difficulty increases, hints do not leak answers),
  factual grounding (claims supported by cited fragments), mechanics, visual/UX (no distracting or
  redundant elements), accessibility.
- **Agentic solvability check**: a Playwright-driven agent opens each interactive in a headless
  browser, solves it as a learner would, and also tries adversarial actions (extreme slider
  values, empty/invalid input, rapid clicking). Failures feed back into the refine step.
- Record critique results and number of iterations per element; surface them in the publish
  summary.

**Interactive simulations (open-ended, sandboxed, opt-in)**
- A separate `simulation` content component for STEM-style interactives where a fixed catalogue
  is not expressive enough. Here the model may generate HTML/JS, under strict rules:
  - rendered only in a sandboxed iframe on an isolated origin, strict CSP, no network access,
    no access to the parent page except a typed postMessage API (progress, score, events);
  - must pass the generate-then-refine loop including the agentic solvability check;
  - **never published without explicit author approval**; versioned and diffable like any element;
  - feature-flagged per tenant; disabled by default in self-hosted installs.
- Everything else stays declarative.

**Adaptive interface (interface-level personalisation, behind a flag)**
- Beyond adapting content, allow per-learner adaptation of presentation: information density,
  chunk size, navigation (linear vs overview), number of visible hints, using signals from
  Learner Insights (Phase 4). Only within catalogue components and theme constraints.
- Generated remediation UIs (Phase 4) follow evidence-based patterns, e.g. a "explain it in your
  own words" (Feynman) reflection component with AI feedback, elaborative questions, worked examples.
- Measure with standard instruments where surveys are used (SUS, UEQ, NASA-TLX for cognitive load)
  plus behavioural metrics.

**Measuring learning impact**
- Evidence so far comes from small studies (e.g. Learn Your Way: +9% immediate, +11 pp retention
  after 3–5 days, n=60, one chapter), so we measure ourselves: built-in **A/B experiments** per
  course (e.g. generative UI activities vs static content), with delayed retention quizzes
  (e.g. after 3–7 days) as the primary metric, completion and satisfaction as secondary.
- Experiment results visible to authors; experiments opt-in per tenant with learner consent where
  required.

**Quality**
- Component catalogue documented with a live playground (Storybook or similar) including the
  model-facing descriptions.
- Tests: schema validation for every component, fallback rendering for unknown/invalid specs,
  interaction events round-trip, accessibility checks per component.
- Evals: for the golden fixtures, check that the model picks appropriate components (e.g. outline
  editor after outline generation, diff view for every patch) and never emits raw markup outside
  the `simulation` component; for simulations, track solvability pass rate and refine iterations.

---

## Phase 3: Killer feature: "Living Course" (source sync)

A course stays connected to its sources and evolves with them, without losing learner progress.

- **Source connectors**: file re-upload first, then Git repository (path + branch), then Google
  Drive / Notion (design the interface so new connectors are plugins).
- **Change detection**: on source change (webhook, poll or manual "check for updates"),
  re-ingest and compute a fragment-level diff (added / changed / removed fragments).
- **Impact analysis**: using the citations from Phase 2, find every blueprint element that
  depends on changed fragments, including quiz questions whose correct answer may now be wrong.
- **Update proposals**: for each impacted element, the LLM generates a structured patch with a
  human-readable reason ("API parameter renamed in source section 3.2"). Grouped into a single
  **update proposal** the author reviews as a diff: accept all, accept per element, or reject.
- **Progress preservation**: reuse the existing Wellms mechanism; define explicit rules for what
  happens to completion state when a lesson changes (minor edit keeps completion; quiz answer
  changed → mark the question for re-attempt, never silently change a past score).
- **Staleness signals**: per course and per element ("source changed 14 days ago, update pending"),
  visible to authors and optionally to learners.
- **Audit trail**: who accepted which AI change, when, based on which source revision. This
  matters for compliance customers.
- Tests: fixtures with a source v1 and v2 and expected impacted elements; regression test that
  learner progress survives an accepted update.

---

## Phase 4: Personalisation (most-wanted AI capability)

### 4.1 New module: Learner Insights (`learner-insights` package)
A new, self-contained package that replaces any reliance on `escolalms/recommender`.

**Signal ingestion**
- Consume learner activity through domain events and the existing data sources mapped in
  Phase 0.1 (tracker, LRS/xAPI/cmi5, SCORM CMI, H5P and quiz results, progress).
- Normalise into a single append-only **learner signal** stream (learner, course, element ID from
  the blueprint, signal type, value, timestamp), so signals map to the same element IDs used for
  citations and updates.
- Async processing via queues; backfill command for historical data.

**Risk and struggle detection**
- Start with **transparent, rule-based scoring** that is easy to explain and test: inactivity
  streaks, failed or repeated quiz attempts, time on element far above the cohort median,
  abandoned lessons, repeated rewatching or rereading.
- Thresholds configurable per tenant and course; sensible defaults documented.
- Every score comes with **human-readable reasons** ("2 failed attempts on question 3; 9 days
  inactive"). No black-box scores.
- Define a `RiskScorer` interface so a statistical/ML scorer can be added later without changing
  consumers; ship only the rule-based implementation in this phase.
- Output: per learner per course/element a status (on track / struggling / at risk) with reasons,
  exposed via API and domain events (`LearnerStruggling`, `LearnerAtRisk`).

**Interventions (adaptive path)**
- On `LearnerStruggling` for an element: generate a **personal remediation** (simpler explanation,
  extra example or extra practice) with the LLM, grounded only in the course's cited sources.
  Stored as a learner-scoped variant, never a change to the shared course; cached and reused for
  learners with the same struggle pattern on the same element to control cost.
- On `LearnerAtRisk`: configurable nudges via the existing `notifications` package (email/in-app),
  with templates; rate-limited so learners are not spammed.
- Measure effect: track whether learners recover after an intervention (status back to on track,
  next attempt passed) and report it.

**Author analytics**
- Aggregated per element: struggle rate, remediation rate, recovery rate
  ("lesson 4 triggers remediation for 38% of learners").
- High-struggle elements can be turned into a Phase 3 style **update proposal** to improve the
  shared course, reviewed as a diff.

**Privacy**
- Per-tenant toggle for the module; data retention settings; learners can see why they got a
  recommendation or nudge; no learner signals sent to the LLM beyond what a given remediation needs.

**Tests**
- Unit tests for every rule with fixtures; scenario tests (synthetic learner journeys) asserting
  expected status transitions and interventions; tenant isolation tests.

### 4.2 AI tutor
- Per-course chat for learners, answering **only from course content and its sources**, always
  with citations; it says when something is out of scope.
- Respect assessments: no giving away quiz answers during an active attempt.
- Rate and cost limits per tenant; conversations visible in analytics (anonymised by default).

### 4.3 Trust and transparency
- Explain every recommendation ("suggested because you scored low on X").
- Per-tenant settings: AI features on/off, data retention, which model/provider is used.
- Clear labelling of AI-generated content for learners.
- Document data flows to the LLM provider; no training on customer content.

---

## Phase 5: UX and reference frontend

Headless platforms usually need more development effort than classic LMSs; that is their main
adoption barrier. Remove it by shipping a ready-made, themeable experience.

### 5.1 Reference frontend
- Audit the existing Wellms frontends first; propose whether to evolve one or build a new reference
  app. Justify the choice (stack, maintainability, SSR/SEO for course sales pages).
- Themeable via the same presets used by the AI Course Builder (Phase 2.3); per-tenant theme from API.
- **PWA with offline mode**: download lessons, record progress offline, sync when back online,
  with conflict rules defined and tested.

### 5.2 Embeddable widgets / SDK
- Web components (framework-agnostic) for: my courses, continue lesson, course catalogue, quiz,
  AI tutor, certificate badge. Each configurable via attributes, themeable via CSS custom properties.
- Small JS SDK wrapping the API (auth, courses, progress, events) with TypeScript types generated
  from the OpenAPI spec.
- Docs page with live examples for each widget.

### 5.3 Learner UX
- Home starts with "continue where you left off" and a clear "what's next".
- Visible progress and estimated time per lesson and course; favour short lessons (5–10 min).
- Semantic search across all courses the learner can access, with an AI answer that cites
  course content (reuses the Phase 4.2 tutor grounding).
- **Accessibility: WCAG 2.2 AA** for all learner-facing UI, including generated content
  (alt text, heading structure, contrast, keyboard navigation, captions). Courses sold to
  consumers in the EU must also meet European Accessibility Act requirements.
- Automated accessibility checks (axe) in CI for the reference frontend and widgets.

### 5.4 Admin and author UX
- **Time to first course** is the key metric: templates, guided empty states, sample course on
  new tenant creation.
- Bulk operations (enrol, assign, export), one-click standard reports, saved filters.
- AI transparency in the UI everywhere: diffs, citations and recommendation reasons are always
  one click away (Phases 2–4).
- Track UX metrics: time to first course, time to first enrolment, admin task completion times.

---

## Phase 6: Market-essential modules

Each as its own package, following existing patterns. Order by priority below; plan each
separately.

### 6.1 Certificates and compliance (highest buyer demand)
- First check what Wellms already offers (e.g. certificate templates) and extend rather than
  duplicate.
- Certificates with verification URL/QR, issue date, **expiry date** and **recertification**:
  automatic re-enrolment before expiry, reminder schedule via `notifications`.
- Mandatory training: due dates, overdue escalation to managers.
- Compliance reports and audit export (who completed what, when, which course version, which
  certificate), aligned with the Phase 3 audit trail.

### 6.2 Automated enrolment and role-based paths
- Rule engine: "user with attribute X (department, role, location, tenant) → learning path Y,
  due in N days". Rules evaluated on user create/update and on schedule.
- Learning paths as ordered sets of courses with prerequisites.
- Automated notifications: enrolment, reminders, due soon, overdue, completed.

### 6.3 Identity and HR integrations
- SSO: SAML 2.0 and OIDC per tenant.
- SCIM 2.0 provisioning (create, update, deactivate users and groups).
- HRIS sync design via connectors (start with a generic CSV/API import); attributes feed the
  6.2 rule engine.

### 6.4 Commerce (Sylius) and extended enterprise

**Architecture decision: Sylius 2.x as a separate headless commerce service.** Sylius is a
Symfony app, so it runs next to the Laravel LMS, not inside it (no Doctrine/Eloquent mixing).
Learners and admins never see the split: **one reference frontend and one admin experience**
talk to both APIs. Wellms `payments`/`cart`/`vouchers` are retired after migration.

- **Ownership split:** the LMS owns courses, learners, progress and **entitlements** (who has
  access to what, until when, from which order or seat package). Sylius owns catalogue,
  pricing, cart, checkout, promotions/coupons, taxes, payments and invoices.
- **`CommerceProvider` interface** in the LMS; the Sylius adapter is the default implementation.
  Keep the interface small (sync product, create checkout, handle order events) so a minimal
  built-in provider (e.g. Stripe-only one-off payments) or another provider can be added later.
- **Catalogue sync:** publishing a course (or bundle, subscription, seat package) upserts a
  digital product in Sylius (no shipping); unpublishing disables it. LMS IDs stored on Sylius
  products and vice versa.
- **Order → entitlement:** Sylius order-paid / refunded / cancelled events grant or revoke
  entitlements via signed, **idempotent** webhooks, plus a scheduled reconciliation job that
  repairs missed events. Never grant access from the frontend redirect alone.
- **Identity:** a single login across both systems (LMS is the identity source; Sylius customer
  created or linked on first checkout). Design and document the token flow.
- **Tenants ↔ channels:** each LMS tenant maps to a Sylius channel (catalogue, prices, currency,
  locale, tax zone); tenant provisioning (Phase 2.4) also creates the channel.
- **Unified admin:** commerce screens needed day to day (prices, coupons, orders, refunds) are
  surfaced in the LMS admin via the Sylius API; the native Sylius admin remains for advanced
  configuration. The LMS MCP server (7.5) can delegate commerce actions to Sylius' MCP tool.
- **B2B:** seat packages (a company buys N seats and manages its own learners) modelled as a
  Sylius product + an LMS seat pool entitlement; invoices from Sylius.
- **Migration:** plan to migrate existing orders, vouchers and access from Wellms packages
  without losing any learner's access.
- **Tax note:** digital services in the EU require VAT by the buyer's country (OSS); confirm how
  Sylius handles this and document the options.
- Per-tenant branding, course catalogue and sales pages served by the single frontend.
- Partner/customer portals with their own admins and reports scoped to their learners.

### 6.5 Analytics and ROI
- Dashboards: completion, time to complete, assessment results, struggle/recovery rates from
  Learner Insights, certificate status, overdue mandatory training.
- Export and API for BI tools; scheduled email reports.
- Optional link to business metrics provided by the tenant (e.g. imported KPIs per team) to
  show training impact.

### 6.6 Skills and competencies
- Competency framework per tenant; tag blueprint elements and assessments with competencies
  (AI can suggest tags, author confirms).
- Learner skill profile derived from assessment results; skill gaps feed recommendations in
  Learner Insights and the 6.2 path rules.

---

## Phase 7: Developer experience and agents

### 7.1 Course-as-code
- File format for a course: blueprint as Markdown (lesson content) + YAML/JSON (structure,
  metadata, components, citations), documented and versioned with a JSON Schema.
- **CLI** (`ulams`): `init`, `validate`, `preview`, `push`, `pull`, `diff`, `publish`; auth via
  scoped tokens; works against cloud and self-hosted instances.
- **Two-way sync**: edits in the UI and in Git converge; conflicts surfaced as diffs (reuse the
  Phase 2.5 / Phase 3 diff model), never silently overwritten.
- **CI integration**: GitHub Action (and a generic Docker image for other CIs) that validates the
  course, runs accessibility and citation checks, and creates a **preview deployment per PR**
  with a link posted back to the PR.
- Git as a Living Course source (Phase 3): a merged change in docs/repo triggers an update proposal.

### 7.2 Code exercises with autograding
- Exercise component running code in the browser: WebContainers/Sandpack for JS/TS, Pyodide for
  Python; server-side sandbox runner as an optional, isolated service for other languages.
- Grading by running author-defined tests; results stored as assessment attempts and learner
  signals (Phase 4).
- AI hints grounded in the exercise and its tests, without revealing the solution.
- Exercises are part of course-as-code (tests live next to the exercise files).

### 7.3 API, SDK, webhooks
- OpenAPI spec complete and published; typed SDKs generated from it (TypeScript first, PHP second).
- **Webhooks, Stripe-style**: signed payloads, retries with backoff, replay, delivery log and test
  sends in the admin UI, versioned event types (enrolment, progress, completion, certificate
  issued/expiring, update proposal created, etc.).
- API keys with scopes, per-key rate limits, usage stats.
- **Local dev in one minute**: `npx create-ulams` and/or `docker compose up` with seed data,
  a sample course and a test admin account.
- Developer docs site with runnable examples; a free sandbox tenant in the cloud offering.

### 7.4 Plugin system
- Extension points: content components, UI catalogue components (Phase 2.7), source connectors
  (Phase 3), risk rules (Phase 4), webhook consumers, admin pages.
- Plugin manifest, versioning and compatibility checks; plugins installable without forking core.
- Design with a future marketplace in mind (free and paid plugins), but do not build the
  marketplace yet.

### 7.5 MCP server (agents operating the platform)
- First-class MCP server exposing admin/author tools: list/create/update courses, run the AI
  Course Builder, enrol users and groups, query progress and reports, create and review update
  proposals, manage certificates.
- Learner-facing MCP: browse my courses, get the next lesson, take a quiz, ask the tutor, with
  progress recorded in the LMS (learning inside Claude, Cursor and other agent clients).
- **Rich UI inside agent clients**: serve lessons, quizzes and activities as **A2UI over MCP**
  (natively rendered, reusing the Phase 2.7 catalogue) where the client supports it; fall back to
  MCP Apps (iframe) for simulations, and to plain Markdown/text for clients without UI support.
- **Agent safety**: OAuth / scoped tokens per agent, least-privilege presets, **dry-run mode**
  for every mutating tool, idempotency keys, human approval for destructive or bulk actions,
  rate limits, and a dedicated **agent audit log** (which agent, on whose behalf, what, when,
  result).
- Tool descriptions written and evaluated for model usability; an eval suite with typical agent
  tasks (e.g. "enrol the new sales hires in onboarding and send me a report next Friday").

### 7.6 Machine-readable content
- `llms.txt` per tenant and per public course; Markdown version of every course/lesson page.
- Public JSON Schemas for the blueprint and webhooks; `AGENTS.md` in the repo describing how
  agents should work on the codebase.
- **Knowledge export for agents**: export a course (or a whole tenant) as a knowledge pack:
  chunked content with citations and metadata, ready for RAG in company agents. Same source of
  truth for humans and AI; re-exported automatically when the course changes.

---

## Phase 8: Self-hosting

### 8.1 Minimal footprint
- Based on the Phase 0 dependency inventory, propose how to reach: **one application image**,
  PostgreSQL, optional Redis (fallback to DB-backed queue/cache for small installs).
- Commerce is an **optional profile**: installs that do not sell courses run without Sylius;
  installs that do add one Sylius image to the same compose/Helm release, sharing the PostgreSQL
  server (separate database) and Redis. The setup wizard configures the link between them.
- Single `docker compose up` for small installs; **Helm chart** for Kubernetes with sane
  defaults (probes, resources, horizontal scaling of web and workers).
- First-run **setup wizard**: admin account, domain, mail, storage, AI provider (or AI disabled).

### 8.2 AI provider freedom
- Any OpenAI-compatible endpoint, Anthropic, and local models (Ollama, vLLM), configured per
  task as in Phase 2.1.
- Every feature degrades gracefully with AI disabled: manual authoring, no tutor, rule-based
  insights still work.
- Document recommended models per task and known quality trade-offs of small local models.

### 8.3 Air-gapped and privacy
- No telemetry by default (opt-in only, documented payload); no runtime dependency on external
  CDNs or services (self-hosted fonts, LiaScript, H5P libraries).
- Offline licence activation for paid modules.
- Data residency: all data, files and AI logs stay in the customer's infrastructure.

### 8.4 Operations
- Automatic, safe migrations on upgrade; documented upgrade path; **stable and LTS channels**
  with semantic versioning and changelogs.
- `backup` and `restore` commands (DB + files + config), tested in CI.
- Health checks and metrics endpoint (Prometheus format); structured logs.
- Zero-downtime deploy guidance for the Helm chart.

### 8.5 Security and compliance pack
- **SBOM** per release, **signed container images**, automated CVE scanning in CI with a
  documented patch policy.
- Hardening guide (TLS, secrets, network policies, backups, least privilege).
- Ready-made documentation for customer audits (e.g. ISO 27001, GDPR): data flow diagrams
  (including LLM calls), data retention and deletion, access control model, audit logs,
  sub-processors list for the cloud offering.

---

## Quality bar (all phases)

- Mocked LLM in unit/feature tests; a separate eval command runs the real model on golden fixtures
  (short Markdown, medium PDF, technical doc with code) and checks: schema validity, citation
  coverage (every element cites ≥1 fragment), quiz answers supported by content, duration within
  the brief, cost per run. Output a report.
- Tenant isolation tests for every new endpoint.
- Accessibility (WCAG 2.2 AA) checks for every learner-facing UI change.
- Learning features that claim pedagogical benefit (generative UI activities, remediation,
  adaptive interface) ship with an A/B experiment definition and a delayed-retention metric.
- E2E happy path: upload Markdown → interview → course live on subdomain → edit a quiz question
  via chat → change the source → accept the update proposal → learner progress intact.
- Existing test suite and linters stay green; H5P/SCORM behaviour unchanged.
- Each package has a README: what it does, configuration, limitations, API.

## Out of scope (for now)
Image/video generation, AI avatars, custom themes beyond presets, real-time multi-author
collaboration, fine-tuning, billing for the builder itself.

## Rules
- Prefer existing packages and patterns over new abstractions; justify every new dependency.
- Small, reviewable commits; one milestone at a time; each milestone demoable.
- Stop after every plan and wait for approval.
- Track progress in `docs/ROADMAP-TODO.md` and follow the working rules in `CLAUDE.md`.
