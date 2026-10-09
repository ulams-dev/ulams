/*
 * Runs first in the LiaScript build frame (injected by LiaScriptPlayer::publishPlayer). The SCORM
 * 1.2 build looks for window.API and then window.top.API. window.top is the learner front on
 * another origin, so reading it throws and the build stops before it renders. Our player page,
 * the parent frame on the same content origin, provides the API: hand it over before the build
 * starts, so the build never reaches window.top.
 */
(function () {
  try {
    if (!window.API && window.parent && window.parent !== window && window.parent.API) {
      window.API = window.parent.API;
    }
  } catch (e) {
    // a parent on another origin: nothing to hand over
  }
})();
