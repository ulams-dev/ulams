# 0094. Interactive demo courses are written as Markdown module files and seeded through the domain services

- Status: Proposed
- Date: 2026-10-10
- Plan: `docs/plans/interactive-demos.md` (M8)

## Context and problem statement

The Gravity and Poland demo academies need courses of about a hundred topics each (Interactive, rich text, Layout,
GIFT quizzes), the Polish course is a translation of the English one, every number must cite a source, and the hourly
demo reset has to recreate all of it. The existing experiences (Coffee Atlas and the others) describe their
program in PHP arrays, which is awkward for 12 lessons of prose, JSON layouts and GIFT questions, and the API
container sees only `api/`, so it cannot read the packages in `demo-content/`.

## Decision

- **One Markdown file per lesson** in `api/database/seeds/Demo/content/<course>/modules/NN-slug.md`: a front
  matter block (title, summary, duration) and `::: kind attributes` … `:::` blocks for the topics, in order.
  `interactive` and `richtext` bodies are Markdown, `layout` is JSON (`document` and `fallback`), `quiz` is GIFT.
  `Support/ModuleFile` parses it and `Support/BuildsInteractiveCourse` turns it into the `program()` of an
  experience. Topics are created through `TopicFactoryHelper` and the topic repository, as for the other
  experiences, and the GIFT questions through the question service.
- **Citations are data.** `{{src:ID}}` in a text resolves through `content/<course>/sources.json` (a copy of the
  package's own list); `Support/Sources` numbers the citations of one text, merges ids that name the same page and
  appends the list (a compact line under an Interactive text, a "Sources" heading under rich text, a "Sources" callout
  under a Layout). The last lesson lists every source by lesson and carries the CC BY 4.0 attribution.
- **The two poland courses are two experiences over one package**: `PolandExperience` (English) seeds
  `PolandPolishExperience` after itself ("Polska w liczbach", its own text, landing fields and certificate labels).
  A learner picks a language once and everything, down to the question text, is in it. The unit tests keep the two
  courses parallel (same blocks, step ranges, citations, question types and numerical answers).
- **The packages reach the seeder as cached zips** (`assets/cache/interactive/<name>.zip`, git-ignored), built on
  the host by `make -C api demo-packages`. A missing zip stops the seeding with a message instead of skipping the
  topics. The same zip (SHA-1 in the version's change note) is reused on a re-seed; a changed zip becomes a new
  version of the package.
- **The landing hero** needs no extra setting: the showcase endpoint (ADR 0093) returns the first Interactive
  topic of the first public course, and each course's welcome lesson ends with one (the whole solar system, the
  map).
- **The course text is CC BY 4.0** (#148), stated in the first lesson, the last lesson and the landing's Sources
  section. A figure that the simulation shows differently from NASA is fixed in the course, and in the package text
  where a learner would read both (`demo-content/gravity/FACTCHECK.md`).

## Consequences

- Good: the course text can be reviewed as prose in a pull request, the owner's review against `FACTCHECK.md`
  reads the same files the seeder reads, and the reset needs no network.
- Good: tests (`DemoInteractiveContentTest`, `demo-content/tests/unit/courses.test.mjs`) check layouts against
  the learner catalogue, GIFT types, citations, step ranges and the EN/PL parallelism without a database.
- Bad: a new file format to know (about 100 lines of parser) and a host-side build step before seeding these
  two tenants.
- Bad: Polish course text is a translation kept by hand next to the English one; the parallelism test catches
  structure, not wording.

## Alternatives considered

- **PHP arrays per experience.** Rejected: the same reasons as in the context; long strings, JSON and GIFT in PHP
  quoting.
- **Course export/import (`.zip`) of a course built in the admin.** Rejected: it would not be reviewable as text
  and could not be checked against the sources in a test.
- **Building the packages inside the seeder.** Rejected: needs Node and Chromium in the API container.
- **One bilingual course.** Rejected: the platform's course language is one value per course, and a course
  catalogue by language is the pattern a real tenant would follow.
