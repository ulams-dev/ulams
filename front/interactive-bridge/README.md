# @ulams/interactive-bridge

The `ulams-ix` v1 `postMessage` protocol between a lesson page and an interactive package
([ADR 0086](../../docs/decisions/0086-interactive-topic-type.md),
[ADR 0087](../../docs/decisions/0087-interactive-bridge-protocol.md)). MIT, no dependencies, ESM.

## In a package

```js
import { connect } from "./vendor/interactive-bridge.js"; // dist/interactive-bridge.js, one file

const bridge = connect({
  steps: ["what-is-gravity", "inertia"],
  capabilities: { steps: true, reducedMotion: true },
  onInit(init) {}, // locale, theme tokens, display, chrome, startStep, range, reducedMotion
  onGoToStep(step) {},
});
bridge.stepChanged("inertia");
bridge.progress(0.5);
bridge.complete();
bridge.score(8, 10);
bridge.resize(document.documentElement.scrollHeight);
```

Calls made before `init`, or inside `onInit`, are queued. A package that loads data after start passes
`whenReady: promise` (and `steps: () => ids`) to hold `ready` back until it knows its steps. When the page runs on its own (`window.parent === window`) every
call is a no-op, so the package still works standalone.

## On the lesson page

```ts
import { createHost, newNonce } from "@ulams/interactive-bridge";

const host = createHost(iframe, { nonce: newNonce(), init, onMessage, onTimeout });
host.goToStep("inertia");
```

A message is accepted only from the frame's window, with `origin === "null"` (the frame is sandboxed
without `allow-same-origin`), the launch nonce, at most 16 KB and a valid payload. No `ready` within 10 s
calls `onTimeout`.

## Files

- `schema/v1/*.json`: the JSON Schemas of every message (the API copies them).
- `schema/ulams-interactive.v1.json`: the manifest schema (a test keeps the API's copy identical).
- `npm run build` writes `dist/interactive-bridge.js` (under 3 KB min+gzip, MIT header).
