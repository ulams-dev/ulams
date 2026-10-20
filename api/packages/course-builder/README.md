# course-builder: the AI Course Builder

Turns an author's source (Markdown, PDF, DOCX) into a cited course through a short interview, an
outline with learning objectives the author approves, generated lessons and quizzes, and an apply
through the LMS domain services. Afterwards any element can be refined by chatting about it; every
change is a reviewable diff and a new blueprint version. Records: ADR 0010, 0011, 0022–0029; plan
`docs/plans/phase-2.md`. The shared course-design policy for API and local agents is ADR 0103 and
`docs/plans/phase-2-course-design-skill.md`.

- Author guide: [`docs/course-builder/author-guide.md`](../../../docs/course-builder/author-guide.md)
- Settings, limits, permissions, queues: [`docs/course-builder/admin-settings.md`](../../../docs/course-builder/admin-settings.md)
- Architecture, AG-UI stream, adding a step, eval command: [`docs/course-builder/developer-notes.md`](../../../docs/course-builder/developer-notes.md)
- Prompts and how to iterate on them: [`resources/prompts/README.md`](resources/prompts/README.md)
- Course-design capability policy: [`resources/course-design/policy.json`](resources/course-design/policy.json)

The policy inventories learner shells, native formats, every official H5P content type, GIFT
question types, tracking signals, fallbacks and accessibility requirements. Inventory does not mean
generation permission: `CourseDesignPolicy::apiGeneratable()` intersects `api-generated` entries
with the installation's enabled content types and installed H5P libraries. The project Agent Skill
at `.cursor/skills/ulams-course-designer/` reads the same policy and uses the existing CLI/MCP
builder workflow. Run `yarn course-design:check` to verify that its compact inventory is current.

```
Upload ─▶ IngestRun: Source Document + fragments (frg_… ids from the heading position)
       ─▶ InterviewRun (light model): 6 questions as catalogue controls; answers fill the Course Brief
       ─▶ OutlineRun (default model): objectives + outline, proposed as a version ── author approves/edits/rejects
       ─▶ GenerateRun: lessons ▸ grounding ▸ quizzes + final test ▸ metadata ▸ content version (resumable steps)
       ─▶ apply proposal ── author approves ─▶ ApplyRun: course, lessons, topics, GIFT questions, landing page
       ─▶ element chat: patch version as a DiffView ── approve ─▶ re-apply of the changed elements; undo/redo/restore
```

Everything the browser sees is an AG-UI event (`GET …/sessions/{id}/events`, SSE with resume); the
interactive cards are A2UI v0.9 surfaces from the `@ulams/ui` builder catalogue, built by code from
validated data. The model never writes UI markup, never calls tools and never sees the source outside
an untrusted wrapper in the user turn.

```bash
vendor/bin/phpunit --testsuite course-builder              # fake driver; includes a recorded live run
php artisan course-builder:eval --fixtures=all --author=1  # free, fake driver
php artisan course-builder:eval --fixtures=coffee --live --author=1   # real model, costs money
```
