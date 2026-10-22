# Plan: course versions with a visual diff, and course variants by level and role

Status: approved by the owner on 2026-10-22 with the recommended defaults for Q1 to Q11; ADR 0192 is Accepted. Milestones CV-1, CV-2 and CV-4 are merged; CV-3 (the studio compare screen, the version list API and the SDK client) is implemented in PR #425; CV-5 is being built in three parts (1: API, routing, entitlement and progress; 2: learner screens; 3: Living Course and certificates) and parts 1 and 3 are implemented; CV-6 is not.
TODO items: Phase 3.1 and Phase 4.4 in `docs/ROADMAP-TODO.md` (all marked `(new)`). The spec
(`docs/ROADMAP-PROMPT.md`) does not mention either feature yet; it needs a matching paragraph in Phases 3 and 4
once the owner approves.

Facts below come from `main` at `2f8cebb25` (2026-10-22). Paths are relative to the repo root.

---

## 1. Goal

> An author can open any course and see a numbered list of the versions learners actually got, pick any two
> versions and see on one screen what changed: lessons added, removed or moved, word-level text changes,
> citations that changed and quiz answers that now differ, with the effect on learner progress. When building a
> course, the author can ask for variants by level and role; the builder drafts them together from the same
> sources, every element cited, and the author reviews each as a diff against the base. Learners start in the
> variant that fits them and can move when the evidence says so, never silently.

Requested by the owner on 2026-10-22, who wants both on the roadmap, on the landing page and in the docs first,
then this plan approved before any code.

## 2. TODO items covered

| # | TODO item (short) | Milestone |
|---|---|---|
| V1 | 3.1 Course releases recorded on every applied change, listed per course | CV-1 |
| V2 | 3.1 Compare any two versions or releases in the studio | CV-2, CV-3 |
| V3 | 3.1 Semantic diff on top of `BlueprintDiff`, shared by studio, CLI and MCP | CV-2 |
| V4 | 3.1 Releases and compare for adopted hand-made courses (after CAC-5) | CV-6 |
| V5 | 3.1 Tenant isolation and WCAG 2.2 AA for releases and compare | CV-1 to CV-3 |
| R1 | 4.4 Interview asks which levels and roles to cover | CV-4 |
| R2 | 4.4 Generate several variants at once, cited, logged, capped | CV-4 |
| R3 | 4.4 Review each variant as a diff against the base; Living Course updates every variant | CV-4, CV-5 |
| R4 | 4.4 Learner routing: self-declared, placement, admin or rule, adaptive hint | CV-5 |
| R5 | 4.4 One entitlement covers all variants; certificates name the variant | CV-5 |
| R6 | 4.4 Tenant isolation, WCAG, evals | CV-4, CV-5 |

Related, not covered: the Phase 6.1 policy lifecycle items ("departmental course variants"). The variant model
here is meant to serve them later (a department is a role); the policy owner dashboard stays in Phase 6.

## 3. What exists and is reused

| Piece | Where | Reused for |
|---|---|---|
| Append-only blueprint versions per session (`kind`, `status`, `parent_id`, `source_revisions`, `diff_from_parent`, `variant_group`) | `api/packages/course-builder/src/Models/Version.php`, migrations `2026_10_10_100000_*`, `2026_10_22_100000_*` | Version list; branches get a `variant_id` |
| Element-aware diff by stable id, field level | `src/Blueprint/BlueprintDiff.php` | Base of the semantic diff |
| Diff of any two versions | `GET api/admin/course-builder/versions/{v}/diff?against=` | Compare API (moved to a route without the AI gate) |
| Three-way merge by element id (CAC-4) | `src/CourseAsCode/ElementMerge.php`, `front/cli/src/course/merge.ts` | Rebasing a variant on a newer base; conflict display |
| Blueprint v3 schema (origin, citations rule) | `resources/schemas/course-blueprint/` (ADR 0160) | Variant documents are ordinary v3 documents |
| Apply through domain services, entity map | `src/Apply/BlueprintApplier.php`, `course_builder_entity_map` | Applying each variant to its own LMS course; release creation |
| Diff view with word-level marks (jsdiff) | `@ulams/ui` builder components | Compare screen |
| Variant comparison ("Give me 2 options", L2-13) | `variant_group` on versions | Not reused for audiences: those are alternative proposals for one element, a different concept. Naming in code: "options" for L2-13, "variants" for audiences |
| Living Course impact analysis via citations, proposals, progress rules | `api/packages/living-course`, ADR 0030, 0033 | Propagating source updates to variants |
| Learner Insights rules with reasons | `api/packages/learner-insights` | Adaptive hint to switch variant |
| Entitlements with source keys | `api/packages/entitlements`, ADR 0130 | One family grant covers every variant course |
| Enrolment rule engine | `api/packages/compliance` (`RuleEngine`) | Assigning a variant by attribute (role, department) |
| AI call log | `ai_calls` (`subject_type`, `subject_id`, cost, tokens, model) | Cost per variant |

