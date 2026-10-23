/**
 * scorm-again 3 changed several defaults compared with 2.6, which this player shipped with. Learners must
 * not notice the upgrade (CLAUDE.md: H5P and SCORM behaviour unchanged), so the 2.6 behaviour is set
 * explicitly here. The playback specs (front/tests/scorm-playback, ADR 0197) pin the result; a change to this
 * object is a SCORM behaviour change and needs the product owner's approval.
 *
 * The API content player (api/packages/scorm/resources/content-player/player.js) sets the same values.
 */
export const SCORM_AGAIN_LEGACY_SETTINGS = {
  // SCORM 1.2: 3 derives lesson_status from the score and the mastery score by default; 2.6 left the
  // status the SCO set.
  mastery_override: false,
  // 2.6 sent a commit with fetch right away (asyncCommit: false) and returned "true" without waiting; 3 sends
  // it with a synchronous XMLHttpRequest by default. `useAsynchronousCommits` is the 2.6 transport.
  useAsynchronousCommits: true,
  // 2.6 batched commits for 500 ms only with asyncCommit: true, which was never set.
  throttleCommits: false,
  // Defaults that are the same in 2.6 and 3; set so that a later default change does not alter playback.
  autocommit: false,
  strict_errors: true,
  autoCompleteLessonStatus: false,
  accumulateSessionTimeOnTerminate: false,
} as const;
