/*
 * SCORM 2004 (4th Edition) learner session (MIT, written for ulams).
 *   ?outcome=passed (default) | failed
 *   ?scenario=full (default) | no-completion   no-completion never sets completion_status or
 *                                              success_status, to pin the defaults
 *   ?phase=first (default) | resume
 */
(function () {
  'use strict';
  var fx = window.UlamsFixture;
  var s = fx.start('2004');
  var outcome = fx.params.get('outcome') === 'failed' ? 'failed' : 'passed';
  var scenario = fx.params.get('scenario') === 'no-completion' ? 'no-completion' : 'full';
  var phase = fx.params.get('phase') === 'resume' ? 'resume' : 'first';

  if (s.found) {
    s.call('init');
    s.call('get', 'cmi.learner_id');
    s.call('get', 'cmi.entry');
    s.call('get', 'cmi.completion_status');
    s.call('get', 'cmi.success_status');
    s.call('get', 'cmi.location');
    s.call('get', 'cmi.total_time');
    s.call('get', 'cmi.suspend_data');

    if (phase === 'first') {
      s.call('set', 'cmi.location', 'chapter-2/page-3');
      s.call('set', 'cmi.score.min', '0');
      s.call('set', 'cmi.score.max', '100');
      s.call('set', 'cmi.score.raw', outcome === 'passed' ? '85' : '40');
      s.call('set', 'cmi.score.scaled', outcome === 'passed' ? '0.85' : '0.4');
      s.call('set', 'cmi.progress_measure', '0.75');
      s.call('set', 'cmi.suspend_data', fx.filler(8192));
      s.call('set', 'cmi.interactions.0.id', 'q1');
      s.call('set', 'cmi.interactions.0.type', 'choice');
      s.call('set', 'cmi.interactions.0.learner_response', 'b');
      s.call('set', 'cmi.interactions.0.correct_responses.0.pattern', 'b');
      s.call('set', 'cmi.interactions.0.result', 'correct');
      s.call('set', 'cmi.session_time', 'PT1M30S');
      if (scenario === 'full') {
        s.call('set', 'cmi.completion_status', 'incomplete');
      }
      s.call('commit');
      if (scenario === 'full') {
        s.call('set', 'cmi.completion_status', 'completed');
        s.call('set', 'cmi.success_status', outcome);
      }
      s.call('set', 'cmi.exit', 'suspend');
      s.call('commit');
      // validation strictness: a read-only element, a range error and a vocabulary error
      s.call('set', 'cmi.learner_id', 'someone-else');
      s.call('set', 'cmi.score.scaled', '2');
      s.call('set', 'cmi.completion_status', 'done');
      s.call('set', 'cmi.progress_measure', 'half');
    } else {
      s.call('set', 'cmi.completion_status', 'completed');
      s.call('set', 'cmi.session_time', 'PT45S');
    }

    s.call('get', 'cmi.completion_status');
    s.call('get', 'cmi.success_status');
    s.call('get', 'cmi.score.scaled');
    s.call('get', 'cmi.total_time');
    s.call('finish');
  }
  s.done();
})();
