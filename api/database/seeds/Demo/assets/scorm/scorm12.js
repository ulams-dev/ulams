/*
 * Minimal SCORM 1.2 runtime wrapper shared by the demo packages.
 * Finds the LMS API in the parent frames; without one the activity still
 * works and just reports "offline".
 */
(function (global) {
  function findApi(win) {
    var tries = 0;
    while (win && tries < 10) {
      if (win.API) return win.API;
      if (win.parent === win) break;
      win = win.parent;
      tries++;
    }
    if (global.opener && global.opener.API) return global.opener.API;
    return null;
  }

  var api = findApi(global);
  var started = Date.now();
  var finished = false;

  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function sessionTime() {
    var s = Math.round((Date.now() - started) / 1000);
    return pad(Math.floor(s / 3600)) + ':' + pad(Math.floor((s % 3600) / 60)) + ':' + pad(s % 60);
  }

  var Scorm = {
    connected: false,
    init: function () {
      if (!api) return false;
      this.connected = String(api.LMSInitialize('')) === 'true';
      if (this.connected) {
        var status = api.LMSGetValue('cmi.core.lesson_status');
        if (!status || status === 'not attempted') {
          api.LMSSetValue('cmi.core.lesson_status', 'incomplete');
          api.LMSCommit('');
        }
      }
      return this.connected;
    },
    get: function (key) { return this.connected ? api.LMSGetValue(key) : ''; },
    set: function (key, value) { if (this.connected) api.LMSSetValue(key, String(value)); },
    commit: function () { if (this.connected) api.LMSCommit(''); },
    /* score 0..100, passed true/false/null (null = completed only) */
    complete: function (score, passed) {
      if (!this.connected) return;
      if (typeof score === 'number') {
        this.set('cmi.core.score.min', 0);
        this.set('cmi.core.score.max', 100);
        this.set('cmi.core.score.raw', Math.round(score));
      }
      this.set('cmi.core.lesson_status', passed === null ? 'completed' : (passed ? 'passed' : 'failed'));
      this.set('cmi.core.session_time', sessionTime());
      this.commit();
    },
    finish: function () {
      if (!this.connected || finished) return;
      finished = true;
      this.set('cmi.core.session_time', sessionTime());
      this.set('cmi.core.exit', '');
      api.LMSFinish('');
    }
  };

  global.Scorm = Scorm;
  global.addEventListener('load', function () { Scorm.init(); });
  global.addEventListener('beforeunload', function () { Scorm.finish(); });
})(window);
