/*
 * SCORM player served from the tenant content origin (<slug>.content.<base>/scorm/_player/).
 * The SCO runs in an iframe on the same origin, so it finds window.API / window.API_1484_11
 * here. Launch parameters arrive in the URL fragment (never sent to a server):
 *   #api=<tenant API base URL>&sco=<SCO uuid>&token=<topic-scoped tracking token>
 * The token only reads this SCO's launch data and writes this learner's tracking for it; the
 * learner's API token never reaches this origin.
 */
(function () {
  'use strict';

  var status = document.getElementById('status');
  var params = new URLSearchParams(window.location.hash.slice(1));
  var api = params.get('api');
  var sco = params.get('sco');
  var token = params.get('token');
  // keep the token out of the address bar and history
  window.history.replaceState(null, '', window.location.pathname);

  function fail(message) {
    status.textContent = message;
    status.hidden = false;
  }

  if (!api || !sco || !token || typeof Scorm12API === 'undefined') {
    fail('This content could not be started. Close it and open the lesson again.');
    return;
  }

  var base = api.replace(/\/+$/, '') + '/api/scorm/content/' + encodeURIComponent(sco);
  var headers = { 'X-Ulams-Tracking-Token': token, Accept: 'application/json' };

  function post(data) {
    return fetch(base + '/track', {
      method: 'POST',
      headers: Object.assign({ 'Content-Type': 'application/json' }, headers),
      body: JSON.stringify(data),
      keepalive: true
    }).catch(function () {
      // tracking is retried on the next commit
    });
  }

  function start(data) {
    var settings = Object.assign({}, data.player || {}, { autocommit: false });
    if (data.version === 'scorm_2004') {
      window.API_1484_11 = new Scorm2004API(settings);
      window.API_1484_11.loadFromJSON(data.cmi || {});
      window.API_1484_11.on('SetValue.cmi.*', function (element, value) {
        var cmi = {};
        cmi[element] = value;
        post({ cmi: cmi });
      });
      window.API_1484_11.on('Commit', function () {
        post({ cmi: window.API_1484_11.cmi });
      });
    } else {
      window.API = new Scorm12API(settings);
      window.API.loadFromJSON(data.cmi || {});
      window.API.on('LMSSetValue.cmi.*', function (element, value) {
        var cmi = {};
        cmi[element] = value;
        post({ cmi: cmi });
      });
      window.API.on('LMSCommit', function () {
        post({ cmi: window.API.cmi });
      });
    }

    var frame = document.createElement('iframe');
    frame.title = data.title || 'Course content';
    frame.src = data.entry_url;
    frame.allow = 'fullscreen; autoplay';
    document.body.appendChild(frame);
    status.hidden = true;
  }

  fetch(base, { headers: headers })
    .then(function (response) {
      if (!response.ok) {
        throw new Error(String(response.status));
      }
      return response.json();
    })
    .then(function (body) {
      start(body.data);
    })
    .catch(function (error) {
      fail(error && error.message === '401'
        ? 'This session has expired. Close the content and open the lesson again.'
        : 'This content could not be loaded. Check your connection and reload the page.');
    });
})();
