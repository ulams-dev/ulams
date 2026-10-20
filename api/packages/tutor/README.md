# tutor: a course tutor that answers only from the course

A learner asks a question inside a course and gets a short answer built only from that course's own
content, with citations that link back to the lesson. Outside the course it says so. During an open quiz
attempt it never gives or confirms quiz answers. **Off by default per tenant** (`tutor.enabled`).
Records: ADR 0060 (proposed), ADR 0061 (privacy); plan `docs/plans/phase-4.md` section 8.

- `TutorIndexer`: chunks of the applied blueprint (lesson blocks and the source text they cite) for
  Course Builder courses, rich text topics split at headings for other courses, with a PostgreSQL
  `tsvector` and a GIN index. Quiz questions, options, explanations and final tests are never indexed.
  The index rebuilds itself when the applied version or the topic content changes (`tutor:index` forces it).
- `PostgresRetriever`: the words of the question OR-ed, ranked with `ts_rank_cd`, plus up to four chunks
  of the open topic; `LikeRetriever` is the fallback for databases without full-text search. No
  embeddings, no vector database.
- `TutorService`: checks the switch, course access and limits (30 questions per learner and day, 6 per
  minute, a monthly tenant budget), asks the `tutor` task (profile `light`) with the chunks, the last six
  messages and the question, each learner text inside an escaped `<learner_message>` wrapper. The answer
  must cite chunks that were provided; an in-scope answer needs a citation. While the learner has a quiz
  attempt open (`AttemptGuard`), the mode is `attempt_active` and an answer that contains a correct option
  (`Support/Leak`, 80 % of the words of a longer option, the phrase of a short one) is replaced by a standard
  refusal and flagged. `ai_calls.user_id` is null; the subject is the conversation.
- Conversations belong to the learner (list, show, delete) and are deleted after 90 days
  (`tutor:prune`) or with the account. Analytics for authors and admins are counts only, for groups of
  at least `learner_insights.min_group_size` learners.

```bash
vendor/bin/phpunit --testsuite tutor        # fake driver, a course built through the real builder pipeline
php artisan tutor:index --course=1
```

- Settings, env variables, privacy: `front/docs-site/src/content/docs/admin/tutor.mdx`
