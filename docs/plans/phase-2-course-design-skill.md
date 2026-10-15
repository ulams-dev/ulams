# Phase 2: shared course-design skill

- Status: Approved 2026-10-15
- Scope: Phase 2.3, 2.4 and 2.7; prepares Phase 5 brand import and Phase 7 agents
- Proposed ADR: `docs/decisions/0103-shared-course-design-policy.md`

## Goal

Give the Laravel Course Builder and local agents the same evidence-based method for turning sources
and optional brand assets into an engaging, white-label, tracked ULAMS course.

The first useful slice is:

1. ask the author which learner experience they want;
2. collect missing learning, delivery and brand constraints through one adaptive interview;
3. choose only supported building blocks from a capability registry;
4. produce a reviewable Course Brief and Course Blueprint;
5. apply the blueprint through ULAMS domain services so ordinary topic, quiz and course progress
   continues to work.

This milestone does not implement every H5P library, the simulation sandbox, a Figma importer or a
new learner shell. It makes those capabilities explicit and prevents an agent from promising or
generating a format that the installation cannot safely render and track.

## Why a `SKILL.md` alone is not sufficient

Cursor and Claude can load an Agent Skill; the Laravel API cannot. Making PHP parse an
agent-oriented Markdown file would mix runtime policy with client instructions and make schema
validation fragile.

Use three layers:

1. **Canonical course-design policy** in versioned JSON files in
   `api/packages/course-builder/resources/course-design/`. It contains capabilities, selection
   rules, learner experience shells and the normalized tracking contract.
2. **Laravel adapter** loads that policy, intersects it with runtime capabilities (installed H5P
   libraries, enabled topic types and feature flags), and supplies the resulting compact catalogue
   to interview, outline, generation and validation.
3. **Project Agent Skill** in `.cursor/skills/ulams-course-designer/`. It reads the same policy and
   operates ULAMS through the `ulams builder` CLI/MCP tools. It never writes LMS tables or silently
   approves generated work.

The JSON Schemas and generated summaries make drift between the API and local agents testable.

## Current state

- Course Brief v2 asks about audience, level, duration, lesson length, tone, assessments, language,
  pricing and one of six fixed themes.
- It does not ask for learner experience/navigation, prior knowledge beyond the three-level label,
  delivery context, accessibility needs, preferred activity mix, tracking/completion rules, a
  design system, a logo or other brand assets.
- Blueprint v2 supports `richtext`, `liascript`, `h5p` and curated `interactive` lessons.
- Automatic H5P generation is intentionally limited to `H5P.Blanks`, `H5P.DragText` and
  `H5P.Dialogcards`, each with an owned schema and deterministic mapper.
- The Layout topic validates nine approved learner components: Callout, Steps, ComparisonTable,
  H5PFrame, LiaScriptLesson, Timeline, FlipCards, CodeBlock and PracticeActivity.
- Existing topic types also cover video, audio, image, PDF, oEmbed, SCORM, cmi5, GIFT quizzes,
  projects and LTI. They are not yet all Course Builder generation choices.
- The CLI builder and local MCP server already preserve approvals, citations, versions, costs and
  domain-service application.
- Theme application stores a preset and accent. Logo/design-system ingestion and arbitrary course
  shell selection do not exist.

## Course-design policy

### Capability records

Each building block has:

- stable `id`, family and author-facing label;
- purpose and suitable learning-objective verbs;
- required source/assets and supported languages;
- generation mode: `deterministic`, `catalogue-reference`, `author-import`, `sandboxed` or
  `unavailable`;
- renderer/topic type and runtime feature checks;
- tracking support: view, progress, attempt, response, score, completion, pass/fail and resume;
- accessibility requirements and a non-interactive text fallback;
- citation and objective requirements;
- security/network/licence caveats;
- estimated author effort and generation cost class.

The policy inventories the full official H5P catalogue, but runtime availability comes from the H5P
service. An inventory entry is not permission to let the model emit raw H5P params.

### Normalized families

- Explain: prose, callout, steps, example, comparison, timeline, glossary, accordion.
- Show: image, figure, gallery, audio, video, PDF, diagram, image hotspots, before/after.
- Recall: flip/dialog/flash cards, true/false, multiple choice, short answer, blanks, mark words.
- Arrange: drag words, matching, ordering, sort paragraphs/images and structure strips.
- Decide: scenarios, branching, case studies and role-based dilemmas.
- Practise: scaffolded practice, GIFT quiz, H5P question sets, code exercises and projects.
- Explore: charts, maps, 360 tours, curated interactives and sandboxed simulations.
- Reflect: open response, Cornell notes, survey/questionnaire and Feynman explanation.
- Integrate: LiaScript, SCORM, cmi5, LTI, Adapt and imported H5P packages.

