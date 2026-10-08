# 0014. Preview SCORM packages client-side with a service worker (`@escolalms/scorm-player`)

- Status: Accepted (retroactive)
- Date: 2025-02-05

## Context and Problem Statement

Admins upload SCORM packages (zip files) and attach SCOs to course topics. Until 2025 the preview was an
iframe pointing at the API's player route (`${REACT_APP_API_URL}/api/scorm/play/<uuid>`), which needs
the API to unpack and serve the package and to run the SCORM runtime server-side.

## Considered Options

- Server-rendered player in an iframe (2022–2025).
- In-browser player: `scorm-again` as the SCORM API plus a service worker that serves files out of the
  zip (chosen, first inline in the admin, then extracted to a package).

## Decision Outcome

`public/service-worker-scorm.js` (with `jszip`, `mimetypes` and `txml` copied into `public/modules/`)
intercepts requests under `__scorm__/`, reads the package zip in the browser and answers with the
unpacked files. `src/components/Scorm/preview.tsx` renders `ScormPreview` from
`@escolalms/scorm-player` (`EscolaLMS/Scorm-player`, created 2025-02-14), which wraps `scorm-again`. In
dev mode `config/plugin-scorm.ts` returns 404 for `/courses/scorms/preview/__scorm__/` so the umi dev
server does not answer requests meant for the worker ("app in app" problem). This change required
switching to browser routing (0008).

### Consequences

- Good: preview works without server-side unpacking; the same player can be reused by the front-end.
- Bad: needs service-worker support, a path-based router and server rewrites; the commit history notes
  unresolved icon problems in some packages.
- Bad: large third-party files (jszip ≈11.5k lines) are committed into `public/modules/`.

## Evidence

- `fbee42f8` 2022-01-13 "scorm as topic and scorm sco (#274)" — `admin/src/components/Scorm/preview.tsx`
  (iframe to `/api/scorm/play/<uuid>`).
- `e568b2e6` 2025-01-24 "scorm player front beta" — `admin/public/service-worker-scorm.js`, `admin/public/modules/*`.
- `6e2453ca` 2025-02-05 "working on preview, problem with icons i specific scorm not resolved yet" —
  `admin/package.json` (+`scorm-again ^2.6.0`), `admin/config/config.ts`, `admin/src/components/Scorm/preview.tsx`.
- `a733be08` 2025-02-07 "app in app problem resolved" — `admin/config/plugin-scorm.ts`, `admin/config/config.ts`.
- `9218d2bd` 2025-02-18 "scorm player pacakge" — `admin/package.json` (`@escolalms/scorm-player`, −`scorm-again`),
  `preview.tsx` (−226 lines).
- Scorm-player repo: `42aec2c` / `e003387` 2025-02-14 "first commit" / "init commit".
