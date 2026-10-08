/*
 * Minimal cmi5 assignable-unit runtime shared by the demo packages.
 * Implements the launch sequence from the cmi5 spec: read the launch
 * parameters, fetch the auth token, read LMS.LaunchData, then send
 * initialized / progressed / completed / passed / terminated statements
 * built from the LMS context template. Without launch parameters (opened
 * directly) the activity still works and reports "offline".
 */
(function (global) {
  var CMI5_CATEGORY = 'https://w3id.org/xapi/cmi5/context/categories/cmi5';
  var MOVEON_CATEGORY = 'https://w3id.org/xapi/cmi5/context/categories/moveon';
  var VERBS = {
    initialized: 'http://adlnet.gov/expapi/verbs/initialized',
    progressed: 'http://adlnet.gov/expapi/verbs/progressed',
    completed: 'http://adlnet.gov/expapi/verbs/completed',
    passed: 'http://adlnet.gov/expapi/verbs/passed',
    failed: 'http://adlnet.gov/expapi/verbs/failed',
    terminated: 'http://adlnet.gov/expapi/verbs/terminated'
  };

  var params = new URLSearchParams(global.location.search);
  var launch = {
    endpoint: params.get('endpoint'),
    fetch: params.get('fetch'),
    actor: params.get('actor'),
    registration: params.get('registration'),
    activityId: params.get('activityId')
  };
  var state = { token: null, launchData: {}, started: Date.now(), terminated: false };

  function uuid() {
    if (global.crypto && global.crypto.randomUUID) return global.crypto.randomUUID();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
      var r = Math.random() * 16 | 0;
      return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
    });
  }
  function base() { return launch.endpoint.replace(/\/?$/, '/'); }
  function headers() {
    return {
      'Authorization': 'Basic ' + state.token,
      'X-Experience-API-Version': '1.0.3',
      'Content-Type': 'application/json'
    };
  }
  function duration() { return 'PT' + Math.round((Date.now() - state.started) / 1000) + 'S'; }
  function clone(o) { return JSON.parse(JSON.stringify(o || {})); }

  function context(categories) {
    var ctx = clone(state.launchData.contextTemplate);
    ctx.registration = launch.registration;
    ctx.contextActivities = ctx.contextActivities || {};
    var cats = ctx.contextActivities.category || [];
    (categories || []).forEach(function (id) { cats.push({ id: id, objectType: 'Activity' }); });
    if (cats.length) ctx.contextActivities.category = cats;
    return ctx;
  }

  function send(verb, result, categories, extensions) {
    if (!Cmi5.connected) return Promise.resolve(false);
    var statement = {
      id: uuid(),
      actor: JSON.parse(launch.actor),
      verb: { id: VERBS[verb], display: { 'en-US': verb } },
      object: { id: launch.activityId, objectType: 'Activity' },
      context: context(categories),
      timestamp: new Date().toISOString()
    };
    if (result) statement.result = result;
    if (extensions) statement.context.extensions = Object.assign(statement.context.extensions || {}, extensions);
    return fetch(base() + 'statements', { method: 'POST', headers: headers(), body: JSON.stringify(statement) })
      .then(function (r) { return r.ok; })
      .catch(function () { return false; });
  }

  var Cmi5 = {
    connected: false,
    launchData: function () { return state.launchData; },
    start: function () {
      if (!launch.endpoint || !launch.fetch || !launch.actor) return Promise.resolve(false);
      return fetch(launch.fetch, { method: 'POST' })
        .then(function (r) { return r.json(); })
        .then(function (body) {
          state.token = body['auth-token'];
          if (!state.token) throw new Error('no auth token');
          var q = new URLSearchParams({
            stateId: 'LMS.LaunchData',
            activityId: launch.activityId,
            agent: launch.actor,
            registration: launch.registration || ''
          });
          return fetch(base() + 'activities/state?' + q.toString(), { headers: headers() })
            .then(function (r) { return r.ok ? r.json() : {}; })
            .catch(function () { return {}; });
        })
        .then(function (data) {
          state.launchData = data || {};
          Cmi5.connected = true;
          return send('initialized', null, [CMI5_CATEGORY]);
        })
        .catch(function () { Cmi5.connected = false; return false; });
    },
    /* progress 0..100, sent as a cmi5 "allowed" statement */
    progress: function (percent, label) {
      return send('progressed', {
        extensions: { 'https://w3id.org/xapi/cmi5/result/extensions/progress': Math.round(percent) },
        response: label || ''
      });
    },
    complete: function () {
      return send('completed', { completion: true, duration: duration() }, [CMI5_CATEGORY, MOVEON_CATEGORY]);
    },
    /* scaled score 0..1 */
    pass: function (scaled) {
      var mastery = state.launchData.masteryScore;
      var success = typeof mastery === 'number' ? scaled >= mastery : true;
      return send(success ? 'passed' : 'failed', {
        success: success,
        score: { scaled: Math.max(0, Math.min(1, scaled)) },
        duration: duration()
      }, [CMI5_CATEGORY, MOVEON_CATEGORY]);
    },
    terminate: function () {
      if (state.terminated) return Promise.resolve(true);
      state.terminated = true;
      return send('terminated', { duration: duration() }, [CMI5_CATEGORY]);
    },
    exit: function () {
      Cmi5.terminate().then(function () {
        var url = state.launchData.returnURL;
        if (url) global.location.href = url;
      });
    }
  };

  global.Cmi5 = Cmi5;
  global.addEventListener('pagehide', function () { Cmi5.terminate(); });
})(window);
