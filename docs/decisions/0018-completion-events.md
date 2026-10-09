# 0018. External content completes topics; completion events fire after progress is saved

- Status: Accepted (2026-10-09)
- Date: 2026-10-09

## Context and problem statement

SCORM packages, LiaScript courses and LTI tools report completion outside the topic API. Before
Phase 1 a finished SCORM package left its topic open. Lesson and course completion, certificates and
the LTI grade passback all hang off `TopicFinished`. The Moodle conformance run showed that the event
fired before the new progress row was saved, so a listener running at once (sync queue) saw the old
state and sent the previous score. Phase 1 decisions 5, 23 and 38.

## Decision

- Every external completion goes through `CourseProgressRepository::updateInTopic()`:
  `ScormScoCompleted` (first `completed`/`passed`) completes every SCORM topic using that SCO for
  learners with course access (checked on the course policy); LiaScript completes at the last section
  or on `completed`/`passed`; an LTI score with `Completed` or `FullyGraded` completes the LTI topic.
  An in-progress row is created first so the transition fires the usual events.
- `TopicFinished` and the lesson/course check are dispatched **after** the progress row is saved.
- Scores from tools are append-only; a later lower score never reopens a topic.

## Consequences

- Listeners can read the learner's progress including the topic just finished, whatever the queue
  driver.
- Certificates and course completion now follow package completion automatically.
