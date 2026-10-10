# 0095. The Ulam course: every statement traced to a fact sheet, sources numbered, four licensed photographs

- Status: Proposed
- Date: 2026-10-10
- Plan: `docs/plans/interactive-demos.md` (M9b)

## Context and problem statement

The Scottish Book course is about a real person and real events, and its sources disagree in places (the day of
his birth, his advisor, who supplied the notebook, how the book came through the war). Lesson text written from
memory, or by a model, would drift from them. The course also uses five interactives, two of which (the problem
cards and the journey map) carry facts of their own, and four photographs with four different licence situations.

## Decision

- **A fact sheet is the only source of statements.** `docs/plans/interactive-demos-ulam-facts.md` (M9a) is mirrored
  in `demo-content/ulam/facts.json` (`{text, sources, status}` per fact id, statuses `ok`, `differs`, `second`,
  `resolution`, `not-used`) and `demo-content/ulam/sources.json` (numbered sources `U-nn`, Wikipedia and other pointer
  sources left out). The seeder reads a copy of `sources.json` (`Demo/content/ulam/sources.json`), as for the other
  courses (ADR 0094); a test keeps the copies equal.
- **Every quiz question carries a `// facts:` line** (fact ids and `[U-nn]` sources) above it in the module file. A
  test fails when a question has none, names an unknown fact, rests on a `second` or `not-used` fact, or rests on a
  disputed (`differs`) fact without saying it asks only about the undisputed part. Disputed points (who bought the
  notebook, how the book survived, the ship of 1939, the day of birth) are never quizzed; the lessons say that
  sources differ.
- **Quotations are exact and from the sheet.** A test checks that every quotation of four words or more in the
  lessons occurs in the fact sheet; the product-name sentence is tested verbatim.
- **Problems the sources do not support are dropped, not guessed.** The problem cards carry nine problems; 77(a) has no
  card because its statement could not be sourced (recorded as `3b/77a`, status `not-used`), and cards without a
  recorded yes or no have no guess. Where the later reading of the sources changed a row of the sheet (Problem 184's
  prize is a kilogram of fatback, older editions say bacon; the Schrandt-Ulam rule replaces the Ulam-Warburton rule in
  the `automaton` package, reproduced against OEIS A170896), `facts.json` records it with `added: "M9b"`.
- **One course, five packages.** The Markdown lesson blocks take `package=ulam-<name>`; `ContentPackages::ULAM` lists the
  five zips (`ulam-spiral.zip` and so on, built by `make -C api demo-packages`). The first Interactive topic of the first
  lesson is the spiral, which the landing hero plays (ADR 0093). A topic that completes by score takes `score=60`.
- **Images.** Four photographs are shown, each only because the licence of its Commons page was read: two from Los
  Alamos National Laboratory (shown with the laboratory's notice, repeated in the last lesson), one CC BY-SA 1.0 and one
  CC BY-SA 4.0 (credit, licence link, "resized", share-alike kept). They are fetched once by
  `demo-content/ulam/scripts/fetch-images.mjs` into `api/database/seeds/Demo/assets/ulam/images/` (WebP, each under
  300 KB), referenced as `{{asset:file.webp}}` in a text, and listed in `demo-content/ulam/CREDITS.md` and `LICENSING.md`.
  A test checks that every file has its credit line next to it. Photographs that rely on Polish-law public domain, "Ulam
  holding the FERMIAC" and photographs of Scottish Book pages are not used.
- **Module and lesson.** The course has eight modules (the LMS lessons) of sixteen numbered lessons (their text topics:
  1.1, 1.2, 2.1 and so on), a welcome lesson, a 14-question final test and a sources lesson.

## Consequences

- Good: a reviewer can open the fact sheet, a module and the test side by side; a wrong date can only enter the
  course through the sheet, where it has a source.
- Good: the course states disagreements instead of settling them silently, and never asks about them.
- Bad: an extra layer (`facts.json`) to keep in step with the sheet and the text; the tests catch references, not wording.
- Bad: the `// facts:` convention is checked by a Node test, not by the seeder; the PHP side checks the structure.

## Alternatives considered

- **Quizzes written without fact ids.** Rejected: nothing would stop a question from drifting to a disputed point.
- **Keeping the Ulam-Warburton rule and calling it an illustration.** Rejected: the rule that bears Ulam's name is
  Schrandt-Ulam and its counts are published (OEIS A170896), so the step can be cited instead of hedged.
- **Drawn portraits of the people of the Lwów School.** Not done: the people cards carry text only, because no photograph
  of them has a confirmed licence and the drawn likeness of a real person is a risk of its own.