## 4. Scope

In scope: releases and compare for builder courses (CV-1 to CV-3), audience variants in the builder (CV-4),
learner routing and variant-aware Living Course (CV-5), releases for adopted hand-made courses once CAC-5 ships
(CV-6).

Out of scope: variants of courses not built with the builder; variants that differ in language (translation
is a separate feature); learner-written or per-learner variants (that is Phase 4.1 remediation); automatic
switching without the learner's confirmation; branching inside a lesson (adaptive layouts stay in Phase 2.7).

## 5. Milestones

| Milestone | Content | Depends on |
|---|---|---|
| CV-1 | `course_builder_releases`, release on apply, release list API and studio list, CLI `ulams course releases` | — |
| CV-2 | `SemanticDiff` (moves, citations, quiz, summary), compare API without the AI gate, CLI `ulams course diff --from --to`, MCP tool | CV-1 |
| CV-3 | Studio compare screen (tree, inline and side by side, citations panel, quiz impact), axe and keyboard specs | CV-2 |
| CV-4 | Interview question, variant branches, generation run, per-variant review as a diff, apply each variant to its own course, family in the catalogue | CV-3 |
| CV-5 | Learner routing (self-declared, placement, admin or rule, hint), family entitlement, progress carry-over, variant-aware Living Course, certificates | CV-4 |
| CV-6 | Releases and compare for adopted hand-made courses | CAC-5 |

Each milestone is one PR with its tests, as the other plans do.

## 6. Data model

All tables live in the tenant database (database per tenant, ADR 0007), so no `tenant_id` column.

### 6.1 Releases (CV-1)

`course_builder_releases`

| Column | Type | Notes |
|---|---|---|
| `id` | ulid | |
| `session_id` | ulid, index | |
| `variant_id` | ulid, nullable | null = base |
| `number` | unsigned int | sequential per (session, variant), unique together |
| `version_id` | ulid | the applied version |
| `previous_release_id` | ulid, nullable | |
| `course_id` | bigint | LMS course the version was applied to |
| `trigger` | string(16) | `generate`, `author`, `chat`, `update` (Living Course), `insights`, `import` (course-as-code), `restore`, `rebase` |
| `source_revisions` | json, nullable | copied from the version |
| `approved_by` | bigint, nullable | who approved the version |
| `summary` | json | counts from the semantic diff against the previous release (lessons, elements, citations, quiz answers changed) |
| `released_at` | timestamp | when apply finished |

A release is written by the apply step, in the same transaction that sets `applied_version_id`. Undo and redo
that re-apply create a release with the trigger `restore` (learners saw the change, so it is a release). A
failed or partial apply writes nothing.

### 6.2 Variants (CV-4)

`course_builder_variants`

| Column | Type | Notes |
|---|---|---|
| `id` | ulid | |
| `session_id` | ulid, index | |
| `key` | string(64) | slug, unique per session, e.g. `expert-developer` |
| `label` | string | shown to learners, e.g. "Expert, developer" |
| `level` | string(16), nullable | `beginner`, `intermediate`, `expert` |
| `role` | string(64), nullable | free text or preset |
| `audience_brief` | json | what the interview decided for this audience (prior knowledge, goals, depth) |
| `course_id` | bigint, nullable | its own LMS course after the first apply |
| `current_version_id`, `applied_version_id` | ulid, nullable | same meaning as on the session |
| `based_on_version_id` | ulid | the base version this branch was last derived from or rebased on |
| `status` | string(16) | `draft`, `approved`, `retired` |
| `position` | unsigned small int | order in the picker |

The session row stays the base branch (no variant row for the base). Changes to existing tables:

