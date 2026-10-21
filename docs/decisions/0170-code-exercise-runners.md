# 0170. Code exercises run in our own browser runner inside the opaque content-origin sandbox, with an optional gVisor server runner for verified grading

- Status: Proposed
- Date: 2026-10-21
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
