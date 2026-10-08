# 0007. Play SCORM packages client-side with `@escolalms/scorm-player`

- Status: Accepted (retroactive)
- Date: 2025-02-18

## Context and Problem Statement

SCORM topics were first shown in an `<iframe>` pointing at the API's player endpoint
(`${apiUrl}/api/scorm/play/${uuid}`, also the default in the SDK). The API had to serve the
unpacked SCORM files and the runtime API page.

## Considered Options

- Iframe to the API-side player (used 2022-09-16 to 2025-02-18).
- React component with a client-side SCORM runtime (chosen).

## Decision Outcome

A new package `EscolaLMS/Scorm-player` (`@escolalms/scorm-player`, first commit 2025-02-14)
exposes `ScormPreview`. It uses `scorm-again` for the SCORM 1.2 runtime API and a service
worker (`public/service-worker-scorm.js`, with `public/modules/jszip.js`, `mimetypes.js`,
`txml.js`) that unpacks the package zip in the browser and serves its files to the player
iframe. The front replaced the API iframe in `ScormPlayer.tsx`, `CourseProgramPreview` and
`pages/courses/preview`, passing `onScormPost/onScormGet` callbacks for tracking.

### Consequences

- Good: no server-side unpacking/serving of SCORM assets for playback.
- Bad: requires service workers (HTTPS, same-origin `public/` assets copied into each app
  that uses the player); ~14 000 lines of vendored JS in `public/modules`.
- Bad: only SCORM 1.2 is listed as supported in the package README.

## Evidence

- `c78661d7` 2022-09-16 "Feature/scorm player (#323)" - `front/src/components/Course/Players/ScormPlayer.tsx` (iframe version)
- `2705114b` 2025-02-18 "switched to scorm player" - `front/package.json`, `front/public/service-worker-scorm.js`, `front/public/modules/*`, `ScormPlayer.tsx`, `CourseProgramPreview/index.tsx`, `pages/courses/preview/index.tsx`
- Scorm-player repo: `42aec2c`/`e003387` 2025-02-14 "first commit"/"init commit", `package.json` (`scorm-again`, rollup), `README.md`
