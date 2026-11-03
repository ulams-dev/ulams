# 0170. Code exercises run in our own browser runner inside the opaque content-origin sandbox, with an optional gVisor server runner for verified grading

- Status: Accepted
- Date: 2026-10-21
- Accepted: 2026-10-25 (owner approved the plan with its recommended defaults; AX-1 implements the browser runner)
- Plan: `docs/plans/phase-7-agents-extensions.md` (2)

## Context and problem statement

Spec 7.2 asks for code exercises with autograding: WebContainers or Sandpack for JS/TS, Pyodide for
Python, and an optional server sandbox. The product must be free to self-host and work air-gapped
(principles 8, 9). Learner code is untrusted, and so are results computed in the learner's browser.

## Considered options

1. WebContainers: production use in a commercial setting needs a StackBlitz licence; hosted assets.
2. Sandpack: Apache-2.0 clients, but the bundler is hosted by CodeSandbox unless we build and host it.
3. Our own runner: esbuild-wasm (MIT) for JS/TS and Pyodide (MPL-2.0) in Web Workers, inside the opaque
   sandbox and CSP family of ADR 0086, talking over `ulams-ix` (ADR 0087).
4. Server sandboxes: Docker + gVisor, Firecracker, Cloudflare Sandbox, Judge0/Piston.

## Decision

Option 3 in the browser, served from the content origin at `/runtime/*` with no network and
`'wasm-unsafe-eval'` only. Results from the browser are `practice` results stored with
`verified = false`; they never count for certificates or compliance gates. `verified` exercises are
graded by an optional `api/code-runner` service that runs each submission with hidden tests in a
one-shot Docker container on the gVisor runtime (no network, read-only root, cgroup limits, pinned
images). Cloudflare Sandbox is an optional driver; Firecracker is documented, not built.

## Consequences

- Good: no licence or third-party runtime; works offline; reuses an audited sandbox and bridge.
- Good: graded results cannot be forged from the browser.
- Bad: ~11 MB (esbuild) and ~15–25 MB (Pyodide) of static assets, lazy-loaded.
- Bad: if Pyodide needs `'unsafe-eval'`, it gets its own runtime prefix inside the opaque frame.
- Bad: verified grading needs gVisor on the host; without it only practice mode is available.

## How the browser runner is built (AX-1)

- Two pages, two opaque frames, one inside the other, both served from the content origin under
  `/runtime/code-runner/<version>/` (the static files ship with the API; `esbuild.wasm`, 14 MB, is fetched at
  image build, pinned by checksum, and is not in git). The policies are data, in
  `api/packages/code-exercises/resources/runner-csp.json`; the API sends them and the front-end tests read the
  same file.
  - `runner.html` (`sandbox="allow-scripts"`) speaks `ulams-ix` with the lesson page and bundles the exercise with
    esbuild-wasm on its main thread. Its policy is the plan's: `script-src 'self' 'wasm-unsafe-eval'`,
    `connect-src 'self'` (for the wasm file only). It never runs learner code.
  - `exec.html` is created by the runner for each run, in a second sandboxed frame, and runs the bundle in a Worker
    made from a blob. Its policy has **no `connect-src`** (so no fetch, XHR, WebSocket, EventSource, beacon, image
    or frame request: they all fall back to `default-src 'none'`), `worker-src blob:` and no `'unsafe-eval'`. Blob
    workers inherit the policy of the page that creates them, which is why the code runs in this second page and
    not in the runner: it is the only way to give the learner's code a policy without `connect-src 'self'`.
  - Both pages also send `sandbox allow-scripts`, so opening one directly gives it an opaque origin as well. The
    runner may be framed only by the tenant's own front and admin. `exec.html` has no `frame-ancestors`: the runner
    frames it and the runner is sandboxed, an opaque origin that no `frame-ancestors` source can match (not even
    `'self'`; verified in Chromium), so any restriction would block it. It needs none: the page has no network and
    an opaque origin, and runs only code that arrives with the nonce of its URL fragment.
- The Worker's harness removes `fetch`, `XMLHttpRequest`, `WebSocket`, `importScripts`, `Worker`, `postMessage`
  and the other ways out from the global scope before the learner's code runs, captures `console`, and signs its
  result with a secret of the run that only the harness knows. This is a second layer: the policy is the boundary.
  In practice mode it is still not a security boundary for the *result*: the browser is the learner's.
- Every import is resolved among the exercise's own files by an esbuild plugin; a package name, a URL or a path
  outside the exercise is a build error. A run happens only when the learner presses a button, never on load.
- Time limits are enforced three times: the exec page terminates the Worker at the exercise's limit (500 ms to
  30 s, default 5 s), the runner stops waiting two seconds later and removes the frame, and the lesson page
  reloads the runner if it does not answer six seconds after that. A learner's endless loop cannot freeze the lesson.
- Bridge: `ulams-ix` v1 gets `loadExercise`, `run`, `reset` (page to runner) and `runResult` (runner to page) as
  optional types, with a 640 KB limit for those three instead of 16 KB. Hosts and packages that do not know them
  drop them, as the versioning rule of ADR 0087 says.
- Results are stored as `verified = false` practice attempts; the score is recomputed on the server from the
  per-test results. They complete the topic by its completion rule and produce the `code.attempt_finished`
  signal; nothing that issues certificates or checks compliance reads them as a grade.
- Known limits: memory is not capped (a hostile exercise can make the learner's own tab slow or crash it; the
  run starts only on the learner's action); practice results can be forged by the learner; the editor is a plain
  text area (CodeMirror 6 stays a candidate for a later milestone).
