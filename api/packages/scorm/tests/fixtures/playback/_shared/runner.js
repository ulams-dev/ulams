/*
 * ulams SCORM playback fixtures: shared runner (MIT, written for ulams, no third-party code).
 *
 * A SCO script describes a learner session as a list of runtime calls. The runner finds the SCORM
 * API in the parent frames, makes every call, and records the call, its return value and the
 * error code that follows it. The trace is available as window.__scormTrace and is posted to the
 * top window when the session ends, so a test harness never has to wait on a timer.
 */
(function () {
  'use strict';

  var VERSIONS = {
    '1.2': {
      global: 'API',
      init: 'LMSInitialize', get: 'LMSGetValue', set: 'LMSSetValue',
      commit: 'LMSCommit', finish: 'LMSFinish', error: 'LMSGetLastError'
    },
    '2004': {
      global: 'API_1484_11',
      init: 'Initialize', get: 'GetValue', set: 'SetValue',
      commit: 'Commit', finish: 'Terminate', error: 'GetLastError'
    }
  };

  var params = new URLSearchParams(window.location.search);
  var trace = [];
  window.__scormTrace = trace;
  window.__scormDone = false;

  function findApi(name) {
    var w = window;
    for (var depth = 0; w && depth < 20; depth++) {
      if (w[name]) { return w[name]; }
      if (w === w.parent) { break; }
      w = w.parent;
    }
    return null;
  }

  function start(version) {
    var spec = VERSIONS[version];
    var api = findApi(spec.global);
    var session = {
      params: params,
      version: version,
      found: !!api,
      call: function (kind, element, value) {
        var fn = spec[kind];
        var args = kind === 'get' ? [element] : kind === 'set' ? [element, value] : [''];
        var entry = { fn: fn, args: args };
        try {
          entry.result = String(api[fn].apply(api, args));
          entry.error = String(api[spec.error]());
        } catch (e) {
          entry.exception = String((e && e.message) || e);
        }
        trace.push(entry);
        return entry.result;
      },
      note: function (label, value) {
        trace.push({ note: label, value: value === undefined ? null : value });
      },
      done: function () {
        window.__scormDone = true;
        var message = { source: 'ulams-scorm-fixture', type: 'done', trace: trace };
        try { window.top.postMessage(message, '*'); } catch (e) { /* no top window */ }
      }
    };
    if (!api) {
      session.note('api-not-found', spec.global);
    }
    return session;
  }

  /** A string of exactly `length` characters, the same on every run. */
  function filler(length) {
    var unit = 'ulams-suspend-data-0123456789|';
    var out = '';
    while (out.length < length) { out += unit; }
    return out.slice(0, length);
  }

  window.UlamsFixture = { start: start, filler: filler, params: params };
})();
