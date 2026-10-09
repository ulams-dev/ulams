# 0039. Author preview of draft courses runs on its own routes with the author's token

- Status: Proposed
- Date: 2026-10-09

## Context and problem statement

A course built in the studio is created unpublished. The studio's "Preview as learner" link opened the public course page, which the web app fetches as the visitor (the auto-logged-in demo student, or nobody). The API answers 403 for a draft, and the page turned that into a 404.

## Decision

- Previews live on `/preview/courses/{id}` and `/preview/courses/{id}/{topicId}`, rendered with the same course page and lesson player components as the public routes. The public routes stay unchanged.
- The server fetches the course with the studio author's own token (the `ulams_author` cookie), through `GET /api/profile/me` and `GET /api/courses/{id}/program`, where the API's `attend` policy admits admins and authors. A visitor without that cookie never gets the demo-admin auto sign-in on these routes.
- Access is granted only when the author may edit the course (admin, `course_update`, or `course_update_authored` on a course they author; the same rule as the API's `update` policy). Everything else, including a token of another tenant, answers with the ordinary 404.
- Preview responses are `Cache-Control: private, no-store`, `X-Robots-Tag: noindex` and carry a robots meta tag. They never use the public in-memory cache.
- Preview tracks nothing: no ping or completion, no tracked launches (SCORM content origin, LiaScript, LTI), no H5P xAPI forwarding, and quizzes show a note instead of starting an attempt.

## Consequences

- Good: an author sees the real learner pages before publishing, without granting the demo student access to drafts.
- Good: no new API endpoint; the API stays the authority on who may read a course.
- Bad: quizzes cannot be tried in preview; a later step could run them against a throwaway attempt.
- Bad: the edit rule is mirrored in the web app (`canEditCourse`) and must follow the API policy.
