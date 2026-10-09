# 0071. Interactive preview in the studio: learner pages from the blueprint, in a frame

- Status: Proposed
- Date: 2026-10-09

## Context and problem statement

After a course is built, the author wants to see it as a learner will and discuss each element with the
assistant. The element chat already works on blueprint ids (course, module, lesson, block, question;
`PatchService`), but the learner pages render LMS entities, which carry no blueprint ids and merge a
lesson's blocks into one rich-text topic. The studio layout and the tenant theme also cannot share one
document: the theme's base CSS is global.

## Decision

- The preview is rendered from the current blueprint version, not from the LMS, so every element has its
  blueprint id (`data-blueprint-id`, plus a part for the few ids that mark several sections) and the author
  discusses what they will approve, not a stale applied copy.
- The blueprint is mapped to the `Course` shape the learner pages read (`lib/blueprint-preview.ts`), so the
  course page, the lesson player and the landing render with the same catalogue components and the same
  player component (`LessonPlayer` gained a content slot). Quiz questions are read-only cards with the
  correct answers; nothing is tracked.
- The learner page is a same-origin frame (`/studio/s/:id/frame/…`, tenant theme) inside the studio page
  (`/studio/s/:id/preview/…`, studio layout) next to the chat panel. The studio island (`studio/preview.ts`)
  listens in the frame, selects an element (a real "Discuss" button per element, so Tab + Enter works; a click
  on the element also selects) and scopes the existing element chat (`runs.message` with the element id).
  The chat, diff, approve/reject, undo and redo use the existing builder API and timeline; no new endpoint.
- After a new version arrives the island fetches the frame page again and swaps only the elements whose
  markup changed (highlighted for 2.6 s); if elements appeared or disappeared the frame reloads.
- Landing sections are not blueprint elements: they are generated from the course. They are marked with the
  course id and a part name, so discussing one edits the course title, subtitle or description. Quizzes as a
  whole are not editable by chat (the API patches questions), so only questions are selectable.
- Access is the existing studio rule: the author's session and the API's session policy. A session of another
  author or tenant answers 404.

## Consequences

- Good: no new API, no new JS framework, the same renderer and player as learners get.
- Good: the author can reach the exact element the assistant edits, with its citations next to the diff.
- Bad: the preview shows the blueprint rendering, which differs slightly from the applied LMS pages (rich-text
  topics are one per lesson there; the quiz is the GIFT-rendered topic).
- Bad: a frame hides the learner page from the studio's tab order only partly; focus is moved by script
  (select moves focus to the chat, Esc moves it back to the element's Discuss button).
