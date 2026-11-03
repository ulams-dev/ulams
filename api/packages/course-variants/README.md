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

## Learner routing (CV-5)

Guide: `front/docs-site/src/content/docs/developers/course-variants-api.mdx`. Tables added: `course_variant_assignments` (one current
row per learner and family, history kept), `course_variant_rules`, `course_variant_placement_items` and `_attempts`, `course_variant_hints`;
the family gets `pinned_course_id`, `lock_assigned`, `placement_enabled`, `hints_enabled` and how its default course is named in the picker.

- `AssignmentService`: the learner's own choice (`self`, `placement`, `hint`) or an admin's or rule's assignment. Choosing needs access to the
  family; an admin's assignment grants it. It supersedes the old row, enrols through the family entitlement and carries progress over.
- `FamilyAccess`: one entitlement covers the family. A learner with an entitlement (or a group) for any course of it gets a derived `family`
  entitlement for the variant they use (`EntitlementService::syncDerived`), kept in step by a listener on `AccessProjected`: it ends with the
  entitlements it derives from and follows their end date.
- `ProgressCarryOver`: completion moves to the new course for topics the `TopicCorrespondence` contract says are the same content (the Course
  Builder implements it from its entity map; quiz topics are never paired), through the courses `ProgressService`.
- `PlacementService`: optional, advisory, scored by code. Questions come from the `PlacementSource` contract (the builder: cited quiz questions that
  already exist). `HintService`: at most one hint per learner and family per week, dismissing stops them; the rules are the `FitSignals` contract
  (Learner Insights). `RuleAssigner`: attribute rules through the compliance rule engine, only for learners who already have access.
- Catalogue: variant courses stay `findable = false`; their page is open through `CourseVisibility`, and the default course's card carries
  `variant_family`.
- Endpoints: `api/courses/{course}/variants` (public), `variant`, `placement` (learner), `api/admin/course-variant-groups` (permission
  `course_variants_assign`). Command: `course-variants:assign-by-rules` (daily).

Install: registered by the Course Builder provider and in `api/config/app.php`; run the migrations. Tests:
`php artisan test --testsuite course-variants`.
