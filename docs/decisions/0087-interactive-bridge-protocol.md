# 0087. The `ulams-ix` bridge protocol and the `@ulams/interactive-bridge` library (MIT)

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/interactive-demos.md` (M1)

## Context and problem statement

An interactive package (ADR 0086) runs in an opaque sandbox. The only way to talk to it is
`postMessage`. Several parties have to speak the same messages: the lesson page in `front/web`, our
own MIT packages, the GPL gravity build and, later, `simulation` elements (ADR 0053). A private
`postMessage` dialect per package would drift. A heavy SDK would bloat small packages, and it would
put more code under each package's licence than needed.

## Considered options

1. **A small versioned protocol (`ulams-ix`, v1) with a zero-dependency MIT library for both ends.**
2. Reuse xAPI over HTTP from the frame (cmi5 style). That needs a token and network access in the
   frame, which ADR 0086 rules out.
3. Reuse the SCORM `window.API`. That needs `allow-same-origin` and has no notion of steps.

## Decision

Option 1.

- **Envelope.** Every message has the shape `{ "ulams-ix": 1, "type": string, "nonce": string, ...payload }`.
  Messages larger than 16 KB, with an unknown `type` or with a wrong nonce are dropped. The JSON
  Schemas live in `front/interactive-bridge/schema/v1/*.json` and are used by both ends and by the API.
- **Parent → package.**
  - `init`: nonce, locale, theme tokens, `reducedMotion`, `display` (`inline|background|fullscreen`),
    `chrome` (`full|minimal|none`), `startStep`, `range` `{from,to}`, `readOnly`.
  - `goToStep`: the target step.
  - `setTheme`, `setLocale`, `pause`, `resume`.
- **Package → parent.**
  - `ready`: protocol version, the step ids it knows, `capabilities`
    (`steps`, `reducedMotion`, `locales`, `score`).
  - `resize`: the content height, for inline mode.
  - `stepChanged`: the current step.
  - `progress`: a value from 0 to 1.
  - `complete`.
  - `score`: raw, max and an optional passed flag.
  - `event`: an xAPI-like verb IRI, an object id inside the package, and an optional result.
  - `error`: a code such as `webgl-unavailable`, plus a message.
- **Handshake.** The parent creates a random nonce per launch and sends `init` with target origin
  `"*"`. An opaque frame has no origin to target, and `init` carries nothing secret. The package
  answers `ready` with the nonce. The parent accepts messages only from that frame's `contentWindow`,
  with `origin === "null"` and the nonce. If no `ready` arrives within 10 s, the lesson shows the text
  alternative.
- **Rate limits.** The parent forwards at most 20 events per second to the BFF, batched every 2 s.
  The API accepts at most 60 requests per minute per learner and topic.
- **Library.** `front/interactive-bridge` (`@ulams/interactive-bridge`, MIT, no dependencies, ESM,
  under 3 KB min+gzip) exports `connect(options)` for packages and `createHost(frame, options)` for
  the parent. It also ships as one file, `dist/interactive-bridge.js`, so packages without a build
  step and third-party repositories can vendor it with its MIT header until it is published to npm
  (#77).
- **Versioning.** A new optional field is a minor change. Renaming or removing a field means
  `"ulams-ix": 2`. Hosts keep accepting v1.

## Consequences

- Good: one contract for interactive packages and simulations, testable without a browser (the schema
  tests) and in Playwright with a fixture package.
- Good: the library is MIT, so GPL packages may bundle it (MIT is GPL-compatible), and MIT packages are
  not affected by any GPL package.
- Bad: packages must adopt the bridge to report steps and completion. Packages without it can still
  play, but only with `completion_rule = on_open`.
