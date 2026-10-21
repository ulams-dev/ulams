# @ulams/code-runner

The browser runner of [code exercises](../../docs/decisions/0170-code-exercise-runners.md): it bundles an exercise
(the learner's files and the author's tests) with esbuild-wasm and runs the tests in a Worker, inside two nested
opaque (sandboxed) frames on the tenant content origin. MIT.

```
lesson page ── ulams-ix ──▶ runner.html   (sandbox="allow-scripts", CSP: connect-src 'self' for esbuild.wasm only)
                              │ bundles with esbuild-wasm; an esbuild plugin resolves imports among the exercise's files only
                              └─ nonce in the URL fragment ──▶ exec.html   (sandboxed again, CSP: no connect-src, no eval)
                                                                 └─ Worker from a blob: harness + bundle, killed at the time limit
```

- `src/bundle.ts` bundles an exercise (a package name, a URL or a path outside the exercise is a build error).
- `src/harness.ts` is the `test`/`describe`/`expect` harness that runs in front of the bundle in the Worker. It removes
  `fetch`, `XMLHttpRequest`, `WebSocket`, `importScripts`, `Worker` and `postMessage` from the Worker's scope
  (the content security policy is the real boundary), captures `console` and signs its result with a secret of the run.
- `src/runner-core.ts` is the runner's logic without the DOM (time limits, one run at a time, the answer to `run`);
  `src/runner.ts` and `src/exec.ts` are the two pages' wiring.
- `src/exec-protocol.ts` is the small protocol between the two pages. The protocol with the lesson page is `ulams-ix`
  (`loadExercise`, `run`, `reset`, `runResult` in `@ulams/interactive-bridge`).

`yarn workspace @ulams/code-runner build` writes `runner.html`, `exec.html`, `runner.js`, `exec.js` and `esbuild.js`
(the browser build of esbuild-wasm, unchanged) into
`api/packages/code-exercises/resources/runtime/code-runner/<version>/`, which the API serves under
`/runtime/code-runner/<version>/` on the content origin, and copies `esbuild.wasm` (14 MB, not in git) next to
them for local use. `yarn workspace @ulams/code-runner test` fails when the committed files are stale. Bump `VERSION` in
`scripts/build.mjs` (and `runner.version` in the API package) when the runner changes in a way the lesson page must not
mix with an older runner: the version is part of the URL, and the files are cached for an hour.

The policies of the two pages live in `api/packages/code-exercises/resources/runner-csp.json`.