- `course_builder_versions.variant_id` (ulid, nullable, index). Number stays unique per session.
- `course_builder_entity_map.variant_id` (ulid, nullable); unique key becomes
  `(session_id, variant_id, element_id, entity_type)`. On MySQL and PostgreSQL a nullable column in a unique
  key does not deduplicate nulls, so the migration stores the base as an empty string sentinel or uses a
  generated column; the choice is made in CV-4 with a test on both drivers.
- `course_variant_groups` in `courses` (or a small `course-variants` package, see Q1): `id`, `default_course_id`,
  and `course_variant_group_members` (`group_id`, `course_id`, `variant_key`, `label`, `level`, `role`,
  `position`). This keeps the LMS side independent of the builder: a family can in future be made by hand.
- `course_variant_assignments`: `user_id`, `group_id`, `course_id`, `source` (`self`, `placement`, `admin`,
  `rule`, `hint`), `reason` (text shown to the learner), `decided_at`. One current row per (user, group); history
  kept with `superseded_at`.

### 6.3 Element ids across branches

A variant keeps the base element id for every module, lesson, block and question it inherits or changes, and
gets a new id only for a new element. Ids are unique inside one document (Blueprint v3 rule), not across
branches. Consequences: `BlueprintDiff` compares a variant with the base with no mapping; `ElementMerge` rebases
a variant; Living Course impact analysis finds the same element in every branch by id; progress carry-over
matches topics by element id and fingerprint through the entity map.

## 7. Diff algorithm (CV-2)

`SemanticDiff` wraps `BlueprintDiff::compare($from, $to)` and adds, in one pass over the flattened trees:

1. **Moves and reorders.** An element present in both whose ancestor chain differs is `moved` (with from and
   to parents). Siblings in the same parent are compared with a longest common subsequence of ids; elements
   outside it are `reordered`. A moved element can also be changed (both flags).
2. **Citations.** For each element, the citation sets are compared as sets of fragment ids:
   `added`, `removed`, and `repointed` when a fragment id was replaced by the fragment it moved to in a newer source
   revision (the moved and moved-with-change matches of `FragmentDiff`, stored in `living_course_fragment_changes`,
   ADR 0031). Each citation carries the passage text and the
   source revision so the screen can show both passages.
3. **Quiz semantics.** For questions: `stem`, `options` (by option id), `correct`, `explanation` and `type`. A
   change of the correct answer is flagged `answerKeyChanged` and annotated with the ADR 0033 consequence
   ("learners who answered get a re-attempt notice"). Option text edits without a key change are `wording`.
4. **Text.** Fields stay whole values in the payload; the UI computes word-level marks with jsdiff, as today.
   Very long fields (over 20 000 characters) are sent with a server-side line diff instead, to keep the payload
   bounded.
5. **Summary.** Counts per kind and per type (modules, lessons, blocks, questions; citations added, removed,
   repointed; answer keys changed), plus "lessons whose completion is kept / that get an 'Updated' notice"
   computed with the same minor or major rule Living Course uses.

The payload is a stable JSON shape (versioned `diffVersion: 1`) used by the studio, the CLI and the MCP tool.
It is computed on request and cached per `(fromVersionId, toVersionId)` in the cache store for 24 hours;
versions are immutable, so the cache never goes stale. Comparing two branches (variant against base) is the same
call with versions from different branches of one session.

## 8. Compare UI (CV-3)

Route: `/studio/courses/:id/versions` (list) and `/studio/courses/:id/compare?from=&to=` (compare), keyed by
course id like the Insights page, so they work for every builder course and later for adopted ones.

- **Version list.** Releases newest first: number, date, trigger icon and label, approver, one-line summary.
  A toggle shows all versions (proposals, rejected, undone) for authors who want the full history. Two checkboxes
  or "Compare with previous" pick the pair.
- **Compare screen.** Left: the course tree with a badge per node (Added, Removed, Moved, Changed) as text and
  icon, filter chips by kind and type, "only changes" on by default. Right: the selected element, inline or side
  by side, word-level marks, citations panel (passage before and after for a repointed citation), quiz panel
  with the answer key change and its progress consequence. Summary bar at the top. Swap from and to.
- **Variant mode.** The same screen with "Base" and a variant as the pair; a variant picker above the tree.
- **Accessibility.** Added and removed are never shown by colour alone (text badge, `<ins>`/`<del>` with
  visually hidden "added"/"removed" text), the tree is a proper tree view with arrow-key navigation, focus moves
  to the detail panel on selection, reduced motion respected, axe and keyboard specs in the `web-a11y` job.
