/*
 * SCORM 1.2 learner session (MIT, written for ulams).
 *   ?outcome=passed (default) | failed   final lesson_status after the score is set
 *   ?phase=first (default) | resume      resume reads what the LMS gave back and finishes
 */
(function () {
  'use strict';
  var fx = window.UlamsFixture;
  var s = fx.start('1.2');
  var outcome = fx.params.get('outcome') === 'failed' ? 'failed' : 'passed';
  var phase = fx.params.get('phase') === 'resume' ? 'resume' : 'first';

  if (s.found) {
    s.call('init');
    s.call('get', 'cmi.core.student_id');
    s.call('get', 'cmi.core.entry');
    s.call('get', 'cmi.core.lesson_status');
    s.call('get', 'cmi.core.lesson_location');
    s.call('get', 'cmi.core.total_time');
    s.call('get', 'cmi.suspend_data');

    if (phase === 'first') {
      s.call('set', 'cmi.core.lesson_location', 'chapter-2/page-3');
      s.call('set', 'cmi.core.score.min', '0');
      s.call('set', 'cmi.core.score.max', '100');
      s.call('set', 'cmi.core.score.raw', outcome === 'passed' ? '85' : '40');
      s.call('set', 'cmi.core.lesson_status', 'incomplete');
      s.call('set', 'cmi.suspend_data', fx.filler(4096));
      s.call('set', 'cmi.core.session_time', '0000:01:30.00');
      s.call('commit');
      s.call('set', 'cmi.core.lesson_status', outcome);
      s.call('set', 'cmi.core.exit', 'suspend');
      s.call('commit');
      // validation strictness: a read-only element, a type error and a range error
      s.call('set', 'cmi.core.student_id', 'someone-else');
      s.call('set', 'cmi.core.score.raw', 'not-a-number');
      s.call('set', 'cmi.core.score.raw', '150');
      s.call('set', 'cmi.core.lesson_status', 'done');
      s.call('set', 'cmi.suspend_data', fx.filler(4097));
    } else {
      s.call('set', 'cmi.core.lesson_status', 'completed');
      s.call('set', 'cmi.core.session_time', '0000:00:45.00');
    }

    s.call('get', 'cmi.core.lesson_status');
    s.call('get', 'cmi.core.score.raw');
    s.call('get', 'cmi.core.total_time');
    s.call('finish');
  }
  s.done();
})();
