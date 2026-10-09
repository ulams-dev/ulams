/*
 * LiaScript player on the tenant content origin (<slug>.content.<base>/liascript/_player/).
 * The LiaScript SCORM 1.2 build (fetched at image build time, packages/liascript/bin) runs in a
 * same-origin iframe and reports through window.API; this page forwards slide position and status
 * to the API with a topic-scoped token. Launch parameters arrive in the URL fragment:
 *   #api=<tenant API>&topic=<topic id>&token=<progress token>&course=<path of the Markdown>&sections=<n>
 * The admin editor's live preview opens it with #preview=1&course=<draft>: nothing is reported.
 */
(function () {
  'use strict';

  var status = document.getElementById('status');
  var params = new URLSearchParams(window.location.hash.slice(1));
  var api = params.get('api');
  var topic = params.get('topic');
  var token = params.get('token');
  var course = params.get('course');
  var preview = params.get('preview') === '1';
  window.history.replaceState(null, '', window.location.pathname);

  function fail(message) {
    status.textContent = message;
    status.hidden = false;
  }

  if (!course || course.charAt(0) !== '/' || (!preview && (!api || !topic || !token))) {
    fail('This course could not be started. Close it and open the lesson again.');
    return;
  }

  var endpoint = preview ? '' : api.replace(/\/+$/, '') + '/api/liascript/progress/' + encodeURIComponent(topic);
  var state = { location: null, status: null, score: null };
  var timer = null;

  function send() {
    timer = null;
    if (preview) {
      return;
    }
    fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Ulams-Tracking-Token': token },
      body: JSON.stringify(state),
      keepalive: true
    }).then(function (response) {
      if (response.status === 401) {
        fail('This session has expired. Close the course and open the lesson again.');
      }
    }).catch(function () {
      // sent again with the next change
    });
  }

  function schedule() {
    if (timer === null) {
      timer = window.setTimeout(send, 400);
    }
  }

  var values = {};
  window.API = {
    LMSInitialize: function () { return 'true'; },
    LMSFinish: function () { if (timer !== null) { window.clearTimeout(timer); send(); } return 'true'; },
    LMSGetValue: function (key) {
      if (key === 'cmi.core.lesson_status') { return values[key] || 'not attempted'; }
      if (key === 'cmi.core.entry') { return 'ab-initio'; }
      return values[key] || '';
    },
    LMSSetValue: function (key, value) {
      values[key] = String(value);
      if (key === 'cmi.core.lesson_location') { state.location = parseInt(value, 10); schedule(); }
      if (key === 'cmi.core.lesson_status') { state.status = String(value); schedule(); }
      if (key === 'cmi.core.score.raw') { state.score = parseFloat(value); }
      return 'true';
    },
    LMSCommit: function () { return 'true'; },
    LMSGetLastError: function () { return '0'; },
    LMSGetErrorString: function () { return ''; },
    LMSGetDiagnostic: function () { return ''; }
  };

  var frame = document.createElement('iframe');
  frame.title = 'Course';
  frame.allow = 'fullscreen; autoplay; clipboard-write';
  frame.src = 'build/index.html?' + course;
  frame.addEventListener('load', function () { status.hidden = true; });
  document.body.appendChild(frame);
})();