### Selection rules

Choose an activity only when it serves an approved objective and the cited source supports its
answer/feedback. Prefer the smallest interaction that creates useful learner effort:

- remembering → retrieval practice, not decorative animation;
- procedure → steps, worked example, then guided practice;
- discrimination/comparison → examples, comparison or sorting;
- decision under conditions → scenario/branching with explanatory feedback;
- spatial/visual identification → labelled figure or hotspots with a list fallback;
- code/application → runnable exercise only when a safe runtime exists, otherwise a code block plus
  GIFT/self-check;
- reflection/judgement → open response or project, not a fake automatically graded answer.

Avoid interaction for interaction's sake. Apply the existing scaffolding sequence: activate prior
knowledge → toolbox → increasingly difficult challenge → tiered hints → explanatory feedback →
worked solution after an attempt.

## Learner experience shells

Add one required interview field with a source-aware default:

- `course`: multi-page lessons with a left outline; default for long, resumable or compliance
  courses;
- `scroll`: one continuous, anchored page; for short narrative or marketing-style learning;
- `slides`: one slide at a time with keyboard/touch navigation and a linear progress indicator; for
  concise visual material;
- `decide_for_me`: choose from duration, objective structure, device and delivery context.

Recommendation: treat “slides” and “presentation” as one shell in the schema. A Course Presentation
H5P activity remains a building block inside a lesson, not a second global shell. If facilitator
notes or presenter controls are required, add a later `facilitated` mode instead of duplicating
learner navigation now.

Every shell uses the same course/module/lesson/topic entities and progress API. Scrolling and slides
must emit stable element/topic progress; course completion remains an LMS decision, never a
frontend-only redirect or final-screen flag.

## Adaptive interview and Course Brief v3

Ask only questions whose answers are not supplied by sources, brand input or tenant defaults.
Every question keeps a “decide for me” option and explains why it matters.

1. Audience, role and learning context.
2. Prior knowledge/experience and accessibility or device constraints.
3. Desired outcomes and assessment stakes.
4. Total duration and target lesson length.
5. Learner experience shell: course, scroll or slides.
6. Activity preference: balanced, practice-heavy, visual, scenario-led or minimal-interaction.
7. Language and tone.
8. Completion, pass score, attempts, certificate/compliance needs.
9. Brand input:
   - existing design-system/brand-guide upload or URL;
   - logo and optional square mark;
   - permitted fonts and image/icon assets;
   - otherwise a short style interview: mood, light/dark, density, colour direction and imagery.
10. Pricing/site questions already present.

Brand files are untrusted input. Extract a constrained token proposal (colour roles, typography
roles, spacing/radius/density and asset references), show a diff/live preview, run contrast and
asset checks, and require an admin/author approval. Never accept free-form CSS or scripts.

## White-label output

- Expand tenant theme data from `preset + accent` to a versioned approved token set while preserving
  presets as defaults.
- Store uploaded logos in tenant storage through the file/settings services, with type, dimensions,
  size, licence/ownership confirmation and alt text.
- Render every shell and catalogue component only with `--ulams-*` tokens.
- Use the same token document in Studio previews and learner pages.
- Keep ULAMS platform branding separate from tenant branding.
- Missing design system: propose 2–3 constrained variants from the style answers; the author chooses
  one. Do not invent a customer logo.

## Tracking contract

Normalize component events to stable blueprint and LMS topic IDs:

- `viewed`, `progressed`, `attempted`, `answered`, `scored`, `completed`, `passed`, `failed`,
  `resumed`;
- score/max score, progress 0–100, attempt ID and duration where supported;
- source adapter (`native`, `gift`, `h5p-xapi`, `scorm`, `cmi5`, `liascript`, `lti`,
  `interactive-bridge`);
- capability flags so unsupported signals are absent, not fabricated.

H5P completion varies by library. The policy must carry a tested compatibility matrix and a
fallback completion rule. Drag activities need click/keyboard alternatives, media needs captions
or transcript, visual interactions need equivalent text, and non-essential motion respects reduced
motion.

## Implementation milestones