- **Strings** in EN, PL and ZH from the start (studio strings are translated, ADR 0106).
- **Components.** A `VersionCompare` component in `@ulams/ui` built from the existing diff view, registered in
  the catalogue with a model description so an agent can render it, and an example in the docs playground.

## 9. Variant generation in the builder (CV-4)

### 9.1 The interview question

After the existing audience and level questions, one new question with chips: **"Who is this course for?"**

- **One course for everyone** (default, today's behaviour, no extra cost).
- **Several levels**: pick from beginner, intermediate, expert.
- **Several roles**: free text chips with presets from the brief's audience answer (e.g. developer, support,
  manager).
- **Levels and roles**: the product of both, shown as a grid the author can trim (uncheck a cell).

The chip shows the estimated extra cost ("about 3 × the lesson cost; estimated $1.80") from the cost of the
base lessons in this session or a per-lesson average when none exists. "Decide for me" picks one course for
everyone unless the sources plainly cover several levels. The answer is stored in Course Brief v3 as
`audiences: [{key, label, level, role, notes}]`; a brief without `audiences` is one course (v2 briefs read
unchanged). Hard cap: `COURSE_BUILDER_MAX_VARIANTS` (default 6) per course; the grid refuses more cells.

### 9.2 Generation

1. The base outline is generated and approved as today (objectives first). The base is the default audience
   (usually intermediate).
2. For each audience, one **variant outline** call receives the approved base outline, the audience brief and the
   fragment index (not the full sources), and returns a structured plan per lesson: `keep`, `condense`, `deepen`,
   `skip`, or `add` (a new lesson with objectives and the fragment ids it rests on). Validated by JSON Schema;
   `add` and `deepen` must name fragments that exist. The author approves each variant outline (diff against the
   base outline) before any lesson is written.
3. Lessons: `keep` copies the base element (no call). `skip` removes it. `condense` and `deepen` are one lesson
   call each with the base lesson, the audience brief and the cited fragments; `add` is a normal lesson call.
   Every generated block must cite fragments of the session and passes the existing grounding check; an uncited
   claim fails the step as today. Quizzes follow the lesson's level (a beginner variant gets more scaffolded
   questions); each question cites its fragment and is support-checked.
4. The run is the existing queued, resumable pipeline: steps are per (variant, lesson), streamed over the event
   log with the variant key on each event, retryable one by one.
5. Review: each variant shows as a diff against the current base version (section 8, variant mode). The author
   approves variants one by one; an unapproved variant is never applied or shown to learners.
