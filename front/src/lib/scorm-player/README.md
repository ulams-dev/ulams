# scorm-player (vendored)

`<ScormPreview>` React component that plays SCORM packages via scorm-again and a service worker.

- Upstream: https://github.com/EscolaLMS/Scorm-player (npm `@escolalms/scorm-player`)
- Version: 0.0.0, commit `e003387c047f550bac820249a85c169d2e6c94b7`
- Copied: `src/` only. Shared by front and admin: import as `@lms/scorm-player`
  (front: `tsconfig.json` paths; admin: umi `alias` in `config/config.ts` + `tsconfig.json` paths).
- Runtime dep: `scorm-again` (in both apps' `package.json`).
- Service worker: upstream `public/sw/*` was NOT copied. The files actually served are each app's own
  `public/service-worker-scorm.js` + `public/modules/*`, which already differ from upstream (the npm
  package's `dist/public` copy was never served). Callers pass `serviceWorkerUrl="/service-worker-scorm.js"`.
- License: none declared upstream.