### M2.6a — policy and project skill (first demoable slice)

1. Add policy JSON Schema and initial capability catalogue covering every current ULAMS topic type,
   all official H5P types, GIFT question types and approved layout components.
2. Add `CourseDesignPolicy` in PHP: validate, load and filter by installation capabilities.
3. Add `.cursor/skills/ulams-course-designer/SKILL.md` plus focused references for interview,
   selection, branding, tracking and CLI/MCP workflow.
4. Add a generator/check that produces a compact model-facing summary from the policy and fails on
   stale generated output.
5. Add three skill evals: a branded compliance course, a short one-page product course and a
   slide-based technical course. Compare with a no-skill baseline and present the review.

Demo: ask a local agent to design those three courses. It asks the missing questions, returns a
reviewable brief/blueprint recommendation, uses only installed capabilities and invokes the existing
builder without auto-approving outline/apply/publish.

### M2.6b — Course Brief v3 and interview

Add shell, prior knowledge/context, activity preference, completion and constrained brand fields;
new A2UI interview controls; v2→v3 upgrade; CLI flags and SDK types; brief edit/staleness rules.

### M2.6c — generated Layout lessons

Let the generation pipeline emit schema-valid Layout topic documents from the approved learner
catalogue. Add component-selection evals, deterministic accessibility checks and markdown fallbacks.
This completes existing roadmap item L2-21 before adding more visual components.

### M2.6d — shells and approved brand tokens

Implement `course`, `scroll` and `slides` in the reference frontend, all against the same topic and
progress APIs. Add brand asset upload/token proposal/diff/approval and connect approved tokens to
tenant settings.

### M2.6e — capability expansion

Add new components/mappers in small reviewed groups. Recommended first group:

- native/GIFT: matching and numerical questions;
- H5P: MultiChoice, TrueFalse, SingleChoiceSet, Summary, MarkTheWords and SortParagraphs;
- layout: labelled Figure/ImageHotspots with text fallback, Accordion, Tabs and Reflection.

Each H5P mapper gets an owned schema, deterministic params mapping, source-support checks, a real
service integration test and a verified completion rule. Complex H5P types stay import/reference
only until they meet the same bar.

## Tests and evals

- Policy schema, unique IDs, valid fallbacks and runtime filtering.
- Drift checks across PHP, Skill references, frontend catalogue and topic-layout manifest.
- Brief v2→v3 upgrade; adaptive omission of already-known answers.
- Tenant isolation for asset and brief endpoints.
- Prompt-injection fixtures in uploaded design systems and brand guides.
- WCAG 2.2 AA: keyboard, focus, target size, drag alternative, contrast, reduced motion, captions
  and textual equivalents.
- Tracking contract tests per adapter; no false completion.
- Blueprint citations/objective coverage and publish blocking for unsupported components.
- Mocked LLM tests plus real-model evals only through the eval command.

## Dependencies

No new runtime dependency in M2.6a. Use the existing JSON Schema validators, CLI/MCP registry,
H5P service API and course-builder prompt infrastructure.

Later brand-guide parsing should reuse current PDF/DOCX/Markdown ingestion. Figma import remains a
Phase 5 plugin/integration and needs its own dependency/licence plan.

## Risks

- A giant catalogue can encourage decorative or unsupported interactions. Mitigation: objective-led
  selection, runtime filtering and generation modes.
- H5P library versions and xAPI/completion behaviour vary. Mitigation: installed-library discovery,
  per-version conformance fixtures and explicit tracking capabilities.
- White-label freedom can break accessibility and consistency. Mitigation: semantic tokens,
  constrained variants and approval instead of arbitrary CSS.
- One-page/slides may look complete while LMS state is not. Mitigation: normalized server-recorded
  events and conformance tests for every shell.
- Design-system uploads can contain prompt injection, scripts or unlicensed assets. Mitigation:
  untrusted-source boundaries, file hardening, metadata-only extraction and ownership confirmation.

## Decisions confirmed 2026-10-15

1. Canonical JSON policy with thin API and Agent Skill adapters; `SKILL.md` is not the API runtime
   format.
2. Three initial shells: course/sidebar, one-page scroll and slides; presentation is slides.
3. Inventory all H5P types, but generate only types with tested deterministic mappings.
4. Accept approved design tokens and uploaded assets; no arbitrary CSS.
5. Store the project skill in `.cursor/skills/ulams-course-designer` and make it call the existing
   CLI/MCP tools.
