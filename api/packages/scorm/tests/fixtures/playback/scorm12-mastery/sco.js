/*
 * SCORM 1.2 mastery score (MIT, written for ulams). The manifest sets adlcp:masteryscore to 70.
 * The SCO sets a score and never sets lesson_status, so the status shows whether the player
 * derives it from the score (scorm-again setting mastery_override).
 *   ?score=65 (default)
 */
(function () {
  'use strict';
  var fx = window.UlamsFixture;
  var s = fx.start('1.2');
  var score = fx.params.get('score') || '65';

  if (s.found) {
    s.call('init');
    s.call('get', 'cmi.student_data.mastery_score');
    s.call('get', 'cmi.core.lesson_status');
    s.call('set', 'cmi.core.score.min', '0');
    s.call('set', 'cmi.core.score.max', '100');
    s.call('set', 'cmi.core.score.raw', score);
    s.call('get', 'cmi.core.lesson_status');
    s.call('set', 'cmi.core.session_time', '0000:00:20.00');
    s.call('commit');
    s.call('get', 'cmi.core.lesson_status');
    s.call('finish');
  }
  s.done();
})();