6. Apply: each approved variant is applied to its own LMS course through `BlueprintApplier` (title gets the
   variant label, the base course's theme, price and category are copied), and the family is created or
   updated in `course_variant_groups`. Publishing the base publishes the family; a variant can be retired.

### 9.3 Later edits

- An author or chat edit happens on one branch. The studio asks "apply to other variants too?" when the edited
  element exists unchanged in other branches; yes creates one proposed version per branch (no model call when
  the element is identical), each approved separately.
- A base change is not pushed into variants automatically. The variant shows "Base changed since this variant
  was derived (3 elements)"; **Rebase** runs `ElementMerge::merge(oldBase, variant, newBase)` and proposes the
  result as a version of kind `rebase`; conflicts are shown with the CAC-4 conflict view and resolved by the
  author.

## 10. Living Course with variants (CV-5)

- Change detection and impact analysis run once per source change (fragments are shared). Impact analysis walks
  every branch's current applied version and lists impacted elements per branch.
- For each impacted element, one patch call for the base. For each variant whose element has the same
  fingerprint as the base's, the base patch is reused (no call). Only divergent elements get their own patch
  call with the variant's audience brief.
- Proposals: one proposal per branch, linked by a `proposal_group` ulid, reviewed together on one screen (tabs
  per variant) with "accept in all variants" for elements that share the patch. Progress rules (ADR 0033) apply
  per variant course unchanged.
- Insights proposals (ADR 0181) are per variant: struggle is measured per variant course.

## 11. Learner routing (CV-5)

- **Picker on the course page.** For a family, the course page shows the variants with label and a one-line
  "who it is for". The learner picks one, or takes the placement check. The choice is stored in
  `course_variant_assignments` with source `self`.
- **Placement check (optional per course).** Generated with the base: 6 to 12 questions spanning the variants'
  levels, each citing its fragment and tied to an objective; scored by code at run time (no LLM call, no learner
  data sent anywhere). The result proposes a variant with the reason ("You answered 5 of 6 intermediate
  questions; we suggest Expert"); the learner confirms or picks another (source `placement`).
- **Admin and rules.** An admin can assign a variant per learner or group; the enrolment rule engine gets a
  `variant` action (e.g. department = Sales → role Sales). An assignment by admin or rule is shown to the learner
  as "assigned by your organisation" and, by default, can still be changed by the learner (Q4).
- **Adaptive hint.** Two transparent Learner Insights rules: "too easy" (first-try pass on every check of the
  first two modules and time per lesson under half the cohort median) and "too hard" (the existing struggling
  status in the first two modules). Either shows a dismissible hint with the reason and a "Switch to …" button.
  Never more than one hint per course per week; never an automatic switch.
- **Switching.** Switching enrols the learner in the other variant course (family entitlement already covers
  it), marks the old assignment superseded and copies completion for topics whose element id and fingerprint are
  identical in both variant courses, through the courses domain service (never a direct table write). Quiz
  attempts are not copied; past scores stay where they were. Switching back restores the old course progress
  (it was never deleted).
- **Access.** The family has one entitlement source key (`family:<group_id>`) that grants every variant course;
  Sylius sells one product per family; the LMS decides access (ADR 0130 unchanged). Catalogue and widgets show
  the family once (default variant's card, "Available for: Beginner, Expert").
- **Certificates.** Each variant course has its certificate template; the certificate names the variant. Mandatory
  training assigned to a family is fulfilled by completing any variant unless the admin pins a variant (Q3).

## 12. API

New route group `api/admin/course-versions` (`auth:api`, no AI gate; view needs the builder's `view` ability):

| Method and path | Purpose |
|---|---|
| `GET courses/{course}/releases` | Releases of the course's branch (paginated), with summary |
| `GET courses/{course}/versions` | All versions of the branch (for the "show all" toggle) |
| `GET courses/{course}/compare?from=&to=` | Semantic diff payload; `from` and `to` are version or release ids of the same session |
| `GET releases/{release}` | One release with its version document |

Builder additions under `api/admin/course-builder` (AI gate, as today):

| Method and path | Purpose |
|---|---|
| `GET sessions/{s}/variants` | Variants with status, course and base drift count |
| `POST sessions/{s}/variants` | Create variants from the interview answer (or add one later) |
| `PATCH variants/{v}` / `DELETE variants/{v}` | Rename, reorder, retire |
| `POST variants/{v}/rebase` | Propose a rebase on the current base |
| `POST sessions/{s}/runs` with `{"stage": "variants", "variants": [...]}` | Generation run (existing endpoint, new stage) |

Learner and admin routes in the new LMS-side package (Q1):

| Method and path | Purpose |
|---|---|
| `GET api/courses/{course}/variants` | Family members for the picker (public data only) |
| `PUT api/courses/{course}/variant` | Learner chooses or switches (body: `course_id`) |
| `GET api/courses/{course}/placement` / `POST .../placement/attempts` | Placement questions and scoring |
| `GET/PUT api/admin/course-variant-groups/{group}/assignments` | Admin assignment per user or group |

CLI: `ulams course releases`, `ulams course diff --from --to [--json]`, `ulams course variants list|rebase`.
MCP: `course_releases`, `course_compare` (read-only). OpenAPI annotations on every endpoint; SDK regenerated in the
implementing PRs (not in this docs PR).

Built in CV-2: `course diff` already existed (folder against base, server or one version), so `--from` and `--to` are two new flags of
that command, and `ulams course compare <course> --from --to` is the standalone form that the MCP tool runs (`course_compare`, read-only,
toolset `builder`). `GET courses/{course}/versions` (the "all versions" list) is left for CV-3, which needs it for the screen.

Built in CV-5 part 1 (differences from the table above, decided while building): the family is `course_variant_groups` with `pinned_course_id`, `lock_assigned`, `placement_enabled`,
`hints_enabled` and the default course's picker label, level and role; there is no `family:<group_id>` source key, because an entitlement is per course: the learner gets one derived
entitlement per variant they use, source type `family`, key `family:<group>:user:<user>:course:<course>`, ended and moved with the entitlements it derives from (an event on every `course_user`
projection keeps it in step). Assignment rules live in `course_variant_rules` and reuse the compliance rule engine's conditions, because the compliance rules are tied to a learning path;
they route only learners who already have access. The placement questions are not generated: they are chosen from the cited quiz questions the family's courses already have (a question
of the base counts for the base level, one a variant added or changed for that variant's level), so nothing new needs author review and no model is called. Admin endpoints are under
`api/admin/course-variant-groups/{group}` (`assignments`, `rules`, `placement`, settings) and the hint is dismissed with `POST api/courses/{course}/variant/hints/{hint}/dismiss`.
`PUT api/courses/{course}/variant` carries the source (`self`, `placement` with `attempt_id`, `hint` with `hint_id`). Completion carry-over pairs topics by element id, content and parent
(not their order, which moves when a variant skips a lesson) and never pairs quiz topics.

Built in CV-5 part 3: the proposal group is `living_course_proposals.proposal_group` with `variant_id`; a variant element counts as inheriting when it is word for word the base's (fingerprint of the element), and the base patch is copied once the base analysis settles (also when some groups failed), so the shared patches cost nothing. Divergent elements use the same `update` task with the audience as data, not a separate `variant_patch` task. New sections of the source ("uncovered") are for the base only. Staleness marks stay per session (the base's). `POST proposals/{p}/items/{i}/accept-in-group` is the "accept in all variants"; a tabbed review screen in the studio and insights proposals per variant are not built. The certificate shows the variant through `@VarCourseVariant` and `course_variant` on the verification record.

## 13. Tenant isolation, security and privacy

- Every new route is behind `auth:api` and is covered automatically by `TenantIsolationCoverageTest`
  (ADR 0190); the public variant list (`GET api/courses/{course}/variants`) is added to
  `api/tests/fixtures/public-routes.php` with its reason only if it is served without auth (Q6; default: same
  auth as the course page).
- Package-level isolation tests in the style of `compliance/tests/Api/TenantIsolationTest.php` for the compare,
  release, variant and assignment endpoints: a token of tenant B gets 401; a version id of another session in the
  same tenant gets 404 (compare refuses a pair from two sessions).
- Authorization: compare and releases follow the session policy (author, admin, `living_course_review`);
  assignment by admin needs a new permission `course_variants_assign` seeded for `admin`.
- Uploaded sources stay untrusted input: the variant outline and lesson prompts reuse the existing
  prompt-injection framing; the audience brief is author text and is passed as data, not instructions.
- No learner data reaches the LLM: placement is scored by code; hints are rules; generation sees only sources,
  the base blueprint and the author's brief.

## 14. Citations and LLM cost logging

- Variants share fragments, so every citation in a variant resolves to the same passage view as the base. The
  Blueprint v3 rule (AI elements must cite; only author elements may lack citations) applies per branch, and the
  publish check runs per variant.
- New tasks in the task registry: `variant_outline`, `variant_lesson` (condense, deepen and add share it with a
  mode), `variant_patch` (Living Course divergent element). Each task gets a profile in `api/packages/ai/config/ai.php`
  (`AI_TASK_*_PROFILE`, `default` for lessons, `light` for the outline plan); no model name outside config.
- Every call writes an `ai_calls` row with `subject_type = course_builder_variant` and `subject_id` = variant
  id, so `ai:usage` and the studio cost panel can show cost per variant; the session's running cost includes
  all variants and the session budget (`budget_micro_usd`) stops the run when exceeded.
- Reuse avoids calls: `keep` lessons and identical Living Course patches cost nothing; the base sources are
  prompt-cached across variant calls (same prefix).

## 15. Migrations

All additive and reversible, one per milestone:

| Milestone | Migration |
|---|---|
| CV-1 | create `course_builder_releases`; backfill one release per session from `applied_version_id` (trigger `generate`, `released_at` = version `decided_at`) with a command `course-builder:backfill-releases` per tenant |
| CV-4 | create `course_builder_variants`; add `variant_id` to `course_builder_versions` and `course_builder_entity_map` and change the entity map unique key; Course Brief schema v3 file |
| CV-5 | create `course_variant_groups`, `course_variant_group_members`, `course_variant_assignments`; `proposal_group` on `living_course_proposals`; permission seeder |

No data is rewritten; existing sessions are base-only branches. H5P, SCORM, quiz and certificate tables are not
touched.

## 16. Tests

- Unit: `SemanticDiff` fixtures (move, reorder, move+change, citation added, removed, repointed, answer key
  change, wording change, long text); release creation on apply, undo and failed apply; variant outline schema
  validation; fingerprint reuse for Living Course patches; `ElementMerge` rebase with and without conflicts.
- Feature (LLM mocked with the fake driver): interview with audiences; variant run writes per-variant steps and
  `ai_calls` rows with the variant subject; an uncited variant block fails the step; apply creates one LMS course
  per variant through domain services; family entitlement grants every variant; switching copies only identical
  completions and never touches attempts; Living Course update on a base element produces reused patches for
  inheriting variants and a separate call for a divergent one; progress survives (the existing checksum test,
  per variant).
- Tenant isolation: route coverage (automatic) plus package suites (section 13).
- Frontend: vitest and axe for `VersionCompare` and the picker; Playwright (mocked API) for the compare screen,
  keyboard path through the tree, reduced motion; studio e2e on the fake driver for the variant flow.
- Evals (real model only through the eval command): `course-builder:eval --suite variants` with fixtures that
  check grounding (no uncited claims), level fit (an expert variant does not re-explain basics; a beginner variant
  defines terms before use, judged by a rubric) and that `skip` decisions are justified by the audience brief.

## 17. Risks

| Risk | Mitigation |
|---|---|
| Cost grows with the number of variants | Cap (default 6), estimate before the run, reuse of `keep` lessons and identical patches, session budget |
| Variants drift from the base over time | Base drift badge, rebase through `ElementMerge`, one Living Course analysis for all branches |
| One session to many courses breaks code that reads `session.course_id` | Audit of every read in CV-4 (`grep course_id` in `course-builder`, `living-course`, `learner-insights`, `tutor`); branch-aware accessor; tests per caller |
| Learners confused by several variants of one course | Picker with one-line "who it is for", placement as a suggestion, family shown once in the catalogue |
| Wrong level fit makes variants useless | Variant outline reviewed by the author before lessons are written; level-fit eval; hints to switch |
| Progress carry-over copies completion for content that differs | Copy only on equal element id and fingerprint; otherwise the lesson stays open; tested |
| Compare payload too large for big courses | Only-changes default, server line diff for long fields, cache per pair |

## 18. Open questions (with recommended defaults)

| # | Question | Recommended default |
|---|---|---|
| Q1 | Where does the LMS-side family (groups, members, assignments, placement) live: in `courses` or in a new `course-variants` package? | New `course-variants` package (separate composer package, per conventions); `courses` stays unchanged |
| Q2 | Default cap on variants per course | 6 (e.g. 3 levels × 2 roles); operator can raise it with `COURSE_BUILDER_MAX_VARIANTS` |
| Q3 | Do mandatory training and recertification accept any variant? | Any variant fulfils it unless the admin pins one |
| Q4 | May a learner change a variant assigned by an admin or a rule? | Yes by default, with a per-course setting to lock assigned variants (compliance) |
| Q5 | Is the base itself a variant learners can pick? | Yes, labelled with its audience (usually "Intermediate") |
| Q6 | Is the variant list public (anonymous visitors on the landing) or for signed-in learners only? | Public like the course page (labels and descriptions only) |
| Q7 | Does undo or redo that re-applies an old version create a release? | Yes, trigger `restore`: learners saw the change |
| Q8 | Should the placement check be on by default for a family? | Off; the self-declared picker is the default, placement is one click to add |
| Q9 | How many hints to switch per course and learner? | At most one per week, dismiss stops hints for that course |
| Q10 | Versions for hand-made courses before CAC-5 | Not offered; the compare screen says "adopt this course to track versions" |
| Q11 | Should the landing show a distinct "Planned" badge instead of the existing "Coming" status for features that are not started? | Keep "Coming" (one roadmap badge across landing and docs); a new status needs a UI enum, three translations and a manifest change, which is cheap later if wanted |

## 19. Demo once built

1. Open a builder course with a connected source in the studio, **Versions**: three releases (generate, author edit, Living
   Course update). Compare release 1 with release 3: one lesson moved, one paragraph rewritten (word marks), two
   citations repointed to source revision 2, one quiz answer key changed with the "re-attempt notice" note.
2. New course from the same source, choose **Levels: beginner, expert**. Approve the base outline, then the
   expert variant outline (two lessons skipped, one deepened, one added from section 4.2). Generate, review the
   expert variant as a diff against the base, approve, apply.
3. As a learner, open the course page, take the placement check, get "Expert" suggested, accept. Later accept a
   source update once and see it proposed for both variants, with one patch reused.
