# course-variants: families of courses for different levels and roles

The LMS side of course variants (ADR 0192, Phase 4.4 of `docs/ROADMAP-PROMPT.md`). A **family** groups the LMS
courses that are variants of one course: a default course (the base) and one member course per audience (a level, a
role or both). The package knows nothing about the Course Builder: the builder registers a variant's course here when
it applies the variant, and a family can in future be made by hand.

- Plan: `docs/plans/course-versions-and-variants.md` (section 6.2). Guide:
  `front/docs-site/src/content/docs/creators/course-versions-and-variants.mdx`.
- Tables: `course_variant_groups` (`default_course_id`) and `course_variant_group_members` (`course_id`, `variant_key`,
  `label`, `level`, `role`, `position`).
- A member course is hidden from the public catalogue (`findable` is switched off through the courses repository), so
  the family shows once, as its default course. Learners reach a variant through the family (routing is milestone CV-5).
- Learner assignments, the picker endpoints, the placement check and the family entitlement are CV-5 and not built yet.

Install: registered by the Course Builder provider and in `api/config/app.php`; run the migrations. Tests:
`php artisan test --testsuite course-variants`.
